<?php
require_once('connectDb.php');
require 'configSmtp.php'; 
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use OTPHP\TOTP;
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json; charset=UTF-8");
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

            // compress longblob data to base64 string
            if ($data && isset($data['file_receipt'])) {
                // TODO this will not work if the image is png and has transparency
                $data['file_receipt'] = 'data:image/jpeg;base64,' . base64_encode($data['file_receipt']);
                
            }
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

            // compress longblob data to base64 string
            foreach ($data as &$row) {
                if (isset($row['file_receipt'])) {
                    $row['file_receipt'] = base64_encode($row['file_receipt']);
                }
            }

            // file_put_contents('debugTransactions.log', 'Fetched data: ' . print_r($data, true) . PHP_EOL, FILE_APPEND);
            // if (json_last_error() !== JSON_ERROR_NONE) {
            //     file_put_contents('debugTransactionsEncode.log', 'JSON encoding error: ' . json_last_error_msg() . PHP_EOL, FILE_APPEND);
            // }
        }

        // ob_clean();
        echo json_encode($data);
        break;
        
    case 'POST':
        // TODO encType="multipart/form-data
        $transaction = json_decode(file_get_contents('php://input'), true);
        // file_portrait,
        // :file_portrait,
        // released_at,
        // :released_at,
        $selectedDocsJson = !empty($transaction['selectedDocuments']) ? json_encode($transaction['selectedDocuments']) : '[]';

        $qy = "
        INSERT INTO transactions (
            reference_number, service_type, delivery_region,
            id_doc, doc_name, doc_quantity, price, price_total, 
            name_req, phone_req, email_req, 
            firstname_owner, lastname_owner, phone_owner,
            course, course_year, year_last,
            purpose_req, selected_docs,
            statusPayment, statusTransit, id_employee, overdue_days, 
            created_at, updated_at
            " . (!empty($transaction['name_middle']) ? ", middlename_owner" : "") . "
            " . (!empty($transaction['id_swu']) ? ", id_swu" : "") . "
            " . (!empty($transaction['desc_req']) ? ", desc_req" : "") . "
            " . (!empty($transaction['delivery_city']) ? ", delivery_city" : "") . "
            " . (!empty($transaction['delivery_district']) ? ", delivery_district" : "") . "
            " . (!empty($transaction['delivery_street']) ? ", delivery_street" : "") . "
            " . (!empty($transaction['file_portrait']) ? ", file_portrait" : "") . "
        ) VALUES (
            :reference_number, :service_type, :delivery_region,
            :id_doc, :doc_name, :doc_quantity, :price, :price_total, 
            :name_req, :phone_req, :email_req,
            :firstname_owner, :lastname_owner, :phone_owner, 
            :course, :course_year, :year_last, 
            :purpose_req, :selected_docs,
            :statusPayment, :statusTransit, :id_employee, :overdue_days, 
            :created_at, :updated_at
            " . (!empty($transaction['name_middle']) ? ", :middlename_owner" : "") . "
            " . (!empty($transaction['id_swu']) ? ", :id_swu" : "") . "
            " . (!empty($transaction['desc_req']) ? ", :desc_req" : "") . "
            " . (!empty($transaction['delivery_city']) ? ", :delivery_city" : "") . "
            " . (!empty($transaction['delivery_district']) ? ", :delivery_district" : "") . "
            " . (!empty($transaction['delivery_street']) ? ", :delivery_street" : "") . "
            " . (!empty($transaction['file_portrait']) ? ", :file_portrait" : "") . "
        )";

        $stmt = $db_connection->prepare($qy);

        $default_values = [
            ':id_employee' => null,
            ':overdue_days' => 0,
            ':statusPayment' => 'Not Paid',
            ':statusTransit' => 'Request Placed',
            // ':released_at' => null,
            ':created_at' => date('Y-m-d H:i:s'),
            ':updated_at' => date('Y-m-d H:i:s'),
        ];
        // $transaction['selectedDocuments']
        
        $transaction_values = [
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
            ':firstname_owner' => $transaction['name_first'],
            ':lastname_owner' => $transaction['name_last'],
            ':phone_owner' => $transaction['phone_owner'],
            ':course' => $transaction['course'],
            ':course_year' => $transaction['course_year'],
            ':year_last' => $transaction['year_last'],
            ':purpose_req' => $transaction['purpose'],
            ':selected_docs' => $selectedDocsJson
        ];

        if (!empty($transaction['name_middle'])) {
            $transaction_values[':middlename_owner'] = $transaction['name_middle'];
        } 
        if (!empty($transaction['id_swu'])) {
            $transaction_values[':id_swu'] = $transaction['id_swu'];
        } 
        if (!empty($transaction['desc_req'])) {
            $transaction_values[':desc_req'] = $transaction['desc_req'];
        }
        if (!empty($transaction['delivery_city'])) {
            $transaction_values[':delivery_city'] = $transaction['delivery_city'];
        } 
        if (!empty($transaction['delivery_district'])) {
            $transaction_values[':delivery_district'] = $transaction['delivery_district'];
        }
        if (!empty($transaction['delivery_street'])) {
            $transaction_values[':delivery_street'] = $transaction['delivery_street'];
        }  
        if (!empty($transaction['file_portrait'])) {
            $transaction_values[':file_portrait'] = $transaction['portrait'];
        }


        if ($stmt->execute(array_merge($default_values, $transaction_values))) {
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

        if (!$found_reference_no || empty($transaction['owner_lastname'])) {
            echo json_encode(['status' => 0, 'message' => 'Invalid reference number or owner lastname']);
            exit;
        }

        // " . (!empty($transaction['file_receipt']) ? ", file_receipt" : "") . "
        // " . (!empty($transaction['file_document']) ? ", file_document" : "") . "
        // " . (!empty($transaction['file_receipt']) ? ", :file_receipt" : "") . "
        // " . (!empty($transaction['file_document']) ? ", :file_document" : "") . "
        
        $qy = "
        UPDATE transactions
        SET
            service_type = :service_type,
            delivery_region = :delivery_region,
            id_doc = :id_doc,
            doc_name = :doc_name,
            doc_quantity = :doc_quantity,
            price = :price,
            price_total = :price_total,
            name_req = :name_req,
            phone_req = :phone_req,
            email_req = :email_req,
            firstname_owner = :firstname_owner,
            lastname_owner = :lastname_owner,
            phone_owner = :phone_owner,
            course = :course,
            course_year = :course_year,
            year_last = :year_last,
            purpose_req = :purpose_req,
            statusPayment = :statusPayment,
            statusTransit = :statusTransit,
            id_employee = :id_employee,
            overdue_days = :overdue_days,
            updated_at = :updated_at
        ";

        if (!empty($transaction['owner_middlename'])) {
            $qy .= ", middlename_owner = :middlename_owner";
        }
        if (!empty($transaction['owner_SWU'])) {
            $qy .= ", id_swu = :id_swu";
        }
        if (!empty($transaction['description'])) {
            $qy .= ", desc_req = :desc_req";
        }
        if (!empty($transaction['delivery_city'])) {
            $qy .= ", delivery_city = :delivery_city";
        }
        if (!empty($transaction['delivery_district'])) {
            $qy .= ", delivery_district = :delivery_district";
        }
        if (!empty($transaction['delivery_street'])) {
            $qy .= ", delivery_street = :delivery_street";
        }
        if (!empty($transaction['file_portrait'])) {
            $qy .= ", file_portrait = :file_portrait";
        }
        if (!empty($transaction['file_receipt'])) {
            $qy .= ", file_receipt = :file_receipt";
        }

        $qy .= " WHERE reference_number = :reference_number AND lastname_owner = :lastname_owner";

        $stmt = $db_connection->prepare($qy);

        $transaction_values = [
            ':reference_number' => $found_reference_no,
            ':service_type' => $transaction['service'],
            ':delivery_region' => $transaction['region'],
            ':id_doc' => $transaction['doc_id'],
            ':doc_name' => $transaction['doc_type'],
            ':doc_quantity' => $transaction['doc_quantity'],
            ':price' => $transaction['doc_price'],
            ':price_total' => $transaction['total_price'],
            ':name_req' => $transaction['requestor_name'],
            ':phone_req' => $transaction['requestor_phone'],
            ':email_req' => $transaction['requestor_email'],
            ':firstname_owner' => $transaction['owner_firstname'],
            ':lastname_owner' => $transaction['owner_lastname'],
            ':phone_owner' => $transaction['owner_phone'],
            ':course' => $transaction['owner_course'],
            ':course_year' => $transaction['owner_course_year'],
            ':year_last' => $transaction['owner_year_last'],
            ':purpose_req' => $transaction['purpose'],
            ':statusPayment' => $transaction['status_payment'],
            ':statusTransit' => $transaction['status_transit'],
            ':id_employee' => $transaction['staff'],
            ':overdue_days' => $transaction['overdue_days'],
            ':updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($transaction['file_receipt'])) {
            $transaction_values[':file_receipt'] = base64_decode($transaction['file_receipt']);
        }
        if (!empty($transaction['description'])) {
            $transaction_values[':desc_req'] = $transaction['description'];
        }
        if (!empty($transaction['delivery_city'])) {
            $transaction_values[':delivery_city'] = $transaction['delivery_city'];
        }
        if (!empty($transaction['delivery_district'])) {
            $transaction_values[':delivery_district'] = $transaction['delivery_district'];
        }
        if (!empty($transaction['delivery_street'])) {
            $transaction_values[':delivery_street'] = $transaction['delivery_street'];
        }
        if (!empty($transaction['file_portrait'])) {
            $transaction_values[':file_portrait'] = base64_decode($transaction['file_portrait']);
        }

        if ($stmt->execute($transaction_values)) {
            try {
                $mailRespond = new PHPMailer(true);
                $mailRespond->Host = MAILHOST;
                $mailRespond->isSMTP();
                $mailRespond->SMTPAuth = true;
                $mailRespond->Username = USERNAME;
                $mailRespond->Password = PASSWORD;
                $mailRespond->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
                $mailRespond->Port = 587;

                $selectedDocsRows = '';
                if (!empty($transaction['selected_docs']) && is_array($transaction['selected_docs'])) {
                    foreach ($transaction['selected_docs'] as $doc) {
                        $document = htmlspecialchars($doc['document']);
                        $quantity = htmlspecialchars($doc['quantity']);
                        $price = htmlspecialchars(number_format($doc['price'], 2));
                        $totalPrice = htmlspecialchars(number_format($doc['totalPrice'], 2));
                        $selectedDocsRows .= "
                        <tr>
                            <td style='border: 1px solid #ddd; padding: 8px;'>{$document}</td>
                            <td style='border: 1px solid #ddd; padding: 8px;'>{$quantity}</td>
                            <td style='border: 1px solid #ddd; padding: 8px;'>{$price}</td>
                            <td style='border: 1px solid #ddd; padding: 8px;'>{$totalPrice}</td>
                        </tr>";
                    }
                } else {
                    $selectedDocsRows = '<tr><td colspan="4">No documents selected.</td></tr>';
                }

                // Add the final total row
                $selectedDocsRows .= "
                <tr>
                    <td colspan='3' style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>Total:</td>
                    <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>". htmlspecialchars(number_format($transaction['total_price'], 2)) ."</td>
                </tr>";

                // HTML table for selected documents
                $selectedDocsTable = "
                <table style='border-collapse: collapse; width: 100%;'>
                    <thead>
                        <tr>
                            <th style='border: 1px solid #ddd; padding: 8px;'>Document</th>
                            <th style='border: 1px solid #ddd; padding: 8px;'>Quantity</th>
                            <th style='border: 1px solid #ddd; padding: 8px;'>Price</th>
                            <th style='border: 1px solid #ddd; padding: 8px;'></th>
                        </tr>
                    </thead>
                    <tbody>
                        {$selectedDocsRows}
                    </tbody>
                </table>";

                // from, to, body
                $mailRespond->setFrom(SEND_FROM, SEND_FROM_NAME);
                $mailRespond->addAddress($transaction['requestor_email']);
                $mailRespond->addReplyTo(REPLY_TO, REPLY_TO_NAME);
                $mailRespond->isHTML(true);
                $mailRespond->Subject = $transaction['reference'] . ' DocuQuest Update';
                // TODO only show invoice if $transaction['status_transit'] === Accepted
                // TODO if $transaction['status_transit'] === Rejected show remarks
                $mailRespond->Body = '
                <html>
                    <head>
                    <style>
                        ' . $css . '
                    </style>
                    </head>
                    <body> 
                        <strong>Your request '.$transaction['reference'].' is: '. $transaction['status_transit'] .'.</strong>
                        <br/><br/>
                        <p>This is an official sales invoice.</p>
                        ' . $selectedDocsTable . '
                        <br/>
                        <i>Please do not reply to this email.</i>
                    </body>
                </html>
                ';
                $mailRespond->AltBody = '
                    <strong>Your request '.$transaction['reference'].' is: '. $transaction['status_transit'] .'.</strong>


                    This is an official sales invoice.

                    PLEASE DO NOT REPLY TO THIS EMAIL.
                ';

                if($mailRespond->send()){
                    $response = ['status'=>1, 'message'=>'PATCH transaction successful.'];
                }

            } catch (Exception $e) {
                $response = [
                    'status'=>0,
                    'message'=> "Message could not be sent to user. Mailer Error: {$mailRespond->ErrorInfo}",
                ];
            }

        } else {
            $response = ['status'=>0, 'message'=>'Sorry, PATCH transaction failed.'];
        }

        echo json_encode($response);
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
