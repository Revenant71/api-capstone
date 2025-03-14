<?php
require_once('connectDb.php');

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST ");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':


        break;

    case 'POST':
        $file = $_FILES['file_receipt'] ?? null; // Access the uploaded file
        $refNumber = $_POST['refNumber'] ?? null;
        $trackingName = $_POST['trackingName'] ?? null;

        if (!$refNumber) {
            echo json_encode(['success' => false, 'message' => 'Reference number is required.']);
            exit;
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !in_array($file['type'], $allowedTypes)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid file upload. Only JPEG, PNG, and PDF images are allowed.',
            ]);
            exit;
        }

        // Limit to 20MB
        // if ($file['size'] > 20 * 1024 * 1024) { 
        //     echo json_encode(['success' => false, 'message' => 'File is too large.']);
        //     exit;
        // }
        
        // Determine the correct prefix based on MIME type
        $prefix = match ($file['type']) {
            'image/jpeg' => 'data:image/jpeg;base64,',
            'image/png' => 'data:image/png;base64,',
            // TODO test if application/pdf to png works 
            'application/pdf' => 'data:image/png;base64,',
            default => '',
        };

        if (!$prefix) {
            echo json_encode(['success' => false, 'message' => 'Unsupported file type.']);
            exit;
        }

        if ($file['error'] === UPLOAD_ERR_OK) {
          // Read the file content as binary
          $fileContent = file_get_contents($file['tmp_name']);
          $base64EncodedFile = $prefix . base64_encode($fileContent);

          $qy = "UPDATE transactions SET file_receipt = :fileReceipt WHERE reference_number = :refNumber AND lastname_owner = :trackingName";

          try {
            $stmt = $db_connection->prepare($qy);
            // $stmt->bindParam(':fileReceipt', $fileContent, PDO::PARAM_LOB);
            $stmt->bindParam(':fileReceipt', $base64EncodedFile, PDO::PARAM_STR); // include prefix in the string
            $stmt->bindParam(':refNumber', $refNumber, PDO::PARAM_STR);
            $stmt->bindParam(':trackingName', $trackingName, PDO::PARAM_STR);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'File uploaded and stored successfully.']);
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