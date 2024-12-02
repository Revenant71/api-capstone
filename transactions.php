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
        $found_reference_no = $URI_array[3] ?? null;

        if (isset($found_reference_no) && is_numeric($found_reference_no)) {
            $qy = "
            SELECT 
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author
            FROM transactions TCN
            LEFT JOIN documents DOC ON TCN.id_doc = DOC.id
            WHERE TCN.reference_number = :reference
            UNION
            SELECT 
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author
            FROM transactions TCN
            RIGHT JOIN documents DOC ON TCN.id_doc = DOC.id
            WHERE TCN.reference_number = :reference;
            ";

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':reference', $found_reference_no, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $qy = "
            SELECT 
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author
            FROM transactions TCN
            LEFT JOIN documents DOC ON TCN.id_doc = DOC.id
            UNION
            SELECT 
                TCN.*, 
                DOC.title AS DOC_title, 
                DOC.author AS DOC_author
            FROM transactions TCN
            RIGHT JOIN documents DOC ON TCN.id_doc = DOC.id;
            ";

            $stmt = $db_connection->prepare($qy);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($data);
        break;

    case 'POST':
        $transaction = json_decode(file_get_contents('php://input'), true);

        $qy = "
        INSERT INTO transactions (
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

        $default_values = [
            ':id_doc' => null,
            ':id_owner' => null,
            ':filepath_receipt' => null,
            ':id_employee' => null,
            ':overdue_days' => 0,
            ':statusPayment' => 'Not Paid',
            ':statusTransit' => 'Request Placed',
            ':created_at' => date('Y-m-d H:i:s'),
            ':updated_at' => date('Y-m-d H:i:s'),
        ];

        $stmt->execute(array_merge($default_values, [
            ':reference_number' => $transaction['reference_number'],
            ':name_req' => $transaction['name_req'],
            ':phone_req' => $transaction['phone_req'],
            ':email_req' => $transaction['email_req'],
            ':id_swu' => $transaction['id_swu'],
            ':name_owner' => $transaction['name_owner'],
            ':phone_owner' => $transaction['phone_owner'],
            ':course' => $transaction['course'],
            ':catg_req' => $transaction['catg_req'],
            ':purpose_req' => $transaction['purpose_req'],
            ':desc_req' => $transaction['desc_req'],
        ]));

        echo json_encode(["message" => "Transaction created successfully"]);
        break;

    case 'PATCH':
        $transaction = json_decode(file_get_contents('php://input'), true);

        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3];

        $qy = "
        UPDATE transactions 
        SET 
            id_doc = :id_doc, 
            email_req = :email_req, 
            id_swu = :id_swu, 
            name_owner = :name_owner, 
            course = :course, 
            purpose_req = :purpose_req, 
            desc_req = :desc_req, 
            filepath_receipt = :filepath_receipt, 
            statusPayment = :statusPayment, 
            statusTransit = :statusTransit, 
            id_employee = :id_employee, 
            updated_at = :updated_at
        WHERE reference_number = :reference
        ";

        $stmt = $db_connection->prepare($qy);

        $stmt->execute([
            ':reference' => $found_reference_no,
            ':id_doc' => $transaction['id_doc'],
            ':email_req' => $transaction['email_req'],
            ':id_swu' => $transaction['id_swu'],
            ':name_owner' => $transaction['name_owner'],
            ':course' => $transaction['course'],
            ':purpose_req' => $transaction['purpose_req'],
            ':desc_req' => $transaction['desc_req'],
            ':filepath_receipt' => $transaction['filepath_receipt'],
            ':statusPayment' => $transaction['statusPayment'],
            ':statusTransit' => $transaction['statusTransit'],
            ':id_employee' => $transaction['id_employee'],
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);

        echo json_encode(["message" => "PATCH successful"]);
        break;

    case 'DELETE':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_reference_no = $URI_array[3];

        $qy = "
        DELETE FROM transactions 
        WHERE reference_number = :reference
        ";

        $stmt = $db_connection->prepare($qy);
        $stmt->bindParam(':reference', $found_reference_no);

        if ($stmt->execute()) {
            echo json_encode(['message' => 'DELETE successful']);
        } else {
            echo json_encode(['message' => 'DELETE failed']);
        }
        break;
}
?>
