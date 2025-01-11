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
        // login for staff
        case 'POST':
            $user = json_decode(file_get_contents(filename: 'php://input'));
            $received_email = $user->email ?? null;
            $received_plainPass = $user->password ?? null;
            
            if (!$received_email || !$received_plainPass) {
                $response = ['status' => 0, 'message' => 'Email and password are required.'];
                echo json_encode($response);
                exit;
            }

            $qy = "SELECT * FROM users WHERE email = :mail";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':mail', var: $received_email);
            $stmt->execute();
            
            $found_user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($found_user) {
                if ($found_user['status'] !== 'active') {
                    // User is deactivated
                    $response = [
                        'status' => 0,
                        'message' => 'Your account is deactivated. Please contact support.'
                    ];
                } elseif (password_verify($received_plainPass, $found_user['password']) ){
                    // login is successful
                    // $_SESSION['status_user'] = $found_user['status'];
                    $_SESSION['id_user'] = $found_user['id'];
                    $_SESSION['role_user'] = $found_user['account_type'];
                    $_SESSION['name_user'] = $found_user['lastname']. ', '.$found_user['firstname']. ' ' .$found_user['middlename']  ;
                    
                    $response = [
                        'status'=>1,
                        'message'=>'Login staff successful.',
                        'name'=>$_SESSION['name_user'],
                        'id'=>$_SESSION['id_user'],
                        'role'=>$_SESSION['role_user'],
                        // 'status_user'=>$_SESSION['status_user']
                    ];
                } else {
                    // password mismatch
                    error_log("Password verification failed for email: $received_email");
                    $response = ['status' => 0, 'message' => 'Incorrect password.'];
                }
            } else {
                // User does not exist
                error_log("No user found with email: $received_email");
                $response = ['status' => 0, 'message' => 'No user found with the provided credentials.'];
            }

            echo json_encode($response);
            break;
    }
?>