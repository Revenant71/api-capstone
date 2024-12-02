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
            $found_id = $URI_array[3];

            $qy = "SELECT * FROM categories_docs";

            if ($found_id && is_numeric($found_id)) {
                $qy .= " WHERE id=:id";
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
            $category = json_decode(file_get_contents('php://input'));

            $qy = "INSERT INTO categories_docs(name_ctg_doc, created_at, updated_at) VALUES(:name, :created, :updated)";
            $stmt = $db_connection->prepare($qy);
            
            $created_at = date('Y-m-d H:i:s');            
            $updated_at = date('Y-m-d H:i:s');

            $stmt->bindParam(':name', $category->txt_name_catg); // TODO follow form field name
            $stmt->bindParam(':created', $created_at);
            $stmt->bindParam(':updated', $updated_at);

            if ($stmt->execute()) {
                $response = ['status'=>1, 'message'=>'POST document category successful.'];
            } else {
                $response = ['status'=>0, 'message'=>'SORRY, POST document category failed.'];
            }
            
            echo json_encode($response);
            break;

        case 'PATCH':
            $category = json_decode(file_get_contents('php://input'));
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = $URI_array[3];

            $qy = "UPDATE categories_docs SET name_ctg_doc=:name, updated_at=:updated WHERE id=:id";
            $stmt = $db_connection->prepare($qy);
            
            $updated_at = date('Y-m-d H:i:s');

            if ($found_id && is_numeric($found_id)) {
                $stmt->bindParam(':id', $found_id);
            }

            $stmt->bindParam(':name', $category->txt_name_catg);  // TODO follow form field name
            $stmt->bindParam(':updated', $updated_at);

            if ($stmt->execute()) {
                $response = ['status'=>1, 'message'=>'PATCH document category successful.'];
            } else {
                $response = ['status'=>0, 'message'=>'SORRY, PATCH document category failed.'];
            }

            echo json_encode($response);
            break;
        
        case 'DELETE':
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = $URI_array[3];

            $qy = "DELETE FROM categories_docs WHERE id=:id";
            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':id', $found_id);

            if ($stmt->execute()) {
                $response = ['status'=>1, 'message'=>'DELETE document category successful.'];
            } else {
                $response = ['status'=>0, 'message'=>'OOPS, DELETE document category failed.'];
            }
            
            echo json_encode($response);
            break;
    }
?>