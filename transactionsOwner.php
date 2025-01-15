<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method){
    case 'GET': // FIX: Parse the URI to separate query parameters
        $parsed_url = parse_url($_SERVER['REQUEST_URI']); // Parse the URL
        $path = $parsed_url['path']; // Extract the path part
        $URI_array = explode('/', $path); // Split the path into parts
        $found_reference_no = isset($URI_array[3]) ? $URI_array[3] : null;
    
        // Retrieve trackingName from query parameters
        $found_lastname = isset($_GET['trackingName']) ? $_GET['trackingName'] : null;
    
        if ($found_reference_no && $found_lastname) {
            $qy = "
            SELECT 
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author,
                DOC.category_id AS DOC_category_id
            FROM transactions TCN
            LEFT JOIN documents DOC ON TCN.id_doc = DOC.id
            WHERE TCN.reference_number = :reference AND TCN.lastname_owner = :lastname_owner;
            ";
    
            try {
                $stmt = $db_connection->prepare($qy);
                $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_STR);
                $stmt->bindParam(':lastname_owner', $found_lastname, PDO::PARAM_STR);
                $stmt->execute();
    
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
                if ($data) {
                    // Return data for given reference number and last name
                    echo json_encode($data);
                } else {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'No matching transaction found.',
                        'refnumber' => $found_reference_no,
                        'lastname' => $found_lastname
                    ]);
                }
            } catch (PDOException $e) {
                echo json_encode(['status' => 0, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 0, 'message' => 'Invalid or missing tracking data.']);
        }
        break;
    

    case 'POST':
        $transaction = json_decode(file_get_contents('php://input'));

        if (isset($transaction->trackingNumber) && isset($transaction->trackingName)) {
            $qy = "
            SELECT 
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author,
                DOC.category_id AS DOC_category_id
            FROM transactions TCN
            LEFT JOIN documents DOC ON TCN.id_doc = DOC.id
            WHERE TCN.lastname_owner LIKE :lastname_owner AND TCN.reference_number LIKE :reference_number
            LIMIT 1;
            ";
            
            // Add wildcards
            $found_owner = '%' . $transaction->trackingName . '%';
            $found_reference_no = '%' . $transaction->trackingNumber . '%';
            
            try {        
                $stmt = $db_connection->prepare($qy);
                $stmt->bindParam(':reference_number', $found_reference_no, PDO::PARAM_STR);
                $stmt->bindParam(':lastname_owner', $found_owner, PDO::PARAM_STR);
                $stmt->execute();
                
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
                if ($data) {
                    echo json_encode($data); // Return found data
                } else {
                    echo json_encode(['status' => 0, 'message' => 'No matching transaction found.']);
                }
            } catch (PDOException $e) {
                echo json_encode(['status' => 0, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 0, 'message' => 'Invalid input: trackingNumber and trackingName are required.']);
        }
        break;
}
?>