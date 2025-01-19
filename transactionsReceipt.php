<?php
require_once('connectDb.php');

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PATCH");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

// TODO
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':


        break;

    case 'PATCH':
        $file = $_FILES['file_receipt']; // Access the uploaded file
        $refNumber = $_POST['refNumber'];
        $trackingName = $_POST['trackingName'];

        if (!$refNumber) {
            echo json_encode(['success' => false, 'message' => 'Reference number is required.']);
            exit;
        }

        $allowedTypes = ['image/jpeg', 'image/png'];
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG and PNG are allowed.']);
            exit;
        }

        // Limit to 10MB
        // if ($file['size'] > 10 * 1024 * 1024) { 
        //     echo json_encode(['success' => false, 'message' => 'File is too large.']);
        //     exit;
        // }
        
        if ($file['error'] === UPLOAD_ERR_OK) {
          // Read the file content as binary
          $fileContent = file_get_contents($file['tmp_name']);
            
          $qy = "UPDATE transactions SET file_receipt = :fileReceipt WHERE reference_number = :refNumber AND lastname_owner = :trackingName";

          try {
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':fileReceipt', $fileContent, PDO::PARAM_LOB);
            $stmt->bindParam(':refNumber', $refNumber, PDO::PARAM_STR);
            $stmt->bindParam(':trackingName', $trackingName, PDO::PARAM_STR);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'File uploaded successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file to the database.']);
            }
          } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
          }
        } else {
            echo json_encode(['success' => false, 'message' => 'File upload failed.']);
        }
        break;
}
?>