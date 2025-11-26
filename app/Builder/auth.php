<?php
require_once '../config/database.php';
require_once '../config/jwt.php';
require_once '../models/User.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$jwt = new JWTHandler();

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', $path);
$action = end($path_parts);

switch($method) {
    case 'POST':
        if($action === 'login') {
            // Login
            $data = json_decode(file_get_contents("php://input"), true);
            
            if(empty($data['email']) || empty($data['password'])) {
                sendResponse(400, null, 'Email e password sono obbligatori');
            }
            
            $userData = $user->login($data['email'], $data['password']);
            
            if($userData) {
                $token = $jwt->generateToken($userData);
                
                sendResponse(200, [
                    'token' => $token,
                    'user' => $userData
                ], 'Login effettuato con successo');
            } else {
                sendResponse(401, null, 'Credenziali non valide');
            }
            
        } elseif($action === 'logout') {
            // Logout (lato client rimuoverà il token)
            sendResponse(200, null, 'Logout effettuato con successo');
            
        } elseif($action === 'change-password') {
            // Change password
            $token = $jwt->getTokenFromHeader();
            
            if(!$token) {
                sendResponse(401, null, 'Token mancante');
            }
            
            $tokenData = $jwt->validateToken($token);
            if(!$tokenData) {
                sendResponse(401, null, 'Token non valido');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if(!isset($input['currentPassword']) || !isset($input['newPassword'])) {
                sendResponse(400, null, 'Password attuale e nuova password sono obbligatorie');
            }
            
            if(empty($input['currentPassword']) || empty($input['newPassword'])) {
                sendResponse(400, null, 'Le password non possono essere vuote');
            }
            
            // Verifica password attuale
            $userData = $user->getById($tokenData['user_id']);
            if(!$userData) {
                sendResponse(404, null, 'Utente non trovato');
            }
            
            if(!password_verify($input['currentPassword'], $userData['password'])) {
                sendResponse(400, null, 'Password attuale non corretta');
            }
            
            // Aggiorna password
            if($user->updatePassword($tokenData['user_id'], $input['newPassword'])) {
                sendResponse(200, ['message' => 'Password aggiornata con successo']);
            } else {
                sendResponse(500, null, 'Errore nell\'aggiornamento della password');
            }
            
        } elseif($action === 'update-site-url') {
            // Update site URL
            $token = $jwt->getTokenFromHeader();
            
            if(!$token) {
                sendResponse(401, null, 'Token mancante');
            }
            
            $tokenData = $jwt->validateToken($token);
            if(!$tokenData) {
                sendResponse(401, null, 'Token non valido');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if(!isset($input['site_url']) || empty(trim($input['site_url']))) {
                sendResponse(400, null, 'URL del sito è richiesto');
            }
            
            $site_url = trim($input['site_url']);
            
            // Valida l'URL
            if(!filter_var($site_url, FILTER_VALIDATE_URL)) {
                sendResponse(400, null, 'URL non valido');
            }
            
            // Aggiorna site_url
            if($user->updateSiteUrl($tokenData['user_id'], $site_url)) {
                sendResponse(200, ['message' => 'URL del sito aggiornato con successo', 'site_url' => $site_url]);
            } else {
                sendResponse(500, null, 'Errore nell\'aggiornamento dell\'URL');
            }
            
        } else {
            sendResponse(404, null, 'Endpoint non trovato');
        }
        break;
        
    case 'GET':
        if($action === 'me') {
            // Get current user info
            $token = $jwt->getTokenFromHeader();
            
            if(!$token) {
                sendResponse(401, null, 'Token mancante');
            }
            
            $tokenData = $jwt->validateToken($token);
            
            if(!$tokenData) {
                sendResponse(401, null, 'Token non valido');
            }
            
            $userData = $user->getById($tokenData['user_id']);
            
            if($userData) {
                sendResponse(200, $userData);
            } else {
                sendResponse(404, null, 'Utente non trovato');
            }
        } elseif($action === 'change-password') {
            // Change password
            $token = $jwt->getTokenFromHeader();
            
            if(!$token) {
                sendResponse(401, null, 'Token mancante');
            }
            
            $tokenData = $jwt->validateToken($token);
            if(!$tokenData) {
                sendResponse(401, null, 'Token non valido');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if(!isset($input['currentPassword']) || !isset($input['newPassword'])) {
                sendResponse(400, null, 'Password attuale e nuova password sono obbligatorie');
            }
            
            // Verifica password attuale
            $userData = $user->getById($tokenData['user_id']);
            if(!$userData || !password_verify($input['currentPassword'], $userData['password'])) {
                sendResponse(400, null, 'Password attuale non corretta');
            }
            
            // Aggiorna password
            if($user->updatePassword($tokenData['user_id'], $input['newPassword'])) {
                sendResponse(200, ['message' => 'Password aggiornata con successo']);
            } else {
                sendResponse(500, null, 'Errore nell\'aggiornamento della password');
            }
        } else {
            sendResponse(404, null, 'Endpoint non trovato');
        }
        break;
        
    default:
        sendResponse(405, null, 'Metodo non consentito');
        break;
}
