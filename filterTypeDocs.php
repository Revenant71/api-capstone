<?php
// Set headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// DB connection
$host = "localhost";
$db_name = "drts_capstone";
$username = "root"; // change if needed
$password = "";     // change if needed

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->exec("set names utf8");
} catch(PDOException $exception){
    http_response_code(500);
    echo json_encode(array("message" => "Database connection error: " . $exception->getMessage()));
    exit();
}

// Query to get only the names
$query = "SELECT name FROM categories_docs ORDER BY name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();

$num = $stmt->rowCount();

if ($num > 0) {
    $document_names = array();
    $document_names["records"] = array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        array_push($document_names["records"], array("name" => $name));
    }

    http_response_code(200);
    echo json_encode($document_names);
} else {
    http_response_code(404);
    echo json_encode(array("message" => "No document names found."));
}
?>
