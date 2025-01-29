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
header("Access-Control-Allow-Methods: GET, POST");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch($method){
    case 'GET':
        
        // echo json_encode($response)
        break;

    case 'POST':
        $transaction = json_decode(file_get_contents('php://input'), true);
        
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3] ?? null;

        if (!$found_reference_no || empty($transaction['owner_firstname']) || empty($transaction['owner_lastname']) || empty($transaction['contact_email']) || empty($transaction['service']) || empty($transaction['selected_docs'])){
            echo json_encode(['status' => 0, 'message' => 'Invalid reference number, owner firstname, owner lastname, courier email, service type, or selected docs']);
        }
        // $transaction['contact_name'];
        // $transaction['contact_phone'];
        // $transaction['name_staff'];
        // $transaction['owner_firstname'];
        // $transaction['owner_middlename'];
        // $transaction['owner_lastname'];

        try {
            $mailCourier = new PHPMailer(true);
            $mailCourier->Host = MAILHOST;
            $mailCourier->isSMTP();
            $mailCourier->SMTPAuth = true;
            $mailCourier->Username = USERNAME;
            $mailCourier->Password = PASSWORD;
            $mailCourier->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
            $mailCourier->Port = 587;

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
            
            $owner_middlename = isset($transaction['owner_middlename']) && !empty($transaction['owner_middlename']) 
            ? " " . htmlspecialchars($transaction['owner_middlename']) . " " 
            : "";


            
            // Generate the service table
            $serviceTableRows = "";

            // Requestor info
            $serviceTableRows .= "
            <tr>
                <th scope='row' style='border: 1px solid #ddd; padding: 8px;'>Requestor Name</th>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$transaction['name_requestor']}</td>
            </tr>
            <tr>
                <th scope='row' style='border: 1px solid #ddd; padding: 8px;'>Requestor Phone</th>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$transaction['phone_requestor']}</td>
            </tr>  
            ";

            if (!empty($transaction['requested_date'])) {
                $formattedRequestedDate = htmlspecialchars(date('M d, Y', strtotime(explode('T', $transaction['requested_date'])[0])));
                $serviceTableRows .= "
                <tr>
                    <th scope='row' style='border: 1px solid #ddd; padding: 8px;'>Requested</th>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$formattedRequestedDate}</td>
                </tr>";
            }


            if (!empty($transaction['updated_date'])) {
                $formattedUpdatedDate = htmlspecialchars(date('M d, Y', strtotime(explode('T', $transaction['updated_date'])[0])));
                $serviceTableRows .= "
                <tr>
                    <th scope='row' style='border: 1px solid #ddd; padding: 8px;'>Updated</th>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$formattedUpdatedDate}</td>
                </tr>";
            }
            
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
            
            // Table showing more details for courier
            $contactTable = "
            <table>
                <tr>
                    <th scope='row'>Subject</th>
                    <td>" . htmlspecialchars($transaction['contact_subject']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Description</th>
                    <td>" . htmlspecialchars($transaction['contact_body']) . "</td>
                </tr>
            </table>";
            

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
            
            // from, to, body
            $mailCourier->setFrom(SEND_FROM, SEND_FROM_NAME);
            $mailCourier->addAddress($transaction['contact_email']);
            $mailCourier->addReplyTo(REPLY_TO, REPLY_TO_NAME);
            $mailCourier->isHTML(true);
            $mailCourier->Subject = 'Asking Update for ' . $found_reference_no;
            $mailCourier->Body = '
            <html>
                <head>
                <style>
                    
                </style>
                </head>
                <body>
                    <p>Hello, this an official message from DocuQuest.</p>
                    
                    We are asking for an update for request <strong>'.$found_reference_no .'</strong>.
                    <br/>
                    
                    ' . $contactTable . '
                    
                    <br/>

                    <p><strong>'.$found_reference_no .' information:</strong></p>
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

            $mailCourier->AltBody = '
            Hello, this is an official message from DocuQuest.
            
            We are asking for an update for request ' . $found_reference_no . '.
            
            Subject: ' . strip_tags($transaction['contact_subject']) . '
            Description: ' . strip_tags($transaction['contact_body']) . '
            
            ' . $found_reference_no . ' information:
            
            Service Details:
            ' . strip_tags($serviceTable) . '
            
            Document Details:
            ' . strip_tags($selectedDocsTable) . '
            
            This is an official update for your document request.
            If you have any issues in processing your request, you may use this as your reference when you approach the Registrar\'s office.
            
            PLEASE DO NOT REPLY TO THIS EMAIL.
            ';          


            if($mailCourier->send()){
                $response = ['status'=>1, 'message'=>`Emailed release successfully.`];
            }
        } catch (Exception $e) {
            $response = [
                'status'=>0,
                'message'=> "Email could not be sent to user. Mailer Error: {$mailCourier->ErrorInfo}",
            ];
        }
        
        echo json_encode($response);
        break;
}
?>