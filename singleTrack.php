<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, PATCH, DELETE");
header("Access-Control-Allow-Credentials: true");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':

        break;

    case 'PATCH':

        break;

    case 'DELETE':

        break;        
}
?>