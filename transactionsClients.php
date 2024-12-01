<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, PATCH, DELETE");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_id_client = isset($URI_array[3]) ? $URI_array[3] : null;

        $qy = "SELECT * 
            TCN.id TCN_id,
            TCN.reference_number TCN_reference_number,
            TCN.id_doc TCN_id_doc,
            TCN.name_req TCN_name_req,
            TCN.phone_req TCN_phone_req,
            TCN.email_req TCN_email_req,
            TCN.id_swu TCN_id_swu,
            TCN.id_owner TCN_id_owner,
            TCN.name_owner TCN_name_owner,
            TCN.phone_owner TCN_phone_owner,
            TCN.course TCN_course,
            TCN.catg_req TCN_catg_req,
            TCN.purpose_req TCN_purpose_req,
            TCN.desc_req TCN_desc_req,
            TCN.filepath_receipt TCN_filepath_receipt,
            TCN.statusPayment TCN_statusPayment,
            TCN.statusTransit TCN_statusTransit,
            TCN.id_employee TCN_id_employee,
            TCN.overdue_days TCN_overdue_days,
            TCN.created_at TCN_created_at,
            TCN.updated_at TCN_updated_at,
            C.id C_id,
            C.email C_name,
            C.email C_email,
            C.phone C_phone
            FROM `transactions` AS TCN
            INNER JOIN `clients` AS C
        FROM `transactions`
        ";

        if (isset($found_id_client) && is_numeric($found_id_client)) {
            $qy .= " WHERE TCN.id_owner = :id";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':id', $found_id_client, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db_connection->prepare($qy);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($data);
        break;


    // TODO patch
    case 'PATCH':
        // modify some request details here
         
        break;

    
    case 'DELETE':
        // delete request here
         
        break;        
}

?>