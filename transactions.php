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

            $qy = "SELECT * FROM `transactions`";
        
            if (isset($found_id) && is_numeric($found_id)) {
                $qy .= " WHERE id = :id";
                $stmt = $db_connection->prepare($qy);
                $stmt->bindParam(':id', $found_id);
                $stmt->execute();
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $db_connection->prepare($qy);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            echo json_encode($data);
            break;
        
        case 'POST':
            $transaction = json_decode(file_get_contents('php://input'));

            $qy = "INSERT INTO transactions(type_doc, client_id,
            client_name, client_email, client_phone,
            title_req, description, status,
            created_at, updated_at)
            VALUES(:doc_type, :id_client,
            :name_client, :email_client, :phone_client,
            :title_request, :desc_request, :status_request,
            :created, :updated
            )";

            $stmt = $db_connection->prepare($qy);

            $status = "PENDING";
            $created_at = date('Y-m-d H:i:s');
            $updated_at = date('Y-m-d H:i:s');

            $stmt->bindParam(':doc_type', $transaction->documentType);
            $stmt->bindParam(':id_client', $transaction->studentId);
            $stmt->bindParam(':name_client', $transaction->nameOwner);
            // TODO column + bind for course 
            $stmt->bindParam(':email_client', $transaction->email);            
            $stmt->bindParam(':phone_client', $transaction->req_phone); // follow form field name
            $stmt->bindParam(':title_request', $transaction->req_title); // follow form field name
            $stmt->bindParam(':desc_request', $transaction->req_body); // follow form field name
            $stmt->bindParam(':status_request', $status);
            $stmt->bindParam(':created', $created_at);
            $stmt->bindParam(':updated', $updated_at);

            if($stmt->execute()){
                $response = ['status'=>1, 'message'=>"POST successful."];
            } else {
                $response = ['status'=>0, 'message'=>"SORRY, POST failed."];
            }

            echo json_encode($response);
            break;

        case 'PATCH':
            
            $transaction = json_decode(file_get_contents('php://input'));

            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = $URI_array[3];

            $qy = "UPDATE transactions SET id_doc=:doc_id, filepath_doc=:doc_file, type_doc=:doc_type,
            client_id=:client_id, client_name=:client_name, client_email=:client_email, client_phone=:client_phone,
            title_req=:request_title, description=:request_desc, status=:request_status, id_employee=:emp_id
            updated_at=:updated WHERE id=:id";
    
            $stmt = $db_connection->prepare($qy);
            $updated_at = date('Y-m-d H:i:s');

            if($found_id && is_numeric($found_id)){
                $stmt->bindParam(':id', $found_id);                
            }

            // TODO refer to FormTransaction.js
            $stmt->bindParam(':doc_id', );
            $stmt->bindParam(':doc_file', );
            $stmt->bindParam(':doc_type', );
            $stmt->bindParam(':client_id', );
            $stmt->bindParam(':client_name', );
            $stmt->bindParam(':client_email', );
            $stmt->bindParam(':client_phone', );
            $stmt->bindParam(':request_title', );
            $stmt->bindParam(':request_desc', );
            $stmt->bindParam(':request_status', );
            $stmt->bindParam(':id_employee', );
            $stmt->bindParam(':updated', $updated_at);
            
            if($stmt->execute()){
                $response = ['status'=>1, 'message'=>"PATCH successful."];
            } else {
                $response = ['status'=>0, 'message'=>"SORRY, PATCH failed."];
            }
            
            echo json_encode($response);
            break;
        
        case 'DELETE':
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = $URI_array[3];

            $qy = "DELETE FROM transactions WHERE id=:id";

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':id', $found_id);

            if($stmt->execute()){
                $response = ['status'=>1, 'message'=>'DELETE successful.'];    
            } else {
                $response = ['status'=>0, 'message'=>'Oops! DELETE failed.'];
            }

            echo json_encode($response);
            break;
    }
?>