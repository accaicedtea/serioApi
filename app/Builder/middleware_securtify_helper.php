<?php
// Middleware di protezione per tutti gli endpoint API
require_once '../middleware/security.php';
require_once '../config/database.php';

function applySecurity($endpoint_type = 'api') {
    $database = new Database();
    $db = $database->getConnection();
    $security = new SecurityMiddleware($db);
    
    // Applica header di sicurezza
    $security->applySecurityHeaders();
    
    // Controlla rate limiting
    $security->checkRateLimit($endpoint_type);
    
    return $security;
}

// Funzione per endpoint con rate limiting più stretto
function applyStrictSecurity() {
    return applySecurity('strict_endpoints');
}

// Funzione per endpoint di login
function applyLoginSecurity() {
    return applySecurity('login');
}
?>