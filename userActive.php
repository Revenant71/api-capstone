<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: PATCH");
    
$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'PATCH':
        $found_id = isset($_GET['id']) ? $_GET['id'] : null;
        error_log("Received ID for reactivation: " . $found_id); // Log the received ID
        
        if (!$found_id || !is_numeric($found_id)) {
            error_log("Invalid or missing ID");
            echo json_encode(['status' => 0, 'message' => 'Invalid or missing ID']);
            exit;
        }

        try {
            $qy = "UPDATE users SET status = 'active', updated_at = NOW() WHERE id = :id";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':id', $found_id);
    
            if ($stmt->execute()) {
                echo json_encode(['status' => 1, 'message' => 'User reactivated successfully']);
            } else {
                error_log("Reactivate user error: " . json_encode($stmt->errorInfo())); // Log detailed error info
                echo json_encode(['status' => 0, 'message' => 'Failed to reactivate user']);
            }
        } catch (Exception $e) {
            error_log("Exception during reactivate user: " . $e->getMessage());
            echo json_encode(['status' => 0, 'message' => 'Internal server error during delete']);
        }

        exit;
}
?>