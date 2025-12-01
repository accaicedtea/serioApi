<?php

// Load helper functions (includes loadEnv)
require_once __DIR__ . '/../core/helpers.php';

// Load environment variables
loadEnv();

// Funzione per ottenere la configurazione del database
function getDatabaseConfig($environment = null) {
    // Se non viene passato un environment, usa quello dall'ENV
    if ($environment === null) {
        $environment = env('ENVIRONMENT', 'development');
    }
    
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
            'user' =>  'accaicedtea',
            'pass' =>  '',
            'charset' => 'utf8mb4',
        ],
    ];
    
    return $databases[$environment] ?? $databases['development'];
}

// Compatibilità retroattiva: ritorna development di default
return getDatabaseConfig();

