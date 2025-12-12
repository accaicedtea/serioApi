<?php

// Controlla se la route corrente richiede una connessione al database
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$databaseRoutes = ['/database', '/generator', '/deploy'];

foreach ($databaseRoutes as $route) {
    if (strpos($currentPath, $route) === 0) {
        // Vai alla home se non c'è connessione
        header('Location: /');
        exit;
        }
        break;
    
}
// Tutte le routes
$routes = [
    // Home routes
    '/' => [
        'controller' => 'HomeController',
        'action' => 'index',
        'method' => 'GET'
    ],
    // Database routes
    '/database' => [
        'controller' => 'DatabaseController',
        'action' => 'index',
        'method' => 'GET'
    ],

    '/database/schema' => [
        'controller' => 'DatabaseController',
        'action' => 'schema',
        'method' => 'GET'
    ],
    
    '/database/tables' => [
        'controller' => 'DatabaseController',
        'action' => 'tables',
        'method' => 'GET'
    ],
    
    '/database/query' => [
        'controller' => 'DatabaseController',
        'action' => 'query',
        'method' => 'GET'
    ],
    
    '/database/stats' => [
        'controller' => 'DatabaseController',
        'action' => 'stats',
        'method' => 'GET'
    ],
    // Generator routes
    '/generator' => [
        'controller' => 'GeneratorController',
        'action' => 'index',
        'method' => 'GET'
    ],
    
    '/generator/save' => [
        'controller' => 'GeneratorController',
        'action' => 'save',
        'method' => 'POST'
    ],
    
    '/generator/security' => [
        'controller' => 'GeneratorController',
        'action' => 'createSecurityTables',
        'method' => 'POST'
    ],
    
    '/generator/views' => [
        'controller' => 'GeneratorController',
        'action' => 'views',
        'method' => 'GET'
    ],
    
    '/generator/test-view' => [
        'controller' => 'GeneratorController',
        'action' => 'testView',
        'method' => 'POST'
    ],
    
    '/generator/save-view' => [
        'controller' => 'GeneratorController',
        'action' => 'saveView',
        'method' => 'POST'
    ],
    
    '/generator/toggle-view' => [
        'controller' => 'GeneratorController',
        'action' => 'toggleView',
        'method' => 'POST'
    ],
    
    '/generator/delete-view' => [
        'controller' => 'GeneratorController',
        'action' => 'deleteView',
        'method' => 'POST'
    ],
    // API Builder routes
    '/generator/builder' => [
        'controller' => 'ApiBuilderController',
        'action' => 'index',
        'method' => 'GET'
    ],
    
    '/builder/generate' => [
        'controller' => 'ApiBuilderController',
        'action' => 'generate',
        'method' => 'POST'
    ],
    // FTP Deploy routes
    '/deploy' => [
        'controller' => 'FtpDeployController',
        'action' => 'index',
        'method' => 'GET'
    ],
    
    '/deploy/upload' => [
        'controller' => 'FtpDeployController',
        'action' => 'upload',
        'method' => 'POST'
    ],
    
    '/deploy/test' => [
        'controller' => 'FtpDeployController',
        'action' => 'test',
        'method' => 'POST'
    ],
    
    '/deploy/move' => [
        'controller' => 'FtpDeployController',
        'action' => 'move',
        'method' => 'POST'
    ],
    
    // '/nuovo/esempio' => [
    //     'controller' => 'NuovoController',
    //     'action' => 'index',
    //     'method' => 'GET'
    // ],

    
];

return $routes;