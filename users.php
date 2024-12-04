<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE");
    
    $db_attempt = new connectDb;
    $db_connection = $db_attempt->connect(); 

    // for "remember me" setting
    function createRememberToken($length = 50){ // default is 50 bytes
        return bin2hex(random_bytes($length));
    }

    $method = $_SERVER['REQUEST_METHOD'];
    switch ($method) {
        case 'GET':
            $user = json_decode(file_get_contents('php://input'));
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = isset($URI_array[3]) ? $URI_array[3] : null;

            $qy = "SELECT * FROM users";

            if ($found_id && is_numeric($found_id)) {
                $qy .= " WHERE id=:id";
                $stmt = $db_connection->prepare($qy);
                $stmt->bindParam(':id', $found_id);
                $stmt->execute();
                $data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($data && isset($data['img_profile'])) {
                    $data['img_profile'] = base64_encode($data['img_profile']);
                }
            } else {
                $stmt = $db_connection->prepare($qy);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($data as &$row) {
                    if (isset($row['img_profile'])) {
                        $row['img_profile'] = base64_encode($row['img_profile']);
                    }
                }
            }
        
            echo json_encode($data);
            break;
        
        // For Registrar to create new staff accounts
        case 'POST':
            $user = json_decode(file_get_contents('php://input'));
            
            $qy = "INSERT INTO users(img_profile, name, email, email_verified_at,
            password, account_type, remember_token,
            created_at, updated_at) 
            VALUES(:pfp, :name, :email, :verified, :pass,
            :role, :remember, :created, :updated)";
            
            $foundPicture = base64_decode($user->profilePicture);
            $hash_pass = password_hash($user->staffPass, PASSWORD_BCRYPT);
            $token = createRememberToken();
            $created_at = date('Y-m-d H:i:s');            
            $updated_at = date('Y-m-d H:i:s');

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':pfp', $foundPicture, PDO::PARAM_LOB);
            $stmt->bindParam(':name', $user->staffName);
            $stmt->bindParam(':email', $user->staffEmail);
            $stmt->bindParam(':verified', $created_at); // TODO verification feature (one time email?)
            $stmt->bindParam(':pass', $hash_pass); 
            $stmt->bindParam(':role', $user->staffRole);
            $stmt->bindParam(':remember', $token);
            $stmt->bindParam(':created', $created_at); 
            $stmt->bindParam(':updated', $updated_at);

            if ($stmt->execute()) {
                $response = ['status'=>1, 'message'=>'POST user successful.'];
            } else {
                $response = ['status'=>0, 'message'=>'SORRY, POST user failed.'];
            }
            
            echo json_encode($response);
            break;

        case 'PATCH':
            $user = json_decode(file_get_contents('php://input'));
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = isset($URI_array[3]) ? $URI_array[3] : null;
            
            if (!$found_id || !is_numeric($found_id)) {
                echo json_encode(['status' => 0, 'message' => 'Invalid or missing ID']);
                exit;
            }
            
            $query = "UPDATE users SET ";
            $params = [];

            if (isset($user->profilePicture)) {
                $foundPicture = base64_decode($user->profilePicture);
                $query .= "img_profile=:img_profile, ";
                $params[':img_profile'] = $foundPicture;
            }
            if (isset($user->staffName)) {
                $query .= "name=:name, ";
                $params[':name'] = $user->staffName;
            }
            if (isset($user->staffEmail)) {
                $query .= "email=:email, ";
                $params[':email'] = $user->staffEmail;
            }
            if (isset($user->staffRole)) {
                $query .= "account_type=:role, ";
                $params[':role'] = $user->staffRole;
            }
            if (isset($user->staffPass)) {
                $query .= "password=:pass, ";
                $params[':pass'] = password_hash($user->staffPass, PASSWORD_BCRYPT);
            }
            
            $query .= "updated_at=:updated WHERE id=:id";
            $params[':updated'] = date('Y-m-d H:i:s');
            $params[':id'] = $found_id;
            
            $stmt = $db_connection->prepare($query);
            
            if ($stmt->execute($params)) {
                $stmt = $db_connection->prepare("SELECT * FROM users WHERE id=:id");
                $stmt->bindParam(':id', $found_id);
                $stmt->execute();
                $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
                echo json_encode(['status' => 1, 'message' => 'User updated', 'data' => $updatedUser]);
            } else {
                echo json_encode(['status' => 0, 'message' => 'Update failed']);
            }

            break;
            
        case 'DELETE':
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = isset($URI_array[3]) ? $URI_array[3] : null;
                
            /*
            delete pfp
            axios delete http://localhost:80/api_arts/users.php/5?action=removeProfilePicture

            delete user
            axios delete http://localhost:80/api_arts/users.php/5
            */

                // Check if the DELETE request is for the profile picture
                if (isset($_GET['action']) && $_GET['action'] === 'removeProfilePicture') {
                    $query = "UPDATE users SET img_profile=NULL WHERE id=:id";
            
                    $stmt = $db_connection->prepare($query);
                    $stmt->bindParam(':id', $found_id);
            
                    if ($stmt->execute()) {
                        echo json_encode(['status' => 1, 'message' => 'Profile picture deleted successfully.']);
                    } else {
                        echo json_encode(['status' => 0, 'message' => 'Failed to delete profile picture.']);
                    }
                } else {
                    // Default DELETE case to delete a client or user
                    $query = "DELETE FROM users WHERE id=:id";
            
                    $stmt = $db_connection->prepare($query);
                    $stmt->bindParam(':id', $found_id);
            
                    if ($stmt->execute()) {
                        echo json_encode(['status' => 1, 'message' => 'Record deleted successfully.']);
                    } else {
                        echo json_encode(['status' => 0, 'message' => 'Failed to delete record.']);
                    }
                }
                break;    
    }
?>