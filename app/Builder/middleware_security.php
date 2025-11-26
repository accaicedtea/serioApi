<?php
class SecurityMiddleware {
    private $db;
    private $redis = null;
    
    // Configurazioni di rate limiting
    private $rateLimits = [
        'login' => [
            'max_attempts' => 5,
            'window' => 900, // 15 minuti
            'ban_duration' => 3600 // 1 ora
        ],
        'api' => [
            'max_requests' => 10000,
            'window' => 60, // 1 minuto
            'ban_duration' => 300 // 5 minuti
        ],
        'strict_endpoints' => [
            'max_requests' => 10,
            'window' => 60, // 1 minuto
            'ban_duration' => 600 // 10 minuti
        ]
    ];
    
    public function __construct($db) {
        $this->db = $db;
        
        // Prova a connettersi a Redis se disponibile
        if (class_exists('Redis')) {
            try {
                $this->redis = new Redis();
                $this->redis->connect('127.0.0.1', 6379);
                $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
            } catch (Exception $e) {
                $this->redis = null;
            }
        }
    }
    
    public function checkRateLimit($endpoint_type = 'api') {
        $client_ip = $this->getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Controlla IP bannati
        if ($this->isIPBanned($client_ip)) {
            $this->sendSecurityResponse(429, 'IP temporaneamente bannato per troppe richieste');
        }
        
        // Controlla suspicious patterns
        if ($this->detectSuspiciousActivity($client_ip, $user_agent)) {
            $this->banIP($client_ip, $this->rateLimits[$endpoint_type]['ban_duration']);
            $this->sendSecurityResponse(429, 'Attività sospetta rilevata');
        }
        
        // Controlla rate limiting
        if (!$this->isRateLimitOK($client_ip, $endpoint_type)) {
            $this->logSecurityEvent($client_ip, 'RATE_LIMIT_EXCEEDED', $endpoint_type);
            $this->banIP($client_ip, $this->rateLimits[$endpoint_type]['ban_duration']);
            $this->sendSecurityResponse(429, 'Troppe richieste. Riprova più tardi.');
        }
        
        // Incrementa contatore richieste
        $this->incrementRateLimit($client_ip, $endpoint_type);
        
        return true;
    }
    
    public function checkBruteForce($email = null) {
        $client_ip = $this->getClientIP();
        
        // Controlla tentativi di login falliti per IP
        $ip_attempts = $this->getFailedAttempts($client_ip, 'ip');
        if ($ip_attempts >= $this->rateLimits['login']['max_attempts']) {
            $this->banIP($client_ip, $this->rateLimits['login']['ban_duration']);
            $this->sendSecurityResponse(429, 'Troppi tentativi di login falliti da questo IP');
        }
        
        // Controlla tentativi per email specifica
        if ($email) {
            $email_attempts = $this->getFailedAttempts($email, 'email');
            if ($email_attempts >= $this->rateLimits['login']['max_attempts']) {
                $this->logSecurityEvent($client_ip, 'EMAIL_BRUTE_FORCE', $email);
                $this->sendSecurityResponse(429, 'Troppi tentativi di login per questa email');
            }
        }
        
        return true;
    }
    
    public function recordFailedLogin($email = null) {
        $client_ip = $this->getClientIP();
        
        // Registra tentativo fallito per IP
        $this->recordFailedAttempt($client_ip, 'ip');
        
        // Registra tentativo fallito per email
        if ($email) {
            $this->recordFailedAttempt($email, 'email');
        }
        
        $this->logSecurityEvent($client_ip, 'FAILED_LOGIN', $email);
    }
    
    public function clearFailedAttempts($email = null) {
        $client_ip = $this->getClientIP();
        
        // Pulisci tentativi per IP
        $this->clearAttempts($client_ip, 'ip');
        
        // Pulisci tentativi per email
        if ($email) {
            $this->clearAttempts($email, 'email');
        }
    }
    
