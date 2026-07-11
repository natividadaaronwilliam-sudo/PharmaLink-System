<?php
/**
 * FILE: upload_prescription.php
 * Saves the uploaded prescription file, then (for image files) runs it
 * through Tesseract OCR, matches any recognized drug names against
 * drugs_master + inventory_lots to see if each one is in stock, and
 * stores the result on the prescriptions row. The full result (OCR text +
 * stock matches) is also returned in the JSON response so the customer
 * portal can pop up a "your prescription vs. our stock" summary right
 * after upload, instead of only showing it later in the history table.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id || strtolower($_SESSION['user_role'] ?? '') !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

if (empty($_FILES['prescription_file']['name']) || $_FILES['prescription_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please choose a file to upload.']);
    exit;
}

$allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
$ext = strtolower(pathinfo($_FILES['prescription_file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, WEBP, or PDF files are allowed.']);
    exit;
}
if ($_FILES['prescription_file']['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File must be under 5MB.']);
    exit;
}

$dir = __DIR__ . '/uploads/prescriptions';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$filename = 'rx_' . $user_id . '_' . time() . '.' . $ext;
$filepath = $dir . '/' . $filename;

if (!move_uploaded_file($_FILES['prescription_file']['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO prescriptions (user_id, filename, ocr_status, created_at) VALUES (?, ?, 'pending', NOW())");
$stmt->bind_param('is', $user_id, $filename);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to save prescription record: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}
$prescription_id = $stmt->insert_id;
$stmt->close();

// ---------------------------------------------------------------------
// Run OCR (images only — PDFs are skipped since Tesseract needs an
// image, not a PDF, and pdftoppm/ImageMagick aren't guaranteed to be
// installed alongside XAMPP).
// ---------------------------------------------------------------------
$extracted_text = null;
$availability_summary = [];
$ocr_status = 'failed';

if ($ext === 'pdf') {
    $ocr_status = 'skipped_pdf';
} else {
    $ocr_error = null;
    $extracted_text = run_tesseract_ocr($filepath, $ocr_error);

    if ($extracted_text === null) {
        // Tesseract isn't installed / not found on this machine.
        $ocr_status = 'unavailable';
    } elseif (trim($extracted_text) === '') {
        $ocr_status = 'failed';
    } else {
        $availability_summary = match_prescription_to_stock($conn, $extracted_text);
        $ocr_status = 'completed';
    }
}

$update = $conn->prepare(
    "UPDATE prescriptions SET extracted_text = ?, availability_summary = ?, ocr_status = ? WHERE id = ?"
);
$availability_json = json_encode($availability_summary);
$update->bind_param('sssi', $extracted_text, $availability_json, $ocr_status, $prescription_id);
$update->execute();
$update->close();

$response_message = 'Prescription uploaded.';
switch ($ocr_status) {
    case 'unavailable':
        $response_message = 'Prescription uploaded, but OCR is not installed on this server.';
        break;
    case 'skipped_pdf':
        $response_message = 'Prescription uploaded. PDF files are stored for pharmacist review (OCR only reads images).';
        break;
    case 'failed':
        $response_message = 'Prescription uploaded, but the text could not be read clearly. A pharmacist will review it manually.';
        break;
    case 'completed':
        $response_message = empty($availability_summary)
            ? 'Prescription uploaded and read, but no matching medicines were found in our catalog.'
            : 'Prescription uploaded and read successfully.';
        break;
}

echo json_encode([
    'success' => true,
    'message' => $response_message,
    'prescription_id' => $prescription_id,
    'filename' => $filename,
    'ocr_status' => $ocr_status,
    'extracted_text' => $extracted_text,
    'availability_summary' => $availability_summary,
]);

$conn->close();

// =======================================================================
// Helpers
// =======================================================================

/**
 * Runs Tesseract OCR on an image file and returns the recognized text,
 * or null if Tesseract could not be found/executed on this machine.
 */
function run_tesseract_ocr(string $imagePath, ?string &$error = null): ?string
{
    if (!function_exists('shell_exec')) {
        $error = 'shell_exec is disabled on this server.';
        return null;
    }

    // Try a plain "tesseract" call first (works if it's on PATH), then
    // fall back to the common default install locations on Windows/XAMPP.
    $candidates = [];
    $envPath = getenv('TESSERACT_PATH');
    if ($envPath) {
        $candidates[] = $envPath;
    }
    $candidates[] = 'tesseract';
    $candidates[] = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    $candidates[] = 'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe';
    $candidates[] = '/usr/bin/tesseract';
    $candidates[] = '/usr/local/bin/tesseract';

    foreach ($candidates as $bin) {
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($imagePath) . ' stdout -l eng 2>&1';
        $output = @shell_exec($cmd);

        if ($output === null) {
            continue;
        }
        $lower = strtolower($output);
        $looksMissing = strpos($lower, 'not recognized') !== false
            || strpos($lower, 'not found') !== false
            || strpos($lower, 'no such file') !== false
            || strpos($lower, 'command not found') !== false;

        if (!$looksMissing) {
            return trim($output);
        }
    }

    $error = 'Tesseract binary could not be located.';
    return null;
}

/**
 * Normalizes OCR text and checks it against every active drug's
 * generic/brand name. Returns a list of ['name' => ..., 'status' => 'In Stock'|'Out of Stock'].
 */
function match_prescription_to_stock(mysqli $conn, string $text): array
{
    $normalized = ' ' . strtoupper(preg_replace('/[^A-Za-z0-9]+/', ' ', $text)) . ' ';

    $sql = "SELECT dm.drug_id, dm.generic_name, dm.brand_name,
                   COALESCE(SUM(CASE WHEN il.is_active = 1 AND il.expiration_date >= CURDATE()
                                      THEN il.current_stock ELSE 0 END), 0) AS total_stock
            FROM drugs_master dm
            LEFT JOIN inventory_lots il ON dm.drug_id = il.drug_id
            WHERE dm.is_active = 1
            GROUP BY dm.drug_id, dm.generic_name, dm.brand_name";

    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    $matches = [];
    while ($row = $result->fetch_assoc()) {
        $candidates = array_unique(array_filter([
            trim((string)$row['generic_name']),
            trim((string)$row['brand_name']),
        ]));

        $matchedName = null;
        foreach ($candidates as $name) {
            if ($name === '') {
                continue;
            }
            $needle = ' ' . strtoupper($name) . ' ';
            if (strpos($normalized, $needle) !== false) {
                $matchedName = $name;
                break;
            }
        }

        if ($matchedName !== null) {
            $matches[] = [
                'name' => $matchedName,
                'status' => ((int)$row['total_stock'] > 0) ? 'In Stock' : 'Out of Stock',
            ];
        }
    }

    return $matches;
}