<?php
require_once('connectDb.php');

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");

// Initialize database connection
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

// Handle GET requests to fetch courses
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT id, course_name FROM courses ORDER BY course_name ASC";
        $stmt = $db_connection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($result) {
            echo json_encode(['status' => 'success', 'courses' => $result]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No courses found']);
        }
    } catch (PDOException $e) {
        // file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] GET Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch courses']);
    }
    exit;
}

// Handle POST requests to add a new course
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['course_name']) || empty(trim($input['course_name']))) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input: Course name is required']);
            exit;
        }

        $courseName = htmlspecialchars(trim($input['course_name'])); // Sanitize input

        $query = "INSERT INTO courses (course_name) VALUES (:course_name)";
        $stmt = $db_connection->prepare($query);
        $stmt->bindParam(':course_name', $courseName, PDO::PARAM_STR);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Course added successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add course']);
        }
    } catch (PDOException $e) {
        //file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] POST Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to add course']);
    }
    exit;
}

// Handle DELETE requests to delete a course
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        // Parse the query string for the ID
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input: Course ID is required']);
            exit;
        }

        $courseId = intval($_GET['id']); // Extract and sanitize the ID

        $query = "DELETE FROM courses WHERE id = :id";
        $stmt = $db_connection->prepare($query);
        $stmt->bindParam(':id', $courseId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Course deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete course']);
        }
    } catch (PDOException $e) {
        file_put_contents('error_log.txt', '[' . date('Y-m-d H:i:s') . '] DELETE Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete course']);
    }
    exit;

}

// Invalid request method
http_response_code(405); // Method Not Allowed
echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
?>
