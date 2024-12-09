<?php
require_once('connectDb.php');
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use OTPHP\TOTP;
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 
$css = file_get_contents('http://localhost/api_drts/cssEmailRecover.php'); // get css file

// TODO add cooldown for resetting password
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        # code...
        
        break;

    case 'POST':
        $account = json_decode(file_get_contents(filename: 'php://input'));
        // $token_verify = md5(rand()); 

        // generate OTP
        $totp = TOTP::create();
        $totp->setDigits(6); // set OTP length
        $stringOTP = $totp->now(); // OTP string
        // $hashedOTP = password_hash($stringOTP, PASSWORD_DEFAULT);
        $expiry = time() + 300; // OTP valid for 5 minutes
        
        $query_email_user = "SELECT `email` FROM users WHERE email=:email LIMIT 1";
        $query_email_client = "SELECT `email` FROM clients WHERE email=:email LIMIT 1";

        // check for account in users table
        $stmt = $db_connection->prepare($query_email_user);
        $stmt->bindParam(':email', $account->forgotEmail);
        $stmt->execute();
        $dataUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dataUser){
            try {
                // config
                $mailRecover = new PHPMailer(true);
                $mailRecover->Host = 'smtp.gmail.com';
                $mailRecover->isSMTP();
                $mailRecover->SMTPAuth = true;
                $mailRecover->Username = 'myself@gmail.com';
                $mailRecover->Password = 'password';
                $mailRecover->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
                $mailRecover->Port = 587;

                // from, to, body
                $mailRecover->setFrom('myself@gmail.com', 'MeMyself');
                $mailRecover->addReplyTo('myself@gmail.com', 'MeMyself');
                $mailRecover->addAddress($dataUser['email']);
                $mailRecover->Subject = 'DocuQuest Reset Password';
                $mailRecover->isHTML(true);
                $mailRecover->ContentType = 'text/html';
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
                            <h3>'.htmlspecialchars($stringOTP, ENT_QUOTES, 'UTF-8').'</h3>

                            <p>DO NOT use on any OTP code unless you are the one who sent it.</p>
                            <br/>
                            <i>Do not reply to this email.</i>
                        </body>
                    </html>
                ';
                $mailRecover->AltBody = '
                    Your OTP is:'.htmlspecialchars($stringOTP, ENT_QUOTES, 'UTF-8').'
                    DO NOT REPLY TO THIS EMAIL.
                ';

                $mailRecover->send();

                // TODO verify otp only on backend
                $response = [
                    'status'=>1,
                    'message'=> 'Found a user account with the given email',
                    'foundEmail'=> $dataUser['email'],
                    'otpData'=>[
                        'expiry' => $expiry,
                        'otp' => $stringOTP
                    ]
                ];
            } catch (Exception $e) {
                // Handle the error
                $response = [
                    'status'=>0,
                    'message'=> "Message could not be sent. Mailer Error: {$mailRecover->ErrorInfo}",
                ];
            }

        } else {
            $response = [
                'status'=>0,
                'message'=> 'SORRY, Did not find a user account with this email',
            ];

            // check for account in clients table
            $stmt = $db_connection->prepare($query_email_client);
            $stmt->bindParam(':email', $account->forgotEmail);
            $stmt->execute();
            $dataClient = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($dataClient){
                try {
                    // config
                    $mailRecover = new PHPMailer(true);
                    $mailRecover->Host = 'smtp.gmail.com';
                    $mailRecover->isSMTP();
                    $mailRecover->SMTPAuth = true;
                    $mailRecover->Username = 'myself@gmail.com';
                    $mailRecover->Password = 'password';
                    $mailRecover->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
                    $mailRecover->Port = 587;

                    // from, to, body
                    $mailRecover->setFrom('myself@gmail.com', 'MeMyself');
                    $mailRecover->addReplyTo('myself@gmail.com', 'MeMyself');
                    $mailRecover->addAddress($dataClient['email']);
                    $mailRecover->Subject = 'DocuQuest Reset Password';
                    $mailRecover->isHTML(true);
                    $mailRecover->ContentType = 'text/html';
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
                                <h3>'.htmlspecialchars($stringOTP, ENT_QUOTES, 'UTF-8').'</h3>
    
                                <p>DO NOT use on any OTP code unless you are the one who sent it.</p>
                                <br/>
                                <i>Do not reply to this email.</i>
                            </body>
                        </html>
                    ';
                    $mailRecover->AltBody = '
                    Your OTP is:'.htmlspecialchars($stringOTP, ENT_QUOTES, 'UTF-8').'
                    DO NOT REPLY TO THIS EMAIL.
                    ';

                    $mailRecover->send();
    
                    $response = [
                        'status'=>1,
                        'message'=> 'Found a client account with the given email',
                        'foundEmail'=> $dataClient['email'],
                        'otpData'=>[
                            'expiry' => $expiry,
                            'otp' => $stringOTP
                        ]
                    ];
                } catch (Exception $e) {
                    // Handle the error
                    $response = [
                        'status'=>0,
                        'message'=> "Message could not be sent. Mailer Error: {$mailRecover->ErrorInfo}"
                    ];
                }
            } else {
                $response = [
                    'status'=>0,
                    'message'=> 'SORRY, Did not find a client account with this email',
                ];
            }
        }

        echo json_encode($response);
        break;
}

?>