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
        $found_reference_no = $URI_array[3];

        if (isset($found_reference_no) && is_numeric($found_reference_no)) {
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
            DOC.title AS DOC_title,
            DOC.author AS DOC_author
            FROM `transactions` AS TCN
            LEFT JOIN `documents` AS DOC
            ON TCN.id_doc = DOC.id
            WHERE TCN.reference_number = :reference
            
            UNION
            
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
            TCN.purpose_req TCN_purpose_req,
            TCN.desc_req AS TCN_desc_req,
            TCN.filepath_receipt AS TCN_filepath_receipt,
            TCN.statusPayment AS TCN_statusPayment,
            TCN.statusTransit AS TCN_statusTransit,
            TCN.id_employee AS TCN_id_employee,
            TCN.overdue_days AS TCN_overdue_days,
            TCN.created_at AS TCN_created_at,
            TCN.updated_at AS TCN_updated_at,
            DOC.title AS DOC_title,
            DOC.author AS DOC_author
            FROM `transactions` AS TCN
            RIGHT JOIN `documents` AS DOC
            ON TCN.id_doc = DOC.id
            WHERE TCN.reference_number = :reference;
            ";

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
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
            DOC.title AS DOC_title,
            DOC.author AS DOC_author        
            FROM `transactions` AS TCN
            LEFT JOIN `documents` AS DOC
            ON TCN.id_doc = DOC.id

            UNION

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
            DOC.title DOC_title,
            DOC.author DOC_author        
            FROM `transactions` AS TCN
            RIGHT JOIN `documents` AS DOC
            ON TCN.id_doc = DOC.id;
            ";

            $stmt = $db_connection->prepare($qy);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($data);
        break;

    case 'POST':
        $transaction = json_decode(file_get_contents('php://input'));

        $qy = "INSERT INTO transactions (
            reference_number, id_doc, name_req, phone_req, email_req,
            id_swu, id_owner, name_owner, phone_owner, course,
            catg_req, purpose_req, desc_req, filepath_receipt,
            statusPayment, statusTransit, id_employee, overdue_days, 
            created_at, updated_at
        ) VALUES (
            :reference_number, :id_doc, :name_req, :phone_req, :email_req,
            :id_swu, :id_owner, :name_owner, :phone_owner, :course,
            :catg_req, :purpose_req, :desc_req, :filepath_receipt,
            :statusPayment, :statusTransit, :id_employee, :overdue_days,
            :created_at, :updated_at
        )";
    
        $stmt = $db_connection->prepare($qy);
    
        $statusPayment = 'Not Paid';
        $statusTransit = 'Request Placed';
        $created_at = date('Y-m-d H:i:s');
        $updated_at = date('Y-m-d H:i:s');
        $overdue_days = 0;
        $emptypath = null;
        
        $stmt->bindParam(':reference_number', $transaction->reference_number);
        $stmt->bindParam(':id_doc', $emptypath, PDO::PARAM_NULL);
        $stmt->bindParam(':name_req', $transaction->name_req);
        $stmt->bindParam(':phone_req', $transaction->phone_req);
        $stmt->bindParam(':email_req', $transaction->email_req);
        $stmt->bindParam(':id_swu', $transaction->id_swu);
        $stmt->bindParam(':id_owner', $emptypath, PDO::PARAM_NULL);
        $stmt->bindParam(':name_owner', $transaction->name_owner);
        $stmt->bindParam(':phone_owner', $transaction->phone_owner);
        $stmt->bindParam(':course', $transaction->course);
        $stmt->bindParam(':catg_req', $transaction->catg_req);
        $stmt->bindParam(':purpose_req', $transaction->purpose_req);
        $stmt->bindParam(':desc_req', $transaction->desc_req);
        $stmt->bindParam(':filepath_receipt', $emptypath, PDO::PARAM_NULL);
        $stmt->bindParam(':statusPayment', $statusPayment);
        $stmt->bindParam(':statusTransit', $statusTransit);
        $stmt->bindParam(':id_employee', $emptypath, PDO::PARAM_NULL);
        $stmt->bindParam(':overdue_days', $overdue_days, PDO::PARAM_INT);
        $stmt->bindParam(':created_at', $created_at);
        $stmt->bindParam(':updated_at', $updated_at);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Transaction created successfully"]);
        } else {
            echo json_encode(["message" => "Failed to create transaction"]);
        }

        break;
    
    // TODO include request category
    case 'PATCH':
        $transaction = json_decode(file_get_contents('php://input'));

        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3];

        $qy = "UPDATE transactions 
        SET 
            id_doc = :id_doc, 
            email_req = :email_req, 
            id_swu = :id_swu, 
            name_owner = :name_owner, 
            course = :course, 
            -- catg_req = :catg_req, 
            purpose_req = :purpose_req, 
            desc_req = :desc_req, 
            filepath_receipt = :filepath_receipt, 
            statusPayment = :status_payment, 
            statusTransit = :status_transit, 
            id_employee = :id_employee, 
            updated_at = :updated_at
        WHERE reference_number = :reference";

        $stmt = $db_connection->prepare($qy);

        $updated_at = date('Y-m-d H:i:s');

        $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_INT); 
        $stmt->bindParam(':id_doc', $transaction->id_doc, PDO::PARAM_INT); // TODO refer to employee/admin CRUD
        $stmt->bindParam(':email_req', $transaction->email, PDO::PARAM_STR);
        $stmt->bindParam(':id_swu', $transaction->id_swu, PDO::PARAM_INT);
        $stmt->bindParam(':name_owner', $transaction->name_owner, PDO::PARAM_STR);
        $stmt->bindParam(':course', $transaction->course, PDO::PARAM_STR);
        // $stmt->bindParam(':catg_req', $transaction->type_document, PDO::PARAM_STR);
        $stmt->bindParam(':purpose_req', $transaction->purpose, PDO::PARAM_STR);
        $stmt->bindParam(':desc_req', $transaction->desc_req, PDO::PARAM_STR);
        $stmt->bindParam(':filepath_receipt', $transaction->filepath_receipt, PDO::PARAM_STR); // TODO refer to upload receipt component
        $stmt->bindParam(':status_payment', $transaction->statusPayment, PDO::PARAM_STR);
        $stmt->bindParam(':status_transit', $transaction->statusTransit, PDO::PARAM_STR);
        $stmt->bindParam(':id_employee', $transaction->id_employee, PDO::PARAM_INT);
        $stmt->bindParam(':updated_at', $updated_at, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $response = ['status' => 1, 'message' => "PATCH successful."];
        } else {
            $response = ['status' => 0, 'message' => "PATCH failed."];
        }

        echo json_encode($response);
        break;

    case 'DELETE':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3];

        $qy = "
        DELETE 
        FROM transactions AS TCN
        WHERE TCN.reference_number = :reference
        ";

        $stmt = $db_connection->prepare($qy);
        $stmt->bindParam(':reference', $found_reference_no);

        if ($stmt->execute()) {
            $response = ['status' => 1, 'message' => 'DELETE successful.'];
        } else {
            $response = ['status' => 0, 'message' => 'DELETE failed.'];
        }

        echo json_encode($response);
        break;
}
?>