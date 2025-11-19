<?php
namespace App\Controllers;

use Core\Controller;
use Core\Security;
use Core\Database;

class ApiBuilderController extends Controller {
    private $configFile = __DIR__ . '/../../config/api_config.json';
    private $outputPath = __DIR__ . '/../../generated-api';
    private $dbConfig;
    private $databaseName;
    
    public function index() {
        $config = $this->loadConfig();
        $this->loadDatabaseConfig();
        
        // Trova il database corrente nella configurazione
        $currentDbConfig = $config[$this->databaseName] ?? [];
        
        // Filtra le tabelle abilitate
        $enabledTables = $this->getEnabledTables($currentDbConfig);
        
        // Conta le viste personalizzate ABILITATE
        $views = $this->getEnabledViews($config);
        
        $data = [
            'title' => 'API Builder - Generatore',
            'config' => $currentDbConfig,
            'databaseName' => $this->databaseName,
            'enabledCount' => count($enabledTables),
            'viewsCount' => count($views),
            'outputPath' => $this->outputPath,
        ];
        
        $this->view('/generator/builder', $data);
    }
    
    public function generate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /generator/builder');
            exit;
        }
        
        if (!Security::checkCsrf($_POST['csrf_token'] ?? '')) {
            die('Token CSRF non valido');
        }
        
        try {
            $config = $this->loadConfig();
            $this->loadDatabaseConfig();
            
            // Usa la configurazione del database corrente
            $currentDbConfig = $config[$this->databaseName] ?? [];
            
            if (empty($currentDbConfig)) {
                throw new \Exception("Configurazione non trovata per il database: {$this->databaseName}");
            }
            
            // Crea la struttura delle cartelle
            $this->createDirectoryStructure();
            
            // Genera i file base
            $this->generateConfigFile();
            $this->generateAppConfig();
            $this->copyApiConfig();
            $this->generateDatabaseClass();
            $this->generateSecurityClass();
            $this->generateRouterClass();
            $this->generateCacheClass();
            
            $this->generateIndexFile();
            $this->generateTestHtml();
            $this->generateHtaccess();
            $this->generateRootIndex();
            $this->protectConfigFolder();
            
            // Genera modelli e controller per ogni tabella abilitata
            foreach ($currentDbConfig as $tableName => $tableConfig) {
                // Salta _views e le viste virtuali  
                if ($tableName === '_views' || substr($tableName, 0, 6) === '_view_') {
                    continue;
                }
                
                if (isset($tableConfig['enabled']) && $tableConfig['enabled'] === true) {
                    $columns = $this->getTableColumns($tableName);
                    $this->generateModel($tableName, $columns);
                    $this->generateController($tableName, $tableConfig, $columns);
                }
            }
            
            // Genera ViewsController se ci sono viste abilitate
            $views = $this->getEnabledViews($config);
            if (!empty($views)) {
                $this->generateViewsController($views);
            }
            
            // Genera AuthController
            $this->generateAuthController();
            
            // Genera JWT helper
            $this->generateJwtHelper();
            
            // Genera helpers.php
            $this->generateHelpersFile();
            
            // Genera routes.php
            $this->generateRoutes($config);
            
            // Genera README.md
            $this->generateReadme($config);
            
            // Genera router.php per PHP built-in server
            $this->generateRouterPhp();
            
            header('Location: /generator/builder?success=1');
            exit;
        } catch (\Exception $e) {
            header('Location: /generator/builder?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
    
    private function getEnabledViews($config) {
        $views = [];
        $this->loadDatabaseConfig();
        
        // Controlla se ci sono viste nel database corrente
        if (isset($config[$this->databaseName]['_views'])) {
            foreach ($config[$this->databaseName]['_views'] as $viewName => $viewConfig) {
                // Verifica se la vista virtuale è abilitata
                if (isset($config[$this->databaseName]['_view_' . $viewName]['enabled']) 
                    && $config[$this->databaseName]['_view_' . $viewName]['enabled'] === true) {
                    $views[$viewName] = $viewConfig;
                }
            }
        }
        
        return $views;
    }
    
    private function getEnabledTables($currentDbConfig) {
        return array_filter($currentDbConfig, function($table, $key) {
            return $key !== '_views' 
                && substr($key, 0, 6) !== '_view_' 
                && isset($table['enabled']) 
                && $table['enabled'] === true;
        }, ARRAY_FILTER_USE_BOTH);
    }
    
    private function loadDatabaseConfig() {
        if ($this->dbConfig === null) {
            $this->dbConfig = include __DIR__ . '/../../config/database.php';
            $this->databaseName = $this->dbConfig['dbname'] ?? 'unknown';
        }
    }
    
    private function generateRoutes($config) {
        $this->loadDatabaseConfig();
        
        $content = "<?php\n// Auto-generated routes\n\n";
        
        $currentDbConfig = $config[$this->databaseName] ?? [];
        
        foreach ($currentDbConfig as $tableName => $tableConfig) {
            // Salta _views e le viste virtuali
            if ($tableName === '_views' || substr($tableName, 0, 6) === '_view_') {
                continue;
            }
            
            if (isset($tableConfig['enabled']) && $tableConfig['enabled'] === true) {
                $className = $this->toCamelCase($tableName);
                $content .= "// {$tableName} routes\n";
                $content .= "\$router->get('/api/{$tableName}', ['Controllers\\\\{$className}Controller', 'index']);\n";
                $content .= "\$router->get('/api/{$tableName}/{id}', ['Controllers\\\\{$className}Controller', 'show']);\n";
                $content .= "\$router->post('/api/{$tableName}', ['Controllers\\\\{$className}Controller', 'store']);\n";
                $content .= "\$router->put('/api/{$tableName}/{id}', ['Controllers\\\\{$className}Controller', 'update']);\n";
                $content .= "\$router->delete('/api/{$tableName}/{id}', ['Controllers\\\\{$className}Controller', 'destroy']);\n\n";
            }
        }
        
        // Aggiungi route per viste personalizzate
        $views = $this->getEnabledViews($config);
        if (!empty($views)) {
            $content .= "// Custom views routes\n";
            $content .= "\$router->get('/api/views/{name}', ['Controllers\\\\ViewsController', 'execute']);\n\n";
        }
        
        // Aggiungi route per autenticazione
        $content .= "// Authentication routes\n";
        $content .= "\$router->post('/api/auth/login', ['Controllers\\\\AuthController', 'login']);\n";
        $content .= "\$router->post('/api/auth/register', ['Controllers\\\\AuthController', 'register']);\n";
        $content .= "\$router->get('/api/auth/me', ['Controllers\\\\AuthController', 'me']);\n";
        
        file_put_contents($this->outputPath . '/config/routes.php', $content);
    }
    
    private function generateReadme($config) {
        $this->loadDatabaseConfig();
        
        $currentDbConfig = $config[$this->databaseName] ?? [];
        
        $enabledTables = $this->getEnabledTables($currentDbConfig);
        
        $content = "# Generated API\n\n";
        $content .= "Auto-generated RESTful API with PHP.\n\n";
        $content .= "## Database: {$this->databaseName}\n\n";
        $content .= "## Deployment on Web Server (Apache/Nginx)\n\n";
        $content .= "1. Upload this entire folder to your web server\n";
        $content .= "2. Point your domain/subdomain document root to the `public` folder\n";
        $content .= "3. Ensure the database credentials in `config/database.php` are correct\n";
        $content .= "4. The `.htaccess` file will handle URL rewriting automatically\n\n";
        $content .= "## Available Endpoints\n\n";
        
        foreach ($enabledTables as $tableName => $tableConfig) {
            $content .= "### {$tableName}\n\n";
            $content .= "- `GET /api/{$tableName}` - List all (limit/offset params)\n";
            $content .= "- `GET /api/{$tableName}/{id}` - Get single item\n";
            $content .= "- `POST /api/{$tableName}` - Create new\n";
            $content .= "- `PUT /api/{$tableName}/{id}` - Update\n";
            $content .= "- `DELETE /api/{$tableName}/{id}` - Delete\n\n";
        }
        
        $views = $this->getEnabledViews($config);
        if (!empty($views)) {
            $content .= "## Custom Views\n\n";
            foreach ($views as $viewName => $view) {
                $content .= "### {$viewName}\n";
                $content .= "- `GET /api/views/{$viewName}`\n";
                $content .= "- Description: " . ($view['description'] ?? 'N/A') . "\n\n";
            }
        }
        
        $content .= "Generated on: " . date('Y-m-d H:i:s') . "\n";
        
        file_put_contents($this->outputPath . '/README.md', $content);
    }
    
    private function createDirectoryStructure() {
        $dirs = [
            $this->outputPath,
            $this->outputPath . '/config',
            $this->outputPath . '/core',
            $this->outputPath . '/Models',
            $this->outputPath . '/Controllers',
            $this->outputPath . '/public'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    private function generateConfigFile() {
        $this->loadDatabaseConfig();
        
        $content = "<?php\n";
        $content .= "return [\n";
        $content .= "    'host' => '{$this->dbConfig['host']}',\n";
        $content .= "    'dbname' => '{$this->dbConfig['dbname']}',\n";
        $content .= "    'user' => '{$this->dbConfig['user']}',\n";
        $content .= "    'pass' => '{$this->dbConfig['pass']}',\n";
        $content .= "    'charset' => '{$this->dbConfig['charset']}'\n";
        $content .= "];\n";
        
        file_put_contents($this->outputPath . '/config/database.php', $content);
    }
    
    private function generateAppConfig() {
        $content = "<?php\nreturn [\n    'debug' => false,\n    'timezone' => 'Europe/Rome'\n];\n";
        file_put_contents($this->outputPath . '/config/app.php', $content);
    }
    
    private function copyApiConfig() {
        copy($this->configFile, $this->outputPath . '/config/api_config.json');
    }
    
    private function protectConfigFolder() {
        $htaccess = "Deny from all\n";
        file_put_contents($this->outputPath . '/config/.htaccess', $htaccess);
    }
    
    private function generateDatabaseClass() {
        $content = <<<'PHP'
<?php
namespace Core;

class Database {
    private $pdo;

    public function __construct() {
        $config = require __DIR__ . '/../config/database.php';
        
        if ($config === null) {
            throw new \Exception('Database configuration file not found.');
        }
        if (!isset($config['host'], $config['dbname'], $config['user'], $config['pass'], $config['charset'])) {
            throw new \Exception('Database configuration is incomplete.');
        }
        if($config['host'] == 'localhost'){
            $config['host'] = '127.0.0.1';
        }

        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        $this->pdo = new \PDO($dsn, $config['user'], $config['pass'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    public function getConnection() {
        return $this->pdo;
    }
}

PHP;
        file_put_contents($this->outputPath . '/core/Database.php', $content);
    }
    
    private function generateSecurityClass() {
        $content = <<<'PHP'
<?php
namespace Core;

class Security {
    public static function validateAuth($requiredLevel = 'all') {
        if ($requiredLevel === 'all') {
            return true;
        }
        
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        // Usa substr invece di str_starts_with per compatibilità
        if (empty($authHeader) || substr($authHeader, 0, 7) !== 'Bearer ') {
            sendResponse(401, null, 'Unauthorized');
        }
        
        return true;
    }
    
    public static function checkRateLimit($tableName, $limit, $window) {
        return true;
    }
}

PHP;
        file_put_contents($this->outputPath . '/core/Security.php', $content);
    }
    
    private function generateRouterClass() {
        $content = <<<'PHP'
<?php
namespace Core;

class Router {
    private $routes = [];
    
    public function get($path, $handler) {
        $this->addRoute('GET', $path, $handler);
    }
    
    public function post($path, $handler) {
        $this->addRoute('POST', $path, $handler);
    }
    
    public function put($path, $handler) {
        $this->addRoute('PUT', $path, $handler);
    }
    
    public function delete($path, $handler) {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    private function addRoute($method, $path, $handler) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }
    
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Normalizza il path rimuovendo il base path
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $basePath = str_replace('\\', '/', dirname($scriptName));
        
        // Rimuovi il base path dall'URI
        if ($basePath !== '/' && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        
        // Normalizza
        $uri = '/' . trim($uri, '/');
        
        // Gestisci PUT e DELETE tramite POST con _method
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            
            // Converti {param} in regex
            $pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $uri, $matches)) {
                // Estrai solo i parametri nominati
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $this->callHandler($route['handler'], $params);
            }
        }
        
        // Nessuna route trovata - debug info
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Endpoint not found',
            'uri' => $uri,
            'method' => $method,
            'script_name' => $scriptName,
            'base_path' => $basePath,
            'original_uri' => \$_SERVER['REQUEST_URI']
        ]);
    }
    
    private function callHandler($handler, $params) {
        if (is_array($handler)) {
            [$controller, $method] = $handler;
            $instance = new $controller();
            return $instance->$method($params);
        }
        
        if (is_callable($handler)) {
            return call_user_func($handler, $params);
        }
    }
}

PHP;
        file_put_contents($this->outputPath . '/core/Router.php', $content);
    }
    
    private function generateIndexFile() {
        $content = <<<'PHP'
<?php
// Helper function for JSON responses
function sendResponse($statusCode = 200, $data = null, $message = null) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    $response = [];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($message !== null) {
        $response['message'] = $message;
    }
    if ($statusCode >= 400) {
        $response['error'] = $message ?? 'An error occurred';
    }
    
    echo json_encode($response);
    exit;
}

// Load additional helpers
require_once __DIR__ . '/../core/helpers.php';

// Autoloader
spl_autoload_register(function ($class) {
    $base_dir = __DIR__ . '/../';
    
    // Converti namespace in percorso file
    $path = str_replace('\\', '/', $class) . '.php';
    
    // Prova prima con il case esatto
    $file = $base_dir . $path;
    if (file_exists($file)) {
        require $file;
        return;
    }
    
    // Prova con il namespace in lowercase (per cartelle core, models, controllers)
    $parts = explode('/', $path);
    if (count($parts) > 1) {
        $parts[0] = strtolower($parts[0]); // Prima parte in lowercase (Core -> core)
        $file = $base_dir . implode('/', $parts);
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    exit;
});

set_exception_handler(function($exception) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    exit;
});

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load config
$appConfig = require __DIR__ . '/../config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'UTC');

// Initialize router
$router = new Core\Router();

// Load routes
require __DIR__ . '/../config/routes.php';

// Dispatch
$router->dispatch();

PHP;
        file_put_contents($this->outputPath . '/public/index.php', $content);
    }
    
    private function generateHtaccess() {
        // Public .htaccess
        $publicHtaccess = <<<'HTACCESS'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
HTACCESS;
        file_put_contents($this->outputPath . '/public/.htaccess', $publicHtaccess);
        
        // Root .htaccess - reindirizza tutto a index.php nella root
        $rootHtaccess = <<<'HTACCESS'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
HTACCESS;
        file_put_contents($this->outputPath . '/.htaccess', $rootHtaccess);
    }
    
    private function generateRootIndex() {
        $content = <<<'PHP'
<?php
// Questo file funge da proxy per public/index.php
// Salva il SCRIPT_NAME originale e calcola il base path corretto
$originalScriptName = $_SERVER['SCRIPT_NAME'];
$originalUri = $_SERVER['REQUEST_URI'];

// Il base path è la directory in cui si trova questo index.php
// Es: se SCRIPT_NAME è /my_menu/index.php, base_path è /my_menu
$basePath = dirname($originalScriptName);
$basePath = rtrim($basePath, '/');

// Rimuovi il base path dall'URI
if ($basePath !== '' && $basePath !== '/' && strpos($originalUri, $basePath) === 0) {
    $_SERVER['REQUEST_URI'] = substr($originalUri, strlen($basePath));
}

// Imposta SCRIPT_NAME come se fossimo in public/
$_SERVER['SCRIPT_NAME'] = '/public/index.php';

require __DIR__ . '/public/index.php';
PHP;
        file_put_contents($this->outputPath . '/index.php', $content);
    }
    
    private function generateCacheClass() {
        $content = <<<'PHP'
<?php
namespace Core;

class Cache {
    private static $storage = [];
    
    public static function get($key) {
        return self::$storage[$key] ?? null;
    }
    
    public static function set($key, $value, $ttl = 300) {
        self::$storage[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }
    
    public static function has($key) {
        if (!isset(self::$storage[$key])) {
            return false;
        }
        
        if (self::$storage[$key]['expires'] < time()) {
            unset(self::$storage[$key]);
            return false;
        }
        
        return true;
    }
    
    public static function delete($key) {
        unset(self::$storage[$key]);
    }
    
    public static function clear() {
        self::$storage = [];
    }
}

PHP;
        file_put_contents($this->outputPath . '/core/Cache.php', $content);
    }
    
    private function generateTestHtml() {
        $content = <<<'HTML'
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h1 { color: #333; }
        .endpoint { background: #f4f4f4; padding: 15px; margin: 10px 0; border-radius: 5px; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 3px; }
        button:hover { background: #0056b3; }
        pre { background: #282c34; color: #abb2bf; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>API Test Interface</h1>
    <p>Test your API endpoints here</p>
    
    <div class="endpoint">
        <h3>Test Endpoint</h3>
        <input type="text" id="endpoint" placeholder="/api/allergens" style="width: 70%; padding: 8px;">
        <button onclick="testEndpoint()">Test GET</button>
    </div>
    
    <div id="result">
        <h3>Response:</h3>
        <pre id="output">No response yet...</pre>
    </div>
    
    <script>
        async function testEndpoint() {
            const endpoint = document.getElementById('endpoint').value;
            const output = document.getElementById('output');
            
            try {
                const response = await fetch(endpoint);
                const data = await response.json();
                output.textContent = JSON.stringify(data, null, 2);
            } catch (error) {
                output.textContent = 'Error: ' + error.message;
            }
        }
    </script>
</body>
</html>

HTML;
        file_put_contents($this->outputPath . '/public/test.html', $content);
    }
    
    private function generateModel($tableName, $columns) {
        $className = $this->toCamelCase($tableName);
        $primaryKey = $this->getPrimaryKey($columns);
        
        $content = <<<PHP
<?php
namespace Models;

class {$className} {
    private \$db;
    private \$table = '{$tableName}';
    private \$primaryKey = '{$primaryKey}';
    
    public function __construct(\$pdo) {
        \$this->db = \$pdo;
    }
    
    public function findAll(\$limit = 100, \$offset = 0) {
        \$stmt = \$this->db->prepare("SELECT * FROM {\$this->table} LIMIT :limit OFFSET :offset");
        \$stmt->bindValue(':limit', (int)\$limit, \PDO::PARAM_INT);
        \$stmt->bindValue(':offset', (int)\$offset, \PDO::PARAM_INT);
        \$stmt->execute();
        return \$stmt->fetchAll();
    }
    
    public function findById(\$id) {
        \$stmt = \$this->db->prepare("SELECT * FROM {\$this->table} WHERE {\$this->primaryKey} = :id");
        \$stmt->execute(['id' => \$id]);
        return \$stmt->fetch();
    }
    
    public function create(\$data) {
        \$keys = array_keys(\$data);
        \$fields = implode(', ', \$keys);
        \$placeholders = ':' . implode(', :', \$keys);
        
        \$sql = "INSERT INTO {\$this->table} (\$fields) VALUES (\$placeholders)";
        \$stmt = \$this->db->prepare(\$sql);
        \$stmt->execute(\$data);
        
        return \$this->db->lastInsertId();
    }
    
    public function update(\$id, \$data) {
        \$setParts = [];
        foreach (array_keys(\$data) as \$key) {
            \$setParts[] = "\$key = :\$key";
        }
        \$setClause = implode(', ', \$setParts);
        
        \$sql = "UPDATE {\$this->table} SET \$setClause WHERE {\$this->primaryKey} = :id";
        \$data['id'] = \$id;
        \$stmt = \$this->db->prepare(\$sql);
        return \$stmt->execute(\$data);
    }
    
    public function delete(\$id) {
        \$stmt = \$this->db->prepare("DELETE FROM {\$this->table} WHERE {\$this->primaryKey} = :id");
        return \$stmt->execute(['id' => \$id]);
    }
}

PHP;
        file_put_contents($this->outputPath . "/Models/{$className}.php", $content);
    }
    
    private function generateController($tableName, $config, $columns) {
        $className = $this->toCamelCase($tableName);
        
        $content = <<<PHP
<?php
namespace Controllers;

use Models\\{$className};
use Core\Security;

class {$className}Controller {
    private \$model;
    
    public function __construct() {
        \$this->model = new {$className}(db());
    }
    
    public function index(\$params = []) {
        Security::validateAuth('{$config['select']}');
        
        \$limit = \$_GET['limit'] ?? {$config['max_results']};
        \$offset = \$_GET['offset'] ?? 0;
        
        \$data = \$this->model->findAll(\$limit, \$offset);
        
        sendResponse(200, \$data);
    }
    
    public function show(\$params) {
        Security::validateAuth('{$config['select']}');
        
        \$id = \$params['id'] ?? null;
        if (!\$id) {
            sendResponse(400, null, 'ID required');
        }
        
        \$data = \$this->model->findById(\$id);
        
        if (!\$data) {
            sendResponse(404, null, 'Not found');
        }
        
        sendResponse(200, \$data);
    }
    
    public function store(\$params = []) {
        Security::validateAuth('{$config['insert']}');
        
        \$input = json_decode(file_get_contents('php://input'), true);
        
        if (empty(\$input)) {
            sendResponse(400, null, 'No data provided');
        }
        
        \$id = \$this->model->create(\$input);
        
        sendResponse(201, ['id' => \$id], 'Created successfully');
    }
    
    public function update(\$params) {
        Security::validateAuth('{$config['update']}');
        
        \$id = \$params['id'] ?? null;
        if (!\$id) {
            sendResponse(400, null, 'ID required');
        }
        
        \$input = json_decode(file_get_contents('php://input'), true);
        
        if (empty(\$input)) {
            sendResponse(400, null, 'No data provided');
        }
        
        \$success = \$this->model->update(\$id, \$input);
        
        sendResponse(200, ['success' => \$success], 'Updated successfully');
    }
    
    public function destroy(\$params) {
        Security::validateAuth('{$config['delete']}');
        
        \$id = \$params['id'] ?? null;
        if (!\$id) {
            sendResponse(400, null, 'ID required');
        }
        
        \$success = \$this->model->delete(\$id);
        
        sendResponse(200, ['success' => \$success], 'Deleted successfully');
    }
}

PHP;
        file_put_contents($this->outputPath . "/Controllers/{$className}Controller.php", $content);
    }
    
    private function generateViewsController($views) {
        $content = <<<'PHP'
<?php
namespace Controllers;

use Core\Security;

class ViewsController {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = db();
        $this->config = loadApiConfig();
    }
    
    public function execute($params) {
        $viewName = $params['name'] ?? null;
        
        if (!$viewName) {
            sendResponse(400, null, 'View name required');
        }
        
        $databaseName = getDatabaseName();
        
        if (!isset($this->config[$databaseName]['_views'][$viewName])) {
            sendResponse(404, null, 'View not found');
        }
        
        $viewConfig = $this->config[$databaseName]['_views'][$viewName];
        
        Security::validateAuth($viewConfig['require_auth'] ? 'auth' : 'all');
        
        try {
            $stmt = $this->db->prepare($viewConfig['query']);
            $stmt->execute();
            $data = $stmt->fetchAll();
            
            sendResponse(200, $data);
        } catch (\PDOException $e) {
            sendResponse(500, null, 'Query execution failed: ' . $e->getMessage());
        }
    }
}

PHP;
        file_put_contents($this->outputPath . "/Controllers/ViewsController.php", $content);
    }
    
    private function getTableColumns($tableName) {
        $db = db();
        $stmt = $db->query("DESCRIBE `{$tableName}`");
        return $stmt->fetchAll();
    }
    
    private function getPrimaryKey($columns) {
        foreach ($columns as $column) {
            if ($column['Key'] === 'PRI') {
                return $column['Field'];
            }
        }
        return 'id';
    }
    
    private function toCamelCase($string) {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
    
    private function loadConfig() {
        if (file_exists($this->configFile)) {
            return json_decode(file_get_contents($this->configFile), true) ?? [];
        }
        return [];
    }
    
    private function generateRouterPhp() {
        $content = <<<'PHP'
<?php
// Router for PHP built-in server
if (php_sapi_name() === 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
        return false;
    }
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require_once __DIR__ . '/public/index.php';
}

PHP;
        file_put_contents($this->outputPath . '/router.php', $content);
    }
    
    private function generateAuthController() {
        $content = <<<'PHP'
<?php
namespace Controllers;

use Core\JWT;

class AuthController {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    public function login($params = []) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            sendResponse(400, null, 'Email and password required');
        }
        
        // Cerca utente
        $stmt = $this->db->prepare("SELECT * FROM user_auth WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($password, $user['password'])) {
            sendResponse(401, null, 'Invalid credentials');
        }
        
        // Genera JWT token
        $payload = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'name' => $user['name']
        ];
        
        $token = JWT::encode($payload);
        
        // Aggiorna last_login
        $updateStmt = $this->db->prepare("UPDATE user_auth SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        sendResponse(200, [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role']
            ]
        ], 'Login successful');
    }
    
    public function register($params = []) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $name = $data['name'] ?? '';
        
        if (empty($email) || empty($password) || empty($name)) {
            sendResponse(400, null, 'Email, password and name required');
        }
        
        // Verifica se email già esiste
        $stmt = $this->db->prepare("SELECT id FROM user_auth WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            sendResponse(409, null, 'Email already exists');
        }
        
        // Crea utente
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insertStmt = $this->db->prepare(
            "INSERT INTO user_auth (email, password, name, role) VALUES (?, ?, ?, 'manager')"
        );
        
        if ($insertStmt->execute([$email, $hashedPassword, $name])) {
            $userId = $this->db->lastInsertId();
            sendResponse(201, ['id' => $userId], 'User registered successfully');
        } else {
            sendResponse(500, null, 'Registration failed');
        }
    }
    
    public function me($params = []) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        // Usa substr invece di str_starts_with per compatibilità
        if (empty($authHeader) || substr($authHeader, 0, 7) !== 'Bearer ') {
            sendResponse(401, null, 'Unauthorized');
        }
        
        $token = substr($authHeader, 7);
        $payload = JWT::decode($token);
        
        if (!$payload) {
            sendResponse(401, null, 'Invalid token');
        }
        
        // Recupera dati aggiornati utente
        $stmt = $this->db->prepare("SELECT id, email, name, role, last_login FROM user_auth WHERE id = ? AND is_active = 1");
        $stmt->execute([$payload['user_id']]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$user) {
            sendResponse(404, null, 'User not found');
        }
        
        sendResponse(200, $user);
    }
}

