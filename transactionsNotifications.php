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
header("Access-Control-Allow-Methods: GET");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();
$method = $_SERVER['REQUEST_METHOD'];
switch ($method){
    case 'GET':
        $parsed_url = parse_url($_SERVER['REQUEST_URI']); // Parse the URL
        $path = $parsed_url['path']; // Extract the path part
        $URI_array = explode('/', $path); // Split the path into parts
        $found_reference_no = isset($URI_array[3]) ? $URI_array[3] : null;

        if ($found_reference_no) {
            $qy = "
            SELECT 
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author,
                DOC.category_id AS DOC_category_id
            FROM transactions TCN
            LEFT JOIN documents DOC ON TCN.id_doc = DOC.id
            WHERE TCN.reference_number = :reference;
            ";

            try {
                $stmt = $db_connection->prepare($qy);
                $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
                $stmt->execute();
    
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($data) {
                    foreach ($data as &$row) {
                        if (isset($row['file_receipt'])) {
                            // Assume the data is already base64-encoded with a prefix
                            continue;
                        }
                    }

                    echo json_encode($data);
                } else {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'No matching transactions found.',
                        'refnumber' => $found_reference_no
                    ]);
                }
            } catch (PDOException $e) {
                echo json_encode(['status' => 0, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 0, 'message' => 'Invalid or missing reference number.']);
        }
      break;
}
?>