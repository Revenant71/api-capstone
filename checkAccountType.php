<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Credentials: true");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'POST':
        $user = json_decode(file_get_contents(filename: 'php://input'));
        
        if(!isset($_SESSION['role_user'])){
            $response = [
                'statusRole'=>0,
                'msgRole'=>'CLIENT'
            ];
        } else {
            $response = [
                'statusRole'=>1,
                'msgRole'=>'STAFF'
            ];
        }
        
    echo json_encode($response);
    break;
    
}
?>