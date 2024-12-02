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
            $found_id = $URI_array[3];

            $qy = "SELECT * FROM users";

            if ($found_id && is_numeric($found_id)) {
                $qy .= " WHERE id=:id";
                $stmt = $db_connection->prepare($qy);
                $stmt->bindParam(':id', $found_id);
                $stmt->execute();
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $db_connection->prepare($qy);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        
            echo json_encode($data);
            break;
        
        // For Registrar to create new staff accounts
        case 'POST':
            $user = json_decode(file_get_contents('php://input'));
            
            $qy = "INSERT INTO users(name, email, email_verified_at,
            password, account_type, remember_token,
            created_at, updated_at) 
            VALUES(:name, :email, :verified, :pass,
            :role, :remember, :created, :updated)";
            
            $hash_pass = password_hash($user->staffPass, PASSWORD_BCRYPT);
            $token = createRememberToken();
            $created_at = date('Y-m-d H:i:s');            
            $updated_at = date('Y-m-d H:i:s');

            $stmt = $db_connection->prepare($qy);
            // TODO refer to the name attributes in create new staff account form fields
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
                $found_id = $URI_array[3];
            
                if (!$found_id || !is_numeric($found_id)) {
                    echo json_encode(['status' => 0, 'message' => 'Invalid or missing ID']);
                    exit;
                }
            
                $query = "UPDATE users SET ";
                $params = [];
            
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
            
            
    }
?> 