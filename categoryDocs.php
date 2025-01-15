<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, PUT");

    $db_attempt = new connectDb;
    $db_connection = $db_attempt->connect(); 

    $method = $_SERVER['REQUEST_METHOD'];
    switch ($method) {
        case 'GET':
            $URI_array = explode('/', $_SERVER['REQUEST_URI']);
            $found_id = isset($URI_array[3]) ? $URI_array[3] : null;

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
            $data = json_decode(file_get_contents('php://input'), true);
        
            if ($data) {
                // Log incoming data for debugging
                error_log(print_r($data, true));
        
                // Validate the data
                if (isset($data['name'], $data['price'], $data['processing_days'])) {
                    // SQL query to insert the document into categories_docs
                    $qy = "INSERT INTO categories_docs(name, price, processing_days, luzon_price, visayas_price, mindanao_price) 
                           VALUES(:name, :price, :processing_days, :luzon_price, :visayas_price, :mindanao_price)";
                    
                    // Prepare the statement
                    // $data['luzon_price']
                    // $data['visayas_price']
                    // $data['mindanao_price']
                    $empty_int = 0;

                    $stmt = $db_connection->prepare($qy);
                    $stmt->bindParam(':name', $data['name']);
                    $stmt->bindParam(':price', $data['price']);
                    $stmt->bindParam(':processing_days', $data['processing_days']);
                    $stmt->bindParam(':luzon_price', $empty_int);
                    $stmt->bindParam(':visayas_price', $empty_int);
                    $stmt->bindParam(':mindanao_price', $empty_int);

                    // Execute the query and check for success$empty_int
                    if ($stmt->execute()) {
                        // TODO use phpmail email current admin

                        // Return success message as a JSON response
                        $response = ['status' => 1, 'message' => "Document added to categories_docs successfully."];
                    } else {
                        // Return failure message if insertion fails
                        $response = ['status' => 0, 'message' => "Failed to add document to categories_docs."];
                    }
        
                    // Send the response back to the frontend
                    echo json_encode($response);
                } else {
                    // If required fields are missing, send error
                    $response = ['status' => 0, 'message' => 'Missing required fields.'];
                    echo json_encode($response);
                }
            } else {
                // If data is not sent, send error
                $response = ['status' => 0, 'message' => 'No data received.'];
                echo json_encode($response);
            }
            break;

            case 'PATCH':
                $category = json_decode(file_get_contents('php://input'));
                $URI_array = explode('/', $_SERVER['REQUEST_URI']);
                $found_id = isset($URI_array[3]) ? $URI_array[3] : null;
            
                if ($found_id && is_numeric($found_id)) {
                    // Update query for editing an existing record
                    $qy = "UPDATE categories_docs SET 
                               name=:name, 
                               price=:price, 
                               processing_days=:processing_days, 
                               luzon_price=:luzon_price, 
                               visayas_price=:visayas_price, 
                               mindanao_price=:mindanao_price, 
                               updated_at=:updated 
                           WHERE id=:id";
                    $stmt = $db_connection->prepare($qy);
            
                    $updated_at = date('Y-m-d H:i:s');
                    $empty_int = 0;

                    // Bind values based on the data received from the frontend
                    $stmt->bindParam(':id', $found_id);
                    $stmt->bindParam(':name', $category->name);  
                    $stmt->bindParam(':price', $category->price);  
                    $stmt->bindParam(':processing_days', $category->processing_days);  
                    $stmt->bindParam(':updated', $updated_at);
                    $stmt->bindParam(':luzon_price', $empty_int);  
                    $stmt->bindParam(':visayas_price', $empty_int);  
                    $stmt->bindParam(':mindanao_price', $empty_int);  
            
                    if ($stmt->execute()) {
                        $response = ['status' => 1, 'message' => 'Document category updated successfully.'];
                    } else {
                        $response = ['status' => 0, 'message' => 'Failed to update document category.'];
                    }
                } else {
                    $response = ['status' => 0, 'message' => 'Invalid or missing ID for update.'];
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