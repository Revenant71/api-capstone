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
switch($method){
    case 'GET':
        $parsed_url = parse_url($_SERVER['REQUEST_URI']); // Parse the URL
        $path = $parsed_url['path']; // Extract path without query string
        $URI_array = explode('/', trim($path, '/')); // Trim and split path
        $found_reference_no = end($URI_array); // Get last segment as reference number
        
        $lastname_owner = $_GET['lastname_owner'] ?? null; 

        if (!$found_reference_no || !$lastname_owner) {
            echo json_encode(['status' => 0, 'message' => 'Invalid reference number or owner lastname']);
            exit;
        }
        
        try {
            $qy = "SELECT overdue_days FROM transactions WHERE reference_number=:reference AND lastname_owner=:lastname_owner";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
            $stmt->bindParam(':lastname_owner', $lastname_owner, PDO::PARAM_STR);
            $stmt->execute();
    
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$data) {
                echo json_encode(['status' => 0, 'message' => 'No matching transaction found for reference: ' . $found_reference_no . ', owner: ' . $lastname_owner]);
                exit;
            }

            echo json_encode($data);
        } catch (PDOException $e) {
            echo json_encode(['status' => 0, 'message' => 'Database error', 'error' => $e->getMessage()]);
        }
        break;
    
    // TODO send email to requestor
    case 'POST':
        $transaction = json_decode(file_get_contents('php://input'), true);
        
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3] ?? null;

        
        // empty($transaction['requestor_email']) || empty($transaction['service']) || empty($transaction['selected_docs'])
        $transaction['service'];
        $transaction['region'];
        $transaction['requested_date'];
        $transaction['updated_date'];
        $transaction['release_date'];
        $transaction['delivery_city'];
        $transaction['delivery_district'];
        $transaction['delivery_street'];
        $transaction['selected_docs'];
        $transaction['total_price'];

        // echo json_encode($response);
        break;
    case 'PATCH':
        $transaction = json_decode(file_get_contents('php://input'), true);
        
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3] ?? null;
        
        if (!$found_reference_no || empty($transaction['owner_lastname']) || empty($transaction['owner_firstname']) || empty($transaction['overdue_days']) || empty($transaction['staff']) || empty($transaction['requestor_email']) || empty($transaction['service']) || empty($transaction['selected_docs'])){
            echo json_encode(['status' => 0, 'message' => 'Invalid reference number, owner lastname, owner firstname, requestor email, service type, or selected docs']);
        }
        
        // TODO check "status tracking update"

        // try {

        // } catch (PDOException $e) {

        // }


        $qy = "UPDATE transactions SET id_employee = :id_employee, overdue_days = :overdue_days WHERE reference_number = :reference AND lastname_owner = :lastname_owner";
        $stmt = $db_connection->prepare($qy);
        $stmt->bindParam(':id_employee', $transaction['staff'], PDO::PARAM_INT);
        $stmt->bindParam(':overdue_days', $transaction['overdue_days'], PDO::PARAM_INT);
        $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
        $stmt->bindParam(':lastname_owner', $transaction['owner_lastname'], PDO::PARAM_STR);
     
        if($stmt->execute()){

            $response = ['status'=>1, 'message'=>`PATCH overdue_days successful!`];
        } else {
            $response = ['status'=>0, 'message'=>'PATCH '. htmlspecialchars($found_reference_no) . ' overdue_days failed!'];
        }

        // echo json_encode($response)
        break;    
}
?>