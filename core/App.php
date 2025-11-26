<?php
namespace Core;


class App {
    protected $controller = 'HomeController';
    protected $method = 'index';
    protected $params = [];

    public function run() {
        $route = Route::resolve();
        $this->controller = $route['controller'];
        $this->method = $route['method'];
        $this->params = $route['params'];

        $controllerFile = __DIR__ . '/../app/Controllers/' . $this->controller . '.php';
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            $controllerClass = '\App\Controllers\\' . $this->controller;
            $controller = new $controllerClass();
            if (method_exists($controller, $this->method)) {
                call_user_func_array([$controller, $this->method], $this->params);
            } else {
                http_response_code(404);
                echo 'Metodo non trovato';
            }
        } else {
            http_response_code(404);
            echo 'Controller non trovato';
        }
    }
}
