<?php 
require_once('connectDb.php');

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH");

$db_attempt = new connectDb;
$db_connection = $db_attempt->connect();

function getCourseNames($courseIds, $db_connection) {
    if (empty($courseIds)) {
        return [];
    }

    // Convert array of course IDs to a comma-separated string for the SQL query
    $courseIdsString = implode(',', $courseIds);

    // Query the courses table to get course names
    $query = "SELECT id, course_name FROM courses WHERE id IN ($courseIdsString)";
    $stmt = $db_connection->prepare($query);
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Fetch as id => course_name

    // Map course IDs to course names
    $courseNames = [];
    foreach ($courseIds as $courseId) {
        if (isset($courses[$courseId])) {
            $courseNames[] = $courses[$courseId];
        }
    }

    return $courseNames;
}


$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $parsed_url = parse_url($_SERVER['REQUEST_URI']);
        $path = $parsed_url['path'];
        $URI_array = explode('/', $path);

        $found_user = isset($_GET['userid']) && is_numeric($_GET['userid']) ? $_GET['userid'] : null;

        $stats_query = "
            (SELECT COUNT(*) FROM users WHERE TRIM(user_courses) = '' OR user_courses IS NULL) AS unassigned_users,
            (SELECT COUNT(*) FROM workflow WHERE stage_now = stage_max) AS completed_workflows
        ";

        if ($found_user) {
            $qy = "
            SELECT 
                WFL.*,
                $stats_query,
                U.user_courses AS user_courses,
                U.firstname AS user_firstname,
                U.middlename AS user_middlename,
                U.lastname AS user_lastname,
                U2.firstname AS updated_user_firstname,
                U2.middlename AS updated_user_middlename,
                U2.lastname AS updated_user_lastname
            FROM workflow WFL
            LEFT JOIN users U ON WFL.created_by = U.id 
            LEFT JOIN users U2 ON WFL.updated_by = U2.id
            WHERE WFL.created_by = :userid
               OR WFL.updated_by = :userid
            ORDER BY WFL.id ASC
            ";

            $stmt = $db_connection->prepare($qy);
            $stmt->bindParam(':userid', $found_user, PDO::PARAM_INT);

        } else {
            $qy = "
            SELECT 
                WFL.*,
                $stats_query,
                U.user_courses AS user_courses,
                U.firstname AS user_firstname,
                U.middlename AS user_middlename,
                U.lastname AS user_lastname,
                U2.firstname AS updated_user_firstname,
                U2.middlename AS updated_user_middlename,
                U2.lastname AS updated_user_lastname
            FROM workflow WFL
            LEFT JOIN users U ON WFL.created_by = U.id 
            LEFT JOIN users U2 ON WFL.updated_by = U2.id
            ORDER BY WFL.id ASC
            ";

            $stmt = $db_connection->prepare($qy);
        }

        if (!$stmt->execute()) {
            echo json_encode(["status" => "error", "message" => "Database query failed"]);
            exit;
        }

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($data as &$row) {
            if (!empty($row['user_courses'])) {
                $courseIds = explode(',', $row['user_courses']);
                $row['user_course_names'] = getCourseNames($courseIds, $db_connection);
            } else {
                $row['user_course_names'] = [];
            }
        }

        if (!empty($data)) {
            echo json_encode([
                "status" => "success",
                "message" => "Get user and workflow successful",
                "data" => $data
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "No records found"]);
        }

        break;

    case 'POST':

        break;

    case 'PATCH':

        break;
}
?>