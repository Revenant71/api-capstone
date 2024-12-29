<?php
require_once 'connectDb.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 

// Check if connection was successful
if (!$db_connection) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . mysqli_connect_error()])); 
}

// Handle GET request to fetch shipping fees
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch shipping fees from the database
    $sql = "SELECT luzon_price, visayas_price, mindanao_price FROM shipping_fees WHERE id = 1";
    $result = $db_connection->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode($row); // Return shipping fees as a JSON response
    } else {
        error_log("No shipping fees found in the database");
        echo json_encode(['status' => 'error', 'message' => 'No shipping fees found']);
    }
}

// Handle POST request to update shipping fees
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from the request body (shipping fees)
    $data = json_decode(file_get_contents("php://input"), true);
    error_log("Received data: " . print_r($data, true)); // Log the received data

    if (isset($data['Luzon'], $data['Visayas'], $data['Mindanao'])) {
        // Validate input data (check if they are numbers)
        if (!is_numeric($data['Luzon']) || !is_numeric($data['Visayas']) || !is_numeric($data['Mindanao'])) {
            error_log("Invalid shipping fee values: Luzon: {$data['Luzon']}, Visayas: {$data['Visayas']}, Mindanao: {$data['Mindanao']}");
            echo json_encode(['status' => 'error', 'message' => 'Invalid shipping fee values']);
            exit;
        }

        // Prepare the SQL query for updating shipping fees
        $sql = "UPDATE shipping_fees SET luzon_price = ?, visayas_price = ?, mindanao_price = ? WHERE id = 1";
        $stmt = $db_connection->prepare($sql);
        
        // Log SQL preparation status
        if ($stmt === false) {
            error_log('MySQL prepare error: ' . $db_connection->error);
            echo json_encode(['status' => 'error', 'message' => 'Failed to prepare the query']);
            exit;
        }

        error_log("Prepared SQL query successfully");

        // Bind the parameters and execute the query
        $stmt->bind_param('ddd', $data['Luzon'], $data['Visayas'], $data['Mindanao']);

        if ($stmt->execute()) {
            error_log("Shipping fees updated successfully");
            echo json_encode(['status' => 'success', 'message' => 'Shipping fees updated successfully']);
        } else {
            // Log execution error
            error_log('MySQL execution error: ' . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update shipping fees']);
        }

        // Close the statement
        $stmt->close();
    } else {
        error_log("Missing data for shipping fees");
        echo json_encode(['status' => 'error', 'message' => 'Missing data for shipping fees']);
    }
}

// Close the database connection
$db_connection->close();
?>
