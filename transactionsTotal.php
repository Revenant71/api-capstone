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
header("Access-Control-Allow-Methods: GET, POST, PATCH");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'PATCH':
      $transaction = json_decode(file_get_contents('php://input'), true);

      $URI_array = explode('/', $_SERVER['REQUEST_URI']);
      $found_reference_no = $URI_array[3];

      if (!$found_reference_no || empty($transaction['owner_lastname'])) {
        echo json_encode(['status' => 0, 'message' => 'Invalid reference number or owner lastname']);
        exit;
      }
      
      $qy = "
      UPDATE transactions
      SET 
          price_total = :price_total,
          id_employee = :id_employee,
          updated_at = NOW()

      WHERE reference_number = :reference_number AND lastname_owner = :lastname_owner
      ";

      $stmt = $db_connection->prepare($qy);
      $stmt->bindParam(':price_total', $transaction['exceed_total']);
      $stmt->bindParam(':id_employee', $transaction['staff']);
      $stmt->bindParam(':reference_number', $found_reference_no);
      $stmt->bindParam(':lastname_owner', $transaction['owner_lastname']);

      if($stmt->execute()){
        // $transaction['exceed_fee']
        // $transaction['exceed_pages']
           // try {
            //     $mailRespond = new PHPMailer(true);
            //     $mailRespond->Host = MAILHOST;
            //     $mailRespond->isSMTP();
            //     $mailRespond->SMTPAuth = true;
            //     $mailRespond->Username = USERNAME;
            //     $mailRespond->Password = PASSWORD;
            //     $mailRespond->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
            //     $mailRespond->Port = 587;

            //     $selectedDocsRows = '';
            //     if (!empty($transaction['selected_docs']) && is_array($transaction['selected_docs'])) {
            //         foreach ($transaction['selected_docs'] as $doc) {
            //             $document = htmlspecialchars($doc['document']);
            //             $quantity = htmlspecialchars($doc['quantity']);
            //             $price = htmlspecialchars(number_format($doc['price'], 2));
            //             $totalPrice = htmlspecialchars(number_format($doc['totalPrice'], 2));
            //             $selectedDocsRows .= "
            //             <tr>
            //                 <td style='border: 1px solid #ddd; padding: 8px;'>{$document}</td>
            //                 <td style='border: 1px solid #ddd; padding: 8px;'>{$quantity}</td>
            //                 <td style='border: 1px solid #ddd; padding: 8px;'>{$price}</td>
            //                 <td style='border: 1px solid #ddd; padding: 8px;'>{$totalPrice}</td>
            //             </tr>";
            //         }
            //     } else {
            //         $selectedDocsRows = '<tr><td colspan="4">No documents selected.</td></tr>';
            //     }

            //     // Add the final total row
            //     $selectedDocsRows .= "
            //     <tr>
            //         <td colspan='3' style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>Total:</td>
            //         <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>". htmlspecialchars(number_format($transaction['total_price'], 2)) ."</td>
            //     </tr>";

            //     // TODO if $transaction['region']) is not empty,
            //     // query the transactions table and find the region and fee for the given $transaction['region'])
            //     // then add a new <tr></tr> for the region and fee

            //     // HTML table for selected documents
            //     $selectedDocsTable = "
            //     <table style='border-collapse: collapse; width: 100%;'>
            //         <thead>
            //             <tr>
            //                 <th style='border: 1px solid #ddd; padding: 8px;'>Document</th>
            //                 <th style='border: 1px solid #ddd; padding: 8px;'>Quantity</th>
            //                 <th style='border: 1px solid #ddd; padding: 8px;'>Price</th>
            //                 <th style='border: 1px solid #ddd; padding: 8px;'></th>
            //             </tr>
            //         </thead>
            //         <tbody>
            //             {$selectedDocsRows}
            //         </tbody>
            //     </table>";

            //     // from, to, body
            //     $mailRespond->setFrom(SEND_FROM, SEND_FROM_NAME);
            //     $mailRespond->addAddress($transaction['requestor_email']);
            //     $mailRespond->addReplyTo(REPLY_TO, REPLY_TO_NAME);
            //     $mailRespond->isHTML(true);
            //     $mailRespond->Subject = $transaction['reference'] . ' DocuQuest Update';
            //     // TODO only show invoice if $transaction['status_transit'] === Accepted
            //     // TODO if $transaction['status_transit'] === Rejected show remarks
            //     $mailRespond->Body = '
            //     <html>
            //         <head>
            //         <style>
            //             ' . $css . '
            //         </style>
            //         </head>
            //         <body> 
            //             <strong>Your request '.$transaction['reference'].' is: '. $transaction['status_transit'] .'.</strong>
            //             <br/><br/>
            //             <p>This is an official sales invoice.</p>
            //             ' . $selectedDocsTable . '
            //             <br/>
            //             <i>Please do not reply to this email.</i>
            //         </body>
            //     </html>
            //     ';
            //     $mailRespond->AltBody = '
            //         <strong>Your request '.$transaction['reference'].' is: '. $transaction['status_transit'] .'.</strong>


            //         This is an official sales invoice.

            //         PLEASE DO NOT REPLY TO THIS EMAIL.
            //     ';

            //     if($mailRespond->send()){
            //         $response = ['status'=>1, 'message'=>'PATCH transaction successful.'];
            //     }

            // } catch (Exception $e) {
            //     $response = [
            //         'status'=>0,
            //         'message'=> "Message could not be sent to user. Mailer Error: {$mailRespond->ErrorInfo}",
            //     ];
            // }        

        $response = ['status'=>1, 'message'=>'PATCH price_total succeeded!'];
      } else {
        $response = ['status'=>0, 'message'=>'Sorry, PATCH price_total failed.'];
      }

      echo json_encode($response);
      break;

    case 'GET':
        
      break;

    case 'POST':
        
      break;
}
?>