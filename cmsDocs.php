<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 


$method = $_SERVER['REQUEST_METHOD'];
switch ($method){
    case 'GET':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_id = isset($URI_array[3]) ? $URI_array[3] : null;
    
        if ($found_id && is_numeric($found_id)) {
            $qy = "SELECT * FROM categories_docs WHERE id = :id";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':id', $found_id, PDO::PARAM_INT);
        } else {
            // Fetch all documents by default
            $qy = "SELECT * FROM categories_docs";
            $stmt = $db_connection->prepare($qy);
        }
    
        $stmt->execute();
        $data = $found_id ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        echo json_encode($data);
        break;
}
?>