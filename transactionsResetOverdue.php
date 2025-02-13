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

        break;

    case 'POST':

        break;

    case 'PATCH':
        $transaction = json_decode(file_get_contents('php://input'), true);
        
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3] ?? null;
        
        if (!$found_reference_no || empty($transaction['owner_lastname']) || empty($transaction['overdue_days'])) {
            echo json_encode(['status' => 0, 'message' => 'Invalid reference number, owner lastname, or missing overdue_days']);
            exit;
        }

        $qy = "UPDATE transactions 
        SET overdue_days = :overdue_days, updated_overdue = NULL, updated_at = NOW() 
        WHERE reference_number = :reference AND lastname_owner = :lastname_owner";
        
        $stmt = $db_connection->prepare($qy);
        $stmt->bindParam(':overdue_days', $transaction['overdue_days'], PDO::PARAM_INT);
        $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
        $stmt->bindParam(':lastname_owner', $transaction['owner_lastname'], PDO::PARAM_STR);

        if($stmt->execute()){
            $response = ["status" => 1, "message" => "Transaction updated successfully, overdue_days now 0 and updated_overdue set to NULL"];
        } else {
            $response = ["status" => 0, "message" => "Failed to update transaction"];
        }

        echo json_encode($response);
        break;

}
?>