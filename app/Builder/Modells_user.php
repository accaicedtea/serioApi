<?php
require_once '../config/database.php';

class User {
    private $conn;
    private $table_name = "user_auth";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Login user
    public function login($email, $password) {
        $query = "SELECT id, email, password, name, role, is_active FROM " . $this->table_name . " 
                  WHERE email = ? AND is_active = 1 LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verifica password con hash
            if(password_verify($password, $row['password'])) {
                // Update last login
                $this->updateLastLogin($row['id']);
                
                // Return user data without password
                unset($row['password']);
                return $row;
            }
        }
        return false;
    }

    // Get user by ID
    public function getById($id) {
        $query = "SELECT id, email, name, role, is_active, last_login, created_at FROM " . $this->table_name . " 
                  WHERE id = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    // Get user by ID with password (for password verification)
    public function getByIdWithPassword($id) {
        $query = "SELECT id, email, password, name, role, is_active, last_login, created_at FROM " . $this->table_name . " 
                  WHERE id = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    // Get user by email
    public function getByEmail($email) {
        $query = "SELECT id, email, name, role, is_active FROM " . $this->table_name . " 
                  WHERE email = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    // Update last login
    private function updateLastLogin($user_id) {
        $query = "UPDATE " . $this->table_name . " SET last_login = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        return $stmt->execute();
    }

    // Create user
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                SET email=:email, password=:password, name=:name, role=:role";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":email", $data['email']);
        $stmt->bindParam(":password", $data['password']); // In produzione hashare la password
        $stmt->bindParam(":name", $data['name']);
        $stmt->bindParam(":role", $data['role']);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Update user
    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . " 
                SET email=:email, name=:name, role=:role";
        
        if(!empty($data['password'])) {
            $query .= ", password=:password";
        }
        
        $query .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":email", $data['email']);
        $stmt->bindParam(":name", $data['name']);
        $stmt->bindParam(":role", $data['role']);
        
        if(!empty($data['password'])) {
            $stmt->bindParam(":password", $data['password']);
        }
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete user
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Update password
    public function updatePassword($id, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $query = "UPDATE " . $this->table_name . " SET password = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $hashedPassword);
        $stmt->bindParam(2, $id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Update site URL
    public function updateSiteUrl($id, $siteUrl) {
        $query = "UPDATE " . $this->table_name . " SET site_url = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $siteUrl);
        $stmt->bindParam(2, $id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}
