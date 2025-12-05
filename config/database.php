<?php

// Carica le funzioni (per loadEnv)
require_once __DIR__ . '/../core/helpers.php';

// Carica le variabili d'ambiente
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
        // 'example' => [
        //     'dbType' => 'MySQL',
        //     'host' => 'localhost',
        //     'dbname' => 'example_db',
        //     'user' => 'example_user',
        //     'pass' => 'example_pass',
        //     'charset' => 'utf8mb4',
        // ]
    ];
    
    return $databases[$environment] ?? $databases['development'];
}

// Compatibilità retroattiva: ritorna development di default
return getDatabaseConfig();

