<?php
# DB connection. #
# Security Measures. #


class DB {
    private $host = '213.171.200.33';
    private $dbname = 'cturnbull';
    private $username = 'cturnbull';
    private $password = 'Password20*';
    private $conn = null;

    private function validateSearchPattern($value) {
        
        $blacklist = array(
            "UNION", "SELECT", "INSERT", "UPDATE", "DELETE", "DROP",
            "EXEC", "EXECUTE", "UNION ALL", "--", "/*", "*/", ";",
            "OR 1=1", "OR '1'='1"
        );
        
        foreach ($blacklist as $term) {
            if (stripos($value, $term) !== false) {
                throw new Exception("Invalid search pattern detected");
            }
        }
        return preg_replace('/[^a-zA-Z0-9\s\-\']/', '', $value);
    }

    public function connect(): PDO {
        try {
            if ($this->conn === null) {
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::ATTR_CASE => PDO::CASE_NATURAL
                ];
                $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            }
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    public function executeQuery($query, $params = []): PDOStatement {
        try {
            
            if (stripos($query, 'FROM tbl_products') !== false && 
                stripos($query, 'is_deleted') === false) {
                $pos = stripos($query, 'WHERE');
                if ($pos !== false) {
                    $query = substr_replace($query, 'WHERE is_deleted = 0 AND ', $pos, 6);
                } else {
                    $query .= ' WHERE is_deleted = 0';
                }
            }

            $pdo = $this->connect();
            $stmt = $pdo->prepare($query);
            
            foreach ($params as $key => &$value) {
                
                if (strpos($key, 'search') !== false || 
                    strpos($key, 'customer_name') !== false) {
                    $value = $this->validateSearchPattern($value);
                }

                
                $type = PDO::PARAM_STR;
                if (is_int($value)) {
                    $type = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $type = PDO::PARAM_BOOL;
                } elseif (is_null($value)) {
                    $type = PDO::PARAM_NULL;
                }
                
                $stmt->bindValue($key, $value, $type);
            }
            
            $stmt->execute();
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            throw new Exception("Database query failed");
        }
    }
}