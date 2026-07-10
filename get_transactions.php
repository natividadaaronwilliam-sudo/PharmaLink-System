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
    // NOTE: the "sales" table's primary key is sale_id, not transaction_id.
    // It's aliased here because cashier.js reads t.transaction_id from this
    // response (and from transactions.php's response) to display it.
    $query = "
        SELECT 
            sale_id AS transaction_id, 
            total_amount, 
            cash_received, 
            change_amount, 
            date_created 
        FROM 
            sales 
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