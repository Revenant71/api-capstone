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
            // get rows where "responded" is 0
            // TODO in frontend show all but use checkbox to show responded and not responded
            $qy = "SELECT * FROM notifications WHERE id_user=:id_user AND responded = 0";
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
        $input = json_decode(file_get_contents('php://input'));
        
        if (!empty($input->user_id) && !empty($input->type) && !empty($input->message)) {
            try {
                $qy = "INSERT INTO notifications (id, id_user, type, message, responded, updated_responded, created_at, updated_at) 
                    VALUES (UUID(), :id_user, :type, :message, :responded, :updated_responded, NOW(), NOW())";
                $stmt = $db_connection->prepare($qy);
    
                if ($stmt){
                    $hardcoded_responded = 0;
                    $hardcoded_responded_at = null;

                    $stmt->bindParam(':id_user', $input->user_id, PDO::PARAM_INT);
                    $stmt->bindParam(':type', $input->type, PDO::PARAM_STR);
                    $stmt->bindParam(':message', $input->message, PDO::PARAM_STR);
                    $stmt->bindParam(':responded', $hardcoded_responded, PDO::PARAM_INT);
                    // $stmt->bindValue(':updated_responded', null, PDO::PARAM_NULL);
                    $stmt->bindParam(':updated_responded', $hardcoded_responded_at, PDO::PARAM_NULL);

                    if ($stmt->execute()) {
                        echo json_encode(["status" => 1, "message" => "Notification added"]);
                    } else {
                        echo json_encode(["status" => 0, "message" => "Failed to add notification"]);
                    }
                } else {
                    echo json_encode(["status" => 0, "message" => "Failed to prepare statement: " . $qy]);
                }
            } catch (PDOException $e) {
                echo json_encode(["status" => 0, "message" => "Database error: " . $e->getMessage()]);
            }

        } else {
            echo json_encode(["status" => 0, "message" => "Missing required fields"]);
        }
        break;

    case 'PATCH':
        $input = json_decode(file_get_contents('php://input'));
        
        if (!empty($input->user_id) && !empty($input->notif_id) && !empty($input->type) && !empty($input->message)) {
          try {
            $qy = "UPDATE notifications 
                   SET type = :type, 
                       message = :message, 
                       responded = :responded, 
                       updated_responded = CASE WHEN :responded = 1 THEN NOW() ELSE updated_responded END, 
                       updated_at = NOW()
                   WHERE id = :notif_id AND id_user = :id_user";
                   
            $stmt = $db_connection->prepare($qy);

            if ($stmt) {
                $stmt->bindParam(':notif_id', $input->notif_id, PDO::PARAM_STR);
                $stmt->bindParam(':id_user', $input->user_id, PDO::PARAM_INT);
                $stmt->bindParam(':type', $input->type, PDO::PARAM_STR);
                $stmt->bindParam(':message', $input->message, PDO::PARAM_STR);

                // Check if responded value is provided, otherwise default to 0 (not responded)
                $responded_value = isset($input->responded) ? (int)$input->responded : 0;
                $stmt->bindParam(':responded', $responded_value, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $result = $stmt->execute();
                    echo json_encode(["status" => 1, "message" => "Notification successfully updated " . $result]);       
                } else {
                    http_response_code(500);
                    echo json_encode(["status" => 0, "message" => "Failed to update notification: Internal Server Error"]);
                }

            } else {
                http_response_code(500);
                echo json_encode(["status" => 0, "message" => "Failed to prepare \$stmt : Internal Server Error"]);
            }

          } catch (PDOException $e) {
            echo json_encode(["status" => 0, "message" => "Database error: " . $e->getMessage()]);
          }
        } else {
            echo json_encode(["status" => 0, "message" => "Missing required fields"]);
        }

        break;
}
?>