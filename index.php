<?php
// Front controller
require_once './core/helpers.php';
require_once './core/App.php';
require_once './core/Controller.php';
require_once './core/Route.php';
require_once './core/Security.php';
require_once './core/Database.php';

use Core\Security;

// Inizio sessione con opzioni
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// CSRF token
Security::initCsrf();

// AVVIO APP
$app = new Core\App();
$app->run();
