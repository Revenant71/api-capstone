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
        $found_id = isset($URI_array[3]) ? $URI_array[3] : null;

        if (isset($found_id) && is_numeric($found_id)) {
            $qy = "
            SELECT * 
            FROM `transactions` AS TCN
            LEFT JOIN `documents` AS DOC
            ON TCN.id_doc = DOC.id
            WHERE TCN.id = :id OR DOC.id = :id
            
            UNION
            
            SELECT * 
            FROM `transactions` AS TCN
            RIGHT JOIN `documents` AS DOC
            ON TCN.id_doc = DOC.id
            WHERE TCN.id = :id OR DOC.id = :id
            ";

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':id', $found_id, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $qy = "
            SELECT * 
            FROM `transactions` AS TCN
            LEFT JOIN `documents` AS DOC
            ON TCN.id_doc = DOC.id

            UNION

            SELECT * 
            FROM `transactions` AS TCN
            RIGHT JOIN `documents` AS DOC
            ON TCN.id_doc = DOC.id
            ";

            $stmt = $db_connection->prepare($qy);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($data);
        break;

    case 'POST':
        $transaction = json_decode(file_get_contents('php://input'));

        // TODO catg_req column
        $qy = "INSERT INTO transactions(
            id_doc, reference_number, email_req, id_swu, name_owner, course, 
            purpose_req, desc_req, filepath_receipt, 
            statusPayment, statusTransit, id_employee, 
            created_at, updated_at
        ) VALUES (
            :id_doc, :ref_number, :email_req, :id_swu, :name_owner, :course, 
            :purpose_req, :desc_req, :filepath_receipt, 
            :status_payment, :status_transit, :id_employee, 
            :created_at, :updated_at
        )";

        $stmt = $db_connection->prepare($qy);

        $status_payment = "Not Paid";
        $status_transit = "Request Placed";
        $created_at = date('Y-m-d H:i:s');
        $updated_at = date('Y-m-d H:i:s');
        $emptypath = null; // in a new request no document and no receipt are attached yet

        $stmt->bindParam(':id_doc', $emptypath, PDO::PARAM_NULL);
        $stmt->bindParam(':ref_number', $transaction->referenceNumber, PDO::PARAM_STR);
        $stmt->bindParam(':email_req', $transaction->email, PDO::PARAM_STR);
        $stmt->bindParam(':id_swu', $transaction->id_swu, PDO::PARAM_INT);
        $stmt->bindParam(':name_owner', $transaction->name_owner, PDO::PARAM_STR);
        $stmt->bindParam(':course', $transaction->course, PDO::PARAM_STR);
        // $stmt->bindParam(':catg_req', $transaction->type_document, PDO::PARAM_STR);
        $stmt->bindParam(':purpose_req', $transaction->purpose_req, PDO::PARAM_STR);
        $stmt->bindParam(':desc_req', $transaction->desc_req, PDO::PARAM_STR);
        $stmt->bindParam(':filepath_receipt', $emptypath, PDO::PARAM_NULL);
        $stmt->bindParam(':status_payment', $status_payment, PDO::PARAM_STR);
        $stmt->bindParam(':status_transit', $status_transit, PDO::PARAM_STR);
        $stmt->bindParam(':id_employee', $transaction->id_employee, PDO::PARAM_INT);
        $stmt->bindParam(':created_at', $created_at, PDO::PARAM_STR);
        $stmt->bindParam(':updated_at', $updated_at, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $response = ['status' => 1, 'message' => "POST successful."];
        } else {
            $response = ['status' => 0, 'message' => "POST failed."];
        }

        echo json_encode($response);
        break;

    case 'PATCH':
        $transaction = json_decode(file_get_contents('php://input'));

        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_id = isset($URI_array[3]) ? $URI_array[3] : null;

        $qy = "UPDATE transactions AS TCN
        LEFT JOIN documents AS DOC
        ON TCN.id_doc = DOC.id
        SET 
            TCN.id_doc = :id_doc, 
            TCN.email_req = :email_req, 
            TCN.id_swu = :id_swu, 
            TCN.name_owner = :name_owner, 
            TCN.course = :course,
            -- TCN.catg_req = :catg_req,
            TCN.purpose_req = :purpose_req, 
            TCN.desc_req = :desc_req, 
            TCN.filepath_receipt = :filepath_receipt, 
            TCN.statusPayment = :status_payment, 
            TCN.statusTransit = :status_transit, 
            TCN.id_employee = :id_employee, 
            TCN.updated_at = :updated_at
        WHERE TCN.id = :id OR DOC.id = :id";

        $stmt = $db_connection->prepare($qy);

        $updated_at = date('Y-m-d H:i:s');

        $stmt->bindParam(':id', $found_id, PDO::PARAM_INT); 
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
        $found_id = isset($URI_array[3]) ? $URI_array[3] : null;

        $qy = "DELETE FROM transactions WHERE id = :id";

        $stmt = $db_connection->prepare($qy);
        $stmt->bindParam(':id', $found_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $response = ['status' => 1, 'message' => 'DELETE successful.'];
        } else {
            $response = ['status' => 0, 'message' => 'DELETE failed.'];
        }

        echo json_encode($response);
        break;
}
?>