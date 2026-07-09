<?php
// FILE: generate_forecast.php
// Real sales forecasting endpoint for the Admin > Sales Forecasting report.
//
// Method: linear regression (least squares) on daily total sales to capture
// the overall trend, combined with day-of-week seasonal indices (the average
// deviation of each weekday from the trend). This is the same decomposition
// approach ("trend + seasonality") used by classical time-series forecasting
// models, and it works reliably even with the modest amounts of data a
// pharmacy's `sales` table will have — unlike a library like Prophet, which
// needs a much larger history to fit well and isn't something this app can
// depend on being installed on every XAMPP machine running it.

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_pharmacy.php';

$period = isset($_GET['period']) ? (int) $_GET['period'] : 30;
if (!in_array($period, [7, 30, 90], true)) {
    $period = 30;
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

// ------------------------------------------------------------------
// 1. Pull daily totals (sales amount + quantity) for completed sales,
//    over as much history as exists (capped at the last 180 days so a
//    very old, unrelated data point doesn't skew the trend).
// ------------------------------------------------------------------
$sql = "
    SELECT DATE(s.date_created) AS sale_date,
           SUM(s.total_amount) AS daily_total,
           COALESCE(SUM(si.quantity), 0) AS daily_qty
    FROM sales s
    LEFT JOIN sales_items si ON si.sale_id = s.sale_id
    WHERE s.status = 'completed'
      AND s.date_created >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
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
    ]);
    $conn->close();
    exit;
}

// ------------------------------------------------------------------
// 2. Fit trend lines (day index -> value) for both total sales and quantity
// ------------------------------------------------------------------
$xs = range(0, count($history) - 1);
$salesYs = array_column($history, 'total');
$qtyYs = array_column($history, 'qty');

[$salesSlope, $salesIntercept] = linearRegression($xs, $salesYs);
[$qtySlope, $qtyIntercept] = linearRegression($xs, $qtyYs);

// ------------------------------------------------------------------
// 3. Day-of-week seasonal index = average(actual / trend-at-that-point)
//    for each weekday, capped to avoid wild swings from sparse data.
// ------------------------------------------------------------------
$dowSums = array_fill(0, 7, 0.0);
$dowCounts = array_fill(0, 7, 0);

foreach ($history as $i => $point) {
    $trendVal = $salesSlope * $i + $salesIntercept;
    if ($trendVal <= 0) continue;
    $dow = (int) date('w', strtotime($point['date']));
    $ratio = $point['total'] / $trendVal;
    $ratio = max(0.5, min(1.5, $ratio)); // cap swing to +/-50%
    $dowSums[$dow] += $ratio;
    $dowCounts[$dow]++;
}

$dowFactor = [];
for ($d = 0; $d < 7; $d++) {
    $dowFactor[$d] = $dowCounts[$d] > 0 ? ($dowSums[$d] / $dowCounts[$d]) : 1.0;
}

// ------------------------------------------------------------------
// 4. Project forward `$period` days from the last known date
// ------------------------------------------------------------------
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

// ------------------------------------------------------------------
// 5. Per-drug forecast (to find Top Forecasted Items + Top Category)
//    Same trend approach, applied per drug, using its own recent daily
//    quantity history.
// ------------------------------------------------------------------
$sql_items = "
    SELECT d.drug_id, d.generic_name, d.brand_name, d.category,
           DATE(s.date_created) AS sale_date,
           SUM(si.quantity) AS daily_qty
    FROM sales_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN drugs_master d ON si.drug_id = d.drug_id
    WHERE s.status = 'completed'
      AND s.date_created >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
    GROUP BY d.drug_id, DATE(s.date_created)
    ORDER BY d.drug_id, sale_date ASC
";
$result_items = $conn->query($sql_items);

$perDrug = []; // drug_id => ['name'=>, 'category'=>, 'series'=>[qty,...]]
if ($result_items) {
    while ($row = $result_items->fetch_assoc()) {
        $id = $row['drug_id'];
        if (!isset($perDrug[$id])) {
            $label = $row['generic_name'] . ($row['brand_name'] ? " ({$row['brand_name']})" : '');
            $perDrug[$id] = ['name' => $label, 'category' => $row['category'], 'series' => []];
        }
        $perDrug[$id]['series'][] = (int) $row['daily_qty'];
    }
}

$itemForecasts = [];
$categoryTotals = [];

foreach ($perDrug as $id => $info) {
    $series = $info['series'];
    $n = count($series);
    $xsItem = range(0, $n - 1);
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

// Sort items by predicted quantity, take top 5
usort($itemForecasts, fn($a, $b) => $b['predicted_qty'] <=> $a['predicted_qty']);
$topItems = array_slice($itemForecasts, 0, 5);

// Top forecasted category
$topCategory = 'N/A';
if (!empty($categoryTotals)) {
    arsort($categoryTotals);
    $topCategory = array_key_first($categoryTotals);
}

echo json_encode([
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
]);

$conn->close();
