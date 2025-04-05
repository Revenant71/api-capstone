<?php
require_once('connectDb.php');

// Enable error logging
ini_set('log_errors', 1);
// ini_set('error_log', 'php_errors.log');

// CORS Headers
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// Establish database connection FIRST
$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

if (!$db_connection) {
    error_log("Database connection failed");
    http_response_code(500);
    echo json_encode(["status" => 0, "message" => "Database connection error"]);
    exit();
}

function getClaimData($db_connection, $reference, $lastname) {
    $query = "SELECT 
                  t.receiver_name, 
                  t.receive_date, 
                  CONCAT(u.lastname, ', ', u.firstname, ' ', u.middlename) AS claimed_by, 
                  t.statusTransit 
              FROM transactions t
              LEFT JOIN users u ON t.id_employee = u.id
              WHERE t.reference_number = :ref
              AND t.lastname_owner = :lastname
              LIMIT 1";

    $stmt = $db_connection->prepare($query);
    
    if (!$stmt) {
        error_log("Failed to prepare statement: " . implode(" ", $db_connection->errorInfo()));
        return null;
    }

    $stmt->execute([':ref' => $reference, ':lastname' => $lastname]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (!isset($_GET['ref']) || !isset($_GET['lastname'])) {
            throw new Exception("Missing reference or lastname parameter");
        }

        $claimData = getClaimData($db_connection, $_GET['ref'], $_GET['lastname']);
        
        echo json_encode([
            "status" => 1,
            "data" => $claimData ?: null
        ]);
        exit();
    } catch (Exception $e) {
        //error_log("Exception: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            "status" => 0,
            "message" => $e->getMessage()
        ]);
        exit();
    }
}

function recordClaim($db_connection, $reference, $lastname, $receiver, $date, $staff, $action) {
    $status = ($action === 'deliver') ? 'Delivered' : 'Claimed';

    $query = "UPDATE transactions 
              SET receiver_name = :receiver,
                  receive_date = :date,
                  claimed_by = :staff,
                  statusTransit = :status
              WHERE reference_number = :ref
              AND lastname_owner = :lastname";

    $stmt = $db_connection->prepare($query);

    if (!$stmt) {
        error_log("Failed to prepare statement: " . implode(" ", $db_connection->errorInfo()));
        return false;
    }

    $result = $stmt->execute([
        ':receiver' => $receiver,
        ':date' => $date,
        ':staff' => $staff,
        ':status' => $status,
        ':ref' => $reference,
        ':lastname' => $lastname
    ]);

    if (!$result) {
        error_log("Statement execution failed: " . implode(" ", $stmt->errorInfo()));
    }

    return $stmt->rowCount() > 0;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $rawInput = file_get_contents('php://input');
        //error_log("Raw input received: " . $rawInput);
        
        $data = json_decode($rawInput, true);
        if (!$data) {
            throw new Exception("Invalid JSON data: " . json_last_error_msg());
        }

        $required = ['reference', 'owner_lastname', 'receiver_name', 'receive_date', 'staff', 'action'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        if (recordClaim(
            $db_connection,
            $data['reference'],
            $data['owner_lastname'],
            $data['receiver_name'],
            $data['receive_date'],
            $data['staff'],
            $data['action']
        )) {
            // Return the updated record
            $updatedData = getClaimData($db_connection, $data['reference'], $data['owner_lastname']);
            
            echo json_encode([
                "status" => 1,
                "message" => ucfirst($data['action']) . " recorded successfully",
                "data" => $updatedData
            ]);
        } else {
            throw new Exception("No records were updated. Please check the reference number and last name.");
        }
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            "status" => 0,
            "message" => $e->getMessage()
        ]);
    }
    exit();
}