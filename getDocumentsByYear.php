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

// Handle GET request to fetch document request counts per year
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Debugging - Check if query runs
        // file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] Fetching documents by year' . PHP_EOL, FILE_APPEND);

        $query = "SELECT YEAR(released_at) AS year, COUNT(*) AS count FROM transactions WHERE released_at IS NOT NULL GROUP BY YEAR(released_at) ORDER BY year ASC";
        $stmt = $db_connection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$result) {
            // file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] No data found' . PHP_EOL, FILE_APPEND);
        }

        echo json_encode($result);
    } catch (PDOException $e) {
        // file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] GET Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch document data']);
    }
    exit;
}

// Invalid request method
http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
?>
