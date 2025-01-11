<?php
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
        $user = json_decode(file_get_contents(filename: 'php://input'));
        // $user->id;

        $query_get_status = "SELECT `status` FROM users";
        if ($user->id && is_numeric($user->id)) {
            $query_get_status  .= " WHERE id=:id";
            $stmt = $db_connection->prepare($query_get_status);
            $stmt->bindParam(':id', $user->id);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data && $data['status'] !== 'deactivated') {
                $response = [
                    'status'=>1,
                    'recieved'=>$data['status'],
                    'message'=>'Account is active.'
                ];
            } else {
                $response = [
                    'status'=>0,
                    'recieved'=>$data['status'],
                    'message'=>'SORRY, Account is deactivated!'
                ];
            }
        }
        
    echo json_encode($response);
    break;
    
}
?>