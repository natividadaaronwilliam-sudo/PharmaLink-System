<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_pharmacy.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

if (!isset($_FILES['prescription_file']) || $_FILES['prescription_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit;
}

$file = $_FILES['prescription_file'];
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if (!in_array($file['type'], $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, WEBP, or PDF allowed.']);
    exit;
}

$uploadDir = __DIR__ . '/uploads/prescriptions/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'rx_' . $user_id . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
$dest = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO prescriptions (user_id, filename, extracted_text) VALUES (?, ?, ?)');
$note = 'Uploaded by customer';
$stmt->bind_param('iss', $user_id, $filename, $note);
if ($stmt->execute()) {
    $prescription_id = $stmt->insert_id;
    $stmt->close();

    // ------------------------------------------------------------------
    // OCR (Tesseract) + drug availability matching
    // Only attempted for images; PDFs are skipped here (would need an
    // extra pdf->image conversion step like Imagick/Ghostscript).
    // ------------------------------------------------------------------
    $extracted_text = null;
    $availability_summary = null;
    $ocr_status = 'pending';
    $is_image = strpos($file['type'], 'image/') === 0;

    if ($is_image) {
        $tesseractAvailable = false;
        $which = @shell_exec('command -v tesseract 2>/dev/null');
        if ($which && trim($which) !== '') {
            $tesseractAvailable = true;
        }

        if ($tesseractAvailable) {
            $escapedPath = escapeshellarg($dest);
            $ocrOutput = @shell_exec("tesseract {$escapedPath} stdout 2>/dev/null");
            if ($ocrOutput !== null && trim($ocrOutput) !== '') {
                $extracted_text = trim($ocrOutput);
                $ocr_status = 'done';

                // Match the OCR'd text against active drugs_master entries
                // (by generic name or brand name), then check real-time
                // stock from inventory_lots so the customer immediately
                // sees if what's written on the prescription is available.
                $drugsRes = $conn->query("SELECT drug_id, generic_name, brand_name FROM drugs_master WHERE is_active = 1");
                $matches = [];
                if ($drugsRes) {
                    $haystack = mb_strtolower($extracted_text);
                    while ($drug = $drugsRes->fetch_assoc()) {
                        $names = array_filter([$drug['generic_name'], $drug['brand_name']]);
                        $found = false;
                        foreach ($names as $name) {
                            if ($name !== '' && mb_strpos($haystack, mb_strtolower($name)) !== false) {
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) continue;

                        $stockStmt = $conn->prepare("
                            SELECT COALESCE(SUM(current_stock), 0) AS total_stock
                            FROM inventory_lots
                            WHERE drug_id = ? AND is_active = 1 AND expiration_date >= CURDATE()
                        ");
                        $stockStmt->bind_param('i', $drug['drug_id']);
                        $stockStmt->execute();
                        $total_stock = (int)($stockStmt->get_result()->fetch_assoc()['total_stock'] ?? 0);
                        $stockStmt->close();

                        $matches[] = [
                            'drug_id' => (int)$drug['drug_id'],
                            'name' => $drug['generic_name'] . ($drug['brand_name'] ? " ({$drug['brand_name']})" : ''),
                            'status' => $total_stock > 0 ? 'In Stock' : 'Out of Stock',
                            'total_stock' => $total_stock,
                        ];
                    }
                }
                $availability_summary = json_encode($matches);
            } else {
                $ocr_status = 'failed';
            }
        } else {
            // Tesseract isn't installed on this server — leave OCR fields
            // empty rather than failing the whole upload.
            $ocr_status = 'unavailable';
        }
    } else {
        $ocr_status = 'skipped_pdf';
    }

    $updateStmt = $conn->prepare("UPDATE prescriptions SET extracted_text = ?, availability_summary = ?, ocr_status = ? WHERE id = ?");
    $updateStmt->bind_param('sssi', $extracted_text, $availability_summary, $ocr_status, $prescription_id);
    $updateStmt->execute();
    $updateStmt->close();

    echo json_encode(['success' => true, 'message' => 'Prescription uploaded successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error saving prescription.']);
    $stmt->close();
}
$conn->close();