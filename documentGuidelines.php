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
    logError('DB Connection Error', $db_attempt->error);
    sendJsonResponse("error", "Database connection failed");
    exit;
}


// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Send HTTP 200 response
    exit;
}

// Handle GET requests to fetch guidelines
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT id, title, guidelines, created_at FROM document_guidelines ORDER BY created_at DESC";
        $stmt = $db_connection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($result) {
            file_put_contents('debug_log.txt', '[' . date('Y-m-d H:i:s') . "] Fetched Data: " . json_encode($result) . PHP_EOL, FILE_APPEND);
            sendJsonResponse("success", "Data fetched successfully", $result);
        } else {
            file_put_contents('debug_log.txt', '[' . date('Y-m-d H:i:s') . "] No Data Found" . PHP_EOL, FILE_APPEND);
            sendJsonResponse("error", "No guidelines found");
        }
    } catch (PDOException $e) {
        logError('GET Error', $e->getMessage());
        sendJsonResponse("error", "Failed to fetch guidelines");
    }
    exit;
}

// Handle POST requests to update guidelines
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            sendJsonResponse("error", "Invalid JSON input");
            exit;
        }

        if (isset($input['id']) && !empty($input['id'])) {
            // Update an existing entry
            handleUpdate($db_connection, $input);
        } else {
            // Add a new entry
            handleCreate($db_connection, $input);
        }
    } catch (PDOException $e) {
        logError('POST Error', $e->getMessage());
        sendJsonResponse("error", "Failed to process request");
    }
    exit;
}

// Handle DELETE requests
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['id']) || empty($input['id'])) {
            sendJsonResponse("error", "Invalid input: ID is missing");
            exit;
        }

        $id = (int) $input['id'];
        $query = "DELETE FROM document_guidelines WHERE id = :id";
        $stmt = $db_connection->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            sendJsonResponse("success", "Entry deleted successfully");
        } else {
            sendJsonResponse("error", "Failed to delete entry");
        }
    } catch (PDOException $e) {
        logError('DELETE Error', $e->getMessage());
        sendJsonResponse("error", "Failed to delete entry");
    }
    exit;
}

// Invalid request method
http_response_code(405);
sendJsonResponse("error", "Invalid request method");

// Helper Functions
function handleUpdate($db_connection, $input) {
    if (!isset($input['guidelines'], $input['title']) || empty(trim($input['guidelines'])) || empty(trim($input['title']))) {
        sendJsonResponse("error", "Invalid input: Title or guidelines are missing for update");
        exit;
    }

    $id = (int) $input['id'];
    $guidelines = htmlspecialchars(trim($input['guidelines']));
    $title = htmlspecialchars(trim($input['title']));

    $query = "UPDATE document_guidelines SET title = :title, guidelines = :guidelines WHERE id = :id";
    $stmt = $db_connection->prepare($query);
    $stmt->bindParam(':title', $title, PDO::PARAM_STR);
    $stmt->bindParam(':guidelines', $guidelines, PDO::PARAM_STR);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        sendJsonResponse("success", "Entry updated successfully");
    } else {
        sendJsonResponse("error", "Failed to update entry");
    }
}

function handleCreate($db_connection, $input) {
    if (!isset($input['guidelines'], $input['title']) || empty(trim($input['guidelines'])) || empty(trim($input['title']))) {
        sendJsonResponse("error", "Invalid input: Title or guidelines are missing for new entry");
        exit;
    }

    $guidelines = htmlspecialchars(trim($input['guidelines']));
    $title = htmlspecialchars(trim($input['title']));

    $query = "INSERT INTO document_guidelines (title, guidelines) VALUES (:title, :guidelines)";
    $stmt = $db_connection->prepare($query);
    $stmt->bindParam(':title', $title, PDO::PARAM_STR);
    $stmt->bindParam(':guidelines', $guidelines, PDO::PARAM_STR);

    if ($stmt->execute()) {
        $lastInsertId = $db_connection->lastInsertId();
        sendJsonResponse("success", "New entry added successfully", ["id" => $lastInsertId]);
    } else {
        sendJsonResponse("error", "Failed to add new entry");
    }
}

function sendJsonResponse($status, $message, $data = null) {
    $response = ["status" => $status, "message" => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

function logError($type, $message) {
    file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] ' . $type . ': ' . $message . PHP_EOL, FILE_APPEND);
}

?>