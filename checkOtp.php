<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        if(!isset($_SESSION['otp'])){
            $response = [
                'statusOtp'=>0,
                'msgOtp'=>'OTP INVALID; ENTERED OTP DOES NOT MATCH'
            ];
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $userOtp = $input['otp'];
        $userEmail = $input['email'];
        if(isset($_SESSION['otp']) && $_SESSION['otp']['email'] === $userEmail){
            
            if ($_SESSION['otp']['otp'] === $userOtp) {
                if (time() < $_SESSION['otp']['expiry']) {
                    $response = [
                        'status' => 1,
                        'message' => 'OTP verified successfully.'
                    ];
        
                    // Clear the OTP from session after successful verification
                    unset($_SESSION['otp']);
                } else {
                    $response = [
                        'status' => 0,
                        'message' => 'OTP has expired.'
                    ];
                }
            } else {
                $response = [
                    'status' => 0,
                    'message' => 'Invalid OTP.'
                ];
            }
        } else {
            $response = [
                'status' => 0,
                'message' => 'No OTP found for the given email.'
            ];
        }

    echo json_encode($response);
    break;
    
}
?>