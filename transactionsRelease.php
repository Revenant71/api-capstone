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
$css = file_get_contents('http://localhost/api_drts/cssEmailRelease.php');

$method = $_SERVER['REQUEST_METHOD'];
switch ($method){
    case 'POST':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3] ?? null;


        break;
    
    case 'PATCH':
        $transaction = json_decode(file_get_contents('php://input'), true);
        
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3] ?? null;

        // empty($transaction['reference']) ||
        if (!$found_reference_no || empty($transaction['staff']) || empty($transaction['owner_lastname']) || empty($transaction['owner_firstname']) ||  empty($transaction['requestor_email']) || empty($transaction['status_transit']) || empty($transaction['release_date']) || empty($transaction['service']) || empty($transaction['selected_docs'])) {
            echo json_encode(['status' => 0, 'message' => 'Invalid reference number, staff, owner lastname, requestor email, statusTransit, or selected docs']);
        }

        $qy = "UPDATE transactions 
        SET released_at = :released_at, statusTransit = :statusTransit, id_employee = :id_employee, updated_at = NOW()";

        $params = [
            ':released_at' => $transaction['release_date'],
            ':statusTransit' => $transaction['status_transit'],
            ':id_employee' => $transaction['staff'],
            ':reference' => $found_reference_no,
            ':lastname_owner' => $transaction['owner_lastname']
        ];
        
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

        $qy .= " WHERE reference_number = :reference AND lastname_owner = :lastname_owner";

        $stmt = $db_connection->prepare($qy);

        if($stmt->execute($params)) {
            try {
                $mailRelease = new PHPMailer(true);
                $mailRelease->Host = MAILHOST;
                $mailRelease->isSMTP();
                $mailRelease->SMTPAuth = true;
                $mailRelease->Username = USERNAME;
                $mailRelease->Password = PASSWORD;
                $mailRelease->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
                $mailRelease->Port = 587;

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
                
                // Generate the service table
                $serviceTableRows = "";

                // Add Release Date row directly
                if (!empty($transaction['release_date'])) {
                    $formattedReleaseDate = htmlspecialchars(date('M d, Y', strtotime(explode('T', $transaction['release_date'])[0])));
                    $serviceTableRows .= "
                    <tr>
                        <th scope='row' style='border: 1px solid #ddd; padding: 8px;'>Release Date</th>
                        <td style='border: 1px solid #ddd; padding: 8px;'>{$formattedReleaseDate}</td>
                    </tr>";
                }
                

                $serviceTableRows = "";

                // Add Release Date row directly at the top
                if (!empty($transaction['release_date'])) {
                    $formattedReleaseDate = htmlspecialchars(date('M d, Y', strtotime(explode('T', $transaction['release_date'])[0])));
                    $serviceTableRows .= "
                    <tr>
                        <th scope='row' style='border: 1px solid #ddd; padding: 8px;'>Release Date</th>
                        <td style='border: 1px solid #ddd; padding: 8px;'>{$formattedReleaseDate}</td>
                    </tr>";
                }
                
                // Add Service section
                $serviceTableRows .= "
                <tr>
                    <th scope='row' style='border: 1px solid #ddd; padding: 8px;'>Service</th>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$transaction['service']}</td>
                </tr>";
                
                // If the service is "Delivery", add delivery details properly
                if ($transaction['service'] === "Delivery" && !empty($transaction['region'])) {
                    $serviceTableRows .= "
                    <tr>
                        <th scope='row' style='border: 1px solid #ddd; padding: 8px;'>Region</th>
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
                
                // Finalize the Service Table with structured alignment
                $serviceTable = "
                <table style='border-collapse: collapse; width: 60%;'>
                    <tbody>
                        {$serviceTableRows}
                    </tbody>
                </table>";
                
                // HTML table for selected documents
                $selectedDocsTable = "
                <table style='border-collapse: collapse; width: 60%;'>
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

                $hardcodeTransit = "For Releasing";
                
                // from, to, body
                $mailRelease->setFrom(SEND_FROM, SEND_FROM_NAME);
                $mailRelease->addAddress($transaction['requestor_email']);
                $mailRelease->addReplyTo(REPLY_TO, REPLY_TO_NAME);
                $mailRelease->isHTML(true);
                $mailRelease->Subject = $found_reference_no  . ' DocuQuest For Release';
                
                // TODO change body for release
                // TODO add release date to $serviceTable
                $mailRelease->Body = '
                <html>
                    <head>
                    <style>
                        ' . $css . '
                    </style>
                    </head>
                    <body>
                        <p>Hi, '. $transaction['owner_firstname'] .'.</p>
                        
                        Your document request <strong>'.$found_reference_no .'</strong> has been recieved and marked <strong>'. $hardcodeTransit .'.</strong>
                        <br/><br/>

                        <p>Here are the details of your request:</p>
                        ' . $serviceTable . '
                        <br/><br/>
                        ' . $selectedDocsTable . '
                        <br/>

                        <p>This is an official update for your document request.</p>
                        <p>If you have any issues in processing your request, you may use this as your reference when you approach the Registrar\'s office.</p>
                        <br/>
                        <i>Please do not reply to this email.</i>
                    </body>
                </html>
                ';

                $mailRelease->AltBody = '
                Hi, ' . $transaction['owner_firstname'] . ',
                
                Your payment for document request with reference ' . $found_reference_no  . ' has been received and the request has been updated to: ' . $hardcodeTransit . '.
                
                This serves as your official payment invoice.
                
                Service Details:
                ' . strip_tags($serviceTable) . '
                
                Document Details:
                ' . strip_tags($selectedDocsTable) . '
                
                You may show this copy to the finance department when you claim your document. 
                Otherwise, you may keep this as your reference if you opted for your document to be delivered.
                
                PLEASE DO NOT REPLY TO THIS EMAIL.';             


                if($mailRelease->send()){
                    $response = ['status'=>1, 'message'=>`Emailed release successfully.`];
                }
            } catch (Exception $e) {
                $response = [
                    'status'=>0,
                    'message'=> "Email could not be sent to user. Mailer Error: {$mailRelease->ErrorInfo}",
                ];
            }
        } else {
            $response = ['status'=>0, 'message'=>'Release '. htmlspecialchars($found_reference_no) . ' failed!'];
        }

        echo json_encode($response);
        break;
}
?>