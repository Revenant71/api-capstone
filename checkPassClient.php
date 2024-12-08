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
        if(!isset($_SESSION['id_user'])){
            $response = [
                'statusAuth'=>0,
                'msgPassword'=>'CHECKPASS ERROR: USER NOT AUTHENTICATED'
            ];
        }

        // get form data
        $URI_array = explode('/', string: $_SERVER['REQUEST_URI']);
        $found_id = isset($URI_array[3]) ? $URI_array[3] : null;
        $client = json_decode(file_get_contents(filename: 'php://input'));
        
        $query_get_oldpass = "SELECT `password` FROM clients";
        if ($found_id && is_numeric($found_id)) {
            $query_get_oldpass .= " WHERE id=:id";
            $stmt = $db_connection->prepare($query_get_oldpass);
            $stmt->bindParam(':id', $found_id);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data && password_verify($client->currentPassword, $data['password'])){
                $hash_newPassword = password_hash($client->newPassword, PASSWORD_BCRYPT); 
                $query_set_newpass = "UPDATE clients SET `password`=:pass, `updated_at`=:updated WHERE id=:id";
                // $client->oldPassword;
                $updated_at = date('Y-m-d H:i:s');

                $stmt = $db_connection->prepare($query_set_newpass);
                $stmt->bindParam(':pass', $hash_newPassword);
                $stmt->bindParam(':updated', $updated_at);
                $stmt->bindParam(':id', $found_id);
                $stmt->execute();

                $response = [
                    'statusAuth'=>1,
                    'msgPassword'=>'PASSWORD SUCCESSFULLY CHANGED',
                ];
            } else {
                $response = [
                    'statusAuth'=>0,
                    'msgPassword'=>'CHECKPASS ERROR: CURRENT PASSWORD IS INCORRECT',
                ];
            }

        } else {
            $response = [
                'statusAuth'=>0,
                'msgPassword'=>'CHECKPASS ERROR: CANNOT FIND TARGET PASSWORD'
            ];
        }
        echo json_encode($response);
        break;
    
}
?>