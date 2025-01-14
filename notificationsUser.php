<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method){
    case 'GET':
        $URI_array = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
        $found_user = isset($URI_array[3]) ? intval($URI_array[3]) : null;

        if ($found_user && is_numeric($found_user)) {
            $qy = "SELECT * FROM notifications WHERE id_user=:id_user";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':id_user', $found_user);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($data) {
                echo json_encode($data);
            } else {
                echo json_encode(['status' => 0, 'message' => 'No data found for user ID ' . $found_user]);
            }

        } else {
            echo json_encode(['status' => 0, 'message' => 'Invalid or missing user ID ' . $found_user]);
        }
        break;

    case 'POST':
        $user = json_decode(file_get_contents('php://input'));
        
        if (!empty($input->user_id) && !empty($input->type) && !empty($input->message)) {
            $qy = "INSERT INTO notifications (id_user, type, message) VALUES (:user_id, :type, :message)";
            $stmt = $db_connection->prepare($qy);

            $stmt->bindParam(':user_id', $input->user_id, PDO::PARAM_INT);
            $stmt->bindParam(':type', $input->type, PDO::PARAM_STR);
            $stmt->bindParam(':message', $input->message, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                echo json_encode(["status" => 1, "error" => "Notification added"]);
            } else {
                echo json_encode(["status" => 0, "error" => "Failed to add notification"]);
            }
        } else {
            echo json_encode(["status" => 0, "error" => "Invalid request method"]);
        }
        break;
}
?>