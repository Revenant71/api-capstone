<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, PATCH");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_id_client = isset($URI_array[3]) ? $URI_array[3] : null;

        // TODO sql join transactions and clients where id_owner in transactions = id in clients 
        $qy = "SELECT * FROM `transactions`";

        if (isset($found_id_client) && is_numeric($found_id_client)) {
            $qy .= " WHERE id = :id";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':id', $found_id_client, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db_connection->prepare($qy);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($data);
        break;


    // TODO patch
    case 'PATCH':
        // modify some request details here
         
        break;
}

?>