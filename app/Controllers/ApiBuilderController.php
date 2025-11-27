<?php
namespace App\Controllers;

use Core\Controller;
use Core\Security;

class ApiBuilderController extends Controller
{
    private $configFile = __DIR__ . '/../../config/api_config.json';
    private $outputPath = __DIR__ . '/../../generated-api';

    public function index()
    {
        $config = $this->loadConfig();
        $currentDbConfig = $config[getDatabaseName()] ?? [];
        $enabledTables = $this->getEnabledTables($currentDbConfig);

        $data = [
            'title' => 'API Builder - Generatore',
            'config' => $currentDbConfig,
            'databaseName' => getDatabaseName(),
            'enabledCount' => count($enabledTables),
            'outputPath' => $this->outputPath,
        ];

        $this->view('/generator/builder', $data);
    }

    public function generate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /generator/builder');
            exit;
        }

        if (!e($_POST['csrf_token'])) {
            die('Token CSRF non valido');
        }

        try {
            $config = $this->loadConfig();
            $currentDbConfig = $config[getDatabaseName()] ?? [];

            if (empty($currentDbConfig)) {
                throw new \Exception("Configurazione non trovata per il database: " . getDatabaseName());
            }

            // 1. Crea struttura cartelle
            $this->createDirectoryStructure();

            // 2. Genera file di configurazione
            $this->generateConfigFiles();

            // 3. Genera middleware
            $this->generateMiddleware();

            // 4. Genera modelli ed endpoint per ogni tabella abilitata
            $this->generateTablesApi($currentDbConfig);

            // 5. Genera auth endpoint e modello User
            $this->generateAuthApi();

            // 6. Genera .htaccess
            $this->generateHtaccess();

            // 7. Genera documentazione
            $this->generateDocumentation($config);

            header('Location: /generator/builder?success=1');
            exit;
        } catch (\Exception $e) {
            header('Location: /generator/builder?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    // ===== STRUTTURA CARTELLE =====

    private function createDirectoryStructure()
    {
        $dirs = [
            $this->outputPath,
            $this->outputPath . '/config',
            $this->outputPath . '/middleware',
            $this->outputPath . '/models',
            $this->outputPath . '/endpoints',
            $this->outputPath . '/auth'
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    // ===== CONFIGURAZIONE =====

    private function generateConfigFiles()
    {
        // 1. database.php - usa i dati dall'ENV
        $this->generateDatabaseConfig();

        // 2. jwt.php - usa JWT_SECRET dall'ENV
        $this->generateJwtConfig();

        // 3. helpers.php - funzioni globali unificate
        $this->generateHelpersFile();

        // 4. cors.php - CORS headers standalone
        $this->generateCorsFile();

        // 5. api_config.json
        copy($this->configFile, $this->outputPath . '/config/api_config.json');

        // 6. .htaccess per proteggere config
        file_put_contents($this->outputPath . '/config/.htaccess', "Deny from all\n");
    }

    private function generateDatabaseConfig()
    {
        $dbConfig = require __DIR__ . '/../../config/database.php';
        
        // Usa sempre production per le API generate
        $envConfig = $dbConfig;
        if (isset($dbConfig['production'])) {
            $envConfig = $dbConfig['production'];
        } elseif (isset($dbConfig['development'])) {
            $envConfig = $dbConfig['development'];
        }

        $content = <<<PHP
<?php
// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_NAME', '{$envConfig['dbname']}');
define('DB_USER', '{$envConfig['user']}');
define('DB_PASS', '{$envConfig['pass']}');

class Database {
    private \$host = DB_HOST;
    private \$db_name = DB_NAME;
    private \$username = DB_USER;
    private \$password = DB_PASS;
    public \$conn;

    public function getConnection() {
        \$this->conn = null;
        try {
            // Usa 127.0.0.1 invece di localhost per evitare problemi con socket Unix
            \$dsn = "mysql:host=127.0.0.1;dbname=" . \$this->db_name;
            \$this->conn = new PDO(\$dsn, \$this->username, \$this->password);
            
            \$this->conn->exec("set names utf8");
            \$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException \$exception) {
            echo "Connection error: " . \$exception->getMessage();
        }
        return \$this->conn;
    }
}
?>
PHP;
        file_put_contents($this->outputPath . '/config/database.php', $content);
    }

    private function generateJwtConfig()
    {
        $jwtSecret = env('JWT_SECRET', 'd72937b1639933aed25cb50d65f63cb7');

        $content = <<<PHP
<?php

class JWTHandler {
    private \$secret_key = "{$jwtSecret}";
    private \$algorithm = "HS256";
    
    public function generateToken(\$user_data) {
        \$header = json_encode(['typ' => 'JWT', 'alg' => \$this->algorithm]);
        
        \$payload = json_encode([
            'user_id' => \$user_data['id'],
            'email' => \$user_data['email'],
            'role' => \$user_data['role'],
            'name' => \$user_data['name'] ?? '',
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ]);
        
        \$base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(\$header));
        \$base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(\$payload));
        
        \$signature = hash_hmac('sha256', \$base64Header . "." . \$base64Payload, \$this->secret_key, true);
        \$base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(\$signature));
        
        return \$base64Header . "." . \$base64Payload . "." . \$base64Signature;
    }
    
    public function validateToken(\$token) {
        \$parts = explode('.', \$token);
        
        if (count(\$parts) !== 3) {
            return false;
        }
        
        \$header = \$parts[0];
        \$payload = \$parts[1];
        \$signature = \$parts[2];
        
        \$expectedSignature = hash_hmac('sha256', \$header . "." . \$payload, \$this->secret_key, true);
        \$expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(\$expectedSignature));
        
        if (\$signature !== \$expectedSignature) {
            return false;
        }
        
        \$payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], \$payload)), true);
        
        if (\$payloadData['exp'] < time()) {
            return false;
        }
        
        return \$payloadData;
    }
    
    public function getTokenFromHeader() {
        \$headers = getallheaders();
        
        if (!empty(\$headers['Authorization'])) {
            \$authHeader = \$headers['Authorization'];
            if (preg_match('/Bearer\s(\S+)/', \$authHeader, \$matches)) {
                return \$matches[1];
            }
        }
        
        return null;
    }
}

