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
    case 'GET':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3] ?? null;

        if (!$found_reference_no || empty($transaction['owner_lastname']) || empty($transaction['statusTransit'])) {
            echo json_encode(['status' => 0, 'message' => 'Invalid reference number, owner lastname, or missing statusTransit']);
            exit;
        }


        // TODO fetch

        // echo json_encode($response);
        break;
    
    case 'POST':

        break;
    
    case 'PATCH':
        $transaction = json_decode(file_get_contents('php://input'), true);
        
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3] ?? null;
        
        if (!$found_reference_no || empty($transaction['owner_lastname']) || empty($transaction['staff']) || empty($transaction['statusTransit'])) {
            echo json_encode(['status' => 0, 'message' => 'Invalid reference number, owner lastname, staff, or missing statusTransit']);
            exit;
        }

        $qy = "UPDATE transactions 
        SET statusTransit = :statusTransit, id_employee = :id_employee, updated_at = NOW() 
        WHERE reference_number = :reference AND lastname_owner = :lastname_owner";
        
        $stmt = $db_connection->prepare($qy);
        $stmt->bindParam(':statusTransit', $transaction['statusTransit'], PDO::PARAM_STR);
        $stmt->bindParam(':id_employee', $transaction['staff'], PDO::PARAM_INT);
        $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
        $stmt->bindParam(':lastname_owner', $transaction['owner_lastname'], PDO::PARAM_STR);
        
        if($stmt->execute()){
            
            $response = ['status'=>1, 'message'=>`PATCH statusTransit successful!`];
        } else {
            $response = ['status'=>0, 'message'=>'PATCH '. htmlspecialchars($found_reference_no) . ' statusTransit failed!'];
        }
        
        echo json_encode($response);
        break;
}
?>