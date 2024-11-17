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
            $found_id = isset($URI_array[3]) ? $URI_array[3] : null;;

            $qy = "SELECT * FROM documents";

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
            $document = json_decode(file_get_contents('php://input'));

            $qy = "INSERT INTO documents(title, author, category_id,
            created_at, updated_at)
            VALUES(:title, :author, :category, :created, :updated)";

            $created_at = date('Y-m-d H:i:s');
            $updated_at = date('Y-m-d H:i:s');

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':title', $document->txt_title); // TODO follow form field name
            $stmt->bindParam(':author', $document->txt_author); // TODO follow form field name
            $stmt->bindParam(':category', $document->txt_category); // TODO follow form field name
            $stmt->bindParam(':created', $created_at);
            $stmt->bindParam(':updated', $updated_at);

            if ($stmt->execute()) {
                $response = ['status'=>1, 'message'=>"POST document successful."];
            } else {
                $response = ['status'=>0, 'message'=>"SORRY, POST document failed."];
            }
            
            echo json_encode($response);
            break;

        case 'PATCH':
            $document = json_decode(file_get_contents('php://input'));
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = $URI_array[3];

            $qy = "UPDATE documents SET title=:title, author=:author, category_id=:category, updated_at:updated WHERE id=:id";
            $updated_at = date('Y-m-d H:i:s');

            $stmt = $db_connection->prepare($qy);
            
            if ($found_id) {
                $stmt->bindParam(':id', $found_id);
            }
    
            $stmt->bindParam(':title', $document->txt_title); // TODO follow form field name
            $stmt->bindParam(':author', $document->txt_author); // TODO follow form field name
            $stmt->bindParam(':category', $document->txt_category); // TODO follow form field name
            $stmt->bindParam(':updated', $updated_at);
            
            if ($stmt->execute()) {
                $response = ['status'=>1, 'message'=>"PATCH document successful."];
            } else {
                $response = ['status'=>0, 'message'=>"SORRY, PATCH document failed."];
            }

            echo json_encode($response);
            break;
        
        case 'DELETE':
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = $URI_array[3];

            $qy = "DELETE FROM documents WHERE id=:id";
            $stmt = $db_connection->prepare($qy);
            
            if ($found_id) {
                $stmt->bindParam(':id', $found_id);
            } 
            
            if ($stmt->execute()) {
                $response = ['status'=>1, 'message'=>"DELETE document successful."];
            } else {
                $response = ['status'=>0, 'message'=>"SORRY, DELETE document failed."];
            }
            
            echo json_encode($response);
            break;
    }
?>