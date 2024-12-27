<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method){
    case 'POST':
        $transaction = json_decode(file_get_contents('php://input'));

        $qy = "
        SELECT 
            TCN.*, 
            DOC.title AS DOC_title, 
            DOC.author AS DOC_author,
            DOC.category_id AS DOC_category_id
        FROM transactions TCN
        LEFT JOIN documents DOC ON TCN.id_doc = DOC.id
        WHERE TCN.name_last_owner LIKE :lastname_owner OR TCN.reference_number LIKE :reference_number
        UNION
        SELECT 
            TCN.*, 
            DOC.title AS DOC_title, 
            DOC.author AS DOC_author,
            DOC.category_id AS DOC_category_id
        FROM transactions TCN
        RIGHT JOIN documents DOC ON TCN.id_doc = DOC.id
        WHERE TCN.name_last_owner LIKE :lastname_owner OR TCN.reference_number LIKE :reference_number
        LIMIT 1;
        ";
        
        // Add wildcards
        $found_owner = '%' . $transaction->trackingName . '%';
        $found_reference_no = '%' . $transaction->trackingNumber . '%';

        $stmt = $db_connection->prepare($qy);
        $stmt->bindParam(':reference_number', $found_reference_no, PDO::PARAM_STR);
        $stmt->bindParam(':lastname_owner', $found_owner, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode($data);
        exit;
}
?>