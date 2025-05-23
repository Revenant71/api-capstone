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
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, PUT");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 
$css = file_get_contents('http://localhost/api_drts/cssEmailRecover.php');


function getCourseNames($courseIds, $db_connection) {
    if (empty($courseIds)) {
        return [];
    }

    // Convert array of course IDs to a comma-separated string for the SQL query
    $courseIdsString = implode(',', $courseIds);

    // Query the courses table to get course names
    $query = "SELECT id, course_name FROM courses WHERE id IN ($courseIdsString)";
    $stmt = $db_connection->prepare($query);
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Fetch as id => course_name

    // Map course IDs to course names
    $courseNames = [];
    foreach ($courseIds as $courseId) {
        if (isset($courses[$courseId])) {
            $courseNames[] = $courses[$courseId];
        }
    }

    return $courseNames;
}

// for "remember me" setting
function createRememberToken($length = 50) { // default is 50 bytes
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

            if ($data) {
                // Convert user_courses to an array
                if (isset($data['user_courses'])) {
                    $courseIds = explode(',', $data['user_courses']);
                    $data['user_courses'] = getCourseNames($courseIds, $db_connection);
                }
            }
        } else {
            $stmt = $db_connection->prepare($qy);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($data) {
                foreach ($data as &$row) {
                    if (isset($row['user_courses'])) {
                        $courseIds = explode(',', $row['user_courses']); //instead of data it should be row
                        $row['user_courses'] = getCourseNames($courseIds, $db_connection);
                    }
                }
            }
        }

        echo json_encode($data);
        break;

    // For Registrar to create new staff accounts
    case 'POST':
        $user = json_decode(file_get_contents('php://input'));

        $qy = "INSERT INTO users(
            firstname, middlename, lastname,
            email, phone, password, account_type, 
            created_at, updated_at, user_courses) 
            VALUES(:firstname, :middlename, :lastname,
            :email, :phone, :pass, :role,
            :created, :updated, :user_courses)";

        if (!isset($user->staffPass)) {
            echo json_encode(['status' => 0, 'message' => 'Password is required', 'data' => $user]);
            exit;
        }

        $hash_pass = password_hash($user->staffPass, PASSWORD_BCRYPT);
        $token = createRememberToken();
        $created_at = date('Y-m-d H:i:s');            
        $updated_at = date('Y-m-d H:i:s');

        // Convert selected courses to a comma-separated string
        $user_courses = isset($user->courses) ? implode(',', $user->courses) : '';

        $stmt = $db_connection->prepare($qy);
        $stmt->bindParam(':firstname', $user->staffFirstName);
        $stmt->bindParam(':middlename', $user->staffMiddleName);
        $stmt->bindParam(':lastname', $user->staffLastName);
        $stmt->bindParam(':email', $user->staffEmail);
        $stmt->bindParam(':phone', $user->staffPhone);
        $stmt->bindParam(':pass', $hash_pass); 
        $stmt->bindParam(':role', $user->staffRole);
        $stmt->bindParam(':created', $created_at); 
        $stmt->bindParam(':updated', $updated_at);
        $stmt->bindParam(':user_courses', $user_courses);

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

                if ($mailCreate->send()) {
                    $response = [
                        'status' => 1,
                        'message' => 'User created successfully',
                        'newUser' => [
                            'id' => $db_connection->lastInsertId(),
                            'name' => $user->staffLastName . ', ' . $user->staffFirstName . ' ' . $user->staffMiddleName,
                            'email' => $user->staffEmail,
                            'phone' => $user->staffPhone,
                            'role' => $user->staffRole,
                            'createdAt' => $created_at,
                            'updatedAt' => $updated_at,
                            'user_courses' => $user_courses ? explode(',', $user_courses) : [],
                        ]
                    ];
                }
            } catch (Exception $e) {
                // Handle the error
                $response = [
                    'status' => 0,
                    'message' => "Email could not be sent to user. Mailer Error: {$mailCreate->ErrorInfo}",
                ];
            }   
        } else {
            $response = ['status' => 0, 'message' => 'SORRY, Failed to create user!'];
        }

        echo json_encode($response);
        break;

    case 'PATCH':
        $user = json_decode(file_get_contents('php://input'));

        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_id = isset($URI_array[3]) ? intval($URI_array[3]) : null;

        if (!$found_id || !is_numeric($found_id)) {
            echo json_encode(['status' => 0, 'message' => 'Invalid or missing user ID']);
            exit;
        }

        // default response
        $response = ['status' => 0, 'message' => 'No changes made'];

        $query = "UPDATE users SET ";   
        $params = [];

        if (isset($user->userFirstName)) {
            $query .= "firstname=:firstname, ";
            $params[':firstname'] = $user->userFirstName;
        }
        if (isset($user->userMiddleName)) {
            $query .= "middlename=:middlename, ";
            $params[':middlename'] = $user->userMiddleName;
        }
        if (isset($user->userLastName)) {
            $query .= "lastname=:lastname, ";
            $params[':lastname'] = $user->userLastName;
        }
        if (isset($user->userEmail)) {
            $query .= "email=:email, ";
            $params[':email'] = $user->userEmail;
        }
        if (isset($user->userPhone)) {
            $query .= "phone=:phone, ";
            $params[':phone'] = $user->userPhone;
        }
        if (isset($user->user_courses)) {
            $query .= "user_courses=:user_courses, ";
            $params[':user_courses'] = implode(',', $user->user_courses);
        }

        $query .= "updated_at=:updated, ";
        $params[':updated'] = date('Y-m-d H:i:s');

        if (!empty($params)) {
            $query = rtrim($query, ", ") . " WHERE id=:id";
            $params[':id'] = $found_id;
        } else {
            echo json_encode(['status' => 0, 'message' => 'No valid fields to update']);
            exit;
        }

        try {
            $stmt = $db_connection->prepare($query);

            if ($stmt->execute($params)) {
                if ($stmt->rowCount() > 0) {
                    // Fetch updated user
                    $stmt = $db_connection->prepare("SELECT * FROM users WHERE id=:id");
                    $stmt->bindParam(':id', $found_id);
                    $stmt->execute();
                    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Convert user_courses to an array
                    if (isset($updatedUser['user_courses'])) {
                        $updatedUser['user_courses'] = explode(',', $updatedUser['user_courses']);
                    }

                    $response = ['status' => 1, 'message' => 'User updated', 'data' => $updatedUser];
                } else {
                    // No actual changes were made
                    $response = ['status' => 0, 'message' => 'No changes were made'];
                }
            } else {
                // update user failed
                $response = ['status' => 0, 'message' => 'Update user failed! No changes were made'];
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

    case 'PUT':
        // Get raw input and decode JSON
        $input = file_get_contents('php://input');
        $user = json_decode($input);
            
        // Validate JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['status' => 0, 'message' => 'Invalid JSON format']);
            exit;
        }
            
        // Extract ID from URL
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $found_id = end($segments);
            
        // Validate ID
        if (!is_numeric($found_id)) {
            http_response_code(400);
            echo json_encode(['status' => 0, 'message' => 'Invalid user ID format']);
            exit;
        }
        $found_id = (int)$found_id;
            
        // Validate courses
        if (!isset($user->courses) || !is_array($user->courses)) {
            http_response_code(400);
            echo json_encode(['status' => 0, 'message' => 'Courses array is required']);
            exit;
        }
            
        // Process course IDs
        $validCourses = array_map('intval', $user->courses);
            
        try {
            $stmt = $db_connection->prepare(
                "UPDATE users SET user_courses = :courses, updated_at = NOW() WHERE id = :id"
            );

            $stmt->execute([
                ':courses' => implode(',', $validCourses),
                ':id' => $found_id
            ]);
                
            echo json_encode([
                'status' => 1,
                'message' => 'Courses updated successfully',
                'user_id' => $found_id,
                'courses' => $validCourses
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 0,
                'message' => 'Database error',
                'error' => $e->getMessage()
            ]);
        }
        
        break;
}
?>