<?php
session_start();
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Credentials: true");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'POST':
        if(isset($_SESSION['id_user']) && isset($_SESSION['fullname_user']) && $_SESSION['role_user']){
            $response = [
                'status'=>1,
                'message'=>'User session is alive.',
                'fullname'=>$_SESSION['fullname_user'],
                'firstname'=>$_SESSION['firstname_user'],
                'middlename'=>$_SESSION['middlename_user'],
                'lastname'=>$_SESSION['lastname_user'],
                'id'=>$_SESSION['id_user'],
                'role'=>$_SESSION['role_user'],
            ];
        } else {
            $response = ['status' => 0, 'message' => `Incorrect or missing session variables!`];
        }
        
        echo json_encode($response);
        exit();
}
?>