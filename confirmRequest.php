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
switch ($method) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['reference']) || empty($data['email_req']) || empty($data['name_req'])) {
            echo json_encode([
                'status' => 0,
                'message' => 'Missing required fields: reference, email_req, or name_req.',
            ]);
            exit;
        }

        if (isset($data['reference'])) {
            $display_reference = $data['reference'];
            
            try {
                // config
                $mailRequest = new PHPMailer(true);
                $mailRequest->Host = MAILHOST;
                $mailRequest->isSMTP();
                $mailRequest->SMTPAuth = true;
                $mailRequest->Username = USERNAME;
                $mailRequest->Password = PASSWORD;
                $mailRequest->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
                $mailRequest->Port = 587;

                // from, to, body
                $mailRequest->setFrom(SEND_FROM, SEND_FROM_NAME);
                $mailRequest->addAddress($data['email_req']);
                $mailRequest->addReplyTo(REPLY_TO, REPLY_TO_NAME);
                $mailRequest->isHTML(true);
                $mailRequest->Subject = 'Document Request Confirmation';
                // TODO $data['payment_method']
                $mailRequest->Body = '
                    <html>
                        <head>
                        <style>
                            ' . $css . '
                        </style>
                        </head>
                        <body>
                            <p>Hi, '.$data['name_req'].'.</p>
                            
                            <p>Your request has been submitted.</p>
                            <p><em>Use the reference number below to track them on DocuQuest</em></p>
                            <ul>
                             <li> '.$display_reference.' </li>
                            </ul>
                            <p>In order to proceed with your request, please track your required fee by accessing this <a href="http://localhost:3000/start" target="_blank" title="Click here to track your request.">link.</a></p>
                            <br/>
                            <p>Below are your options for the official payment channels:</p>
                            <h3>Modes&nbsp;of&nbsp;Payment</h3>
                            <ul>
                              <li>
                                <strong>ON-SITE</strong>
                                <p>FINANCE OFFICE IS OPEN</p>
                                <p><em>8:00 AM TO 4:00 PM</em></p>
                                <p><em>MONDAY - FRIDAY</em></p>
                              </li>
                            <br/>
                              <li>
                                <strong>ONLINE</strong>
                                <p>Students may process payment through our collecting partners listed below.</p>
                                <strong>PAYMENT COLLECTION FACILITIES</strong>
                                <ul>
                                    <li>
                                    RCBC
                                        <ol type="A">
                                          <li>OVER THE COUNTER- TRANSACT IN BILLS PAYMENT (GREEN FORM) ;<br/> BILLER NAME - SOUTHWESTERN UNIVERSITY INC.</li>
                                          <br/>
                                          <li>ONLINE BANKING- PROCESS YOUR PAYMENT IN PAY BILLS ;<br/> BILLER NAME - SOUTHWESTERN UNIVERSITY INC.</li>
                                        </ol>
                                    </li>
                                    <br/>
                                    <li>
                                    BDO
                                        <ol type="A">
                                          <li>OVER THE COUNTER- TRANSACT IN BILLS PAYMENT ;<br/>INSTITUTIONAL CODE: 1054 ;<br/> BILLER NAME - SOUTHWESTERN UNIVERSITY INC.</li>
                                          <br/>
                                          <li>ONLINE BANKING- PROCESS YOUR PAYMENT IN BILLS PAYMENT;<br/> BILLER NAME - SOUTHWESTERN UNIVERSITY INC.</li>
                                        </ol>
                                    </li>
                                    <br/>
                                    <li>
                                    GCASH
                                        <ol type="A">
                                          <li>TRANSACT IN PAY BILLS ;<br/> BILLER NAME - PHINMA EDUCATION OR PHINMA SOUTHWESTERN UNIVERSITY</li>
                                          <p>IF ID NUMBER DOES NOT START WITH "05-" , PLEASE INDICATE "05-" BEFORE THE ID NUMBER</p>
                                        </ol>
                                    </li>
                                    <br/>
                                    <li>
                                    ECPAY
                                        <ol type="A">
                                          <li>BILLER NAME - PHINMA EDUCATION</li>
                                          <p>IF ID NUMBER DOES NOT START WITH "05-" , PLEASE INDICATE "05-" BEFORE THE ID NUMBER</p>
                                        </ol>
                                    </li>
                                </ul>
                              </li>
                            </ul>

                            <p>Thank you for using DocuQuest.</p>
                            <h3>This an auto-generated email. <em>Please do not reply.</em></h3>
                        </body>
                    </html>      
                ';

                $mailRequest->AltBody = "
                Hi, {$data['name_req']},
                
                Your requests have been submitted.
                Use the reference number below to track them on DocuQuest:
                
                {$display_reference}
                
                MODES OF PAYMENT:
                ON-SITE:
                FINANCE OFFICE IS OPEN
                8:00 AM TO 4:00 PM
                MONDAY - FRIDAY
                
                ONLINE:
                Students may process payment through our collecting partners listed below.
                
                PAYMENT COLLECTION FACILITIES:
                
                RCBC:
                A. OVER THE COUNTER - TRANSACT IN BILLS PAYMENT (GREEN FORM);
                   BILLER NAME - SOUTHWESTERN UNIVERSITY INC.
                B. ONLINE BANKING - PROCESS YOUR PAYMENT IN PAY BILLS;
                   BILLER NAME - SOUTHWESTERN UNIVERSITY INC.
                
                BDO:
                A. OVER THE COUNTER - TRANSACT IN BILLS PAYMENT;
                   INSTITUTIONAL CODE: 1054;
                   BILLER NAME - SOUTHWESTERN UNIVERSITY INC.
                B. ONLINE BANKING - PROCESS YOUR PAYMENT IN BILLS PAYMENT;
                   BILLER NAME - SOUTHWESTERN UNIVERSITY INC.
                
                GCASH:
                A. OVER THE COUNTER - TRANSACT IN PAY BILLS;
                   BILLER NAME - PHINMA EDUCATION OR PHINMA SOUTHWESTERN UNIVERSITY
                   IF ID NUMBER DOES NOT START WITH '05-', PLEASE INDICATE '05-' BEFORE THE ID NUMBER.
                
                ECPAY:
                A. BILLER NAME - PHINMA EDUCATION
                   IF ID NUMBER DOES NOT START WITH '05-', PLEASE INDICATE '05-' BEFORE THE ID NUMBER.
                
                Thank you for using DocuQuest.
                
                This is an auto-generated email. Please do not reply.
                ";
                
                if ($mailRequest->send())
                {
                    $response = [
                        'status' => '1',
                        'message' => 'Reference numbers emailed successfully',
                        'receivedNumbers' => $display_reference,
                    ];
                }
            } catch(Exception $e) {
                $response = [
                    'status'=>0,
                    'message'=> "Email could not be sent to requestor. Mailer Error: {$mailRequest->ErrorInfo}",
                ];
            }
        } else {
            $response = [
                'status' => '0',
                'message' => 'Invalid data format or missing $display_reference',
            ];
        }
        

        echo json_encode($response);
        break;
}
?>