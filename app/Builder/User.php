<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table_name = "user_auth";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($email, $password) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = ? AND is_active = 1 LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user && password_verify($password, $user['password'])) {
            // Aggiorna last_login
            $update_query = "UPDATE " . $this->table_name . " SET last_login = NOW() WHERE id = ?";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->execute([$user['id']]);
            
            // Rimuovi password dalla risposta
            unset($user['password']);
            return $user;
        }
        
        return false;
    }

    public function getById($id) {
        $query = "SELECT id, email, name, role, last_login, created_at FROM " . $this->table_name . " 
                  WHERE id = ? AND is_active = 1 LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updatePassword($id, $new_password) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE " . $this->table_name . " SET password = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        
        if($stmt->execute([$hashed, $id])) {
            return true;
        }
        return false;
    }
}