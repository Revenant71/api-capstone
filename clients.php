<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
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
            $URI_array = explode('/', string: $_SERVER['REQUEST_URI']);
            $found_id = isset($URI_array[3]) ? $URI_array[3] : null;

            $qy = "SELECT * FROM clients";

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
            $client = json_decode(file_get_contents('php://input'));
            
            $qy = "INSERT INTO clients(name, email, 
            password, remember_token,
            created_at, updated_at) 
            VALUES(:name, :email, :pass,
            :remember, :created, :updated)";
            
            $hash_pass = password_hash($client->clientPass, PASSWORD_BCRYPT);
            $token = createRememberToken();
            $created_at = date('Y-m-d H:i:s');            
            $updated_at = date('Y-m-d H:i:s');

            $stmt = $db_connection->prepare($qy);
            // TODO refer to the name attributes in create new client account form fields
            $stmt->bindParam(':name', $client->clientName);
            $stmt->bindParam(':email', $client->clientEmail);
            $stmt->bindParam(':pass', $hash_pass); 
            $stmt->bindParam(':remember', $token);
            $stmt->bindParam(':created', $created_at); 
            $stmt->bindParam(':updated', $updated_at);

            if ($stmt->execute()) {
                $response = ['status'=>1, 'message'=>'POST client successful.'];
            } else {
                $response = ['status'=>0, 'message'=>'SORRY, POST client failed.'];
            }
            
            echo json_encode($response);
            break;

        case 'PATCH':
            $client = json_decode(file_get_contents('php://input'));

            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = $URI_array[3];

            $qy = "UPDATE clients SET name=:name, email=:email,
            password=:pass, remember_token=:remember, updated_at=:updated WHERE id=:id";

            $hash_pass = password_hash($client->clientPass, PASSWORD_BCRYPT);
            $token = createRememberToken();
            $updated_at = date('Y-m-d H:i:s');

            if ($found_id && is_numeric($found_id)) {
                $stmt->bindParam(':id', $found_id);
            }

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':name', $client->name);
            $stmt->bindParam(':email', $client->email);
            $stmt->bindParam(':pass', $hash_pass);
            $stmt->bindParam(':remember', $token);
            $stmt->bindParam(':updated', $updated_at);
            
            if ($stmt->execute()) {
                $response = ['status'=>1, 'message'=>'PATCH client successful.'];
            } else {
                $response = ['status'=>0, 'message'=>'SORRY, PATCH client failed.'];
            }

            echo json_encode($response);
            break;
        
        case 'DELETE':
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = $URI_array[3];

            $qy = "DELETE FROM clients WHERE id=:id";

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':id', $found_id);

            if($stmt->execute()){
                $response = ['status'=>1, 'message'=>'DELETE client successful.'];    
            } else {
                $response = ['status'=>0, 'message'=>'Oops! DELETE client failed.'];
            }

            echo json_encode($response);
            break;
    }
?>