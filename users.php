<?php
require_once('connectDb.php');
require 'configSmtp.php'; 
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use OTPHP\TOTP;
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE");
    
    $db_attempt = new connectDb;
    $db_connection = $db_attempt->connect(); 
    $css = file_get_contents('http://localhost/api_drts/cssEmailRecover.php');

    // for "remember me" setting
    function createRememberToken($length = 50){ // default is 50 bytes
        return bin2hex(random_bytes($length));
    }

    $method = $_SERVER['REQUEST_METHOD'];
    switch ($method) {
        case 'GET':
            $user = json_decode(file_get_contents('php://input'));
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = isset($URI_array[3]) ? $URI_array[3] : null;

            $qy = "SELECT * FROM users";

            if ($found_id && is_numeric($found_id)) {
                $qy .= " WHERE id=:id";
                $stmt = $db_connection->prepare($qy);
                $stmt->bindParam(':id', $found_id);
                $stmt->execute();
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($data && isset($data['img_profile'])) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($data['img_profile']);
                    $data['img_profile'] = "data:$mimeType;base64," . base64_encode($data['img_profile']);
                }
            } else {
                $stmt = $db_connection->prepare($qy);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($data as &$row) {
                    if (isset($row['img_profile'])) {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mimeType = $finfo->buffer($row['img_profile']);
                        $row['img_profile'] = "data:$mimeType;base64," . base64_encode($row['img_profile']);
                    }
                }
            }
        
            echo json_encode($data);
            break;
        
        // For Registrar to create new staff accounts
        case 'POST':
            $user = json_decode(file_get_contents('php://input'));
            // remember_token,
            // :remember,
            // email_verified_at,
            // :verified,

            // TODO hardcode status as deactivated, when clicking on hyperlink in email, go to new component to change status as active
            $qy = "INSERT INTO users(img_profile,
            firstname, middlename, lastname,
            email, phone, password, account_type, 
            created_at, updated_at) 
            VALUES(:pfp,
            :firstname, :middlename, :lastname,
            :email, :phone, :pass, :role,
            :created, :updated)";
            
            // TODO insert pfp for new users
            if (isset($user->profilePicture)) {
                // Extract the Base64 part and validate MIME type
                if (preg_match('/^data:(image\/\w+);base64,/', $user->profilePicture, $type)) {
                    $mimeType = $type[1]; // e.g., image/png
                    $foundPicture = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $user->profilePicture));
                } else {
                    echo json_encode(['status' => 0, 'message' => 'Invalid image format']);
                    exit;
                }
            }

            if (!isset($user->staffPass)) {
                echo json_encode(['status' => 0, 'message' => 'Password is required', 'data' => $user]);
                exit;
            }

            $hash_pass = password_hash($user->staffPass, PASSWORD_BCRYPT);
            $token = createRememberToken();
            $created_at = date('Y-m-d H:i:s');            
            $updated_at = date('Y-m-d H:i:s');

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':pfp', $foundPicture, PDO::PARAM_LOB);
            $stmt->bindParam(':firstname', $user->staffFirstName);
            $stmt->bindParam(':middlename', $user->staffMiddleName);
            $stmt->bindParam(':lastname', $user->staffLastName);
            $stmt->bindParam(':email', $user->staffEmail);
            // $stmt->bindParam(':verified', $user->verified); // TODO verification feature (one time email?)
            $stmt->bindParam(':phone', $user->staffPhone);
            $stmt->bindParam(':pass', $hash_pass); 
            $stmt->bindParam(':role', $user->staffRole);
            $stmt->bindParam(':created', $created_at); 
            $stmt->bindParam(':updated', $updated_at);

            if ($stmt->execute()) {
                try {
                    // config
                    $mailCreate = new PHPMailer(true);
                    $mailCreate->Host = MAILHOST;
                    $mailCreate->isSMTP();
                    $mailCreate->SMTPAuth = true;
                    $mailCreate->Username = USERNAME;
                    $mailCreate->Password = PASSWORD;
                    $mailCreate->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
                    $mailCreate->Port = 587;
    
                    // from, to, body
                    $mailCreate->setFrom(SEND_FROM, SEND_FROM_NAME);
                    $mailCreate->addAddress($user->staffEmail);
                    $mailCreate->addReplyTo(REPLY_TO, REPLY_TO_NAME);
                    $mailCreate->isHTML(true);
                    $mailCreate->Subject = 'Welcome to DocuQuest';
                    // TODO change localhost:3000 to real domain
                    // TODO link to component for verify email
                    // TODO apply css to body
                    $mailCreate->Body = '
                        <html>
                            <head>
                            <style>
                                ' . $css . '
                            </style>
                            </head>
                            <body> 
                                <strong>Hi '.$user->staffFirstName.', Welcome to DocuQuest!</strong>
                                <br/>
                                <p>Your account has been created.</p>
                                <em>Below are your credentials for your reference.</em>
                                <h2>Username: '.$user->staffEmail.'</h2>
                                <h2>Password: '.$user->staffPass.'</h2>       
                                <br/>
                                <strong>You are advised to change your password within 24 hours of receiving this message.</strong>
                                <br/>
                                <p>To login and activate your account,</p>
                                <p>please proceed to <a href="http://localhost:3000/login" target="_blank" title="Click here to login and activate your account.">this link</a></p>
                                <h3>This an auto-generated email. <em>Please do not reply.</em></h3>
                            </body>
                        </html>
                    ';
                    $mailCreate->AltBody = '
                        <strong>Hi '.$user->staffFirstName.', Welcome to DocuQuest!</strong>
                        <br/>
                        <p>Your account has been created.</p>
                        <em>Below are your credentials for your reference.</em>   
                        <h2>Username: '.$user->staffEmail.'</h2>
                        <h2>Password: '.$user->staffPass.'</h2>
                        <br/>
                        <strong>You are advised to change your password within 24 hours of receiving this message.</strong>
                        <br/>  
                        <p>To login and activate your account,</p>
                        <p>please proceed to <a href="http://localhost:3000/login" target="_blank" title="Click here to login and activate your account.">this link</a></p>
                        <h3>This an auto-generated email. <em>Please do not reply.</em></h3>
                    ';
    
                    if ($mailCreate->send())
                    {
                        $response = [
                            'status' => 1,
                            'message' => 'User created successfully',
                            'newUser' => [
                                'id' => $db_connection->lastInsertId(),
                                'pfp' => $foundPicture,
                                'name' => $user->staffLastName . ', ' . $user->staffFirstName . ' ' . $user->staffMiddleName,
                                'email' => $user->staffEmail,
                                'phone' => $user->staffPhone,
                                'role' => $user->staffRole,
                                'createdAt' => $created_at,
                                'updatedAt' => $updated_at,
                            ]
                        ];
                    }
                } catch (Exception $e) {
                    // Handle the error
                    $response = [
                        'status'=>0,
                        'message'=> "Email could not be sent to user. Mailer Error: {$mailCreate->ErrorInfo}",
                    ];
                }   
            } else {
                $response = ['status'=>0, 'message'=>'SORRY, Failed to create user!'];
            }
            
            echo json_encode($response);
            break;

        case 'PATCH':
            // TODO split name into 3 columns
            $user = json_decode(file_get_contents('php://input'));

            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = isset($URI_array[3]) ? intval($URI_array[3]) : null;
            
            if (!$found_id || !is_numeric($found_id)) {
                echo json_encode(['status' => 0, 'message' => 'Invalid or missing user ID']);
                exit;
            }
            
            // default response
            $response = ['status' => 0, 'message' => 'No changes made'];

            // if ($_SERVER['CONTENT_TYPE'] !== 'multipart/form-data') {
            //     echo json_encode(['status' => 0, 'message' => 'Invalid content type.']);
            //     exit;
            // }

            parse_str(file_get_contents("php://input"), $_POST);
            // FIXME these are always null
            $name = isset($_POST['staffName']) ? $_POST['staffName'] : null;
            $email = isset($_POST['staffEmail']) ? $_POST['staffEmail'] : null;
            $phone = isset($_POST['staffPhone']) ? $_POST['staffPhone'] : null;
            $role = isset($_POST['staffRole']) ? $_POST['staffRole'] : null;

            // $user->profilePicture
            $foundPicture = null;
            if (!empty($_FILES['file_pfp']['tmp_name'])) {
                $fileTempName = $_FILES['file_pfp']['tmp_name'];
                $fileMimeType = mime_content_type($fileTempName);
                
                // check if file is an image
                if (strpos($fileMimeType, 'image/') === 0) {
                    $foundPicture = file_get_contents($fileTempName);
                } else {
                    echo json_encode(['status' => 0, 'message' => 'Invalid file type. Only images are allowed.']);
                    exit;
                }
            }

            $query = "UPDATE users SET ";   
            $params = [];
            
            if ($name) {
                $query .= "name=:name, ";
                $params[':name'] = $name;
            }
            if ($email) {
                $query .= "email=:email, ";
                $params[':email'] = $email;
            }
            if ($phone) {
                $query .= "phone=:phone, ";
                $params[':phone'] = $phone;
            }
            if ($role) {
                $query .= "account_type=:role, ";
                $params[':role'] = $role;
            }
            if ($foundPicture) {
                $query .= "img_profile=:img_profile, ";
                $params[':img_profile'] = $foundPicture;
            }
            
            $query .= "updated_at=:updated WHERE id=:id";
            $params[':updated'] = date('Y-m-d H:i:s');
            $params[':id'] = $found_id;
            
            try {
              $stmt = $db_connection->prepare($query);

                if ($stmt->execute($params)) {
                  $response = [
                  'status' => 1,
                  'message' => 'User updated',
                  'data_recieved' => [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                    'foundPicture' => $foundPicture
                  ]];
                  
                  // update user successful
                  $stmt = $db_connection->prepare("SELECT * FROM users WHERE id=:id");
                  $stmt->bindParam(':id', $found_id);
                  $stmt->execute();
                  $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

                  $response['data'] = $updatedUser;
                  //$response = ['status' => 1, 'message' => 'User updated', 'data' => $updatedUser];
                } else {
                  // update user failed
                  $response = ['status' => 0, 'message' => 'Update user failed!'];
                }
            } catch (PDOException $e) {
                $response = ['status' => 0, 'message' => 'Database ERROR: ' . $e->getMessage()];
            }
            
            echo json_encode($response);
            break;
            
        case 'DELETE':
            $found_id = isset($_GET['id']) ? $_GET['id'] : null;
            error_log("Received ID for deactivation: " . $found_id); // Log the received ID
            
            if (!$found_id || !is_numeric($found_id)) {
                error_log("Invalid or missing ID");
                echo json_encode(['status' => 0, 'message' => 'Invalid or missing ID']);
                exit;
            }

            try {
                $qy = "UPDATE users SET status = 'deactivated', updated_at = NOW() WHERE id = :id";
                $stmt = $db_connection->prepare($qy);
                $stmt->bindParam(':id', $found_id);
        
                if ($stmt->execute()) {
                    echo json_encode(['status' => 1, 'message' => 'User deactivated successfully']);
                } else {
                    error_log("Deactivate user error: " . json_encode($stmt->errorInfo())); // Log detailed error info
                    echo json_encode(['status' => 0, 'message' => 'Failed to deactivate user']);
                }
            } catch (Exception $e) {
                error_log("Exception during deactivate user: " . $e->getMessage());
                echo json_encode(['status' => 0, 'message' => 'Internal server error during delete']);
            }

            exit;   
    }
?>