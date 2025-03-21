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

// Fetch Delivery Status for ALL Regions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_delivery_status'])) {
    try {
        $query = "SELECT DISTINCT delivery_status FROM shipping_fees";
        $stmt = $db_connection->prepare($query);
        $stmt->execute();
        $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // If all rows have the same status, return it; otherwise, return mixed
        $unique_status = array_unique($statuses);
        if (count($unique_status) === 1) {
            echo json_encode(["delivery_status" => (int)$unique_status[0]]);
        } else {
            echo json_encode(["delivery_status" => "mixed"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["error" => "Error fetching delivery status: " . $e->getMessage()]);
    }
    exit();
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
    $data = json_decode(file_get_contents('php://input'));

    try {
        if (isset($data->delivery_status)) {
            if (!isset($data->region) || $data->region === "all") {
                // Update ALL Regions' Delivery Status
                $query = "UPDATE shipping_fees SET delivery_status = :delivery_status, updated_at = NOW()";
                $stmt = $db_connection->prepare($query);
                $stmt->bindValue(':delivery_status', $data->delivery_status, PDO::PARAM_INT);
            } else {
                // Update a SPECIFIC Region
                $query = "UPDATE shipping_fees SET delivery_status = :delivery_status, updated_at = NOW() WHERE region = :region";
                $stmt = $db_connection->prepare($query);
                $stmt->bindValue(':delivery_status', $data->delivery_status, PDO::PARAM_INT);
                $stmt->bindValue(':region', $data->region, PDO::PARAM_STR);
            }
            $stmt->execute();
            echo json_encode(['message' => 'Delivery status updated successfully']);
            exit();
        }

        // Ensure region is provided for specific updates
        if (!isset($data->region)) {
            echo json_encode(['error' => 'Region is required']);
            exit();
        }

        $query = "UPDATE shipping_fees SET ";
        $params = [];

        if (isset($data->fee)) {
            $query .= "fee = :fee, ";
            $params[':fee'] = $data->fee;
        }
        if (isset($data->delivery_status)) {
            $query .= "delivery_status = :delivery_status, ";
            $params[':delivery_status'] = $data->delivery_status;
        }

        // Ensure at least one field is updated
        if (!empty($params)) {
            $query .= "updated_at = NOW() WHERE region = :region";
            $params[':region'] = $data->region;

            $stmt = $db_connection->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            echo json_encode(['message' => 'Shipping fee and/or delivery status updated successfully']);
        } else {
            echo json_encode(['error' => 'No valid fields to update']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error updating: ' . $e->getMessage()]);
    }
    exit();
}

// Close the database connection
$db_connection = null;
?>