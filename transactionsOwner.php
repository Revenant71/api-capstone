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
    case 'GET':
        $parsed_url = parse_url($_SERVER['REQUEST_URI']); // Parse the URL
        $path = $parsed_url['path']; // Extract the path part
        $URI_array = explode('/', $path); // Split the path into parts
        $found_reference_no = isset($URI_array[3]) ? $URI_array[3] : null;
    
        // Retrieve trackingName from query parameters
        $found_lastname = isset($_GET['trackingName']) ? $_GET['trackingName'] : null;
    
        if ($found_reference_no && $found_lastname) {
            // AND TCN.statusPayment = 'Not Paid';
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
                    // FIXME foreach not working
                    foreach ($data as &$row) {
                        if (isset($row['file_receipt'])) {
                            // Remove any incorrect or extra prefix if it exists
                            $row['file_receipt'] = preg_replace('/^(dataimage\/jpegbase64,|data:image\/jpeg;base64,)/', '', $row['file_receipt']);
                    
                            // Ensure the value is properly base64 encoded
                            $decoded = base64_decode($row['file_receipt'], true);
                    
                            if ($decoded !== false) {
                                // Re-encode and prepend the correct prefix
                                $row['file_receipt'] = 'data:image/jpeg;base64,' . base64_encode($decoded);
                            } else {
                                // If decoding fails, encode the raw value
                                $row['file_receipt'] = 'data:image/jpeg;base64,' . base64_encode($row['file_receipt']);
                            }
                        }
                    }
                    
                    
                    // foreach ($data as &$row) {
                    //     if (isset($row['file_receipt'])) {
                    //         $row['file_receipt'] = base64_encode($row['file_receipt']);
                    //     }
                    // }
                    
                    echo json_encode($data);
                } else {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'No matching transactions found.',
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
                    // Convert longblob to Base64
                    foreach ($data as &$row) {
                        if (isset($row['file_receipt'])) {
                            // Check if the prefix is incorrect
                            if (str_starts_with($row['file_receipt'], 'dataimage/jpegbase64')) {
                                // Replace the incorrect prefix with the correct one
                                $row['file_receipt'] = str_replace('dataimage/jpegbase64', 'data:image/jpeg;base64,', $row['file_receipt']);
                            } elseif (!str_starts_with($row['file_receipt'], 'data:image/jpeg;base64,')) {
                                // If no prefix or incorrect prefix, encode and add the correct prefix
                                $row['file_receipt'] = 'data:image/jpeg;base64,' . base64_encode($row['file_receipt']);
                            }
                        }
                    }
                    
                    

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