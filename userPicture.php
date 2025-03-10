<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS ");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method){
    // case 'GET':
        

    //     break; 

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);

        $userId = $data['userId'] ?? null;
        $file = $data['file_pfp'] ?? null; // Access the uploaded file

        if (!$userId || !$file) {
            echo json_encode(['success' => false, 'message' => 'User ID and file are required.']);
            exit;
        }

        if (!preg_match('/^data:image\/(jpeg|jpg);base64,/', $file, $matches)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image format. Only JPEG images are allowed.']);
            exit;
        }


        $mimeType = $matches[1];
        $allowedTypes = ['jpeg', 'jpg'];
        if (!in_array($mimeType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG images are allowed.']);
            exit;
        }

        // Limit to 15 MB
        // if ($file['size'] > 15 * 1024 * 1024) { 
        //     echo json_encode(['success' => false, 'message' => 'File is too large.']);
        //     exit;
        // }

        $prefix = match ($mimeType) {
            'jpeg', 'jpg' => 'data:image/jpeg;base64,',
            default => '',
        };

        if (!$prefix) {
            echo json_encode(['success' => false, 'message' => 'Unsupported file type.']);
            exit;
        }

        $qy = "UPDATE users SET img_profile = :filePfp WHERE id = :id";

        try {
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':filePfp', $file, PDO::PARAM_STR); // include prefix in the string
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'File uploaded and stored successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file to the database.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break; 
}
?>