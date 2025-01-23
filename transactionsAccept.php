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
$css = file_get_contents('http://localhost/api_drts/cssEmailAccept.php');

$method = $_SERVER['REQUEST_METHOD'];
switch ($method){
    case 'POST':
        $transaction = json_decode(file_get_contents('php://input'), true);
        
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3] ?? null;

        if (!$found_reference_no || empty($transaction['staff']) || empty($transaction['owner_firstname']) || empty($transaction['requestor_email']) || empty($transaction['service']) || empty($transaction['selected_docs'])) {
            echo json_encode(['status' => 0, 'message' => 'Invalid staff, reference number, owner firstname, requestor email, service type, or selected docs']);
            exit;
        }

        $qy = "UPDATE transactions SET statusTransit = :statusTransit, id_employee = :id_employee, updated_at = NOW()";
        
        $params = [
            ':statusTransit' => "Accepted",
            ':id_employee' => $transaction['staff'],
            ':reference' => $found_reference_no,
            ':firstname_owner' => $transaction['owner_firstname']
        ];

        // Add optional fields
        if (!empty($transaction['region'])) {
            $qy .= ", delivery_region = :delivery_region";
            $params[':delivery_region'] = $transaction['region'];
        }
        if (!empty($transaction['delivery_city'])) {
            $qy .= ", delivery_city = :delivery_city";
            $params[':delivery_city'] = $transaction['delivery_city'];
        }
        if (!empty($transaction['delivery_district'])) {
            $qy .= ", delivery_district = :delivery_district";
            $params[':delivery_district'] = $transaction['delivery_district'];
        }
        if (!empty($transaction['delivery_street'])) {
            $qy .= ", delivery_street = :delivery_street";
            $params[':delivery_street'] = $transaction['delivery_street'];
        }

        $qy .= " WHERE reference_number = :reference AND firstname_owner = :firstname_owner";

        $stmt = $db_connection->prepare($qy);
        // $stmt->bindParam(':statusTransit', $status_accepted, PDO::PARAM_STR);
        // $stmt->bindParam(':id_employee', $transaction['staff'], PDO::PARAM_INT);
        // $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
        // $stmt->bindParam(':firstname_owner', $transaction['owner_firstname'], PDO::PARAM_STR);

        if($stmt->execute($params)) {
            try {
                $mailAccept = new PHPMailer(true);
                $mailAccept->Host = MAILHOST;
                $mailAccept->isSMTP();
                $mailAccept->SMTPAuth = true;
                $mailAccept->Username = USERNAME;
                $mailAccept->Password = PASSWORD;
                $mailAccept->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
                $mailAccept->Port = 587;
                /*
                 $transaction['service']
                 $transaction['region']
                 $transaction['delivery_city']
                 $transaction['delivery_district']
                 $transaction['delivery_street']
                */
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

                // TODO if $transaction['region']) is not empty,
                // query the transactions table and find the region and fee for the given $transaction['region'])
                // then add a new <tr></tr> for the region and fee
                
                // Generate the service table
                $serviceTableRows = "
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$transaction['service']}</td>
                </tr>";

                if ($transaction['service'] === "Delivery" && !empty($transaction['region'])) {
                    $serviceTableRows .= "
                    <tr>
                        <td style='border: 1px solid #ddd; padding: 8px;'>Region</td>
                        <td style='border: 1px solid #ddd; padding: 8px;'>{$transaction['region']}</td>
                    </tr>
                    <tr>
                        <th colspan='2' style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Delivery Details</th>
                    </tr>
                    <tr>
                        <th scope='row' style='border: 1px solid #ddd; padding: 8px;'>City</th>
                        <td style='border: 1px solid #ddd; padding: 8px;'>{$transaction['delivery_city']}</td>
                    </tr>
                    <tr>
                        <th scope='row' style='border: 1px solid #ddd; padding: 8px;'>District/Barangay</th>
                        <td style='border: 1px solid #ddd; padding: 8px;'>{$transaction['delivery_district']}</td>
                    </tr>
                    <tr>
                        <th scope='row' style='border: 1px solid #ddd; padding: 8px;'>Street</th>
                        <td style='border: 1px solid #ddd; padding: 8px;'>{$transaction['delivery_street']}</td>
                    </tr>";
                }

                $serviceTable = "
                <table style='border-collapse: collapse; width: 100%;'>
                    <thead>
                        <tr>
                            <th style='border: 1px solid #ddd; padding: 8px;'>Service</th>
                            ".($transaction['service'] === "Delivery" && !empty($transaction['region']) ? "<th style='border: 1px solid #ddd; padding: 8px;'>Region</th>" : "")."
                        </tr>
                    </thead>
                    <tbody>
                        {$serviceTableRows}
                    </tbody>
                </table>";

                // HTML table for selected documents
                $selectedDocsTable = "
                <table style='border-collapse: collapse; width: 100%;'>
                    <thead>
                        <tr>
                            <th style='border: 1px solid #ddd; padding: 8px;'>Document</th>
                            <th style='border: 1px solid #ddd; padding: 8px;'>Copies</th>
                            <th style='border: 1px solid #ddd; padding: 8px;'>Price (₱)</th>
                            <th style='border: 1px solid #ddd; padding: 8px;'></th>
                        </tr>
                    </thead>
                    <tbody>
                        {$selectedDocsRows}
                    </tbody>
                </table>";

                // from, to, body
                $mailAccept->setFrom(SEND_FROM, SEND_FROM_NAME);
                $mailAccept->addAddress($transaction['requestor_email']);
                $mailAccept->addReplyTo(REPLY_TO, REPLY_TO_NAME);
                $mailAccept->isHTML(true);
                $mailAccept->Subject = $transaction['reference'] . ' DocuQuest Update';
                // $transaction['service']
                $mailAccept->Body = '
                <html>
                    <head>
                    <style>
                        ' . $css . '
                    </style>
                    </head>
                    <body>
                        <p>Hi, '. $transaction['owner_firstname'] .'.</p>
                        <br/>
                        Your request <strong>'.$transaction['reference'].'</strong> is: <strong>'. $transaction['status_transit'] .'.</strong>
                        <br/><br/>
                        <p>This is an official sales invoice.</p>
                        ' . $serviceTable . '
                        <br/><br/>
                        ' . $selectedDocsTable . '
                        <br/>
                        <p>Kindly pay the amount due if you have not paid yet and upload your finance receipt as soon as possible.</p>
                        <br/>
                        <i>Please do not reply to this email.</i>
                    </body>
                </html>
                ';

                $mailAccept->AltBody = '
                Hi, '. $transaction['owner_firstname'] .'.

                Your request ' . $transaction['reference'] . ' is: ' . $transaction['status_transit'] . '. 

                This is an official sales invoice.

                Service Details:
                ' . strip_tags($serviceTable) . '

                Document Details:
                ' . strip_tags($selectedDocsTable) . '

                Kindly pay the amount due if you have not paid yet and upload your finance receipt as soon as possible.

                PLEASE DO NOT REPLY TO THIS EMAIL.';

                if($mailAccept->send()){
                    // $response = ['status'=>1, 'message'=>`Accept statusTransit successful!`];
                    $response = ['status'=>1, 'message'=>`Emailed acceptance successfully.`];
                }
            } catch (Exception $e) {
                $response = [
                    'status'=>0,
                    'message'=> "Email could not be sent to user. Mailer Error: {$mailAccept->ErrorInfo}",
                ];
            }
        } else {
            $response = ['status'=>0, 'message'=>'Accept '. htmlspecialchars($found_reference_no) . ' failed!'];
        }
        
        echo json_encode($response);
        break;
    
    
}
?>