<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, PATCH");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method){
    case 'POST':
    
        break;
        
    case 'PATCH':
    
        break;
}
?>