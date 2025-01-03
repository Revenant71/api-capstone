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
        // TODO encType="multipart/form-data
        $transaction = json_decode(file_get_contents('php://input'), true);
        // TODO check which attributes are empty, do not bind empty attributes
        //  phone_owner,
        // :phone_owner,
        // file_portrait,
        // :file_portrait,
        $qy = "
        INSERT INTO transactions (
            reference_number, service_type, delivery_region,
            id_doc, doc_name, doc_quantity, price, price_total, 
            name_req, phone_req, email_req, 
            id_swu, firstname_owner, middlename_owner, lastname_owner,
            course, course_year, year_last,
            purpose_req,
            statusPayment, statusTransit, id_employee, overdue_days, 
            created_at, updated_at
            " . (!empty($transaction['desc_req']) ? ", desc_req" : "") . "
            " . (!empty($transaction['file_portrait']) ? ", file_portrait" : "") . "
        ) VALUES (
            :reference_number, :service_type, :delivery_region,
            :id_doc, :doc_name, :doc_quantity, :price, :price_total, 
            :name_req, :phone_req, :email_req,
            :id_swu, :firstname_owner, :middlename_owner, :lastname_owner, 
            :course, :course_year, :year_last, 
            :purpose_req, 
            :statusPayment, :statusTransit, :id_employee, :overdue_days, 
            :created_at, :updated_at
            " . (!empty($transaction['desc_req']) ? ", :desc_req" : "") . "
            " . (!empty($transaction['file_portrait']) ? ", :file_portrait" : "") . "
        )";

        $stmt = $db_connection->prepare($qy);

        $default_values = [
            ':id_employee' => null,
            ':overdue_days' => 0,
            ':statusPayment' => 'Not Paid',
            ':statusTransit' => 'Request Placed',
            ':created_at' => date('Y-m-d H:i:s'),
            ':updated_at' => date('Y-m-d H:i:s'),
        ];

        $stmt->execute(array_merge($default_values, [
            ':reference_number' => $transaction['reference_number'],
            ':service_type' => $transaction['service_type'],
            ':delivery_region' => $transaction['delivery_region'],
            ':id_doc' => $transaction['currentDocId'],
            ':doc_name' => $transaction['currentDocument'],
            ':doc_quantity' => $transaction['currentQuantity'],
            ':price' => $transaction['currentPrice'],
            ':price_total' => $transaction['total_price'],
            ':name_req' => $transaction['name_req'],
            ':phone_req' => $transaction['phone_req'],
            ':email_req' => $transaction['email_req'],
            ':id_swu' => $transaction['id_swu'],
            ':firstname_owner' => $transaction['name_first'],
            ':middlename_owner' => $transaction['name_middle'],
            ':lastname_owner' => $transaction['name_last'],
            // ':phone_owner' => $transaction['phone_owner'],
            ':course' => $transaction['course'],
            ':course_year' => $transaction['course_year'],
            ':year_last' => $transaction['year_last'],
            ':purpose_req' => $transaction['purpose'],
        ]));

        if (!empty($transaction['desc_req'])) {
            $execute_values[':desc_req'] = $transaction['desc_req'];
        }
        if (!empty($transaction['file_portrait'])) {
            $execute_values[':file_portrait'] = $transaction['portrait'];
        }

        if ($stmt->execute()) {
            // TODO send total, breakdown, reference number
            // TODO send email not working
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
                            '.$transaction['currentDocument'].'
                            <br/>
                            <strong>Quantity:</strong>
                            '.$transaction['currentQuantity'].'
                            <br/>
                            <strong>Price:</strong>
                            '.$transaction['currentPrice'].'
                            <br/>
                            // TODO invoice + html table
                            
                            <i>Do not reply to this email.</i>
                        </body>
                    </html>
                ';
                $mailRecover->AltBody = '
                    <strong>Reference number:</strong>
                    <h2>'.$transaction['reference_number'].'</h2>
                    <br/>
                    <strong>Document:</strong>
                    '.$transaction['currentDocument'].'
                    <br/>
                    <strong>Quantity:</strong>
                    '.$transaction['currentQuantity'].'
                    <br/>
                    <strong>Price:</strong>
                    '.$transaction['currentPrice'].'
                    <br/>            
                    DO NOT REPLY TO THIS EMAIL.
                ';

                if ($mailRecover->send())
                {
                    $response = [
                        'status'=>1,
                        'message'=> 'Request confirmation email sent!',
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
        // TODO update queries to match updated transactions table
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
            file_portrait = :file_portrait,
            statusPayment = :statusPayment, 
            statusTransit = :statusTransit, 
            id_employee = :id_employee, 
            updated_at = :updated_at
        WHERE reference_number = :reference
        ";

        $stmt = $db_connection->prepare($qy);

        $foundPortrait = $transaction['portrait'];

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

            ':file_portrait' => $foundPortrait,            
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
