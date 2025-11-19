<?php
namespace Core;

class Route {
    private static $routes = [];

    public static function resolve() {
        // Carica le route dal file di configurazione
        self::$routes = require __DIR__ . '/../config/routes.php';
        
        // Ottieni l'URL richiesto
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }
        
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Cerca una corrispondenza esatta
        if (isset(self::$routes[$uri])) {
            $route = self::$routes[$uri];
            if (!isset($route['method']) || $route['method'] === $method) {
                return [
                    'controller' => $route['controller'],
                    'method' => $route['action'] ?? 'index',
                    'params' => []
                ];
            }
        }
        
        // Fallback: routing automatico
        // Esempio: /user/profile/123 → UserController::profile(123)
        $url = explode('/', trim($uri, '/'));
        $controller = !empty($url[0]) ? ucfirst($url[0]) . 'Controller' : 'HomeController';
        $method = $url[1] ?? 'index';
        $params = array_slice($url, 2);
        
        return [
            'controller' => $controller,
            'method' => $method,
            'params' => $params
        ];
    }
}
