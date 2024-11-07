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
            // TODO
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

    // Method to get a client by ID
    // public function getById($id) {
    //     try {
    //         $sql = "SELECT * FROM clients WHERE id = :id";
    //         $stmt = $this->conn->prepare($sql);
    //         $stmt->bindParam(':id', $id);
    //         $stmt->execute();

    //         // Fetch data and assign it to this object's properties
    //         $client = $stmt->fetch(PDO::FETCH_ASSOC);
    //         if ($client) {
    //             $this->id = $client['id'];
    //             $this->id_swu = $client['id_swu'];
    //             $this->name = $client['name'];
    //             $this->email = $client['email'];
    //             $this->phone = $client['phone'];
    //             $this->password = $client['password'];
    //             $this->created_at = $client['created_at'];
    //             $this->updated_at = $client['updated_at'];
    //             return true;
    //         } else {
    //             return false;
    //         }
    //     } catch (PDOException $e) {
    //         echo "Error fetching client: " . $e->getMessage();
    //         return false;
    //     }
    // }

    // Method to update client information
    // public function update() {
    //     try {
    //         // SQL to update client data
    //         $sql = "UPDATE clients SET 
    //                 id_swu = :id_swu, 
    //                 name = :name, 
    //                 email = :email, 
    //                 phone = :phone, 
    //                 password = :password, 
    //                 updated_at = :updated_at 
    //                 WHERE id = :id";
            
    //         $stmt = $this->conn->prepare($sql);

    //         // Hash the password if it needs to be updated
    //         $hashedPassword = password_hash($this->password, PASSWORD_BCRYPT);

    //         // Bind parameters
    //         $stmt->bindParam(':id', $this->id);
    //         $stmt->bindParam(':id_swu', $this->id_swu);
    //         $stmt->bindParam(':name', $this->name);
    //         $stmt->bindParam(':email', $this->email);
    //         $stmt->bindParam(':phone', $this->phone);
    //         $stmt->bindParam(':password', $hashedPassword);
    //         $stmt->bindParam(':updated_at', $this->updated_at);

    //         // Execute the statement
    //         return $stmt->execute();
    //     } catch (PDOException $e) {
    //         echo "Error updating client: " . $e->getMessage();
    //         return false;
    //     }
    // }

    // // Method to delete a client by ID
    // public function delete($id) {
    //     try {
    //         $sql = "DELETE FROM clients WHERE id = :id";
    //         $stmt = $this->conn->prepare($sql);
    //         $stmt->bindParam(':id', $id);
    //         return $stmt->execute();
    //     } catch (PDOException $e) {
    //         echo "Error deleting client: " . $e->getMessage();
    //         return false;
    //     }
    // }
}

$userAdmin = new User();
$userAdmin->insertUser('Dr. Angelita Canene', 'apcanene@phinmaed.com', '66tTfqGsYH7M6WcR4dBRwzQIa', 'ADMIN');

$client = new Client();
$client->insertClient("test123", "Bu Aang", "bung.aang@phinmaed.com", "1234567890", "client123");
?>
