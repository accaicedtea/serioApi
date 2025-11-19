<?php
namespace Core;

class Security {
    private static $rateLimits = [];
    
    public static function initCsrf() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function csrfToken() {
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function checkCsrf($token) {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
    
    public static function checkRateLimit($endpoint, $limit, $window) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = $ip . ':' . $endpoint;
        $now = time();
        
        if (!isset(self::$rateLimits[$key])) {
            self::$rateLimits[$key] = ['count' => 0, 'reset' => $now + $window];
        }
        
        if ($now > self::$rateLimits[$key]['reset']) {
            self::$rateLimits[$key] = ['count' => 0, 'reset' => $now + $window];
        }
        
        self::$rateLimits[$key]['count']++;
        
        if (self::$rateLimits[$key]['count'] > $limit) {
            http_response_code(429);
            die(json_encode([
                'error' => 'Rate limit exceeded',
                'retry_after' => self::$rateLimits[$key]['reset'] - $now
            ]));
        }
        
        return true;
    }
    
    public static function requireAuth() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (empty($authHeader)) {
            http_response_code(401);
            die(json_encode(['error' => 'Authentication required']));
        }
        
        // Verifica token (implementa la tua logica)
        if (!self::validateToken($authHeader)) {
            http_response_code(401);
            die(json_encode(['error' => 'Invalid token']));
        }
        
        return true;
    }
    
    private static function validateToken($token) {
        // TODO: Implementa la validazione del token
        // Per ora accetta qualsiasi token non vuoto
        return !empty($token);
    }
    
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}
