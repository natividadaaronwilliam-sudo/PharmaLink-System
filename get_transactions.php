<?php
// get_transactions.php (Rewritten for MySQLi)
require_once 'db_pharmacy.php'; // This must successfully create the $conn variable

header('Content-Type: application/json');

$data = [];

// Check if the MySQLi connection object ($conn) is available and connected
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Connection Error', 'message' => 'Database connection failed.']);
    exit;
}

try {
    // MySQLi Query
    $query = "
        SELECT 
            transaction_id, 
            total_amount, 
            cash_received, 
            change_amount, 
            date_created 
        FROM 
            SALES 
        WHERE 
            status = 'completed'
        ORDER BY 
            date_created DESC 
        LIMIT 10
    ";
    
    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $result->free();
    } else {
        throw new Exception("Query failed: " . $conn->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database operation failed', 'message' => $e->getMessage()]);
    exit;
}

// Close the connection (optional, but good practice)
$conn->close();

echo json_encode($data);
?>