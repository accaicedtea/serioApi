<?php

// Configuration for different environments
$environment = $_ENV['APP_ENV'] ?? 'development';

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
        'dbname' => 'my_menu_prod',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];

return $databases[$environment];

