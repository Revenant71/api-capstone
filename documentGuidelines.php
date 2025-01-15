<?php
require_once('connectDb.php');
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Enable error reporting during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle GET requests to fetch guidelines
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (!$conn) {
            throw new Exception("Database connection failed.");
        }

        $query = "SELECT guidelines FROM document_guidelines LIMIT 1";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode(['status' => 'success', 'guidelines' => $row['guidelines']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No guidelines found']);
        }
    } catch (Exception $e) {
        file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] GET Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch guidelines']);
    }
    exit;
}

// Handle POST requests to update guidelines
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$conn) {
            throw new Exception("Database connection failed.");
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['guidelines']) || empty(trim($input['guidelines']))) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input: Guidelines are missing or empty']);
            exit;
        }

        $guidelines = htmlspecialchars(trim($input['guidelines'])); // Sanitize input

        // Insert or update guidelines
        $stmt = $conn->prepare("INSERT INTO document_guidelines (id, guidelines) VALUES (1, ?) 
                                ON DUPLICATE KEY UPDATE guidelines = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param("ss", $guidelines, $guidelines);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Guidelines updated successfully']);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] POST Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update guidelines']);
    }
    exit;
}

// Invalid request method
http_response_code(405); // Method Not Allowed
echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
?>
