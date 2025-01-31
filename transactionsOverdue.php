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
            
            if (!$found_reference_no || empty($transaction['owner_lastname']) || empty($transaction['overdue_days']) || empty($transaction['last_overdue_update'])) {
                echo json_encode([
                    'status' => 0,
                    'message' => 'Invalid request parameters',
                    'received_data' => $transaction
                ]);
                exit;
            }
        
            try {
                $db_connection->beginTransaction();
        
                // Fetch overdue_days and updated_overdue with FOR UPDATE
                $qy = "SELECT overdue_days, updated_overdue FROM transactions WHERE reference_number = :reference AND lastname_owner = :lastname_owner FOR UPDATE";
                $stmt = $db_connection->prepare($qy);
                $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
                $stmt->bindParam(':lastname_owner', $transaction['owner_lastname'], PDO::PARAM_STR);
                $stmt->execute();
        
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$data) {
                    $db_connection->rollBack();
                    echo json_encode(['status' => 0, 'message' => 'Transaction not found', 'received_data' => $transaction]);
                    exit;
                }
        
                // Explicitly set timezone to Asia/Manila
                $lastUpdatedOverdue = $data['updated_overdue'] ? new DateTime($data['updated_overdue'], new DateTimeZone('Asia/Manila')) : null;
                $currentDate = new DateTime("now", new DateTimeZone('Asia/Manila'));
        
                // Fix: Compare only the DATE, ignoring time differences
                if (!$lastUpdatedOverdue || $lastUpdatedOverdue->format('Y-m-d') < $currentDate->format('Y-m-d')) {
                    $update_qy = "UPDATE transactions 
                                  SET overdue_days = :overdue_days, updated_overdue = :updated_overdue 
                                  WHERE reference_number = :reference AND lastname_owner = :lastname_owner";
        
                    $stmt = $db_connection->prepare($update_qy);
                    $stmt->bindParam(':overdue_days', $transaction['overdue_days'], PDO::PARAM_INT);
                    $stmt->bindParam(':updated_overdue', $transaction['last_overdue_update'], PDO::PARAM_STR);
                    $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
                    $stmt->bindParam(':lastname_owner', $transaction['owner_lastname'], PDO::PARAM_STR);
                    
                    if ($stmt->execute()) {
                        $db_connection->commit();
                        echo json_encode([
                            'status' => 1,
                            'message' => 'Overdue days updated',
                            'updated_data' => [
                                'reference' => $found_reference_no,
                                'owner_lastname' => $transaction['owner_lastname'],
                                'overdue_days' => $transaction['overdue_days'],
                                'last_overdue_update' => $transaction['last_overdue_update']
                            ]
                        ]);
                    } else {
                        $db_connection->rollBack();
                        echo json_encode([
                            'status' => 0,
                            'message' => 'Database update failed',
                            'received_data' => $transaction
                        ]);
                    }
                } else {
                    $db_connection->rollBack();
                    echo json_encode([
                        'status' => 0,
                        'message' => 'Overdue days already updated today',
                        // 'overdue_days' => $transaction['overdue_days'],
                        // 'last_overdue_update' => $transaction['last_overdue_update'],
                        // 'current_db_data' => $data
                    ]);
                }
            } catch (PDOException $e) {
                $db_connection->rollBack();
                echo json_encode(['status' => 0, 'message' => 'Database error', 'error' => $e->getMessage()]);
            }
        break;
           
}
?>