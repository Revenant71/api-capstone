<?php 
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 

$method = $_SERVER['REQUEST_METHOD'];
switch ($method){
    case 'GET':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_id = isset($URI_array[3]) ? $URI_array[3] : null;

        $qy = "SELECT * FROM purposes";

        if ($found_id && is_numeric($found_id)) {
            $qy .= " WHERE id=:id";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':id', $found_id);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);                
        } else {
            $stmt = $db_connection->prepare($qy);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($data);
        break;
    case 'POST':

        break;
    case 'PATCH':

        break;
    case 'DELETE':

        break;
}
?>