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
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($data['img_profile']);
                    $data['img_profile'] = "data:$mimeType;base64," . base64_encode($data['img_profile']);
                }
            } else {
                $stmt = $db_connection->prepare($qy);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($data as &$row) {
                    if (isset($row['img_profile'])) {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mimeType = $finfo->buffer($row['img_profile']);
                        $row['img_profile'] = "data:$mimeType;base64," . base64_encode($row['img_profile']);
                    }
                }
            }
        
            echo json_encode($data);
            break;
        
        // For Registrar to create new staff accounts
        case 'POST':
            // TODO use phpmailer to verify account

            $user = json_decode(file_get_contents('php://input'));
            // remember_token,
            // :remember,
            // email_verified_at,
            // :verified,
            $qy = "INSERT INTO users(img_profile, name, email,
            phone, password, account_type, 
            created_at, updated_at) 
            VALUES(:pfp, :name, :email,  :phone, :pass,
            :role,  :created, :updated)";
            
            if (isset($user->profilePicture)) {
                // Extract the Base64 part and validate MIME type
                if (preg_match('/^data:(image\/\w+);base64,/', $user->profilePicture, $type)) {
                    $mimeType = $type[1]; // e.g., image/png
                    $foundPicture = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $user->profilePicture));
                } else {
                    echo json_encode(['status' => 0, 'message' => 'Invalid image format']);
                    exit;
                }
            }

            $hash_pass = password_hash($user->staffPass, PASSWORD_BCRYPT);
            $token = createRememberToken();
            $created_at = date('Y-m-d H:i:s');            
            $updated_at = date('Y-m-d H:i:s');

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':pfp', $foundPicture, PDO::PARAM_LOB);
            $stmt->bindParam(':name', $user->staffName);
            $stmt->bindParam(':email', $user->staffEmail);
            // $stmt->bindParam(':verified', $user->verified); // TODO verification feature (one time email?)
            $stmt->bindParam(':phone', $user->staffPhone);
            $stmt->bindParam(':pass', $hash_pass); 
            $stmt->bindParam(':role', $user->staffRole);
            //$stmt->bindParam(':remember', $token);
            $stmt->bindParam(':created', $created_at); 
            $stmt->bindParam(':updated', $updated_at);

            if ($stmt->execute()) {
                $response = [
                    'status' => 1,
                    'message' => 'User created successfully',
                    'newUser' => [
                        'id' => $db_connection->lastInsertId(),
                        'pfp' => $foundPicture,
                        'name' => $user->staffName,
                        'email' => $user->staffEmail,
                        'phone' => $user->staffPhone,
                        'role' => $user->staffRole,
                        'createdAt' => $created_at,
                        'updatedAt' => $updated_at,
                    ]
                ];
            } else {
                $response = ['status'=>0, 'message'=>'SORRY, Failed to create user!'];
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
                if (preg_match('/^data:(image\/\w+);base64,/', $user->profilePicture, $type)) {
                    $mimeType = $type[1];
                    $foundPicture = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $user->profilePicture));
                    $query .= "img_profile=:img_profile, ";
                    $params[':img_profile'] = $foundPicture;
                } else {
                    echo json_encode(['status' => 0, 'message' => 'Invalid image format']);
                    exit;
                }
            }
            if (isset($user->staffName)) {
                $query .= "name=:name, ";
                $params[':name'] = $user->staffName;
            }
            if (isset($user->staffEmail)) {
                $query .= "email=:email, ";
                $params[':email'] = $user->staffEmail;
            }
            if (isset($user->staffPhone)) {
                $query .= "phone=:phone, ";
                $params[':phone'] = $user->staffPhone;
            }
            if (isset($user->staffRole)) {
                $query .= "account_type=:role, ";
                $params[':role'] = $user->staffRole;
            }
            // if (isset($user->staffPass)) {
            //     $query .= "password=:pass, ";
            //     $params[':pass'] = password_hash($user->staffPass, PASSWORD_BCRYPT);
            // }
            
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
            $found_id = isset($_GET['id']) ? $_GET['id'] : null;
            error_log("Received ID for deactivation: " . $found_id); // Log the received ID
            
            if (!$found_id || !is_numeric($found_id)) {
                error_log("Invalid or missing ID");
                echo json_encode(['status' => 0, 'message' => 'Invalid or missing ID']);
                exit;
            }

            try {
                $qy = "UPDATE users SET status = 'deactivated', updated_at = NOW() WHERE id = :id";
                $stmt = $db_connection->prepare($qy);
                $stmt->bindParam(':id', $found_id);
        
                if ($stmt->execute()) {
                    echo json_encode(['status' => 1, 'message' => 'User deactivated successfully']);
                } else {
                    error_log("Deactivate user error: " . json_encode($stmt->errorInfo())); // Log detailed error info
                    echo json_encode(['status' => 0, 'message' => 'Failed to deactivate user']);
                }
            } catch (Exception $e) {
                error_log("Exception during deactivate user: " . $e->getMessage());
                echo json_encode(['status' => 0, 'message' => 'Internal server error during delete']);
            }

            exit;   
    }
?>