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
        $found_region_name = $_GET['region'] ?? null;

        // Prepare the query based on whether the region name is provided
        if (!empty($found_region_name)) {
            $qy = "SELECT fee FROM shipping_fees WHERE region = :region";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':region', $found_region_name, PDO::PARAM_STR);
        } else {
            $qy = "SELECT * FROM shipping_fees";
            $stmt = $db_connection->prepare($qy);
        }

        // Execute the query
        $stmt->execute();
        $shippingFees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return the result as JSON
        echo json_encode($shippingFees);
    } catch (PDOException $e) {
        // Handle and return any database errors
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
            // Update query including the `updated_at` timestamp
            $qy = "UPDATE shipping_fees SET fee = :fee, updated_at = NOW() WHERE region = :region";
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