PHP;
        file_put_contents($this->outputPath . '/config/jwt.php', $content);
    }

    private function generateHelpersFile()
    {
        $content = <<<'PHP'
<?php
// ===== HELPER FUNCTIONS UNIFICATE =====

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

/**
 * Invia una risposta JSON standardizzata
 */
function sendResponse($status, $data = null, $message = null) {
    http_response_code($status);
    
    $response = [];
    
    if ($status >= 400) {
        $response['error'] = true;
        $response['message'] = $message ?? 'An error occurred';
    } else {
        if ($data !== null) {
            $response['data'] = $data;
        }
        if ($message !== null) {
            $response['message'] = $message;
        }
    }
    
    $response['status'] = $status;
    $response['timestamp'] = time();
    
    echo json_encode($response);
    exit;
}

/**
 * Escape HTML per prevenire XSS
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

PHP;
        file_put_contents($this->outputPath . '/config/helpers.php', $content);
    }

    private function generateCorsFile()
    {
        $content = <<<'PHP'
<?php
// File CORS standalone per Altervista
// Includi questo file all'inizio di ogni endpoint

// CORS Headers - Più permissivi per debug
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

// Handle preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

PHP;
        file_put_contents($this->outputPath . '/cors.php', $content);
    }

    // ===== MIDDLEWARE =====

    private function generateMiddleware()
    {
        // 1. auth.php
        $this->generateAuthMiddleware();

        // 2. security.php (basico)
        $this->generateSecurityMiddleware();

        // 3. security_helper.php
        $this->generateSecurityHelper();
    }

    private function generateAuthMiddleware()
    {
        $content = <<<'PHP'
<?php
require_once __DIR__ . '/../config/jwt.php';

function requireAuth() {
    $jwt = new JWTHandler();
    $token = $jwt->getTokenFromHeader();
    
    if(!$token) {
        sendResponse(401, null, 'Token di autenticazione mancante');
    }
    
    $tokenData = $jwt->validateToken($token);
    
    if(!$tokenData) {
        sendResponse(401, null, 'Token non valido o scaduto');
    }
    
    return $tokenData;
}

function requireRole($required_role) {
    $user = requireAuth();
    
    if($user['role'] !== $required_role && $user['role'] !== 'admin') {
        sendResponse(403, null, 'Permessi insufficienti');
    }
    
    return $user;
}

PHP;
        file_put_contents($this->outputPath . '/middleware/auth.php', $content);
    }

    private function generateSecurityMiddleware()
    {
        $content = <<<'PHP'
<?php
class SecurityMiddleware {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function checkRateLimit($endpoint, $limit = 100, $window = 60) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = $ip . ':' . $endpoint;
        
        // Verifica se esiste la tabella rate_limits
        try {
            $query = "SELECT COUNT(*) as count FROM rate_limits 
                     WHERE identifier = :identifier 
                     AND timestamp > DATE_SUB(NOW(), INTERVAL :window SECOND)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->bindParam(':window', $window, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];
            
            if ($count >= $limit) {
                http_response_code(429);
                echo json_encode([
                    'error' => true,
                    'message' => 'Rate limit exceeded. Try again later.',
                    'status' => 429
                ]);
                exit;
            }
            
            // Registra la richiesta
            $insertQuery = "INSERT INTO rate_limits (identifier, endpoint, timestamp) VALUES (:identifier, :endpoint, NOW())";
            $insertStmt = $this->db->prepare($insertQuery);
            $insertStmt->bindParam(':identifier', $identifier);
            $insertStmt->bindParam(':endpoint', $endpoint);
            $insertStmt->execute();
            
            // Pulizia vecchi record
            $cleanQuery = "DELETE FROM rate_limits WHERE timestamp < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            $this->db->exec($cleanQuery);
            
        } catch (PDOException $e) {
            // Se la tabella non esiste, ignora il rate limiting
            return true;
        }
        
        return true;
    }
    
    public function applySecurityHeaders() {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

PHP;
        file_put_contents($this->outputPath . '/middleware/security.php', $content);
    }

    private function generateSecurityHelper()
    {
        $content = <<<'PHP'
<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/database.php';

function applySecurity($endpoint, $limit = 100, $window = 60) {
    $database = new Database();
    $db = $database->getConnection();
    $security = new SecurityMiddleware($db);
    
    $security->applySecurityHeaders();
    $security->checkRateLimit($endpoint, $limit, $window);
    
    return $security;
}

PHP;
        file_put_contents($this->outputPath . '/middleware/security_helper.php', $content);
    }

    // ===== GENERAZIONE TABELLE =====

    private function generateTablesApi($currentDbConfig)
    {
        foreach ($currentDbConfig as $tableName => $tableConfig) {
            // Salta _views
            if ($tableName === '_views' || substr($tableName, 0, 6) === '_view_') {
                continue;
            }

            if (isset($tableConfig['enabled']) && $tableConfig['enabled'] === true) {
                $columns = $this->getTableColumns($tableName);
                $this->generateModel($tableName, $columns);
                $this->generateEndpoint($tableName, $tableConfig, $columns);
            }
        }
    }

    private function generateModel($tableName, $columns)
    {
        $className = $this->toCamelCase($tableName);
        $primaryKey = $this->getPrimaryKey($columns);

        // Genera campi per INSERT/UPDATE
        $fields = [];
        foreach ($columns as $column) {
            if ($column['Field'] !== $primaryKey && $column['Extra'] !== 'auto_increment') {
                $fields[] = $column['Field'];
            }
        }

        $bindParams = '';
        $setParams = '';
        foreach ($fields as $field) {
            $bindParams .= "        \$stmt->bindParam(\":{$field}\", \$data['{$field}']);\n";
            $setParams .= "{$field}=:{$field}, ";
        }
        $setParams = rtrim($setParams, ', ');

        $content = <<<PHP
<?php
require_once __DIR__ . '/../config/database.php';

class {$className} {
    private \$conn;
    private \$table_name = "{$tableName}";

    public function __construct(\$db) {
        \$this->conn = \$db;
    }

    public function getAll() {
        \$query = "SELECT * FROM " . \$this->table_name . " ORDER BY {$primaryKey}";
        \$stmt = \$this->conn->prepare(\$query);
        \$stmt->execute();
        return \$stmt;
    }

    public function getById(\$id) {
        \$query = "SELECT * FROM " . \$this->table_name . " WHERE {$primaryKey} = ? LIMIT 0,1";
        \$stmt = \$this->conn->prepare(\$query);
        \$stmt->bindParam(1, \$id);
        \$stmt->execute();
        return \$stmt;
    }

    public function create(\$data) {
        \$query = "INSERT INTO " . \$this->table_name . " 
                SET {$setParams}";
        
        \$stmt = \$this->conn->prepare(\$query);
        
{$bindParams}        
        if(\$stmt->execute()) {
            return \$this->conn->lastInsertId();
        }
        return false;
    }

    public function update(\$id, \$data) {
        \$query = "UPDATE " . \$this->table_name . " 
                SET {$setParams} 
                WHERE {$primaryKey} = :{$primaryKey}";
        
        \$stmt = \$this->conn->prepare(\$query);
        
        \$stmt->bindParam(":{$primaryKey}", \$id);
{$bindParams}        
        if(\$stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete(\$id) {
        \$query = "DELETE FROM " . \$this->table_name . " WHERE {$primaryKey} = ?";
        \$stmt = \$this->conn->prepare(\$query);
        \$stmt->bindParam(1, \$id);
        
        if(\$stmt->execute()) {
            return true;
        }
        return false;
    }
}

PHP;
        file_put_contents($this->outputPath . "/models/{$className}.php", $content);
    }

    private function generateEndpoint($tableName, $config, $columns)
    {
        $className = $this->toCamelCase($tableName);
        $primaryKey = $this->getPrimaryKey($columns);

        // Controllo autenticazione
        $requiresAuth = ($config['select'] !== 'all' || $config['insert'] !== 'all' ||
            $config['update'] !== 'all' || $config['delete'] !== 'all');

        $authRequire = $requiresAuth ? "require_once __DIR__ . '/../middleware/auth.php';" : "";

        // Validazione campi obbligatori
        $requiredFields = [];
        foreach ($columns as $column) {
            if (
                $column['Null'] === 'NO' && $column['Extra'] !== 'auto_increment' &&
                $column['Field'] !== $primaryKey && !isset($column['Default'])
            ) {
                $requiredFields[] = $column['Field'];
            }
        }

        $requiredCheck = '';
        if (!empty($requiredFields)) {
            $checks = [];
            foreach ($requiredFields as $field) {
                $checks[] = "empty(\$data['{$field}'])";
            }
            $requiredCheck = "if(" . implode(' || ', $checks) . ") {\n        sendResponse(400, null, 'Missing required fields');\n    }\n    ";
        }

        // Controlli di autenticazione per ogni operazione
        $selectAuth = $config['select'] !== 'all' ? "\$user = requireAuth();" : "";
        $insertAuth = $config['insert'] !== 'all' ? "\$user = requireAuth();" : "";
        $updateAuth = $config['update'] !== 'all' ? "\$user = requireAuth();" : "";
        $deleteAuth = $config['delete'] !== 'all' ? "\$user = requireAuth();" : "";

        // Rate limiting dalla configurazione
        $rateLimit = $config['rate_limit'] ?? 100;
        $rateLimitWindow = $config['rate_limit_window'] ?? 60;

        $content = <<<PHP
<?php
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
{$authRequire}
require_once __DIR__ . '/../middleware/security_helper.php';
require_once __DIR__ . '/../models/{$className}.php';

applySecurity('{$tableName}', {$rateLimit}, {$rateLimitWindow});

\$database = new Database();
\$db = \$database->getConnection();
\$model = new {$className}(\$db);

\$method = \$_SERVER['REQUEST_METHOD'];
\$request_uri = \$_SERVER['REQUEST_URI'];
\$path = parse_url(\$request_uri, PHP_URL_PATH);
\$path_parts = explode('/', \$path);
\$id = end(\$path_parts);

switch(\$method) {
    case 'GET':
        {$selectAuth}
        if (\$id && \$id !== '{$tableName}') {
            \$stmt = \$model->getById(\$id);
            \$row = \$stmt->fetch(PDO::FETCH_ASSOC);
            
            if(\$row) {
                sendResponse(200, \$row);
            } else {
                sendResponse(404, null, 'Not found');
            }
        } else {
            \$stmt = \$model->getAll();
            \$items = array();
            
            while (\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) {
                array_push(\$items, \$row);
            }
            
            sendResponse(200, \$items);
        }
        break;
        
    case 'POST':
        {$insertAuth}
        \$data = json_decode(file_get_contents("php://input"), true);
        
        {$requiredCheck}\$item_id = \$model->create(\$data);
        if(\$item_id) {
            sendResponse(201, ['id' => \$item_id], 'Created successfully');
        } else {
            sendResponse(500, null, 'Failed to create');
        }
        break;
        
    case 'PUT':
        {$updateAuth}
        \$data = json_decode(file_get_contents("php://input"), true);
        
        {$requiredCheck}if(\$model->update(\$id, \$data)) {
            sendResponse(200, null, 'Updated successfully');
        } else {
            sendResponse(500, null, 'Failed to update');
        }
        break;
        
    case 'DELETE':
        {$deleteAuth}
        if(\$model->delete(\$id)) {
            sendResponse(200, null, 'Deleted successfully');
        } else {
            sendResponse(500, null, 'Failed to delete');
        }
        break;
        
    default:
        sendResponse(405, null, 'Method not allowed');
        break;
}

PHP;
        file_put_contents($this->outputPath . "/endpoints/{$tableName}.php", $content);
    }

    // ===== AUTENTICAZIONE =====

    private function generateAuthApi()
    {
        // 1. Modello User
        $this->generateUserModel();

        // 2. Endpoint auth/login
        $this->generateAuthEndpoint();

        // 3. auth/me.php
        $this->generateAuthMe();
    }

    private function generateUserModel()
    {
        $content = <<<'PHP'
<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table_name = "user_auth";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($email, $password) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = ? AND is_active = 1 LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user && password_verify($password, $user['password'])) {
            // Aggiorna last_login
            $update_query = "UPDATE " . $this->table_name . " SET last_login = NOW() WHERE id = ?";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->execute([$user['id']]);
            
            // Rimuovi password dalla risposta
            unset($user['password']);
            return $user;
        }
        
        return false;
    }

    public function getById($id) {
        $query = "SELECT id, email, name, role, last_login, created_at FROM " . $this->table_name . " 
                  WHERE id = ? AND is_active = 1 LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updatePassword($id, $new_password) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE " . $this->table_name . " SET password = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        
        if($stmt->execute([$hashed, $id])) {
            return true;
        }
        return false;
    }
}

PHP;
        file_put_contents($this->outputPath . '/models/User.php', $content);
    }

    private function generateAuthEndpoint()
    {
        $content = <<<'PHP'
<?php
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../models/User.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$jwt = new JWTHandler();

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', $path);
$action = end($path_parts);

switch($method) {
    case 'POST':
        if($action === 'login') {
            $data = json_decode(file_get_contents("php://input"), true);
            
            if(empty($data['email']) || empty($data['password'])) {
                sendResponse(400, null, 'Email e password sono obbligatori');
            }
            
            $userData = $user->login($data['email'], $data['password']);
            
            if($userData) {
                $token = $jwt->generateToken($userData);
                
                sendResponse(200, [
                    'token' => $token,
                    'user' => $userData
                ], 'Login effettuato con successo');
            } else {
                sendResponse(401, null, 'Credenziali non valide');
            }
        } else {
            sendResponse(404, null, 'Endpoint non trovato');
        }
        break;
        
    case 'GET':
        if($action === 'me') {
            require_once __DIR__ . '/../middleware/auth.php';
            $tokenData = requireAuth();
            
            $userData = $user->getById($tokenData['user_id']);
            
            if($userData) {
                sendResponse(200, $userData);
            } else {
                sendResponse(404, null, 'Utente non trovato');
            }
        } else {
            sendResponse(404, null, 'Endpoint non trovato');
        }
        break;
        
    default:
        sendResponse(405, null, 'Metodo non consentito');
        break;
}

PHP;
        file_put_contents($this->outputPath . '/endpoints/auth.php', $content);
    }

    private function generateAuthMe()
    {
        $content = <<<'PHP'
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

PHP;
        file_put_contents($this->outputPath . '/auth/me.php', $content);
    }

    // ===== HTACCESS =====

    private function generateHtaccess()
    {
        // 1. .htaccess per Apache
        $rootHtaccess = <<<'HTACCESS'
RewriteEngine On

# CORS Headers per Altervista
<IfModule mod_headers.c>
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
    Header always set Access-Control-Max-Age "3600"
</IfModule>

# Handle preflight OPTIONS requests
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]

# Route /api/auth/* to endpoints/auth.php
RewriteRule ^api/auth/(.*)$ endpoints/auth.php [QSA,L]

# Route /api/{table} to endpoints/{table}.php
RewriteRule ^api/([^/]+)/?$ endpoints/$1.php [QSA,L]

# Route /api/{table}/{id} to endpoints/{table}.php
RewriteRule ^api/([^/]+)/([0-9]+)$ endpoints/$1.php [QSA,L]

# Disable directory listing
Options -Indexes

HTACCESS;
        file_put_contents($this->outputPath . '/.htaccess', $rootHtaccess);

        // 2. index.php router per PHP built-in server
        $indexRouter = <<<'PHP'
<?php
// Router per server PHP built-in

// CORS Headers - SEMPRE per primo
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");

// Handle preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Rimuovi query string
$path = parse_url($request_uri, PHP_URL_PATH);

// Route /api/auth/* to endpoints/auth.php
if (preg_match('#^/api/auth/(.*)#', $path, $matches)) {
    require __DIR__ . '/endpoints/auth.php';
    exit;
}

// Route /api/{table}/{id} to endpoints/{table}.php
if (preg_match('#^/api/([^/]+)/([0-9]+)$#', $path, $matches)) {
    require __DIR__ . '/endpoints/' . $matches[1] . '.php';
    exit;
}

// Route /api/{table} to endpoints/{table}.php
if (preg_match('#^/api/([^/]+)/?$#', $path, $matches)) {
    $endpoint_file = __DIR__ . '/endpoints/' . $matches[1] . '.php';
    if (file_exists($endpoint_file)) {
        require $endpoint_file;
        exit;
    }
}

// 404 - Not Found
http_response_code(404);
header('Content-Type: application/json');
echo json_encode([
    'error' => true,
    'message' => 'Endpoint not found',
    'status' => 404,
    'path' => $path
]);

PHP;
        file_put_contents($this->outputPath . '/index.php', $indexRouter);
    }

    // ===== DOCUMENTAZIONE =====

    private function generateDocumentation($config)
    {
        $this->generateReadme($config);
        $this->generateAuthDoc();
    }

    private function generateReadme($config)
    {
        $currentDbConfig = $config[getDatabaseName()] ?? [];
        $enabledTables = $this->getEnabledTables($currentDbConfig);

        $content = "# Generated API\n\n";
        $content .= "API REST generata automaticamente per il database: **" . getDatabaseName() . "**\n\n";
        $content .= "## Deployment\n\n";
        $content .= "1. Carica questa cartella sul tuo server web\n";
        $content .= "2. Configura il database in `config/database.php`\n";
        $content .= "3. Assicurati che `.htaccess` sia abilitato (mod_rewrite)\n\n";
        $content .= "## Endpoint Disponibili\n\n";

        foreach ($enabledTables as $tableName => $tableConfig) {
            $content .= "### {$tableName}\n\n";
            $content .= "- `GET /api/{$tableName}` - Lista tutti\n";
            $content .= "- `GET /api/{$tableName}/{id}` - Singolo elemento\n";
            $content .= "- `POST /api/{$tableName}` - Crea nuovo\n";
            $content .= "- `PUT /api/{$tableName}/{id}` - Aggiorna\n";
            $content .= "- `DELETE /api/{$tableName}/{id}` - Elimina\n\n";
        }

        $content .= "## Autenticazione\n\n";
        $content .= "- `POST /api/auth/login` - Login con email/password\n";
        $content .= "- `GET /api/auth/me` - Info utente corrente\n\n";
        $content .= "Vedi `AUTH.md` per dettagli completi.\n\n";
        $content .= "Generato il: " . date('Y-m-d H:i:s') . "\n";

        file_put_contents($this->outputPath . '/README.md', $content);
    }

    private function generateAuthDoc()
    {
        $content = <<<'MARKDOWN'
# Documentazione Autenticazione

## Login

**Endpoint:** `POST /api/auth/login`

**Body:**
```json
{
  "email": "utente@example.com",
  "password": "password"
}
```

**Risposta:**
```json
{
  "status": 200,
  "data": {
    "token": "h3eri3u2seriovrglogri...",
    "user": {
      "id": 1,
      "email": "utente@example.com",
      "name": "Nome Utente",
      "role": "user"
    }
  },
  "message": "Login effettuato con successo"
}
```

## Utilizzo Token

Aggiungi il token nell'header Authorization:

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

## Esempio JavaScript

```javascript
// Login
const response = await fetch('https://tuodominio.com/api/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email: 'test@test.com', password: '123456' })
});

const { data } = await response.json();
localStorage.setItem('token', data.token);

// Richiesta autenticata
const protected = await fetch('https://tuodominio.com/api/tabella/1', {
  headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
});
```

Il token JWT ha validità di **24 ore**.

MARKDOWN;
        file_put_contents($this->outputPath . '/AUTH.md', $content);
    }

    // ===== UTILITY =====

    private function getEnabledTables($currentDbConfig)
    {
        return array_filter($currentDbConfig, function ($table, $key) {
            return $key !== '_views'
                && substr($key, 0, 6) !== '_view_'
                && isset($table['enabled'])
                && $table['enabled'] === true;
        }, ARRAY_FILTER_USE_BOTH);
    }

    private function getTableColumns($tableName)
    {
        $db = db();
        $stmt = $db->query("DESCRIBE `{$tableName}`");
        return $stmt->fetchAll();
    }

    private function getPrimaryKey($columns)
    {
        foreach ($columns as $column) {
            if ($column['Key'] === 'PRI') {
                return $column['Field'];
            }
        }
        return 'id';
    }

    private function toCamelCase($string)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    private function loadConfig()
    {
        if (file_exists($this->configFile)) {
            return json_decode(file_get_contents($this->configFile), true) ?? [];
        }
        return [];
    }
}
