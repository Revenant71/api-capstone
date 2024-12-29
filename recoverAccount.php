<?php
require_once('connectDb.php');
require 'configSmtp.php'; 
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use OTPHP\TOTP;
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: PATCH, POST");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 
$css = file_get_contents('http://localhost/api_drts/cssEmailRecover.php'); // get css file

// TODO add cooldown for resetting password
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'PATCH':
        $account = json_decode(file_get_contents(filename: 'php://input'));
        $hash_newPassword = password_hash($account->finalPassword, PASSWORD_BCRYPT); 
        $updated_at = date('Y-m-d H:i:s');

        $query_set_newpassword = "UPDATE users SET password=:newPassword, updated_at=:updated WHERE email=:email LIMIT 1";
        $stmt = $db_connection->prepare($query_set_newpassword);
        $stmt->bindParam(':newPassword', $hash_newPassword);
        $stmt->bindParam(':updated', $updated_at);
        $stmt->bindParam(':email', $account->email);
        
        if ($stmt->execute()) {
            $response = [
                'status'=>1,
                'message'=>'PASSWORD SUCCESSFULLY CHANGED',
            ];
        } else {
            $response = [
                'status'=>0,
                'message'=>'FAILED TO CHANGE PASSWORD',
            ];
        }

        echo json_encode($response);
        break;

    case 'POST':
        $account = json_decode(file_get_contents(filename: 'php://input'));
        // $token_verify = md5(rand()); 

        // generate OTP
        $totp = TOTP::create();
        $totp->setDigits(6); // set OTP length
        $stringOTP = $totp->now(); // OTP string
        $hashedOTP = password_hash($stringOTP, PASSWORD_DEFAULT);
        $expiry = time() + 300; // OTP valid for 5 minutes
        $expiryDatetime = (new DateTime())->setTimestamp($expiry)->format('Y-m-d H:i:s'); 
        
        $query_otp_user = "UPDATE `users` SET otp=:otp, otp_expires_at=:otp_expire WHERE email=:email LIMIT 1";
        $query_email_user = "SELECT `email` FROM users WHERE email=:email LIMIT 1";

        // check for account in users table
        $stmt = $db_connection->prepare($query_email_user);
        $stmt->bindParam(':email', $account->forgotEmail);
        $stmt->execute();
        $dataUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dataUser){
            try {
                // config
                $mailRecover = new PHPMailer(true);
                $mailRecover->Host = MAILHOST;
                $mailRecover->isSMTP();
                $mailRecover->SMTPAuth = true;
                $mailRecover->Username = USERNAME;
                $mailRecover->Password = PASSWORD;
                $mailRecover->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
                $mailRecover->Port = 587;

                // from, to, body
                $mailRecover->setFrom(SEND_FROM, SEND_FROM_NAME);
                $mailRecover->addAddress($dataUser['email']);
                $mailRecover->addReplyTo(REPLY_TO, REPLY_TO_NAME);
                $mailRecover->isHTML(true);
                $mailRecover->Subject = 'DocuQuest Reset Password';
                $mailRecover->Body = '
                    <html>
                        <head>
                        <style>
                            ' . $css . '
                        </style>
                        </head>
                        <body> 
                            <strong>Your OTP is:</strong>
                            <br/>
                            <h2>'.htmlspecialchars($stringOTP, ENT_QUOTES, 'UTF-8').'</h2>
                            
                            <p>DO NOT use on any OTP code unless you are the one who sent it.</p>
                            <p>This OTP code expires in 5 Minutes.</p>
                            <br/>
                            <i>Do not reply to this email.</i>
                        </body>
                    </html>
                ';
                $mailRecover->AltBody = '
                    Your OTP is:'.htmlspecialchars($stringOTP, ENT_QUOTES, 'UTF-8').'
                    DO NOT REPLY TO THIS EMAIL.
                ';

                if ($mailRecover->send())
                {
                    $stmt = $db_connection->prepare($query_otp_user);
                    $stmt->bindParam(':otp', $hashedOTP);
                    $stmt->bindParam(':otp_expire', $expiryDatetime);
                    $stmt->bindParam(':email', $dataUser['email']);
                    $stmt->execute();

                    $response = [
                        'status'=>1,
                        'message'=> 'Found a user account with the given email',
                        'otpData'=>[
                            'expiry' => $expiry,
                        ]
                    ];
                }
            } catch (Exception $e) {
                // Handle the error
                $response = [
                    'status'=>0,
                    'message'=> "Message could not be sent to user. Mailer Error: {$mailRecover->ErrorInfo}",
                ];
            }

        } else {
            $response = [
                'status'=>0,
                'message'=> 'SORRY, Did not find a user account with this email'
            ];
        }

        echo json_encode($response);
        break;
}

?>