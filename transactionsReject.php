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
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, PATCH");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();
$css = file_get_contents('http://localhost/api_drts/cssEmailReject.php');

$method = $_SERVER['REQUEST_METHOD'];
switch ($method){
    case 'POST':
        $transaction = json_decode(file_get_contents('php://input'), true);
        
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3] ?? null;

        if (!$found_reference_no || empty($transaction['staff']) || empty($transaction['owner_lastname']) || empty($transaction['requestor_email']) || empty($transaction['reason']) || empty($transaction['remarks'])) {
            echo json_encode(['status' => 0, 'message' => 'Invalid staff, reference number, owner lastname, requestor email, reason, or remarks']);
            exit;
        }

        $qy = "UPDATE transactions 
        SET statusTransit = :statusTransit, id_employee = :id_employee, updated_at = NOW() 
        WHERE reference_number = :reference AND lastname_owner = :lastname_owner";
        
        $status_rejected = "Rejected";

        $stmt = $db_connection->prepare($qy);
        $stmt->bindParam(':statusTransit', $status_rejected, PDO::PARAM_STR);
        $stmt->bindParam(':id_employee', $transaction['staff'], PDO::PARAM_INT);
        $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
        $stmt->bindParam(':lastname_owner', $transaction['owner_lastname'], PDO::PARAM_STR);
        
        if($stmt->execute()){
            try {
                $mailReject = new PHPMailer(true);
                $mailReject->Host = MAILHOST;
                $mailReject->isSMTP();
                $mailReject->SMTPAuth = true;
                $mailReject->Username = USERNAME;
                $mailReject->Password = PASSWORD;
                $mailReject->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
                $mailReject->Port = 587;

                $mailReject->setFrom(SEND_FROM, SEND_FROM_NAME);
                $mailReject->addAddress($transaction['requestor_email']);
                $mailReject->addReplyTo(REPLY_TO, REPLY_TO_NAME);
                $mailReject->isHTML(true);
                $mailReject->Subject = $found_reference_no . ' has been Rejected';
                $mailReject->Body = '
                    <html>
                        <head>
                        <style>
                            ' . $css . '
                        </style>
                        </head>
                        <body> 
                            <strong>Hello Mr./Ms./Mrs. '.$transaction['owner_lastname'].'.</strong>
                            <br/>
                            <p>We regret to inform you that your request, '.$found_reference_no.'</p>
                            <p><strong>Has been rejected.</strong></p>
                            <br/>
                            <em>Below are details for this course of action.</em>
                            <h2>Reason: '.$transaction['reason'].'</h2>
                            <br/>
                            <h2>Remarks:</h2>
                            <p><em>'.$transaction['remarks'].'</em></p>       
                            
                            <br/>
                            <strong>You can reach out to the Registrar\'s Office for further actions upon receiving this message.</strong>
                            <br/>
                            <p>To make a new request,</p>
                            <p>please proceed to <a href="http://localhost:3000/start" target="_blank" title="Click here to make a new document request instead.">this link</a></p>
                            <h3>This an auto-generated email. <em>Please do not reply.</em></h3>
                        </body>
                    </html>
                ';
                $mailReject->AltBody = "
                Hello Mr./Ms./Mrs. " . $transaction['owner_lastname'] . ".
                We apologize to inform you that your request, " . $found_reference_no . " has been rejected.
                
                Reason: " . $transaction['reason'] . "
                
                Remarks:
                " . $transaction['remarks'] . "
                
                You can reach out to the Registrar's Office for further actions upon receiving this message.
                To make a new request, please proceed to this link: http://localhost:3000/start.
                
                This is an auto-generated email. Please do not reply.
                ";
                

                if($mailReject->send()){
                    // $response = ['status'=>1, 'message'=>`Reject statusTransit successful!`];
                    $response = ['status'=>1, 'message'=>`Emailed rejection successfully.`];
                }
            } catch (Exception $e) {
                $response = [
                    'status'=>0,
                    'message'=> "Email could not be sent to user. Mailer Error: {$mailReject->ErrorInfo}",
                ];
            }

        } else {
            $response = ['status'=>0, 'message'=>'Reject '. htmlspecialchars($found_reference_no) . ' failed!'];
        }
        
        echo json_encode($response);
        break;
        
    case 'PATCH':
        
        break;
}
?>