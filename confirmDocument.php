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
header("Access-Control-Allow-Methods: POST");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 
$css = file_get_contents('http://localhost/api_drts/cssEmailRequestform.php');

$method = $_SERVER['REQUEST_METHOD'];
switch ($method){
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['name_admin']) || empty($data['email_admin']) || empty($data['name_doc']) || empty($data['processing_days']) || empty($data['price'])) {
            echo json_encode(value: [
                'status' => 0,
                'message' => 'Missing required fields: name_admin, email_admin, name_doc, processing_days, or price.',
            ]);
            exit;
        }

        try {
            $mailDocument = new PHPMailer(true);
            $mailDocument->Host = MAILHOST;
            $mailDocument->isSMTP();
            $mailDocument->SMTPAuth = true;
            $mailDocument->Username = USERNAME;
            $mailDocument->Password = PASSWORD;
            $mailDocument->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
            $mailDocument->Port = 587;

            // from, to, body
            $mailDocument->setFrom(SEND_FROM, SEND_FROM_NAME);
            $mailDocument->addAddress($data['email_admin']);
            $mailDocument->addReplyTo(REPLY_TO, REPLY_TO_NAME);
            $mailDocument->isHTML(true);
            $mailDocument->Subject = 'New Document Type Created';
            $mailDocument->Body = '
            <html>
                <head>
                <style>
                    ' . $css . '
                </style>
                </head>
                <body>
                    <p>Hi, '.$data['name_admin'].'.</p>
                    <br/>
                    This message is to confirm that you have successfully made a new document type:
                    <table>
                      <thead>
                        <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Price</th>
                        <th scope="col">Processing Days</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                        <th scope="row">'.$data['name_doc'].'</th>
                        <td>'.$data['price'].'</td>
                        <td>'.$data['processing_days'].'</td>
                        </tr>
                      </tbody>
                    </table>
                </body>
            </html>      
            ';

            $mailDocument->AltBody = "
            Hi, {$data['name_admin']},
            
            This message is to confirm that you have successfully made a new document type:
            
            Name              | Price    | Processing Days
            ------------------|----------|----------------
            {$data['name_doc']} | {$data['price']} | {$data['processing_days']}
            
            Thank you for using DocuQuest.
            
            This is an auto-generated email. Please do not reply.
            ";
            
            
            if ($mailDocument->send())
            {
                $response = [
                    'status' => '1',
                    'message' => 'Reference number emailed successfully',
                    'receivedRef' => $display_reference,
                ];
            }
        } catch (Exception $e) {
            $response = [
                'status'=>0,
                'message'=> "Email could not be sent to admin. Mailer Error: {$mailDocument->ErrorInfo}",
            ];
        }
    echo json_encode($response);
    break;
}
?>