<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Credentials: true");

session_start();
    $db_attempt = new connectDb;
    $db_connection = $db_attempt->connect(); 

    $method = $_SERVER['REQUEST_METHOD']; // retrieve the method of the request made to this code
    switch ($method) {
        // login for client
        case 'POST':
            $user = json_decode(file_get_contents(filename: 'php://input'));
            $received_email = $user->email;
            $received_plainPass = $user->password;
            
            $qy = "SELECT * FROM clients WHERE email = :mail";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':mail', $received_email);
            $stmt->execute();
            
            $found_client = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($found_client && password_verify($received_plainPass, $found_client['password']) ){
                // login is successful
                $_SESSION['id_user'] = $found_client['id'];
                $_SESSION['id_swu'] = $found_client['id_swu'] ? $found_client['id_swu'] : $found_client['id'];
                $_SESSION['name_user'] = $found_client['name'];

                $response = [
                    'status'=>1,
                    'message'=>'Login client successful.',
                    'name'=>$_SESSION['name_user'],
                    'id'=>$_SESSION['id_user'],
                    'id_swu'=>$_SESSION['id_swu']
                ];
            } else {
                // login failed
                $response = ['status'=>0, 'message'=>'Sorry, Login client failed.'];
            }

            echo json_encode($response);
            break;
    }

?>