<?php
// FILE: generate_forecast.php
// Real sales forecasting endpoint for the Admin > Sales Forecasting report.
//
// Primary engine: Facebook Prophet (via forecast_prophet.py), a real
// machine-learning time-series model — linear-growth trend + weekly
// seasonality, fit with Bayesian MAP estimation. PHP pulls the daily
// sales/items history from MySQL, hands it to the Python script as JSON
// over stdin, and returns whatever comes back on stdout.
//
// Fallback engine: if Python or the `prophet` package isn't installed on
// this machine (or the call fails/times out for any reason), we fall back
// to a simple in-PHP linear regression + day-of-week seasonality model so
// the report still works — just with a less sophisticated forecast. The
// response always says which engine actually produced it via "engine".

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_pharmacy.php';

$period = isset($_GET['period']) ? (int) $_GET['period'] : 30;
if (!in_array($period, [7, 30, 90], true)) {
    $period = 30;
}

$HISTORY_WINDOW_DAYS = 180;

// ------------------------------------------------------------------
// 1. Pull daily totals (sales amount + quantity) for completed sales.
// ------------------------------------------------------------------
$sql = "
    SELECT DATE(s.date_created) AS sale_date,
           SUM(s.total_amount) AS daily_total,
           COALESCE(SUM(si.quantity), 0) AS daily_qty
    FROM sales s
    LEFT JOIN sales_items si ON si.sale_id = s.sale_id
    WHERE s.status = 'completed'
      AND s.date_created >= DATE_SUB(CURDATE(), INTERVAL {$HISTORY_WINDOW_DAYS} DAY)
    GROUP BY DATE(s.date_created)
    ORDER BY sale_date ASC
";
$result = $conn->query($sql);

$history = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'date' => $row['sale_date'],
            'total' => (float) $row['daily_total'],
            'qty' => (int) $row['daily_qty'],
        ];
    }
}

if (count($history) < 2) {
    // Not enough historical data yet to fit a trend line. Be honest about
    // it instead of showing a fabricated chart.
    echo json_encode([
        'success' => true,
        'insufficient_data' => true,
        'message' => 'Not enough sales history yet to generate a reliable forecast. Record at least 2 days of completed sales first.',
        'history_days' => count($history),
        'engine' => 'none',
    ]);
    $conn->close();
    exit;
}

// ------------------------------------------------------------------
// 2. Pull per-drug daily quantity history (for Top Forecasted Items /
//    Top Forecasted Category).
// ------------------------------------------------------------------
$sql_items = "
    SELECT d.drug_id, d.generic_name, d.brand_name, d.category,
           DATE(s.date_created) AS sale_date,
           SUM(si.quantity) AS daily_qty
    FROM sales_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN drugs_master d ON si.drug_id = d.drug_id
    WHERE s.status = 'completed'
      AND s.date_created >= DATE_SUB(CURDATE(), INTERVAL {$HISTORY_WINDOW_DAYS} DAY)
    GROUP BY d.drug_id, DATE(s.date_created)
    ORDER BY d.drug_id, sale_date ASC
";
$result_items = $conn->query($sql_items);

$perDrug = []; // drug_id => ['name'=>, 'category'=>, 'series'=>[{date,qty}, ...]]
if ($result_items) {
    while ($row = $result_items->fetch_assoc()) {
        $id = $row['drug_id'];
        if (!isset($perDrug[$id])) {
            $label = $row['generic_name'] . ($row['brand_name'] ? " ({$row['brand_name']})" : '');
            $perDrug[$id] = ['name' => $label, 'category' => $row['category'], 'series' => []];
        }
        $perDrug[$id]['series'][] = ['date' => $row['sale_date'], 'qty' => (int) $row['daily_qty']];
    }
}
$conn->close();

