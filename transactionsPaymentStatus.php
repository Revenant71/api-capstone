<?php
require_once('connectDb.php');

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        // use reference number and Id as basis
        // get status alone

        break;
        
    case 'PATCH':
        $transaction = json_decode(file_get_contents('php://input'), true);
        // use reference number and Id as basis
        // update status 
        break;
}
?>