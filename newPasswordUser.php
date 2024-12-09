<?php 
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST");
    
    $db_attempt = new connectDb;
    $db_connection = $db_attempt->connect(); 

    $method = $_SERVER['REQUEST_METHOD'];
    switch ($method){
        case 'GET':

            // echo json_encode();
            break;

        case 'POST':
            // TODO retrieve account with matching email from database
            $user = json_decode(file_get_contents(filename: 'php://input'));
            $received_email = $user->forgot_email;
            
            $qy = "SELECT * FROM users WHERE email = :mail";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':mail', $received_email);
            $stmt->execute();
            
            $found_user = $stmt->fetch(PDO::FETCH_ASSOC);
            $found_email = $found_user['email'];

            if ($found_user){
                // send password recovery token
                $token_reset = bin2hex(random_bytes(16));
                $hash_token_reset = hash("sha256", $token_reset);

                $expiry = date("Y-m-d H:i:s",time() + 60 * 30); // reset token is good for 30 minutes

                $qy_reset = "UPDATE users SET reset_token=:token, reset_token_expires_at=:token_expires WHERE email=:email_reset";
                $stmt_reset =  $db_connection->prepare($qy_reset);;

                $stmt_reset->bindParam(':token', $hash_token_reset);
                $stmt_reset->bindParam(':token_expires', $expiry);
                $stmt_reset->bindParam(':email_reset', $found_email);
                
                $stmt_reset->execute();

                // TODO if account exists, send an email
                if ($stmt_reset->rowCount() > 0) {
                    
                }

                // $_SESSION['id_user'] = $found_user['id'];
                // $_SESSION['role_user'] = $found_user['account_type'];
                // $_SESSION['name_user'] = $found_user['name'];
                
                $response = [
                    'status'=>1,
                    'message'=>'Recover account successful.',
                    // 'name'=>$_SESSION['name_user'],
                    // 'id'=>$_SESSION['id_user'],
                    // 'role'=>$_SESSION['role_user']
                ];
            } else {
                // login failed
                $response = ['status'=>0, 'message'=>'Sorry, Login staff failed.'];
            }

            // echo json_encode($response);

            break;            
    }

?>