// ------------------------------------------------------------------
// 3. Try Prophet first (real ML). Fall back to linear regression on any
//    failure — missing python, missing `prophet` package, timeout, bad
//    JSON, non-zero exit code, etc.
// ------------------------------------------------------------------
$prophetPayload = json_encode([
    'period' => $period,
    'today' => date('Y-m-d'),
    'range_start' => date('Y-m-d', strtotime("-{$HISTORY_WINDOW_DAYS} days")),
    'daily_sales' => $history,
    'items' => $perDrug,
]);

$prophetResult = runProphetForecast($prophetPayload);

if ($prophetResult !== null) {
    echo $prophetResult;
    exit;
}

// ------------------------------------------------------------------
// Fallback: linear regression + day-of-week seasonality (pure PHP, no
// external dependencies — always available).
// ------------------------------------------------------------------
echo json_encode(runLinearRegressionFallback($history, $perDrug, $period));
exit;


/**
 * Runs forecast_prophet.py as a subprocess, feeding it $jsonPayload on
 * stdin. Returns the raw JSON string from stdout on success, or null if
 * Prophet couldn't be used for any reason (caller should fall back).
 */
function runProphetForecast(string $jsonPayload): ?string {
    $script = __DIR__ . DIRECTORY_SEPARATOR . 'forecast_prophet.py';
    if (!is_file($script)) {
        return null;
    }

    // Try common interpreter names across Linux/macOS (python3) and
    // Windows XAMPP installs (python, or the launcher `py`).
    $candidates = [
        ['python3', $script],
        ['python', $script],
        ['py', '-3', $script],
    ];

    foreach ($candidates as $cmd) {
        $output = tryRunSubprocess($cmd, $jsonPayload);
        if ($output === null) {
            continue; // this interpreter isn't available — try the next
        }

        $decoded = json_decode($output, true);
        if (!is_array($decoded) || !isset($decoded['success'])) {
            continue; // malformed output — try the next candidate, then fall back
        }
        if ($decoded['success'] !== true) {
            return null; // prophet ran but errored (e.g. missing package) — fall back
        }
        return $output;
    }

    return null;
}

/**
 * Runs one candidate command with $stdin piped in. Returns trimmed stdout
 * on a clean (exit code 0) run, or null if the interpreter is missing /
 * the process failed / it took too long.
 */
function tryRunSubprocess(array $cmdParts, string $stdin, int $timeoutSeconds = 20): ?string {
    $escaped = implode(' ', array_map('escapeshellarg', $cmdParts));
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open($escaped, $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        return null;
    }

    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $start = time();
    while (true) {
        $status = proc_get_status($process);
        $stdout .= stream_get_contents($pipes[1]);
        if (!$status['running']) {
            break;
        }
        if (time() - $start > $timeoutSeconds) {
            proc_terminate($process);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            return null;
        }
        usleep(50000); // 50ms
    }

    $stdout .= stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        return null;
    }
    $stdout = trim($stdout);
    return $stdout === '' ? null : $stdout;
}

/**
 * Pure-PHP fallback: linear regression (least squares) on daily totals to
 * capture the overall trend, combined with day-of-week seasonal indices.
 * Used only when Prophet isn't available on this machine.
 */
