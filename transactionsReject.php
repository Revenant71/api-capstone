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

        if (!$found_reference_no || empty($transaction['reason']) || empty($transaction['remarks']) || empty($transaction['staff']) || empty($transaction['owner_firstname']) || empty($transaction['requestor_email']) || empty($transaction['reason']) || empty($transaction['remarks'])) {
            echo json_encode(['status' => 0, 'message' => 'Invalid staff, reference number, reason, remarks owner firstname, requestor email, reason, or remarks']);
            exit;
        }

        $qy = "UPDATE transactions 
        SET statusTransit = :statusTransit, id_employee = :id_employee, reason_reject = :reason_reject, remarks = :remarks, updated_at = NOW() 
        WHERE reference_number = :reference AND firstname_owner = :firstname_owner";
        
        $status_rejected = "Rejected";

        $stmt = $db_connection->prepare($qy);
        $stmt->bindParam(':statusTransit', $status_rejected, PDO::PARAM_STR);
        $stmt->bindParam(':reason_reject', $transaction['reason'], PDO::PARAM_STR);
        $stmt->bindParam(':remarks', $transaction['remarks'], PDO::PARAM_STR);
        $stmt->bindParam(':id_employee', $transaction['staff'], PDO::PARAM_INT);
        $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
        $stmt->bindParam(':firstname_owner', $transaction['owner_firstname'], PDO::PARAM_STR);
        
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
                $mailReject->Subject = $found_reference_no . ' DocuQuest Rejected';
                // TODO external css as ' . $css . '
                // TODO $transaction['receipt'] as html image
                $mailReject->Body = '
                    <html>
                        <head>
                        <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 0;
                            padding: 0;
                            line-height: 1.6;
                            color: #333;
                        }

                        table {
                            width: 60%;
                            border-collapse: collapse;
                            margin: 20px auto; 
                            font-size: 16px;
                            border: 1px solid #ddd;
                        }

                        th, td {
                            text-align: left;
                            padding: 12px;
                            border: 1px solid #ddd;
                        }

                        th {
                            background-color: #f2f2f2;
                            font-weight: bold;
                        }

                        td.remarks {
                            height: 100px; 
                            vertical-align: top;
                            text-align: justify; 
                        }

                        td.reason {
                            text-align: justify; 
                        }

                        h3, p {
                            text-align: justify;
                            margin: 10px 20px;
                        }

                        strong {
                            font-weight: bold;
                        }
                        </style>
                        </head>
                        <body> 
                            <strong>Hi, '.$transaction['owner_firstname'].'.</strong>
                            <br/>
                            <p>We regret to inform you that your request, '.$found_reference_no.' has been <strong>Rejected</strong>.</p>
                            <br/>
                            <em>Below are the details for this course of action:</em>
                            <table>
                                <tr>
                                    <th>Reason</th>
                                    <td>'.$transaction['reason'].'</td>
                                </tr>
                                <tr>
                                    <th>Remarks</th>
                                    <td class="remarks">'.$transaction['remarks'].'</td>
                                </tr>
                            </table>

                            <br/>
                            <strong>You can reach out to the Registrar\'s Office for further actions upon receiving this message.</strong>
                            <br/>
                            <p>To make a new request, please proceed to <a href="http://localhost:3000/start" target="_blank" title="Click here to make a new document request instead.">this link</a>.</p>
                            <h3>This is an auto-generated email. <em>Please do not reply.</em></h3>
                        </body>
                    </html>
                ';
                $mailReject->AltBody = "
                Hi, " . $transaction['owner_firstname'] . ".
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