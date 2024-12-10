<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'POST':
        $input = json_decode(file_get_contents(filename: 'php://input'));

        $query_select_otp = "SELECT `otp`, `otp_expires_at`, `otp_attempts` FROM users WHERE email=:email LIMIT 1";
        $stmt = $db_connection->prepare($query_select_otp);
        $stmt->bindParam(':email', $input->email);
        $stmt->execute();
        $dataUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $current_time = new DateTime();
        $otp_expiration = new DateTime($dataUser['otp_expires_at']);
        
        if ($current_time > $otp_expiration) {
            echo json_encode(['status' => 0, 'message' => 'OTP has expired. Please request a new OTP.']);
            exit;
        }

        // Check if the OTP attempts exceed the allowed limit
        if ($dataUser['otp_attempts'] >= 3) {
            echo json_encode(['status' => 0, 'message' => 'Too many failed attempts. Please request a new OTP.']);
            exit;
        }

        if(password_verify($input->otp, $dataUser['otp'])){
            $query_reset_attempts = "UPDATE users SET otp_attempts = 0 WHERE email=:email";
            $stmt = $db_connection->prepare($query_reset_attempts);
            $stmt->bindParam(':email', $input->email);
            $stmt->execute();
            
            $response = [
                'status'=>1,
                'message'=> 'OTP is correct.'
            ];
        } else {
            $query_update_attempts = "UPDATE users SET otp_attempts = otp_attempts + 1 WHERE email=:email";
            $stmt = $db_connection->prepare($query_update_attempts);
            $stmt->bindParam(':email', $input->email);
            $stmt->execute();

            $response = [
                'status'=>0,
                'message'=> 'Try again, OTP is wrong.'
            ];
        }
    echo json_encode($response);
    break;
    
}
?>