function runLinearRegressionFallback(array $history, array $perDrug, int $period): array {
    $xs = range(0, count($history) - 1);
    $salesYs = array_column($history, 'total');
    $qtyYs = array_column($history, 'qty');

    [$salesSlope, $salesIntercept] = linearRegression($xs, $salesYs);
    [$qtySlope, $qtyIntercept] = linearRegression($xs, $qtyYs);

    $dowSums = array_fill(0, 7, 0.0);
    $dowCounts = array_fill(0, 7, 0);
    foreach ($history as $i => $point) {
        $trendVal = $salesSlope * $i + $salesIntercept;
        if ($trendVal <= 0) continue;
        $dow = (int) date('w', strtotime($point['date']));
        $ratio = max(0.5, min(1.5, $point['total'] / $trendVal));
        $dowSums[$dow] += $ratio;
        $dowCounts[$dow]++;
    }
    $dowFactor = [];
    for ($d = 0; $d < 7; $d++) {
        $dowFactor[$d] = $dowCounts[$d] > 0 ? ($dowSums[$d] / $dowCounts[$d]) : 1.0;
    }

    $lastIndex = count($history) - 1;
    $lastDate = end($history)['date'];

    $forecastLabels = [];
    $forecastValues = [];
    $predictedTotalSales = 0;
    $predictedItemsSold = 0;

    for ($d = 1; $d <= $period; $d++) {
        $futureIndex = $lastIndex + $d;
        $futureDate = date('Y-m-d', strtotime($lastDate . " +{$d} days"));
        $dow = (int) date('w', strtotime($futureDate));

        $trendSales = max(0, $salesSlope * $futureIndex + $salesIntercept);
        $seasonalSales = $trendSales * $dowFactor[$dow];
        $trendQty = max(0, $qtySlope * $futureIndex + $qtyIntercept);

        $forecastLabels[] = date('M j', strtotime($futureDate));
        $forecastValues[] = round($seasonalSales, 2);
        $predictedTotalSales += $seasonalSales;
        $predictedItemsSold += $trendQty;
    }

    $itemForecasts = [];
    $categoryTotals = [];
    foreach ($perDrug as $info) {
        $series = array_column($info['series'], 'qty');
        $n = count($series);
        $xsItem = range(0, max(0, $n - 1));
        [$slope, $intercept] = linearRegression($xsItem, $series);

        $predictedQty = 0;
        for ($d = 1; $d <= $period; $d++) {
            $futureIndex = $n - 1 + $d;
            $predictedQty += max(0, $slope * $futureIndex + $intercept);
        }
        $predictedQty = round($predictedQty);

        $itemForecasts[] = ['name' => $info['name'], 'predicted_qty' => $predictedQty];
        $cat = $info['category'] ?: 'Uncategorized';
        $categoryTotals[$cat] = ($categoryTotals[$cat] ?? 0) + $predictedQty;
    }

    usort($itemForecasts, fn($a, $b) => $b['predicted_qty'] <=> $a['predicted_qty']);
    $topItems = array_slice($itemForecasts, 0, 5);

    $topCategory = 'N/A';
    if (!empty($categoryTotals)) {
        arsort($categoryTotals);
        $topCategory = array_key_first($categoryTotals);
    }

    return [
        'success' => true,
        'insufficient_data' => false,
        'period' => $period,
        'forecast' => [
            'labels' => $forecastLabels,
            'values' => $forecastValues,
        ],
        'predicted_total_sales' => round($predictedTotalSales, 2),
        'predicted_items_sold' => (int) round($predictedItemsSold),
        'top_category' => $topCategory,
        'top_items' => [
            'labels' => array_column($topItems, 'name'),
            'data' => array_column($topItems, 'predicted_qty'),
        ],
        'history_days' => count($history),
        'engine' => 'linear_regression_fallback',
    ];
}

/**
 * Linear regression (least squares): returns [slope, intercept] for y = slope*x + intercept
 */
function linearRegression(array $xs, array $ys): array {
    $n = count($xs);
    if ($n === 0) return [0, 0];
    if ($n === 1) return [0, $ys[0]];

    $sumX = array_sum($xs);
    $sumY = array_sum($ys);
    $sumXY = 0;
    $sumXX = 0;
    for ($i = 0; $i < $n; $i++) {
        $sumXY += $xs[$i] * $ys[$i];
        $sumXX += $xs[$i] * $xs[$i];
    }
    $denominator = ($n * $sumXX - $sumX * $sumX);
    if ($denominator == 0) {
        return [0, $sumY / $n];
    }
    $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
    $intercept = ($sumY - $slope * $sumX) / $n;
    return [$slope, $intercept];
}