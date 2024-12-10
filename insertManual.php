<?php
// THIS CODE IS FOR MANUALLY INSERTING NEW USER OR CLIENT TO DATABASE
require_once 'connectDb.php';

class User {
    private $conn;

    public function __construct() {
        // Initialize the database connection
        $database = new connectDb();
        $this->conn = $database->connect();
    }

    public function insertUser($name, $email, $password, $account_type) {
        try {
            // Hash the password with bcrypt
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // Prepare the SQL statement
            $sql = "INSERT INTO users (name, email, password, account_type) VALUES (:name, :email, :password, :account_type)";
            $stmt = $this->conn->prepare($sql);

            // Bind the parameters
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':account_type', $account_type);

            // Execute the statement
            $stmt->execute();
            
            echo "User successfully inserted!";
        } catch (PDOException $e) {
            echo "Error inserting user: " . $e->getMessage();
        }
    }
}

class Client {
    private $conn;

    // Properties that match the database columns
    public $id;
    public $id_swu;
    public $name;
    public $email;
    public $phone;
    public $password;
    public $created_at;
    public $updated_at;

    public function __construct() {
        // Initialize the database connection
        $database = new connectDb();
        $this->conn = $database->connect();
    }

    // Method to create a new client
    public function insertClient($id_swu, $name, $email, $phone, $password) {
        try {
            // Hash the password with bcrypt
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // Prepare the SQL statement
            $sql = "INSERT INTO clients (id_swu, name, email, phone, password, created_at, updated_at) 
                    VALUES (:id_swu, :name, :email, :phone, :password, NOW(), NOW())";
            $stmt = $this->conn->prepare($sql);

            // Bind the parameters
            $stmt->bindParam(':id_swu', $id_swu);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':password', $hashedPassword);

            // Execute the statement
            $stmt->execute();
            echo "Client successfully inserted!";
        } catch (PDOException $e) {
            echo "Error inserting client: " . $e->getMessage();
        }
    }
}

// $userAdmin = new User();
// $userAdmin->insertUser('Dr. Angelita Canene', 'apcanene.swu@phinmaed.com', 'canene123', 'ADMIN');

// debug admin account
/* $userAdmin = new User();
$userAdmin->insertUser('dqstAdmin', 'dqst.swu@phinmaed.com', 'dqst123', 'ADMIN'); */

$client = new Client();
$client->insertClient("123", "Bu Aang", "bung.aang.swu@phinmaed.com", "1234567890", "client123");

$client2 = new Client();
$client2->insertClient("456", "Simon Says", "sisa.swu@phinmaed.com", "1234567890", "client456");

$client3 = new Client();
$client3->insertClient("999", "King Von", "kvon.swu@phinmaed.com", "1234567890", "kvon123");

$user2 = new User();
$user2->insertUser('Hans Seno', 'hansseno2020@gmail.com', 'hans123', 'EMPLOYEE');
?>
