<?php
require_once('connectDb.php');

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 

// Check if connection was successful
if (!$db_connection) {
    file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] DB Connection Error: ' . $db_attempt->error . PHP_EOL, FILE_APPEND);
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}


// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Send HTTP 200 response
    exit;
}

// Handle GET requests to fetch guidelines
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT guidelines FROM document_guidelines LIMIT 1";
        $stmt = $db_connection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo json_encode(['status' => 'success', 'guidelines' => $result['guidelines']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No guidelines found']);
        }
    } catch (PDOException $e) {
        file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] GET Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch guidelines']);
    }
    exit;
}

// Handle POST requests to update guidelines
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['guidelines']) || empty(trim($input['guidelines']))) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input: Guidelines are missing or empty']);
            exit;
        }

        $guidelines = htmlspecialchars(trim($input['guidelines'])); // Sanitize input

        // Insert or update guidelines
        $query = "INSERT INTO document_guidelines (id, guidelines) VALUES (1, :guidelines) 
                  ON DUPLICATE KEY UPDATE guidelines = :guidelines";
        $stmt = $db_connection->prepare($query);
        $stmt->bindParam(':guidelines', $guidelines, PDO::PARAM_STR);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Guidelines updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update guidelines']);
        }
    } catch (PDOException $e) {
        file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] POST Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update guidelines']);
    }
    exit;
}

// Invalid request method
http_response_code(405); // Method Not Allowed
echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
?>
