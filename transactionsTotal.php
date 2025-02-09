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
        statusTransit = :statusTransit,
        updated_at = NOW()
      ";

      $params = [
        ':price_total' => $transaction['exceed_total'],
        ':id_employee' => $transaction['staff'],
        ':reference' => $found_reference_no,
        ':lastname_owner' => $transaction['owner_lastname'],
        ':statusTransit' => 'Accepted' 
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

      if($stmt->execute($params)){
        // $transaction['exceed_fee']
        // $transaction['exceed_pages']
           try {
                $mailExceed = new PHPMailer(true);
                $mailExceed->Host = MAILHOST;
                $mailExceed->isSMTP();
                $mailExceed->SMTPAuth = true;
                $mailExceed->Username = USERNAME;
                $mailExceed->Password = PASSWORD;
                $mailExceed->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
                $mailExceed->Port = 587;

                $exceedFeeFormatted = number_format((float)$transaction['exceed_fee'], 2);

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
                    <td colspan='3' style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>Total + Additional Fee:</td>
                    <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>". htmlspecialchars(number_format($transaction['exceed_total'], 2)) ."</td>
                </tr>";

                // Generate the service table
                $serviceTableRows = "
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$transaction['service']}</td>
                    " . (!empty($transaction['region']) ? "<td style='border: 1px solid #ddd; padding: 8px;'></td>" : "") . "
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

                // Table structure
                $serviceTable = "
                <table style='border-collapse: collapse; width: 60%;'>
                    <thead>
                        <tr>
                            <th style='border: 1px solid #ddd; padding: 8px;'>Service</th>
                            " . (!empty($transaction['region']) ? "<th style='border: 1px solid #ddd; padding: 8px;'>Region</th>" : "") . "
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
                            <th style='border: 1px solid #ddd; padding: 8px;'>Quantity</th>
                            <th style='border: 1px solid #ddd; padding: 8px;'>Price</th>
                            <th style='border: 1px solid #ddd; padding: 8px;'></th>
                        </tr>
                    </thead>
                    <tbody>
                        {$selectedDocsRows}
                    </tbody>
                </table>";

                // ' . $css . '
                $mailExceed->setFrom(SEND_FROM, SEND_FROM_NAME);
                $mailExceed->addAddress($transaction['requestor_email']);
                $mailExceed->addReplyTo(REPLY_TO, REPLY_TO_NAME);
                $mailExceed->isHTML(true);
                $mailExceed->Subject = $transaction['reference'] . ' Excess Pages';
                $mailExceed->Body = '
                <html>
                    <head>
                    <style>
  
                    </style>
                    </head>
                    <body>
                        <p>Hi, '. $transaction['owner_firstname'] .'.</p>
                        
                        Upon further review,
                        your request <strong>'.$transaction['reference'].'</strong> has one or more document/s with pages exceeding the standard number.
                        <br/>
                        
                        You will need to pay the additional payment fee of <strong>₱'.$exceedFeeFormatted.'</strong> to move forward with your request.
                        <br/>

                        <p>Kindly refer to the request details below to see your updated total.</p>
                        ' . $serviceTable . '
                        <br/><br/>
                        ' . $selectedDocsTable . '
                        <br/>

                        <p>You may show this copy to the finance department for when you will pay the additional fee.</p>
                        <p>Otherwise, you may keep this as your reference if you opted for your document to be delivered.</p>
                        <br/>
                        <i>Please do not reply to this email.</i>
                    </body>
                </html>
                ';

                $mailExceed->AltBody = '
                Hi, ' . $transaction['owner_firstname'] . ',
                
                Upon further review, your request (' . $transaction['reference'] . ') has one or more document(s) with pages exceeding the standard number.
                
                You will need to pay the additional fee of ₱' . $exceedFeeFormatted . ' to move forward with your request.
                
                Kindly refer to the request details below to see your updated total:
                
                Service Details:
                ' . strip_tags($serviceTable) . '
                
                Selected Documents:
                ' . strip_tags($selectedDocsTable) . '
                
                You may show this copy to the finance department when you pay the additional fee.
                Otherwise, you may keep this as your reference if you opted for your document to be delivered.
                
                PLEASE DO NOT REPLY TO THIS EMAIL.
                ';
                

                if($mailExceed->send()){
                  // 'got_data' => $transaction
                  $response = [
                    'status' => 1,
                    'message' => 'PATCH price_total succeeded!',
                    // 'got_data' => $transaction,
                    // 'exceed_fee_sent' => $exceedFeeFormatted
                  ];
                
                }

            } catch (Exception $e) {
                $response = [
                    'status'=>0,
                    'message'=> "Message could not be sent to user. Mailer Error: {$mailExceed->ErrorInfo}",
                ];
            }        
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