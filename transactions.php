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
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();
$css = file_get_contents('http://localhost/api_drts/cssEmailRecover.php');

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = isset($URI_array[3]) ? $URI_array[3] : null;

        // TODO get image
        if (isset($found_reference_no)) {
            $qy = "
            SELECT 
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author,
                DOC.category_id AS DOC_category_id
            FROM transactions TCN
            LEFT JOIN documents DOC ON TCN.id_doc = DOC.id
            WHERE TCN.reference_number = :reference
            UNION
            SELECT 
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author,
                DOC.category_id AS DOC_category_id
            FROM transactions TCN
            RIGHT JOIN documents DOC ON TCN.id_doc = DOC.id
            WHERE TCN.reference_number = :reference;
            ";

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $qy = "
            SELECT 
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author,
                DOC.category_id AS DOC_category_id
            FROM transactions TCN
            LEFT JOIN documents DOC ON TCN.id_doc = DOC.id
            UNION
            SELECT 
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author,
                DOC.category_id AS DOC_category_id
            FROM transactions TCN
            RIGHT JOIN documents DOC ON TCN.id_doc = DOC.id;
            ";

            $stmt = $db_connection->prepare($qy);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($data);
        break;
        
    case 'POST':
        $transaction = json_decode(file_get_contents('php://input'), true);
        // TODO account for all attributes where $transaction->quantity_ctg_1_{index_here}
        // TODO check which attributes are empty, do not bind empty attributes
        // id_owner,
        // :id_owner,
        $qy = "
        INSERT INTO transactions (
            reference_number, id_doc, name_req, phone_req, email_req, 
            id_swu, name_owner, phone_owner, course, 
            catg_req, purpose_req, desc_req, filepath_receipt, 
            statusPayment, statusTransit, id_employee, overdue_days, 
            created_at, updated_at
        ) VALUES (
            :reference_number, :id_doc, :name_req, :phone_req, :email_req, 
            :id_swu,  :name_owner, :phone_owner, :course, 
            :catg_req, :purpose_req, :desc_req, :filepath_receipt, 
            :statusPayment, :statusTransit, :id_employee, :overdue_days, 
            :created_at, :updated_at
        )";

        $stmt = $db_connection->prepare($qy);

        $default_values = [
            ':id_doc' => null,
            ':id_employee' => null,
            ':overdue_days' => 0,
            ':statusPayment' => 'Not Paid',
            ':statusTransit' => 'Request Placed',
            ':created_at' => date('Y-m-d H:i:s'),
            ':updated_at' => date('Y-m-d H:i:s'),
        ];

        $foundReceipt = $transaction['receipt'];

        $stmt->execute(array_merge($default_values, [
            ':reference_number' => $transaction['reference_number'],
            ':name_req' => $transaction['name_req'],
            ':phone_req' => $transaction['phone_req'],
            ':email_req' => $transaction['email_req'],
            ':id_swu' => $transaction['id_swu'],
            // ':id_owner' => $transaction['id_owner'],
            ':name_owner' => $transaction['name_owner'],
            ':phone_owner' => $transaction['phone_owner'],
            ':course' => $transaction['course'],
            ':catg_req' => $transaction['catg_req'],
            ':purpose_req' => $transaction['purpose_req'],
            ':desc_req' => $transaction['desc_req'],
            ':filepath_receipt' => $foundReceipt,
        ]));

        if ($stmt->execute()) {
            // TODO send total, breakdown, reference number
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
                $mailRecover->addAddress($transaction['email_req']);
                $mailRecover->addReplyTo(REPLY_TO, REPLY_TO_NAME);
                $mailRecover->isHTML(true);
                $mailRecover->Subject = 'Docuquest Request Sent';
                // TODO use html table for sales invoice
                $mailRecover->Body = '
                    <html>
                        <head>
                        <style>
                            ' . $css . '
                        </style>
                        </head>
                        <body> 
                            <strong>Reference number:</strong>
                            <h2>'.$transaction['reference_number'].'</h2>
                            <br/>
                            <strong>Document:</strong>
                            
                            <br/>
                            <strong>Quantity:</strong>
                            
                            <br/>
                            <strong>Price:</strong>
                            
                            <br/>
                        
                            <strong>Breakdown:</strong>
                              <table>

                              </table>
                            <br/>
                            
                            <i>Do not reply to this email.</i>
                        </body>
                    </html>
                ';
                $mailRecover->AltBody = '
                    <strong>Reference number:</strong>
                    <h2>'.$transaction['reference_number'].'</h2>
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


            $response = ['status'=>1, 'message'=>'POST transaction successful.'];
        } else {
            $response = ['status'=>0, 'message'=>'SORRY, POST transaction failed.'];
        }
        
        echo json_encode($response);
        break;

    case 'PATCH':
        $transaction = json_decode(file_get_contents('php://input'), true);

        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3];

        $qy = "
        UPDATE transactions 
        SET 
            id_doc = :id_doc, 
            email_req = :email_req, 
            id_swu = :id_swu, 
            name_owner = :name_owner, 
            course = :course, 
            purpose_req = :purpose_req, 
            desc_req = :desc_req, 
            filepath_receipt = :filepath_receipt,
            statusPayment = :statusPayment, 
            statusTransit = :statusTransit, 
            id_employee = :id_employee, 
            updated_at = :updated_at
        WHERE reference_number = :reference
        ";

        $stmt = $db_connection->prepare($qy);

        $foundReceipt = $transaction['receipt'];

        // refer to users for patch
        $stmt->execute([
            ':reference' => $found_reference_no,
            ':id_doc' => $transaction['id_doc'],
            ':email_req' => $transaction['email_req'],
            ':id_swu' => $transaction['id_swu'],
            ':name_owner' => $transaction['name_owner'],
            ':course' => $transaction['course'],
            ':purpose_req' => $transaction['purpose_req'],
            ':desc_req' => $transaction['desc_req'],
            ':filepath_receipt' => $foundReceipt,            
            ':statusPayment' => $transaction['statusPayment'],
            ':statusTransit' => $transaction['statusTransit'],
            ':id_employee' => $transaction['id_employee'],
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);

        echo json_encode(["message" => "PATCH successful"]);
        break;

    case 'DELETE':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3];

        $qy = "
        DELETE FROM transactions 
        WHERE reference_number = :reference
        ";

        $stmt = $db_connection->prepare($qy);
        $stmt->bindParam(':reference', $found_reference_no);

        if ($stmt->execute()) {
            echo json_encode(['status'=>1, 'message' => 'DELETE transaction successful']);
        } else {
            echo json_encode(['status'=>0, 'message' => 'DELETE transaction failed']);
        }
        break;
}
?>