    private function getClientIP() {
        // Gestisce proxy e load balancer
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if ($this->isValidIP($ip)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    private function isValidIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, 
                         FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    
    private function isRateLimitOK($client_ip, $endpoint_type) {
        $key = "rate_limit:{$endpoint_type}:{$client_ip}";
        $limit_config = $this->rateLimits[$endpoint_type];
        
        if ($this->redis) {
            $current = $this->redis->get($key) ?: 0;
            return $current < $limit_config['max_requests'];
        } else {
            // Fallback su database
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM security_logs 
                WHERE client_ip = ? AND endpoint_type = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$client_ip, $endpoint_type, $limit_config['window']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] < $limit_config['max_requests'];
        }
    }
    
    private function incrementRateLimit($client_ip, $endpoint_type) {
        $key = "rate_limit:{$endpoint_type}:{$client_ip}";
        $window = $this->rateLimits[$endpoint_type]['window'];
        
        if ($this->redis) {
            $current = $this->redis->incr($key);
            if ($current == 1) {
                $this->redis->expire($key, $window);
            }
        } else {
            // Log su database per fallback
            $this->logSecurityEvent($client_ip, 'API_REQUEST', $endpoint_type);
        }
    }
    
    private function isIPBanned($client_ip) {
        $key = "banned_ip:{$client_ip}";
        
        if ($this->redis) {
            return $this->redis->exists($key);
        } else {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM banned_ips 
                WHERE ip_address = ? AND expires_at > NOW()
            ");
            $stmt->execute([$client_ip]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        }
    }
    
    private function banIP($client_ip, $duration) {
        $key = "banned_ip:{$client_ip}";
        
        if ($this->redis) {
            $this->redis->setex($key, $duration, json_encode([
                'banned_at' => time(),
                'expires_at' => time() + $duration
            ]));
        } else {
            // Salva su database
            $stmt = $this->db->prepare("
                INSERT INTO banned_ips (ip_address, banned_at, expires_at) 
                VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))
                ON DUPLICATE KEY UPDATE 
                banned_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$client_ip, $duration, $duration]);
        }
        
        $this->logSecurityEvent($client_ip, 'IP_BANNED', "Duration: {$duration}s");
    }
    
    private function getFailedAttempts($identifier, $type) {
        $key = "failed_attempts:{$type}:{$identifier}";
        $window = $this->rateLimits['login']['window'];
        
        if ($this->redis) {
            return $this->redis->get($key) ?: 0;
        } else {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM failed_attempts 
                WHERE identifier = ? AND attempt_type = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$identifier, $type, $window]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'];
        }
    }
    
    private function recordFailedAttempt($identifier, $type) {
        $key = "failed_attempts:{$type}:{$identifier}";
        $window = $this->rateLimits['login']['window'];
        
        if ($this->redis) {
            $current = $this->redis->incr($key);
            if ($current == 1) {
                $this->redis->expire($key, $window);
            }
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO failed_attempts (identifier, attempt_type, client_ip, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$identifier, $type, $this->getClientIP()]);
        }
    }
    
    private function clearAttempts($identifier, $type) {
        $key = "failed_attempts:{$type}:{$identifier}";
        
        if ($this->redis) {
            $this->redis->del($key);
        } else {
            $stmt = $this->db->prepare("
                DELETE FROM failed_attempts 
                WHERE identifier = ? AND attempt_type = ?
            ");
            $stmt->execute([$identifier, $type]);
        }
    }
    
    private function detectSuspiciousActivity($client_ip, $user_agent) {
    
        // Patterns sospetti
        $suspicious_patterns = [
            '/bot|crawler|spider/i',
            '/curl|wget|python|perl/i',
            '/sqlmap|nikto|nessus/i',
            '/masscan|zmap|nmap/i'
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return true;
            }
        }
        
        // Controlla richieste troppo veloci
        if ($this->redis) {
            $key = "request_timing:{$client_ip}";
            $last_request = $this->redis->get($key);
            $current_time = microtime(true);
            
            if ($last_request && ($current_time - $last_request) < 0.1) { // Meno di 100ms
                return true;
            }
            
            $this->redis->setex($key, 60, $current_time);
        }
        
        return false;
    }
    
    private function logSecurityEvent($client_ip, $event_type, $details = '') {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO security_logs (client_ip, event_type, details, user_agent, request_uri, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$client_ip, $event_type, $details, $user_agent, $request_uri]);
        } catch (Exception $e) {
            // Silent fail per non bloccare l'applicazione
            error_log("Security log error: " . $e->getMessage());
        }
    }
    
    private function sendSecurityResponse($status_code, $message) {
        http_response_code($status_code);
        echo json_encode([
            'status' => $status_code,
            'error' => true,
            'message' => $message,
            'timestamp' => time()
        ]);
        exit;
    }
    
    public function getSecurityHeaders() {
        return [
            'X-Frame-Options: DENY',
            'X-Content-Type-Options: nosniff',
            'X-XSS-Protection: 1; mode=block',
            'Referrer-Policy: strict-origin-when-cross-origin',
            'Content-Security-Policy: default-src \'self\'',
            'Strict-Transport-Security: max-age=31536000; includeSubDomains'
        ];
    }
    
    public function applySecurityHeaders() {
        foreach ($this->getSecurityHeaders() as $header) {
            header($header);
        }
    }
}
?>
