<?php
require_once __DIR__ . '/../config/jwt.php';

function requireAuth() {
    $jwt = new JWTHandler();
    $token = $jwt->getTokenFromHeader();
    
    if(!$token) {
        sendResponse(401, null, 'Token di autenticazione mancante');
    }
    
    $tokenData = $jwt->validateToken($token);
    
    if(!$tokenData) {
        sendResponse(401, null, 'Token non valido o scaduto');
    }
    
    return $tokenData;
}

function requireRole($required_role) {
    $user = requireAuth();
    
    if($user['role'] !== $required_role && $user['role'] !== 'admin') {
        sendResponse(403, null, 'Permessi insufficienti');
    }
    
    return $user;
}
