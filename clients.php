<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, enctype");
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
            $URI_array = explode('/', string: $_SERVER['REQUEST_URI']);
            $found_id = isset($URI_array[3]) ? $URI_array[3] : null;

            $qy = "SELECT * FROM clients";

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
            $client = json_decode(file_get_contents('php://input'));
            
            $qy = "INSERT INTO clients(img_profile, name, email, 
            password, remember_token,
            created_at, updated_at) 
            VALUES(:pfp, :name, :email, :pass,
            :remember, :created, :updated)";
            
            $foundPicture = base64_decode($user->profilePicture);
            $hash_pass = password_hash($client->clientPass, PASSWORD_BCRYPT);
            $token = createRememberToken();
            $created_at = date('Y-m-d H:i:s');            
            $updated_at = date('Y-m-d H:i:s');

            $stmt = $db_connection->prepare($qy);
            
            $stmt->bindParam(':pfp', $foundPicture, PDO::PARAM_LOB);
            $stmt->bindParam(':name', $client->clientName);
            $stmt->bindParam(':email', $client->clientEmail);
            $stmt->bindParam(':pass', $hash_pass); 
            $stmt->bindParam(':remember', $token);
            $stmt->bindParam(':created', $created_at); 
            $stmt->bindParam(':updated', $updated_at);

            if ($stmt->execute()) {
                $response = [
                    'status' => 1,
                    'message' => 'Client created successfully',
                    'newUser' => [
                        'id' => $db_connection->lastInsertId(),
                        'pfp' => $foundPicture, 
                        'name' => $client->clientName,
                        'email' => $client->clientEmail,
                        'createdAt' => $created_at,
                        'updatedAt' => $updated_at,
                    ]
                ];
            } else {
                $response = ['status'=>0, 'message'=>'SORRY, Failed to create client!'];
            }
            
            echo json_encode($response);
            break;

        case 'PATCH':
            $client = json_decode(file_get_contents('php://input'));
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = $URI_array[3];
            
            $hash_pass = password_hash($client->clientPass, PASSWORD_BCRYPT);
            $token = createRememberToken();
            $updated_at = date('Y-m-d H:i:s');

            if (!$found_id && is_numeric(!$found_id)) {
                echo json_encode(['status' => 0, 'message' => 'Invalid or missing ID']);
                exit;
            } 

            $query = "UPDATE clients SET ";
            $params = [];
            
            if (isset($client->profilePicture)) {
                $foundPicture = base64_decode($client->profilePicture);
                $query .= "img_profile=:img_profile, ";
                $params[':img_profile'] = $foundPicture;
            }
            if (isset($client->clientName)) {
                $query .= "name=:name, ";
                $params[':name'] = $client->clientName;
            }
            if (isset($client->clientEmail)) {
                $query .= "email=:email, ";
                $params[':email'] = $client->clientEmail;
            }
            if (isset($user->clientPass)) {
                $query .= "password=:pass, ";
                $params[':pass'] = $hash_pass;
            }

            $query .= "updated_at=:updated WHERE id=:id";
            $params[':updated'] = date('Y-m-d H:i:s');
            $params[':id'] = $found_id;

            $stmt = $db_connection->prepare($query);
            
            if ($stmt->execute($params)) {
                $stmt = $db_connection->prepare("SELECT * FROM clients   WHERE id=:id");
                $stmt->bindParam(':id', $found_id);
                $stmt->execute();
                $updatedClient = $stmt->fetch(PDO::FETCH_ASSOC);
            
                echo json_encode(['status' => 1, 'message' => 'Client updated', 'data' => $updatedClient]);
            } else {
                echo json_encode(['status' => 0, 'message' => 'Update failed']);
            }

            break;
        
            case 'DELETE':
                $found_id = isset($_GET['id']) ? $_GET['id'] : null;
                
                if (!$found_id || !is_numeric($found_id)) {
                    echo json_encode(['status' => 0, 'message' => 'Invalid or missing ID']);
                    exit;
                }
                
                try {
                    $qy = "DELETE FROM clients WHERE id=:id";
                    $stmt = $db_connection->prepare($qy);
                    $stmt->bindParam(':id', $found_id);
            
                    if ($stmt->execute()) {
                        echo json_encode(['status' => 1, 'message' => 'Client deleted successfully']);
                    } else {
                        error_log("Delete client error: " . json_encode($stmt->errorInfo())); // Log detailed error info
                        echo json_encode(['status' => 0, 'message' => 'Failed to delete client']);
                    }
                } catch (Exception $e) {
                    error_log("Exception during delete client: " . $e->getMessage());
                    echo json_encode(['status' => 0, 'message' => 'Internal server error during delete']);
                }

                exit;
            
    }
?>