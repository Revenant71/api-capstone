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
                DOC.category_id AS DOC_category_id,
                USER.firstname AS USER_firstname,
                USER.middlename AS USER_middlename,
                USER.lastname AS USER_lastname
            FROM transactions TCN
            LEFT JOIN documents DOC ON TCN.id_doc = DOC.id
            LEFT JOIN users USER ON TCN.id_employee = USER.id
            WHERE TCN.reference_number = :reference

            UNION

            SELECT
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author,
                DOC.category_id AS DOC_category_id,
                USER.firstname AS USER_firstname,
                USER.middlename AS USER_middlename,
                USER.lastname AS USER_lastname
            FROM transactions TCN
            RIGHT JOIN documents DOC ON TCN.id_doc = DOC.id
            LEFT JOIN users USER ON TCN.id_employee = USER.id
            WHERE TCN.reference_number = :reference;
            ";

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            // compress longblob data to base64 string
            if ($data && isset($data['file_receipt'])) {
                foreach ($data as &$row) {
                    if (isset($row['file_receipt'])) {
                        // Remove any incorrect or extra prefix if it exists
                        $row['file_receipt'] = preg_replace('/^(dataimage\/jpegbase64,|data:image\/jpeg;base64,)/', '', $row['file_receipt']);
                
                        // Ensure the value is properly base64 encoded
                        $decoded = base64_decode($row['file_receipt'], true);
                
                        if ($decoded !== false) {
                            // Re-encode and prepend the correct prefix
                            $row['file_receipt'] = 'data:image/jpeg;base64,' . base64_encode($decoded);
                        } else {
                            // If decoding fails, encode the raw value
                            $row['file_receipt'] = 'data:image/jpeg;base64,' . base64_encode($row['file_receipt']);
                        }
                    }
                }
            }

            // Process file_receipt if it exists
            // if ($data && isset($data['file_receipt'])) {
            //     $data['file_receipt'] = encodeImageToBase64($data['file_receipt']);
            // }

            // Process file_portrait if it exists
            if ($data && isset($data['file_portrait']) && !empty($data['file_portrait'])) {
                $data['file_portrait'] = encodeImageToBase64($data['file_portrait']);
            }
        } else {
            $qy = "
            SELECT
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author,
                DOC.category_id AS DOC_category_id,
                USER.firstname AS USER_firstname,
                USER.middlename AS USER_middlename,
                USER.lastname AS USER_lastname
            FROM transactions TCN
            LEFT JOIN documents DOC ON TCN.id_doc = DOC.id
            LEFT JOIN users USER ON TCN.id_employee = USER.id

            UNION

            SELECT
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author,
                DOC.category_id AS DOC_category_id,
                USER.firstname AS USER_firstname,
                USER.middlename AS USER_middlename,
                USER.lastname AS USER_lastname
            FROM transactions TCN
            RIGHT JOIN documents DOC ON TCN.id_doc = DOC.id
            LEFT JOIN users USER ON TCN.id_employee = USER.id;
            ";

            $stmt = $db_connection->prepare($qy);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // compress longblob data to base64 string
            foreach ($data as &$row) {
                if (isset($row['file_receipt'])) {
                    $row['file_receipt'] = base64_encode($row['file_receipt']);
                }

                // if (isset($row['file_receipt'])) {
                //     $row['file_receipt'] = encodeImageToBase64($row['file_receipt']);
                // }
    
                if (isset($row['file_portrait']) && !empty($row['file_portrait'])) {
                    $row['file_portrait'] = encodeImageToBase64($row['file_portrait']);
                }
            }

            // debugging
            // file_put_contents('debugTransactions.log', 'Fetched data: ' . print_r($data, true) . PHP_EOL, FILE_APPEND);
            // if (json_last_error() !== JSON_ERROR_NONE) {
            //     file_put_contents('debugTransactionsEncode.log', 'JSON encoding error: ' . json_last_error_msg() . PHP_EOL, FILE_APPEND);
            // }
        }

        // ob_clean();
        echo json_encode($data);
        break;
    case 'POST':
        $transaction = json_decode(file_get_contents('php://input'), true);
        // released_at,
        // :released_at,

        $rawInput = file_get_contents('php://input');
        //file_put_contents("debug_log.txt", "Received length: " . strlen($rawInput) . "\n", FILE_APPEND);

        if (!$transaction) {
            //file_put_contents("debug_log.txt", "JSON decode failed. Raw input:\n" . $rawInput . "\n", FILE_APPEND);

            http_response_code(400);
            echo json_encode(['status' => 0, 'message' => 'Invalid JSON data received.']);
            exit;
        }

        if (!empty($transaction['file_portrait'])) {
            $base64String = $transaction['file_portrait'];
        
            // Validate Base64 image with prefix (Only JPEG or PNG allowed)
            if (preg_match('/^data:image\/(jpeg|png);base64,/i', $base64String, $matches)) {
                $imageType = $matches[1]; // Extract the image type (jpeg or png)
                
                // store base64 string directly
                $transaction['file_portrait'] = $base64String;
            } else {
                // Log the raw image string and reason
                file_put_contents('debug_log.txt', json_encode([
                    'status' => 'Image format failed',
                    'received_header' => substr($base64String, 0, 100), // Just the first part for preview
                    'content_length' => strlen($base64String),
                    'hint' => 'Expected header: data:image/jpeg;base64 or data:image/png;base64'
                ], JSON_PRETTY_PRINT), FILE_APPEND);

                http_response_code(400);
                echo json_encode(['status' => 0, 'message' => 'Invalid image format. Only JPEG and PNG are allowed.']);
                exit;
            }
        }


        $selectedDocsJson = !empty($transaction['selectedDocuments']) ? json_encode($transaction['selectedDocuments']) : '[]';
        $purposeJson = !empty($transaction['purpose']) ? json_encode($transaction['purpose']) : '[]';
        
        $purposeCount = isset($transaction['purposeCount']) ? (int)$transaction['purposeCount'] : 1;

        $qy = "
        INSERT INTO transactions (
            reference_number, service_type, delivery_region,
            id_doc, doc_name, doc_quantity, price, price_total, 
            name_req, phone_req, email_req, 
            firstname_owner, lastname_owner, phone_owner,
            course, course_year, year_last,
            purpose_req, selected_docs, purpose_count,
            statusPayment, statusTransit, id_employee, overdue_days, 
            created_at, updated_at, payment_channel
            " . (!empty($transaction['name_middle']) ? ", middlename_owner" : "") . "
            " . (!empty($transaction['id_swu']) ? ", id_swu" : "") . "
            " . (!empty($transaction['desc_req']) ? ", desc_req" : "") . "
            " . (!empty($transaction['delivery_city']) ? ", delivery_city" : "") . "
            " . (!empty($transaction['delivery_district']) ? ", delivery_district" : "") . "
            " . (!empty($transaction['delivery_brgy']) ? ", delivery_brgy" : "") . "
            " . (!empty($transaction['delivery_street']) ? ", delivery_street" : "") . "
            " . (!empty($transaction['file_portrait']) ? ", file_portrait" : "") . "
        ) VALUES (
            :reference_number, :service_type, :delivery_region,
            :id_doc, :doc_name, :doc_quantity, :price, :price_total, 
            :name_req, :phone_req, :email_req,
            :firstname_owner, :lastname_owner, :phone_owner, 
            :course, :course_year, :year_last, 
            :purpose_req, :selected_docs, :purpose_count,
            :statusPayment, :statusTransit, :id_employee, :overdue_days, 
            :created_at, :updated_at, :payment_channel
            " . (!empty($transaction['name_middle']) ? ", :middlename_owner" : "") . "
            " . (!empty($transaction['id_swu']) ? ", :id_swu" : "") . "
            " . (!empty($transaction['desc_req']) ? ", :desc_req" : "") . "
            " . (!empty($transaction['delivery_city']) ? ", :delivery_city" : "") . "
            " . (!empty($transaction['delivery_district']) ? ", :delivery_district" : "") . "
            " . (!empty($transaction['delivery_brgy']) ? ", :delivery_brgy" : "") . "
            " . (!empty($transaction['delivery_street']) ? ", :delivery_street" : "") . "
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
            ':purpose_req' => $purposeJson,
            ':purpose_count' => $purposeCount,
            ':payment_channel' => $transaction['payment_method'],
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
        if (!empty($transaction['delivery_brgy'])) {
            $transaction_values[':delivery_brgy'] = $transaction['delivery_brgy'];
        } 
        if (!empty($transaction['delivery_street'])) {
            $transaction_values[':delivery_street'] = $transaction['delivery_street'];
        }  
        if (!empty($transaction['file_portrait'])) {
            $transaction_values[':file_portrait'] = $transaction['file_portrait'];
        }

        if ($stmt->execute(array_merge($default_values, $transaction_values))) {
            $response = ['status'=>1, 'message'=>'POST transaction successful.'];
        } else {
            // file_put_contents('debug_log.txt', json_encode([
            //     'status' => 'SQL execution failed',
            //     'pdo_error' => $stmt->errorInfo(),
            //     'submitted_data' => array_merge($default_values, $transaction_values)
            // ], JSON_PRETTY_PRINT), FILE_APPEND);

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
        
        $qy = "UPDATE transactions SET ";
        $setFields = [];
        $transaction_values = [
            ':reference_number' => $found_reference_no,
            ':lastname_owner' => $transaction['owner_lastname'],
        ];
        
        // Required fields
        $fields = [
            'service_type' => 'service_type',
            'requestor_name' => 'name_req',
            'requestor_phone' => 'phone_req',
            'requestor_email' => 'email_req',
            'owner_firstname' => 'firstname_owner',
            'owner_phone' => 'phone_owner',
            'owner_course' => 'course',
            'owner_course_year' => 'course_year',
            'owner_year_last' => 'year_last',
            'staff' => 'id_employee'
        ];
        
        foreach ($fields as $key => $dbField) {
            if (!empty($transaction[$key])) {
                $setFields[] = "$dbField = :$dbField";
                $transaction_values[":$dbField"] = $transaction[$key];
            }
        }
        
        // Optional fields
        $optionalFields = [
            'payment_method' => 'payment_channel',
            'delivery_region' => 'delivery_region',
            'delivery_city' => 'delivery_city',
            'delivery_district' => 'delivery_district',
            'delivery_brgy' => 'delivery_brgy',
            'delivery_street' => 'delivery_street',
            'currentDocId' => 'id_doc',
            'currentDocument' => 'doc_name',
            'currentQuantity' => 'doc_quantity',
            'currentPrice' => 'price',
            'total_price' => 'price_total',
            'overdue_days' => 'overdue_days',
            'statusPayment' => 'statusPayment',
            'statusTransit' => 'statusTransit',
            'owner_middlename' => 'middlename_owner',
            'id_swu' => 'id_swu',
            'description' => 'desc_req',
        ];
        
        foreach ($optionalFields as $inputKey => $dbField) {
            if (!empty($transaction[$inputKey])) {
                $setFields[] = "$dbField = :$dbField";
                $transaction_values[":$dbField"] = $transaction[$inputKey];
            }
        }
        
        $qy .= implode(", ", $setFields);
        $qy .= " , updated_at = NOW() WHERE reference_number = :reference_number AND lastname_owner = :lastname_owner";
        
        $stmt = $db_connection->prepare($qy);
        
        $response = $stmt->execute($transaction_values)
            ? ['status' => 1, 'message' => 'PATCH transaction succeeded!']
            : ['status' => 0, 'message' => 'Sorry, PATCH transaction failed.'];
        
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

function encodeImageToBase64($imageData) {
    // Detect image type using magic bytes
    if (substr($imageData, 0, 2) === "\xFF\xD8") {
        $imageType = 'jpeg';
    } elseif (substr($imageData, 0, 8) === "\x89PNG\r\n\x1A\n") {
        $imageType = 'png';
    } else {
        return null; // Invalid image data
    }

    return "data:image/$imageType;base64," . base64_encode($imageData);
}

?>
