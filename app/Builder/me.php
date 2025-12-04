<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/User.php';

$user_data = requireAuth();

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$userData = $user->getById($user_data['user_id']);

if($userData) {
    sendResponse(200, $userData);
} else {
    sendResponse(404, null, 'Utente non trovato');
}
