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
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, PUT");

    $db_attempt = new connectDb;
    $db_connection = $db_attempt->connect(); 


    $method = $_SERVER['REQUEST_METHOD'];
    switch ($method) {
        case 'GET':
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = isset($URI_array[3]) ? $URI_array[3] : null;
        
            if ($found_id && is_numeric($found_id)) {
                // Fetch document by ID (ignoring hidden status)
                $qy = "SELECT * FROM categories_docs WHERE id = :id";
                $stmt = $db_connection->prepare($qy);
                $stmt->bindParam(':id', $found_id, PDO::PARAM_INT);
            } else {
                // Fetch only visible documents by default
                $qy = "SELECT * FROM categories_docs WHERE hidden = 0";
                $stmt = $db_connection->prepare($qy);
            }
        
            $stmt->execute();
            $data = $found_id ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
        
            echo json_encode($data);
            break;
        
        
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
        
            if ($data) {
                // Log incoming data for debugging
                error_log(print_r($data, true));
        
                // Validate the data
                if (isset($data['name'], $data['price'], $data['processing_days'])) {
                    // SQL query to insert the document into categories_docs
                    $qy = "INSERT INTO categories_docs(name, price, processing_days, luzon_price, visayas_price, mindanao_price) 
                           VALUES(:name, :price, :processing_days, :luzon_price, :visayas_price, :mindanao_price)";
                
                    $empty_int = 0;

                    $stmt = $db_connection->prepare($qy);
                    $stmt->bindParam(':name', $data['name']);
                    $stmt->bindParam(':price', $data['price']);
                    $stmt->bindParam(':processing_days', $data['processing_days']);
                    $stmt->bindParam(':luzon_price', $empty_int);
                    $stmt->bindParam(':visayas_price', $empty_int);
                    $stmt->bindParam(':mindanao_price', $empty_int);

                    // Execute the query and check for success$empty_int
                    if ($stmt->execute()) {
                        // TODO use phpmail email current admin
                        // try {
                        //     // config
                        //     $mailCreatedDocs = new PHPMailer(true);
                        //     $mailCreatedDocs->Host = MAILHOST;
                        //     $mailCreatedDocs->isSMTP();
                        //     $mailCreatedDocs->SMTPAuth = true;
                        //     $mailCreatedDocs->Username = USERNAME;
                        //     $mailCreatedDocs->Password = PASSWORD;
                        //     $mailCreatedDocs->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
                        //     $mailCreatedDocs->Port = 587;
        
                        //     $mailCreatedDocs->setFrom(SEND_FROM, SEND_FROM_NAME);
                        //     $mailCreatedDocs->addAddress($data['email_staff']);
                        //     $mailCreatedDocs->addReplyTo(REPLY_TO, REPLY_TO_NAME);
                        //     $mailCreatedDocs->isHTML(true);
                        //     $mailCreatedDocs->Subject = 'Document Request Confirmation';
                        //     // TODO anchor
                        //     $mailCreatedDocs->Body = '
                        //         <html>
                        //             <head>
                        //             <style>
                                        
                        //             </style>
                        //             </head>
                        //             <body>
                        //                 <p>Hi, '.$data['name_req'].'.</p>
                                        
                        //                 <p>Your request has been submitted.</p>
                        //                 <p><em>Use the reference number below to track them on DocuQuest</em></p>
                        //                 <ul>
                        //                  <li> '.$display_reference.' </li>
                        //                 </ul>
                        //                 <p>In order to proceed with your request, please track your required fee by accessing this <a href="http://localhost:3000/start" target="_blank" title="Click here to track your request.">link.</a></p>
                        //                 <br/>
                        //                 <p>Below are your options for the official payment channels:</p>
                        //                 <h3>Modes&nbsp;of&nbsp;Payment</h3>
                        //                 <ul>
                        //                   <li>
                        //                     <strong>ON-SITE</strong>
                        //                     <p>FINANCE OFFICE IS OPEN</p>
                        //                     <p><em>8:00 AM TO 4:00 PM</em></p>
                        //                     <p><em>MONDAY - FRIDAY</em></p>
                        //                   </li>
                        //                 <br/>
                        //                   <li>
                        //                     <strong>ONLINE</strong>
                        //                     <p>Students may process payment through our collecting partners listed below.</p>
                        //                     <strong>PAYMENT COLLECTION FACILITIES</strong>
                        //                     <ul>
                        //                         <li>
                        //                         RCBC
                        //                             <ol type="A">
                        //                               <li>OVER THE COUNTER- TRANSACT IN BILLS PAYMENT (GREEN FORM) ;<br/> BILLER NAME - SOUTHWESTERN UNIVERSITY INC.</li>
                        //                               <br/>
                        //                               <li>ONLINE BANKING- PROCESS YOUR PAYMENT IN PAY BILLS ;<br/> BILLER NAME - SOUTHWESTERN UNIVERSITY INC.</li>
                        //                             </ol>
                        //                         </li>
                        //                         <br/>
                        //                         <li>
                        //                         BDO
                        //                             <ol type="A">
                        //                               <li>OVER THE COUNTER- TRANSACT IN BILLS PAYMENT ;<br/>INSTITUTIONAL CODE: 1054 ;<br/> BILLER NAME - SOUTHWESTERN UNIVERSITY INC.</li>
                        //                               <br/>
                        //                               <li>ONLINE BANKING- PROCESS YOUR PAYMENT IN BILLS PAYMENT;<br/> BILLER NAME - SOUTHWESTERN UNIVERSITY INC.</li>
                        //                             </ol>
                        //                         </li>
                        //                         <br/>
                        //                         <li>
                        //                         GCASH
                        //                             <ol type="A">
                        //                               <li>TRANSACT IN PAY BILLS ;<br/> BILLER NAME - PHINMA EDUCATION OR PHINMA SOUTHWESTERN UNIVERSITY</li>
                        //                               <p>IF ID NUMBER DOES NOT START WITH "05-" , PLEASE INDICATE "05-" BEFORE THE ID NUMBER</p>
                        //                             </ol>
                        //                         </li>
                        //                         <br/>
                        //                         <li>
                        //                         ECPAY
                        //                             <ol type="A">
                        //                               <li>BILLER NAME - PHINMA EDUCATION</li>
                        //                               <p>IF ID NUMBER DOES NOT START WITH "05-" , PLEASE INDICATE "05-" BEFORE THE ID NUMBER</p>
                        //                             </ol>
                        //                         </li>
                        //                     </ul>
                        //                   </li>
                        //                 </ul>
            
                        //                 <p>Thank you for using DocuQuest.</p>
                        //                 <h3>This an auto-generated email. <em>Please do not reply.</em></h3>
                        //             </body>
                        //         </html>      
                        //     ';
            
                        //     $mailCreatedDocs->AltBody = "
                        //     Hi, {$data['name_req']},
                            
                        //     Your requests have been submitted.
                        //     Use the reference number below to track them on DocuQuest:
                            
                        //     {$display_reference}
                            
                        //     MODES OF PAYMENT:
                        //     ON-SITE:
                        //     FINANCE OFFICE IS OPEN
                        //     8:00 AM TO 4:00 PM
                        //     MONDAY - FRIDAY
                            
                        //     ONLINE:
                        //     Students may process payment through our collecting partners listed below.
                            
                        //     PAYMENT COLLECTION FACILITIES:
                            
                        //     RCBC:
                        //     A. OVER THE COUNTER - TRANSACT IN BILLS PAYMENT (GREEN FORM);
                        //        BILLER NAME - SOUTHWESTERN UNIVERSITY INC.
                        //     B. ONLINE BANKING - PROCESS YOUR PAYMENT IN PAY BILLS;
                        //        BILLER NAME - SOUTHWESTERN UNIVERSITY INC.
                            
                        //     BDO:
                        //     A. OVER THE COUNTER - TRANSACT IN BILLS PAYMENT;
                        //        INSTITUTIONAL CODE: 1054;
                        //        BILLER NAME - SOUTHWESTERN UNIVERSITY INC.
                        //     B. ONLINE BANKING - PROCESS YOUR PAYMENT IN BILLS PAYMENT;
                        //        BILLER NAME - SOUTHWESTERN UNIVERSITY INC.
                            
                        //     GCASH:
                        //     A. OVER THE COUNTER - TRANSACT IN PAY BILLS;
                        //        BILLER NAME - PHINMA EDUCATION OR PHINMA SOUTHWESTERN UNIVERSITY
                        //        IF ID NUMBER DOES NOT START WITH '05-', PLEASE INDICATE '05-' BEFORE THE ID NUMBER.
                            
                        //     ECPAY:
                        //     A. BILLER NAME - PHINMA EDUCATION
                        //        IF ID NUMBER DOES NOT START WITH '05-', PLEASE INDICATE '05-' BEFORE THE ID NUMBER.
                            
                        //     Thank you for using DocuQuest.
                            
                        //     This is an auto-generated email. Please do not reply.
                        //     ";
                            
                        //     if ($mailCreatedDocs->send())
                        //     {
                        //         $response = [
                        //             'status' => '1',
                        //             'message' => 'New document added to categories_docs successfully.',
                        //         ];
                        //     }
                        // } catch(Exception $e) {
                        //     $response = [
                        //         'status'=>0,
                        //         'message'=> "Email could not be sent to logged in staff. Mailer Error: {$mailCreatedDocs->ErrorInfo}",
                        //     ];
                        // }
                        
                        // Return success message as a JSON response
                        $response = ['status' => 1, 'message' => "New document added to categories_docs successfully."];
                    } else {
                        // Return failure message if insertion fails
                        $response = ['status' => 0, 'message' => "Failed to add document to categories_docs."];
                    }
        
                    // Send the response back to the frontend
                    echo json_encode($response);
                } else {
                    // If required fields are missing, send error
                    $response = ['status' => 0, 'message' => 'Missing required fields.'];
                    echo json_encode($response);
                }
            } else {
                // If data is not sent, send error
                $response = ['status' => 0, 'message' => 'No data received.'];
                echo json_encode($response);
            }
            break;

            case 'PATCH':
                $category = json_decode(file_get_contents('php://input'));
                $URI_array = explode('/', $_SERVER['REQUEST_URI']);
                $found_id = isset($URI_array[3]) ? $URI_array[3] : null;
            
                if ($found_id && is_numeric($found_id)) {
                    // Update query for editing an existing record
                    $qy = "UPDATE categories_docs SET 
                               name=:name, 
                               price=:price, 
                               processing_days=:processing_days, 
                               luzon_price=:luzon_price, 
                               visayas_price=:visayas_price, 
                               mindanao_price=:mindanao_price, 
                               updated_at=:updated 
                           WHERE id=:id";
                    $stmt = $db_connection->prepare($qy);
            
                    $updated_at = date('Y-m-d H:i:s');
                    $empty_int = 0;

                    // Bind values based on the data received from the frontend
                    $stmt->bindParam(':id', $found_id);
                    $stmt->bindParam(':name', $category->name);  
                    $stmt->bindParam(':price', $category->price);  
                    $stmt->bindParam(':processing_days', $category->processing_days);  
                    $stmt->bindParam(':updated', $updated_at);
                    $stmt->bindParam(':luzon_price', $empty_int);  
                    $stmt->bindParam(':visayas_price', $empty_int);  
                    $stmt->bindParam(':mindanao_price', $empty_int);  
            
                    if ($stmt->execute()) {
                        $response = ['status' => 1, 'message' => 'Document category updated successfully.'];
                    } else {
                        $response = ['status' => 0, 'message' => 'Failed to update document category.'];
                    }
                } else {
                    $response = ['status' => 0, 'message' => 'Invalid or missing ID for update.'];
                }
            
                echo json_encode($response);
                break;            
            
        
        case 'DELETE':
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = $URI_array[3];

            $qy = "DELETE FROM categories_docs WHERE id=:id";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':id', $found_id);

            if ($stmt->execute()) {
                $response = ['status'=>1, 'message'=>'DELETE document category successful.'];
            } else {
                $response = ['status'=>0, 'message'=>'OOPS, DELETE document category failed.'];
            }
            
            echo json_encode($response);
            break;            

    }
?>