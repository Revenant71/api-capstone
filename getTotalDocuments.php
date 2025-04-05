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
    //file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] DB Connection Error: ' . $db_attempt->error . PHP_EOL, FILE_APPEND);
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Fetch total number of requested documents
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT 
            SUM(total_requests) AS total_requests,
            SUM(total_unread) AS total_unread,
            SUM(total_review) AS total_review,
            SUM(total_unclaimed) AS total_unclaimed
        FROM (
            SELECT 
                reference_number,
                COUNT(DISTINCT reference_number) AS total_requests,
                SUM(CASE WHEN statusTransit = 'Request Placed' THEN 1 ELSE 0 END) AS total_unread,
                SUM(CASE WHEN statusTransit = 'In Review' THEN 1 ELSE 0 END) AS total_review,
                SUM(CASE WHEN statusTransit = 'Completed' OR overdue_days > 0 THEN 1 ELSE 0 END) AS total_unclaimed
            FROM transactions
            GROUP BY reference_number
        ) AS grouped_results;
        ";
        $stmt = $db_connection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $response = [
            'total_requests' => (int) ($result['total_requests'] ?? 0),
            'total_unread' => (int) ($result['total_unread'] ?? 0),
            'total_review' => (int) ($result['total_review'] ?? 0),
            'total_unclaimed' => (int) ($result['total_unclaimed'] ?? 0),
        ];

        echo json_encode($response);
    } catch (PDOException $e) {
        //file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] GET Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch total document count']);
    }
    exit;
}

// Invalid request method
http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
?>
