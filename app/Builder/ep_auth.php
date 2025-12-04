<?php
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../models/User.php';

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
        } else {
            sendResponse(404, null, 'Endpoint non trovato');
        }
        break;
        
    case 'GET':
        if($action === 'me') {
            require_once __DIR__ . '/../middleware/auth.php';
            $tokenData = requireAuth();
            
            $userData = $user->getById($tokenData['user_id']);
            
            if($userData) {
                sendResponse(200, $userData);
            } else {
                sendResponse(404, null, 'Utente non trovato');
            }
        } else {
            sendResponse(404, null, 'Endpoint non trovato');
        }
        break;
        
    default:
        sendResponse(405, null, 'Metodo non consentito');
        break;
}
