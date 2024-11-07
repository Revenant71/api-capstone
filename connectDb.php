<?php
    class connectDb {
        private $server = 'localhost';
        private $dbname = 'drts_capstone';
        private $user = 'root';
        private $pass = '';
        
        public function connect(){
            try {
                $conn = new PDO("mysql:host={$this->server};
                dbname={$this->dbname}", 
                $this->user,
                $this->pass
                );

                $conn->setAttribute(
                    PDO::ATTR_ERRMODE,
                    PDO::ERRMODE_EXCEPTION
                );

                return $conn; // attempt connecting to DB
            } catch (PDOException $e) {
                // return error message if any problems in connecting to DB occur
                echo "DB Connection Error: ". $e->getMessage();
            }
        }
    }
?>