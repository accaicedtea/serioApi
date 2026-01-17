<?php
class SecurityMiddleware {
    private $db;
    private $tablesExist = null;
    
    public function __construct($db) {
        $this->db = $db;
        $this->checkTablesExist();
    }
    
    private function checkTablesExist() {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'rate_limits'");
            $this->tablesExist = $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $this->tablesExist = false;
        }
    }
    
    public function checkRateLimit($endpoint, $limit = 100, $window = 60) {
        // Se le tabelle non esistono, salta il rate limiting
        if (!$this->tablesExist) {
            return true;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        try {
            // Verifica quante richieste ha fatto questo IP per questo endpoint nella finestra temporale
            $query = "SELECT requests_count, window_start FROM rate_limits 
                     WHERE ip_address = :ip 
                     AND endpoint = :endpoint 
                     AND window_start > DATE_SUB(NOW(), INTERVAL :window SECOND)
                     LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ip', $ip);
            $stmt->bindParam(':endpoint', $endpoint);
            $stmt->bindParam(':window', $window, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // Record esistente - controlla se ha superato il limite
                if ($result['requests_count'] >= $limit) {
                    // Log rate limit exceeded
                    $this->logSecurityEvent('rate_limit_exceeded', $endpoint, [
                        'count' => $result['requests_count'],
                        'limit' => $limit
                    ]);
                    
                    http_response_code(429);
                    echo json_encode([
                        'error' => true,
                        'message' => 'Rate limit exceeded. Try again later.',
                        'status' => 429
                    ]);
                    exit;
                }
                
                // Incrementa il contatore
                $updateQuery = "UPDATE rate_limits 
                               SET requests_count = requests_count + 1, 
                                   last_request = NOW() 
                               WHERE ip_address = :ip AND endpoint = :endpoint";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindParam(':ip', $ip);
                $updateStmt->bindParam(':endpoint', $endpoint);
                $updateStmt->execute();
            } else {
                // Nessun record nella finestra - crea nuovo
                $insertQuery = "INSERT INTO rate_limits (ip_address, endpoint, requests_count, window_start, last_request) 
                               VALUES (:ip, :endpoint, 1, NOW(), NOW())";
                $insertStmt = $this->db->prepare($insertQuery);
                $insertStmt->bindParam(':ip', $ip);
                $insertStmt->bindParam(':endpoint', $endpoint);
                $insertStmt->execute();
            }
            
            // Pulizia vecchi record (ogni 100 richieste circa)
            if (rand(1, 100) === 1) {
                $cleanQuery = "DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
                $this->db->exec($cleanQuery);
            }
            
        } catch (PDOException $e) {
            // Se la tabella non esiste o c'è un errore, ignora il rate limiting
            error_log("Rate limiting error: " . $e->getMessage());
            error_log("Rate limiting error trace: " . $e->getTraceAsString());
            return true;
        }
        
        return true;
    }
    
    public function logSecurityEvent($eventType, $endpoint, $details = []) {
        // Se le tabelle non esistono, salta il logging
        if (!$this->tablesExist) {
            return true;
        }
        
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
            
            $query = "INSERT INTO security_logs (client_ip, event_type, endpoint_type, details, user_agent, request_uri, created_at) 
                     VALUES (:ip, :event, :endpoint, :details, :user_agent, :request_uri, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ip', $ip);
            $stmt->bindParam(':event', $eventType);
            $stmt->bindParam(':endpoint', $endpoint);
            $detailsJson = json_encode($details);
            $stmt->bindParam(':details', $detailsJson);
            $stmt->bindParam(':user_agent', $userAgent);
            $stmt->bindParam(':request_uri', $requestUri);
            $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Security logging error: " . $e->getMessage());
        }
    }
    
    public function applySecurityHeaders() {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
