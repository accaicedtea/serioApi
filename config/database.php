<?php

// Load helper functions (includes loadEnv)
require_once __DIR__ . '/../core/helpers.php';

// Load environment variables
loadEnv();

// Configuration for different environments
$environment = env('ENVIRONMENT', 'development');

$databases = [
    'development' => [
        'dbType' => 'MySQL',
        'host' => 'localhost',
        'dbname' => 'my_menu',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'production' => [
        'dbType' => 'MySQL',
        'host' => 'localhost',
        'dbname' =>  'my_accaicedtea',
        'user' =>  'root',
        'pass' =>  '',
        'charset' => 'utf8mb4',
    ],
];

return $databases[$environment];

