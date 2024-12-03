<?php
require_once('connectDb.php');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: OPTIONS, GET, PATCH");
header("Access-Control-Allow-Credentials: true");

function isLoggedUserSet():bool{
    return isset($_SESSION['id_user']);
}

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect(); 

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_id = $URI_array[3];

        $qy = "SELECT * FROM users";
        if (isLoggedUserSet() && is_numeric($found_id)) {
            /* given user id */
            $qy .= " WHERE id = :id";
            $stmt = $db_connection->prepare(query: $qy);
            $stmt->bindParam(':id', $found_id);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            /* NOT given user id */
            $stmt = $db_connection->prepare($qy);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        json_encode($response);
        break;

    case 'PATCH':
        // refer to example in frontend
        $user = json_decode(file_get_contents('php://input'));
        $URI_array = explode('/', $_SERVER['REQUEST_URI']);
        $found_id = $URI_array[3];

        // TODO query and prepare statment
        

        if (isLoggedUserSet()) {
            $response = [

            ];
        } else {
            $response = [

            ];
        }

        json_encode($response);
        break;
}

?>