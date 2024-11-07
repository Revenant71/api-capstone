<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Credentials: true");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        if(!isset($_SESSION['id_user'])){
            $response = [
                'statusAuth'=>0,
                'msgAuth'=>'USER NOT AUTHENTICATED'
            ];
        } else {
            $response = [
                'statusAuth'=>1,
                'msgAuth'=>'USER AUTHENTICATED'
            ];
        }
        
    echo json_encode($response);
    break;
    
}
?>