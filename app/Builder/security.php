<?php
class SecurityMiddleware {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function checkRateLimit($endpoint, $limit = 100, $window = 60) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = $ip . ':' . $endpoint;
        
        // Verifica se esiste la tabella rate_limits
        try {
            $query = "SELECT COUNT(*) as count FROM rate_limits 
                     WHERE identifier = :identifier 
                     AND timestamp > DATE_SUB(NOW(), INTERVAL :window SECOND)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->bindParam(':window', $window, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];
            
            if ($count >= $limit) {
                http_response_code(429);
                echo json_encode([
                    'error' => true,
                    'message' => 'Rate limit exceeded. Try again later.',
                    'status' => 429
                ]);
                exit;
            }
            
            // Registra la richiesta
            $insertQuery = "INSERT INTO rate_limits (identifier, endpoint, timestamp) VALUES (:identifier, :endpoint, NOW())";
            $insertStmt = $this->db->prepare($insertQuery);
            $insertStmt->bindParam(':identifier', $identifier);
            $insertStmt->bindParam(':endpoint', $endpoint);
            $insertStmt->execute();
            
            // Pulizia vecchi record
            $cleanQuery = "DELETE FROM rate_limits WHERE timestamp < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            $this->db->exec($cleanQuery);
            
        } catch (PDOException $e) {
            // Se la tabella non esiste, ignora il rate limiting
            return true;
        }
        
        return true;
    }
    
    public function applySecurityHeaders() {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
