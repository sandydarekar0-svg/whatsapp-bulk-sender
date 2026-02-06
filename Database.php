<?php
// classes/Database.php

class Database {
    private $conn;
    private $host = DB_HOST;
    private $db = DB_NAME;
    private $user = DB_USER;
    private $pass = DB_PASS;

    public function connect() {
        try {
            $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->db);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection Error: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
            return $this->conn;
        } catch (Exception $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            http_response_code(500);
            exit(json_encode(['success' => false, 'message' => 'Database connection failed']));
        }
    }

    public function query($sql) {
        return $this->conn->query($sql);
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }

    public function lastInsertId() {
        return $this->conn->insert_id;
    }

    public function affectedRows() {
        return $this->conn->affected_rows;
    }

    public function close() {
        $this->conn->close();
    }

    public function beginTransaction() {
        $this->conn->begin_transaction();
    }

    public function commit() {
        $this->conn->commit();
    }

    public function rollback() {
        $this->conn->rollback();
    }
}
