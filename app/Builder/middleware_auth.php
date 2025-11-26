<?php
require_once '../config/jwt.php';

function requireAuth() {
    $jwt = new JWTHandler();
    $token = $jwt->getTokenFromHeader();
    
    if(!$token) {
        sendResponse(401, null, 'Token di autenticazione mancante');
        exit;
    }
    
    $tokenData = $jwt->validateToken($token);
    
    if(!$tokenData) {
        sendResponse(401, null, 'Token non valido o scaduto');
        exit;
    }
    
    // Restituisce i dati dell'utente dal token
    return $tokenData;
}

function requireRole($required_role) {
    $user = requireAuth();
    
    if($user['role'] !== $required_role && $user['role'] !== 'admin') {
        sendResponse(403, null, 'Permessi insufficienti');
        exit;
    }
    
    return $user;
}
