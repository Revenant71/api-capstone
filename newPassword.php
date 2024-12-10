<?php 
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST");
    
    $db_attempt = new connectDb;
    $db_connection = $db_attempt->connect(); 

    $method = $_SERVER['REQUEST_METHOD'];
    switch ($method){
        case 'POST':
            // TODO use in formrecover
            $account = json_decode(file_get_contents(filename: 'php://input'));
            $hash_pass = password_hash($account->newPassword, PASSWORD_BCRYPT); // TODO refer to form field name in formrecover
            
            $query_select_otp = "SELECT `otp`, `otp_expires_at`, `otp_attempts` FROM users WHERE email=:email LIMIT 1";
            $stmt = $db_connection->prepare($query_select_otp);
            $stmt->bindParam(':email', $account->currentEmail); // TODO refer to form field name in formrecover
            $stmt->execute();
            $dataUser = $stmt->fetch(PDO::FETCH_ASSOC);

            $current_time = new DateTime();
            $otp_expiration = new DateTime($dataUser['otp_expires_at']);
            
            if ($current_time > $otp_expiration) {
                echo json_encode(['status' => 0, 'message' => 'OTP has expired. Please request a new OTP.']);
                exit;
            }

            if ($dataUser['otp_attempts'] >= 3) {
                echo json_encode(['status' => 0, 'message' => 'Too many failed attempts. Please request a new OTP.']);
                exit;
            }

            if(password_verify($account->inputOtp, $dataUser['otp'])){
                $updated = date('Y-m-d H:i:s');
                
                $query_update_password = "UPDATE `users` SET password=:newPassword, updated_at=:updated WHERE email=:email LIMIT 1";
                $stmt = $db_connection->prepare($query_update_password);
                $stmt->bindParam(':newPassword', $hash_pass);
                $stmt->bindParam(':updated', $updated);  
                $stmt->bindParam(':email', $dataUser['email']);
                $stmt->execute();

                $response = [
                    'status'=>1,
                    'message'=> 'Successfully changed password.'
                ];
            } else {
                $response = [
                    'status'=>0,
                    'message'=> 'Sorry, too many wrong OTP attempts.'
                ];
            }

            echo json_encode($response);
            break;            
    }

?>