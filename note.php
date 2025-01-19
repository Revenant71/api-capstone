<?php
require_once('connectDb.php');

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 

// Check if the connection was successful
if (!$db_connection) {
    file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] DB Connection Error: ' . $db_attempt->error . PHP_EOL, FILE_APPEND);
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Send HTTP 200 response
    exit;
}

// Handle GET requests to fetch the note
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT note_text FROM notes LIMIT 1";
        $stmt = $db_connection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo json_encode(['status' => 'success', 'note' => $result['note_text']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No note found']);
        }
    } catch (PDOException $e) {
        file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] GET Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch the note']);
    }
    exit;
}

// Handle POST requests to update the note
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['note']) || empty(trim($input['note']))) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input: Note is missing or empty']);
            exit;
        }

        $note = htmlspecialchars(trim($input['note'])); // Sanitize input

        // Insert or update the note
        $query = "INSERT INTO notes (id, note_text) VALUES (1, :note) 
                  ON DUPLICATE KEY UPDATE note_text = :note";
        $stmt = $db_connection->prepare($query);
        $stmt->bindParam(':note', $note, PDO::PARAM_STR);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Note updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update the note']);
        }
    } catch (PDOException $e) {
        file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] POST Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update the note']);
    }
    exit;
}

// Invalid request method
http_response_code(405); // Method Not Allowed
echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
?>
