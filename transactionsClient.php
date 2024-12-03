<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_id_client = $URI_array[3];

        $qy = "
            SELECT 
            TCN.id AS TCN_id,
            TCN.reference_number AS TCN_reference_number,
            TCN.id_doc AS TCN_id_doc,
            TCN.name_req AS TCN_name_req,
            TCN.phone_req AS TCN_phone_req,
            TCN.email_req AS TCN_email_req,
            TCN.id_swu AS TCN_id_swu,
            TCN.id_owner AS TCN_id_owner,
            TCN.name_owner AS TCN_name_owner,
            TCN.phone_owner AS TCN_phone_owner,
            TCN.course AS TCN_course,
            TCN.catg_req AS TCN_catg_req,
            TCN.purpose_req AS TCN_purpose_req,
            TCN.desc_req AS TCN_desc_req,
            TCN.filepath_receipt AS TCN_filepath_receipt,
            TCN.statusPayment AS TCN_statusPayment,
            TCN.statusTransit AS TCN_statusTransit,
            TCN.id_employee AS TCN_id_employee,
            TCN.overdue_days AS TCN_overdue_days,
            TCN.created_at AS TCN_created_at,
            TCN.updated_at AS TCN_updated_at,
            C.id AS C_id,
            C.email AS C_name,
            C.email AS C_email,
            C.phone AS C_phone
            FROM `transactions` AS TCN
            INNER JOIN `clients` AS C ON TCN.id_owner = C.id
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


    // TODO patch for attach receipt
    case 'PATCH':
        // modify some request details here
         
        break;

    
    case 'DELETE':
        // delete request here
         
        break;        
}

?>