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
            $URI_array = explode(separator: '/', string: $_SERVER['REQUEST_URI']);
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
            
            // TODO form field "file_pfp" file upload into column "img_profile"
            // Handle base64 file decoding
            if (!empty($user->file_pfp)) {
                $base64String = $user->file_pfp;
                // TODO folder for file uploads, separate folders for clients and users
                $uploadDir = 'uploads/users'; // Ensure this directory exists and is writable
                $fileName = uniqid() . '.png'; // Customize file extension as needed
                $filePath = $uploadDir . $fileName;

                $fileData = explode(',', $base64String)[1]; // Remove the base64 prefix
                file_put_contents($filePath, base64_decode($fileData));
            }            

            $qy = "UPDATE users SET img_profile=:=file_pfp, name=:name, email=:email,
            password=:pass, account_type=:role,
            remember_token=:remember, updated_at=:updated WHERE id=:id";

            $hash_pass = password_hash($user->staffPass, PASSWORD_BCRYPT);
            $token = createRememberToken();
            $updated_at = date('Y-m-d H:i:s');

            if ($found_id && is_numeric($found_id)) {
                $stmt->bindParam(':id', $found_id);
            }

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':img_profile', $filePath);
            $stmt->bindParam(':name', $user->name);
            $stmt->bindParam(':email', $user->email);
            $stmt->bindParam(':pass', $hash_pass);
            $stmt->bindParam(':role', $user->role); // TODO pass hardcoded role from JS sessionstorage
            $stmt->bindParam(':remember', $token);
            $stmt->bindParam(':updated', $updated_at);
            
            if ($stmt->execute()) {
                $response = ['status'=>1, 'message'=>'PATCH user successful.'];
            } else {
                $response = ['status'=>0, 'message'=>'SORRY, PATCH user failed.'];
            }

            echo json_encode($response);
            break;
        
        case 'DELETE':
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = $URI_array[3];

            $qy = "DELETE FROM users WHERE id=:id";

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':id', $found_id);

            if($stmt->execute()){
                $response = ['status'=>1, 'message'=>'DELETE user successful.'];    
            } else {
                $response = ['status'=>0, 'message'=>'Oops! DELETE user failed.'];
            }

            echo json_encode($response);
            break;
    }
?>