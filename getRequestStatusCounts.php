<?php
require_once('connectDb.php');

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, OPTIONS");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

// Check if the connection was successful
if (!$db_connection) {
    // file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] DB Connection Error: ' . $db_attempt->error . PHP_EOL, FILE_APPEND);
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Fetch the count of each status category
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "
            SELECT 
                SUM(CASE WHEN statusTransit = 'Request Placed' THEN 1 ELSE 0 END) AS request_placed,
                SUM(CASE WHEN statusTransit = 'In Review' THEN 1 ELSE 0 END) AS in_review,
                SUM(CASE WHEN statusTransit = 'Accepted' THEN 1 ELSE 0 END) AS accepted,
                SUM(CASE WHEN statusTransit = 'Rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN statusTransit = 'Payment Sent' THEN 1 ELSE 0 END) AS payment_sent,
                SUM(CASE WHEN statusTransit = 'Processing' THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN statusTransit = 'For Releasing' THEN 1 ELSE 0 END) AS for_releasing,
                SUM(CASE WHEN statusTransit = 'Completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN statusTransit = 'Out for Delivery/Ready to Pick Up' THEN 1 ELSE 0 END) AS out_for_delivery_ready_pickup,
                SUM(CASE WHEN statusTransit = 'Received/Claimed' THEN 1 ELSE 0 END) AS received_claimed,
                SUM(CASE WHEN statusTransit = 'In Transit' THEN 1 ELSE 0 END) AS in_transit,
                SUM(CASE WHEN statusTransit = 'Out for Delivery' THEN 1 ELSE 0 END) AS out_for_delivery,
                SUM(CASE WHEN statusTransit = 'Ready to Pick Up' THEN 1 ELSE 0 END) AS ready_to_pick_up,
                SUM(CASE WHEN statusTransit = 'Received' THEN 1 ELSE 0 END) AS received,
                SUM(CASE WHEN statusTransit = 'Claimed' THEN 1 ELSE 0 END) AS claimed,
                SUM(CASE WHEN statusTransit = 'Delivered' THEN 1 ELSE 0 END) AS delivered
            FROM transactions;
        ";

        $stmt = $db_connection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode($result);
    } catch (PDOException $e) {
        // file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] GET Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch request status counts']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
?>
