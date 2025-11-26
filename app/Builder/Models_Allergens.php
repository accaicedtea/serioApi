<?php
require_once '../config/database.php';

class Allergen {
    private $conn;
    private $table_name = "allergens";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all allergens
    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Get single allergen
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        return $stmt;
    }

    // Create allergen
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                SET name=:name, icon=:icon";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":name", $data['name']);
        $stmt->bindParam(":icon", $data['icon']);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Update allergen
    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . " 
                SET name=:name, icon=:icon 
                WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":name", $data['name']);
        $stmt->bindParam(":icon", $data['icon']);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete allergen
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}
