<?php
require_once 'connectDb.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 

// Check if connection was successful
if (!$db_connection) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle GET request to fetch shipping fees
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch shipping fees from the database
    $sql = "SELECT region, fee FROM shipping_fees";
    $result = $db_connection->query($sql);

    $shippingFees = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $shippingFees[$row['region']] = $row['fee'];
        }
        echo json_encode($shippingFees);
    } else {
        echo json_encode([]);
    }
}

// Handle POST request to update shipping fees
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from the request body (shipping fees)
    $data = json_decode(file_get_contents("php://input"), true);

    // Begin a transaction to update multiple records
    $db_connection->begin_transaction();

    try {
        // Prepare and execute an update for each region
        foreach ($data as $region => $fee) {
            $sql = "UPDATE shipping_fees SET fee = ? WHERE region = ?";
            $stmt = $db_connection->prepare($sql);
            $stmt->bind_param("ds", $fee, $region);
            $stmt->execute();
        }

        // Commit the transaction
        $db_connection->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        // Rollback the transaction if an error occurs
        $db_connection->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Failed to update shipping fees']);
    }
}

// Close the database connection
$db_connection->close();
?>
