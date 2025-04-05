<?php
require_once('connectDb.php');
header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

// Check connection
if (!$db_connection) {
    die(json_encode(["error" => "Connection failed."]));
}

// Query to get the most requested documents
$sql = "SELECT doc_name, COUNT(doc_name) AS request_count 
        FROM transactions 
        GROUP BY doc_name 
        ORDER BY request_count DESC 
        LIMIT 5"; 

try {
    $stmt = $db_connection->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Close connection
    $db_connection = null;

    // Output JSON with pretty print
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}
?>