PHP;
        file_put_contents($this->outputPath . "/Controllers/AuthController.php", $content);
    }
    
    private function generateJwtHelper() {
        $content = <<<'PHP'
<?php
namespace Core;

class JWT {
    private static $secret = 'your-secret-key-change-this-in-production';
    private static $algorithm = 'HS256';
    
    /**
     * Genera un JWT token
     */
    public static function encode($payload, $exp = 86400) {
        $header = [
            'typ' => 'JWT',
            'alg' => self::$algorithm
        ];
        
        $payload['iat'] = time();
        $payload['exp'] = time() + $exp;
        
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", self::$secret, true);
        $signatureEncoded = self::base64UrlEncode($signature);
        
        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }
    
    /**
     * Decodifica e verifica un JWT token
     */
    public static function decode($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        $signature = self::base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", self::$secret, true);
        
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }
        
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

PHP;
        file_put_contents($this->outputPath . '/core/JWT.php', $content);
    }
    
    private function generateHelpersFile() {
        $content = <<<'PHP'
<?php
// Helper functions per API generata

use Core\Database;

/**
 * Ottiene una connessione al database con caching statico
 * Usa Dependency Injection pattern mantenendo la comodità di un helper
 */
function db(): PDO {
    static $connection = null;
    
    if ($connection === null) {
        $database = new Database();
        $connection = $database->getConnection();
    }
    
    return $connection;
}

/**
 * Ottiene il nome del database corrente dalla configurazione
 */
function getDatabaseName(): string {
    $dbConfig = require __DIR__ . '/../config/database.php';
    return $dbConfig['dbname'] ?? 'unknown';
}

/**
 * Carica la configurazione API dal file JSON
 */
function loadApiConfig(): array {
    $apiConfigPath = __DIR__ . '/../config/api_config.json';
    
    if (!file_exists($apiConfigPath)) {
        return [];
    }
    
    return json_decode(file_get_contents($apiConfigPath), true) ?? [];
}

/**
 * Escape HTML per prevenire XSS
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

PHP;
        file_put_contents($this->outputPath . '/core/helpers.php', $content);
    }
}