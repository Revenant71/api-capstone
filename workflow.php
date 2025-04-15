<?php 
require_once('connectDb.php');

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

if (!$db_connection) {
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $parsed_url = parse_url($_SERVER['REQUEST_URI']);
        $path = $parsed_url['path'];
        $URI_array = explode('/', $path);

        $found_uuid = isset($_GET['uqid']) ? $_GET['uqid'] : null;
        $found_reference = isset($_GET['ref']) ? $_GET['ref'] : null;

        if ($found_uuid && $found_reference){
            // Get latest row with matching uuid AND reference number + join
            $qy = "
            SELECT WFL.*,
                U.firstname AS user_firstname,
                U.middlename AS user_middlename,
                U.lastname AS user_lastname,
                U2.firstname AS updated_user_firstname,
                U2.middlename AS updated_user_middlename,
                U2.lastname AS updated_user_lastname
            FROM workflow WFL
            LEFT JOIN users U ON WFL.created_by = U.id 
            LEFT JOIN users U2 ON WFL.updated_by = U2.id
            INNER JOIN transactions T ON WFL.ref = T.reference_number
            WHERE WFL.id = :uuid
            AND T.reference_number = :reference
            ORDER BY WFL.created_date DESC
            LIMIT 1;
            ";

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':uuid', $found_uuid, PDO::PARAM_STR);
            $stmt->bindParam(':reference', $found_reference, PDO::PARAM_STR);
        
        } elseif ($found_uuid || $found_reference) {
            // Get latest row with matching uuid OR reference number + join
            $qy = "
            SELECT DISTINCT WFL.*,
                U.firstname AS user_firstname,
                U.middlename AS user_middlename,
                U.lastname AS user_lastname,
                U2.firstname AS updated_user_firstname,
                U2.middlename AS updated_user_middlename,
                U2.lastname AS updated_user_lastname
            FROM workflow WFL
            LEFT JOIN users U ON WFL.created_by = U.id 
            LEFT JOIN users U2 ON WFL.updated_by = U2.id
            LEFT JOIN transactions T ON WFL.ref = T.reference_number
            ";
        
            // Conditional WHERE Clause
            $conditions = [];
            if ($found_uuid) {
                $conditions[] = "WFL.id = :uuid";
            }
            if ($found_reference) {
                $conditions[] = "T.reference_number = :reference";
            }
            
            // Add conditions to query if they exist
            if (!empty($conditions)) {
                $qy .= " WHERE " . implode(" OR ", $conditions);
            }
        
            $qy .= " ORDER BY WFL.updated_date DESC, WFL.created_date DESC LIMIT 1;";

            $stmt = $db_connection->prepare($qy);
        
            // Conditional Binding
            if ($found_uuid) {
                $stmt->bindParam(':uuid', $found_uuid, PDO::PARAM_STR);
            }
            if ($found_reference) {
                $stmt->bindParam(':reference', $found_reference, PDO::PARAM_STR);
            }

        } else {
            // Get latest row from all data
            $qy = "
            SELECT DISTINCT WFL.*,
                U.firstname AS user_firstname,
                U.middlename AS user_middlename,
                U.lastname AS user_lastname,
                U2.firstname AS updated_user_firstname,
                U2.middlename AS updated_user_middlename,
                U2.lastname AS updated_user_lastname
            FROM workflow WFL
            LEFT JOIN users U ON WFL.created_by = U.id 
            LEFT JOIN users U2 ON WFL.updated_by = U2.id
            LEFT JOIN transactions T ON WFL.ref = T.reference_number
            ORDER BY WFL.created_date DESC
            LIMIT 1;
            ";

            $stmt = $db_connection->prepare($qy);
        }

        if (!$stmt->execute()) {
            echo json_encode(["status" => "error", "message" => "Database query failed"]);
            exit;
        }

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($data)) {
            echo json_encode(["status" => "success", "data" => $data]);
        } else {
            echo json_encode(["status" => "error", "message" => "No records found", "data" => []]);
        }
        
      break;

      case 'POST':
        $action = json_decode(file_get_contents('php://input'));
    
        if (isset($action->created_by, $action->name_action, $action->status_action, $action->stage_now, $action->stage_max)) {
            try {
                function generateUUID() {
                    return sprintf(
                        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000,
                        mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                    );
                }
    
                $uuid = generateUUID();
    
                $qy = "INSERT INTO workflow (id, name_action, status_action, stage_now, stage_max, 
                    score_eff, score_prod, created_by, created_date";
                
                $values = ":uuid, :name_action, :status_action, :stage_now, :stage_max, 
                    :score_eff, :score_prod, :created_by, NOW()";
    
                if (!empty($action->ref)) {
                    $qy .= ", ref";
                    $values .= ", :ref";
                }
    
                if (!empty($action->feedback)) {
                    $qy .= ", feedback";
                    $values .= ", :feedback";
                }

                if (!empty($action->updated_by)) {
                    $qy .= ", updated_by";
                    $values .= ", :updated_by";
                }
    
                if (!empty($action->updated_date)) {
                    $qy .= ", updated_date";
                    $values .= ", :updated_date";
                }
    
                if (!empty($action->due_date)) {
                    $qy .= ", due_date";
                    $values .= ", :due_date";
                }

                $qy .= ") VALUES ($values)";
    
                $stmt = $db_connection->prepare($qy);
    
                if ($stmt) {
                    $score_eff = (float)$action->score_eff;
                    $score_prod = (float)$action->score_prod;
    
                    $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
                    $stmt->bindParam(':name_action', $action->name_action, PDO::PARAM_STR);
                    $stmt->bindParam(':status_action', $action->status_action, PDO::PARAM_STR);
                    $stmt->bindParam(':stage_now', $action->stage_now, PDO::PARAM_INT);
                    $stmt->bindParam(':stage_max', $action->stage_max, PDO::PARAM_INT);
                    $stmt->bindParam(':score_eff', $score_eff, PDO::PARAM_STR);
                    $stmt->bindParam(':score_prod', $score_prod, PDO::PARAM_STR);
                    $stmt->bindParam(':created_by', $action->created_by, PDO::PARAM_INT);
    
                    if (!empty($action->ref)) {
                        $stmt->bindParam(':ref', $action->ref, PDO::PARAM_STR);
                    }
    
                    if (!empty($action->feedback)) {
                        $stmt->bindParam(':feedback', $action->feedback, PDO::PARAM_STR);
                    }

                    if (!empty($action->updated_by)) {
                        $stmt->bindParam(':updated_by', $action->updated_by, PDO::PARAM_INT);
                    }
    
                    if (!empty($action->updated_date)) {
                        $stmt->bindParam(':updated_date', $action->updated_date, PDO::PARAM_STR);
                    }
    
                    if (!empty($action->due_date)) {
                        $stmt->bindParam(':due_date', $action->due_date, PDO::PARAM_STR);
                    }

                    if (!$stmt->execute()) {
                        echo json_encode(["status" => 0, "message" => "Failed to add workflow entry"]);
                    } else {
                        echo json_encode(["status" => 1, "message" => "Workflow entry added"]);
                    }
                } else {
                    echo json_encode(["status" => 0, "message" => "Failed to prepare statement: " . $qy]);
                }
            } catch (PDOException $e) {
                echo json_encode(["status" => 0, "message" => "Database error: " . $e->getMessage()]);
            }
        } else {
            echo json_encode(["status" => 0, "message" => "Missing required fields", "received" => $action]);
        }
    
        break;
    

    case 'PATCH':
        $action = json_decode(file_get_contents('php://input'));
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);

        if (empty($action->uqid)) {
            echo json_encode(["status" => 0, "message" => "Missing required ID"]);
            exit;
        }

        $fields = [];
        $params = [];

        if (!empty($action->name_action)) {
            $fields[] = "name_action = :name_action";
            $params[':name_action'] = $action->name_action;
        }
    
        if (!empty($action->status_action)) {
            $fields[] = "status_action = :status_action";
            $params[':status_action'] = $action->status_action;
        }

        if (isset($action->feedback)) {
            $fields[] = "feedback = :feedback";
            $params[':feedback'] = $action->feedback;
        }
    
        if (isset($action->stage_now)) {
            $fields[] = "stage_now = :stage_now";
            $params[':stage_now'] = $action->stage_now;
        }
    
        if (isset($action->stage_max)) {
            $fields[] = "stage_max = :stage_max";
            $params[':stage_max'] = $action->stage_max;
        }
    
        if (isset($action->score_eff)) {
            $fields[] = "score_eff = :score_eff";
            $params[':score_eff'] = $action->score_eff;
        }
    
        if (isset($action->score_prod)) {
            $fields[] = "score_prod = :score_prod";
            $params[':score_prod'] = $action->score_prod;
        }
    
        if (!empty($action->updated_by)) {
            $fields[] = "updated_by = :updated_by";
            $params[':updated_by'] = $action->updated_by;
        }

        $fields[] = "updated_date = NOW()";

        if (!empty($fields)) {
            $query = "UPDATE workflow SET " . implode(", ", $fields) . " WHERE id = :id";
            $params[':id'] = $action->uqid;
    
            $stmt = $db_connection->prepare($query);
    
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_numeric($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
    
            if ($stmt->execute()) {
                echo json_encode(["status" => 1, "message" => "Workflow updated successfully"]);
            } else {
                echo json_encode(["status" => 0, "message" => "Failed to update workflow"]);
            }
        } else {
            echo json_encode(["status" => 0, "message" => "No valid fields to update"]);
        }

      break;
}
?>