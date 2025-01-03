<?php
require_once 'connectDb.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 

// Check if connection was successful
if (!$db_connection) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . mysqli_connect_error()])); 
}

// Handle GET request to fetch shipping fees
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $qy = "SELECT * FROM shipping_fees";
        $stmt = $db_connection->prepare($qy);
        $stmt->execute();
        $shippingFees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($shippingFees);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Handle POST request to update shipping fees
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve POST data
    $data = json_decode(file_get_contents('php://input'));

    if (isset($data->region) && isset($data->fee)) {
        $region = $data->region;
        $fee = $data->fee;

        try {
            $qy = "UPDATE shipping_fees SET fee = :fee WHERE region = :region";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':fee', $fee);
            $stmt->bindParam(':region', $region);
            $stmt->execute();

            echo json_encode(['message' => 'Shipping fee updated successfully']);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Error updating shipping fee: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Missing parameters']);
    }
}

// Close the database connection
$db_connection = null;
?>
