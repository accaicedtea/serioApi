<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/database.php';

function applySecurity($endpoint, $limit = 100, $window = 60) {
    $database = new Database();
    $db = $database->getConnection();
    $security = new SecurityMiddleware($db);
    
    $security->applySecurityHeaders();
    $security->checkRateLimit($endpoint, $limit, $window);
    
    return $security;
}
