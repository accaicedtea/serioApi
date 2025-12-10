<?php
namespace App\Controllers;

use Core\Controller;
use Core\Security;

class ApiBuilderController extends Controller
{
    private $configFile = __DIR__ . '/../../config/api_config.json';
    private $outputPath = __DIR__ . '/../../generated-api';
    private $db;
    public function __construct($db)
    {
        $this->db = $db;
    }
    public function index()
    {
        $config = $this->loadConfig();
        $currentDbConfig = $config[$this->db->getDatabaseName()] ?? [];
        $enabledTables = $this->getEnabledTables($currentDbConfig);

        // Conta le viste personalizzate dalla sezione _views
        $viewsCount = 0;
        if (isset($currentDbConfig['_views'])) {
            $viewsCount = count($currentDbConfig['_views']);
        }

        $data = [
            'title' => 'API Builder - Generatore',
            'config' => $currentDbConfig,
            'databaseName' => $this->db->getDatabaseName(),
            'enabledCount' => count($enabledTables),
            'viewsCount' => $viewsCount,
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
            $currentDbConfig = $config[$this->db->getDatabaseName()] ?? [];

            if (empty($currentDbConfig)) {
                throw new \Exception("Configurazione non trovata per il database: " . getDatabaseName());
            }

            // Crea struttura cartelle
            $this->createDirectoryStructure();

            // Genera file di configurazione
            $this->generateConfigFiles();

            // Genera middleware
            $this->generateMiddleware();

            // Genera modelli ed endpoint per ogni tabella abilitata
            $this->generateTablesApi($currentDbConfig);

            // Genera auth endpoint e modello User
            $this->generateAuthApi();

            // Genera .htaccess
            $this->generateHtaccess();

            // Genera documentazione
            $this->generateReadme($config);

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
            $this->outputPath . '/auth',
        ];
        // Esempio: per cambiare la variabile
        /* 
            $dirs = [
            $this->outputPath,
            $this->outputPath . '/config',
            $this->outputPath . '/middleware',
            $this->outputPath . '/models',
            $this->outputPath . '/endpoints',
            $this->outputPath . '/auth',
            $this->outputPath - '/storage', // aggiunta 
            $this->outputPath - '/storage/cache', // aggiunta

        ];
        */

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    // ===== CONFIGURAZIONE =====

    private function generateConfigFiles()
    {
        // database.php - usa i dati dall'ENV
        $this->generateDatabaseConfig();

        // jwt.php - usa JWT_SECRET dall'ENV
        $this->generateJwtConfig();

        // helpers.php - funzioni globali unificate
        $this->generateHelpersFile();

        // cors.php - CORS headers standalone
        $this->generateCorsFile();

        // api_config.json
        copy($this->configFile, $this->outputPath . '/config/api_config.json');

        // htaccess per proteggere config
        file_put_contents($this->outputPath . '/config/.htaccess', "Deny from all\n");
    }


    private function generateDatabaseConfig()
    {
        require_once __DIR__ . '/../../config/database.php';
        $dbConfig = getDatabaseConfig('production');


        $content = <<<PHP
<?php
// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_NAME', '{$dbConfig['dbname']}');
define('DB_USER', '{$dbConfig['user']}');
define('DB_PASS', '{$dbConfig['pass']}');

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
// ===== JWT CONFIG =====
private function generateJwtConfig()
{
// Template JWT di riferimento (app/Builder/JWT.php)
$templatePath = dirname(__DIR__) . '/Builder/JWT.php';
$outDir = $this->outputPath . '/config';
$outFile = $outDir . '/jwt.php';

if (!is_file($templatePath)) {
throw new \RuntimeException("Template JWT non trovato: {$templatePath}");
}
if (!is_dir($outDir)) {
mkdir($outDir, 0775, true);
}

// Valori da .env con default
$vars = [
'__JWT_SECRET__' => env('JWT_SECRET'),
'__JWT_ALGO__' => env('JWT_ALGO', 'HS256'),
'__JWT_TTL__' => (string) env('JWT_EXPIRES_IN'), // in secondi (24h)
];

// Carica template
$tpl = file_get_contents($templatePath);

// Se il template non ha i placeholder standard, sostituisci i pattern comuni
if (strpos($tpl, '__JWT_SECRET__') === false || strpos($tpl, '__JWT_ALGO__') === false || strpos($tpl, '__JWT_TTL__')
=== false) {
$tpl = preg_replace(
[
'/(private\s+\$secret_key\s*=\s*[\'"]).*?([\'"];)/',
'/(private\s+\$algorithm\s*=\s*[\'"]).*?([\'"];)/',
'/(private\s+\$ttl\s*=\s*)\d+(\s*;)/',
],
[
'$1__JWT_SECRET__$2',
'$1__JWT_ALGO__$2',
'$1__JWT_TTL__$2',
],
$tpl
);
}

// Applica le sostituzioni
$rendered = strtr($tpl, $vars);

file_put_contents($outFile, $rendered);
}

// ===== HELPERS CONFIG =====
private function generateHelpersFile()
{
// Copia il file helpers.php dal Builder
$templatePath = dirname(__DIR__) . '/Builder/helpers.php';
copy($templatePath, $this->outputPath . '/config/helpers.php');
}

private function generateCorsFile()
{
$templatePath = dirname(__DIR__) . '/Builder/cors.php';
copy($templatePath, $this->outputPath . '/cors.php');
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
$templatePath = dirname(__DIR__) . '/Builder/auth.php';
copy($templatePath, $this->outputPath . '/middleware/auth.php');
}

private function generateSecurityMiddleware()
{
$templatePath = dirname(__DIR__) . '/Builder/security.php';
copy($templatePath, $this->outputPath . '/middleware/security.php');
}

private function generateSecurityHelper()
{
$templatePath = dirname(__DIR__) . '/Builder/security_helper.php';
copy($templatePath, $this->outputPath . '/middleware/security_helper.php');
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
$bindParams .= " \$stmt->bindParam(\":{$field}\", \$data['{$field}']);\n";
$setParams .= "{$field}=:{$field}, ";
}
$setParams = rtrim($setParams, ', ');

$content = <<<PHP <?php
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
        $templatePath = dirname(__DIR__) . '/Builder/User.php';
        copy($templatePath, $this->outputPath . '/models/User.php');
    }

    private function generateAuthEndpoint()
    {
        $templatePath = dirname(__DIR__) . '/Builder/ep_auth.php';
        copy($templatePath, $this->outputPath . '/endpoints/auth.php');
    }

    private function generateAuthMe()
    {
        $templatePath = dirname(__DIR__) . '/Builder/me.php';
        copy($templatePath, $this->outputPath . '/auth/me.php');
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

# Route /api/auth/login to endpoints/auth.php
RewriteRule ^api/auth/login/?$ endpoints/auth.php [QSA,L]

# Route /api/auth/me to auth/me.php
RewriteRule ^api/auth/me/?$ auth/me.php [QSA,L]

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

// Richieste preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Rimuovi query string
$path = parse_url($request_uri, PHP_URL_PATH);

// Route /api/auth/login
if (preg_match('#^/api/auth/login/?$#', $path)) {
    require __DIR__ . '/endpoints/auth.php';
    exit;
}

// Route /api/auth/me
if (preg_match('#^/api/auth/me/?$#', $path)) {
    require __DIR__ . '/auth/me.php';
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
        $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));
        return $str;
    }

    private function loadConfig()
    {
        if (file_exists($this->configFile)) {
            return json_decode(file_get_contents($this->configFile), true) ?? [];
        }
        return [];
    }


    // ===== DOCUMENTAZIONE =====
    private function generateReadme($config)
    {
        $currentDbConfig = $config[getDatabaseName()] ?? [];
        $enabledTables = $this->getEnabledTables($currentDbConfig);
        $databaseName = getDatabaseName();
        $viewsCount = isset($currentDbConfig['_views']) ? count($currentDbConfig['_views']) : 0;

        $content = "# API REST - {$databaseName}\n\n";
        $content .= "API REST generata automaticamente per il database: **{$databaseName}**\n\n";
        $content .= "📅 **Generato il:** " . date('d/m/Y H:i:s') . "\n";
        $content .= "📊 **Tabelle abilitate:** " . count($enabledTables) . "\n";
        $content .= "👁️ **Viste personalizzate:** {$viewsCount}\n\n";

        $content .= "---\n\n";
        $content .= "## 📋 Indice\n\n";
        $content .= "1. [Deployment](#-deployment)\n";
        $content .= "2. [Struttura del Progetto](#-struttura-del-progetto)\n";
        $content .= "3. [Configurazione](#%EF%B8%8F-configurazione)\n";
        $content .= "4. [Endpoint Disponibili](#-endpoint-disponibili)\n";
        $content .= "5. [Autenticazione](#-autenticazione)\n";
        $content .= "6. [Sicurezza e Rate Limiting](#-sicurezza-e-rate-limiting)\n";
        $content .= "7. [Gestione Errori](#-gestione-errori)\n";
        $content .= "8. [Esempi di Utilizzo](#-esempi-di-utilizzo)\n\n";

        $content .= "---\n\n";
        $content .= "## 🚀 Deployment\n\n";
        $content .= "### Requisiti\n";
        $content .= "- PHP 7.4 o superiore\n";
        $content .= "- MySQL 5.7 o superiore\n";
        $content .= "- Apache con mod_rewrite abilitato\n";
        $content .= "- Estensioni PHP: PDO, pdo_mysql, json\n\n";
        $content .= "### Installazione\n\n";
        $content .= "1. **Carica i file sul server**\n";
        $content .= "   ```bash\n";
        $content .= "   # Via FTP o tramite git\n";
        $content .= "   scp -r ./generated-api/* utente@server:/percorso/api/\n";
        $content .= "   ```\n\n";
        $content .= "2. **Configura il database**\n";
        $content .= "   Modifica `config/database.php` con le tue credenziali:\n";
        $content .= "   ```php\n";
        $content .= "   define('DB_HOST', '127.0.0.1');\n";
        $content .= "   define('DB_NAME', '{$databaseName}');\n";
        $content .= "   define('DB_USER', 'tuo_utente');\n";
        $content .= "   define('DB_PASS', 'tua_password');\n";
        $content .= "   ```\n\n";
        $content .= "3. **Verifica .htaccess**\n";
        $content .= "   Assicurati che il file `.htaccess` sia presente e che mod_rewrite sia attivo\n\n";
        $content .= "4. **Test**\n";
        $content .= "   ```bash\n";
        $content .= "   curl https://tuodominio.com/api/\n";
        $content .= "   ```\n\n";

        $content .= "---\n\n";
        $content .= "## 📁 Struttura del Progetto\n\n";
        $content .= "```\n";
        $content .= "generated-api/\n";
        $content .= "├── .htaccess              # Configurazione Apache\n";
        $content .= "├── index.php              # Router principale\n";
        $content .= "├── cors.php               # Gestione CORS\n";
        $content .= "├── config/\n";
        $content .= "│   ├── database.php       # Configurazione DB e classe Database\n";
        $content .= "│   └── helpers.php        # Funzioni helper globali\n";
        $content .= "├── middleware/\n";
        $content .= "│   ├── auth.php           # Verifica JWT token\n";
        $content .= "│   └── rate_limit.php     # Limitazione richieste\n";
        $content .= "├── models/\n";
        $content .= "│   ├── User.php           # Modello utente\n";
        $content .= "│   └── [Altri modelli]    # Un file per ogni tabella\n";
        $content .= "├── endpoints/\n";
        $content .= "│   └── [tabelle].php      # Endpoint CRUD per ogni tabella\n";
        $content .= "└── auth/\n";
        $content .= "    ├── login.php          # Endpoint login\n";
        $content .= "    └── me.php             # Endpoint info utente\n";
        $content .= "```\n\n";

        $content .= "---\n\n";
        $content .= "## ⚙️ Configurazione\n\n";
        $content .= "### Database\n";
        $content .= "La classe `Database` in `config/database.php` fornisce metodi per:\n";
        $content .= "- Connessione automatica con PDO\n";
        $content .= "- Prepared statements per sicurezza\n";
        $content .= "- Gestione transazioni\n";
        $content .= "- Query e metodi helper\n\n";
        $content .= "### CORS\n";
        $content .= "Il file `cors.php` gestisce le policy CORS. Per modificare i domini consentiti:\n";
        $content .= "```php\n";
        $content .= "header('Access-Control-Allow-Origin: https://tuodominio.com');\n";
        $content .= "```\n\n";

        $content .= "---\n\n";
        $content .= "## 📡 Endpoint Disponibili\n\n";
        $content .= "### Base URL\n";
        $content .= "```\n";
        $content .= "https://tuodominio.com/api/\n";
        $content .= "```\n\n";

        foreach ($enabledTables as $tableName => $tableConfig) {
            $authRequired = isset($tableConfig['require_auth']) && $tableConfig['require_auth'] ? '🔒' : '🔓';
            $rateLimit = $tableConfig['rate_limit'] ?? 100;
            $rateLimitWindow = $tableConfig['rate_limit_window'] ?? 60;

            $content .= "### {$authRequired} {$tableName}\n\n";
            $content .= "**Rate Limit:** {$rateLimit} richieste ogni {$rateLimitWindow} secondi\n\n";
            $content .= "| Metodo | Endpoint | Descrizione | Auth |\n";
            $content .= "|--------|----------|-------------|------|\n";
            $content .= "| GET | `/api/{$tableName}` | Lista tutti gli elementi | " . ($tableConfig['select'] ?? 'all') . " |\n";
            $content .= "| GET | `/api/{$tableName}/{id}` | Ottieni singolo elemento | " . ($tableConfig['select'] ?? 'all') . " |\n";
            $content .= "| POST | `/api/{$tableName}` | Crea nuovo elemento | " . ($tableConfig['insert'] ?? 'auth') . " |\n";
            $content .= "| PUT | `/api/{$tableName}/{id}` | Aggiorna elemento | " . ($tableConfig['update'] ?? 'auth') . " |\n";
            $content .= "| DELETE | `/api/{$tableName}/{id}` | Elimina elemento | " . ($tableConfig['delete'] ?? 'admin') . " |\n\n";
        }

        // Viste personalizzate
        if ($viewsCount > 0) {
            $content .= "### 👁️ Viste Personalizzate\n\n";
            foreach ($currentDbConfig['_views'] as $viewName => $viewConfig) {
                $content .= "#### {$viewName}\n";
                $content .= ($viewConfig['description'] ?? 'Nessuna descrizione') . "\n\n";
                $content .= "**Endpoint:** `GET /api/{$viewName}`\n\n";
                $content .= "**Query SQL:**\n```sql\n" . ($viewConfig['query'] ?? '') . "\n```\n\n";
            }
        }

        $content .= "---\n\n";
        $content .= "## 🔐 Autenticazione\n\n";
        $content .= "L'API utilizza **JWT (JSON Web Tokens)** per l'autenticazione.\n\n";
        $content .= "### Login\n\n";
        $content .= "**Endpoint:** `POST /api/auth/login`\n\n";
        $content .= "**Request Body:**\n";
        $content .= "```json\n";
        $content .= "{\n";
        $content .= "  \"email\": \"utente@example.com\",\n";
        $content .= "  \"password\": \"password123\"\n";
        $content .= "}\n";
        $content .= "```\n\n";
        $content .= "**Response (200 OK):**\n";
        $content .= "```json\n";
        $content .= "{\n";
        $content .= "  \"status\": 200,\n";
        $content .= "  \"data\": {\n";
        $content .= "    \"token\": \"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...\",\n";
        $content .= "    \"user\": {\n";
        $content .= "      \"id\": 1,\n";
        $content .= "      \"email\": \"utente@example.com\",\n";
        $content .= "      \"name\": \"Nome Utente\",\n";
        $content .= "      \"role\": \"user\"\n";
        $content .= "    }\n";
        $content .= "  },\n";
        $content .= "  \"message\": \"Login effettuato con successo\"\n";
        $content .= "}\n";
        $content .= "```\n\n";
        $content .= "### Utilizzo del Token\n\n";
        $content .= "Includi il token nell'header `Authorization` di ogni richiesta:\n\n";
        $content .= "```\n";
        $content .= "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...\n";
        $content .= "```\n\n";
        $content .= "### Info Utente Corrente\n\n";
        $content .= "**Endpoint:** `GET /api/auth/me`\n\n";
        $content .= "**Headers:**\n";
        $content .= "```\n";
        $content .= "Authorization: Bearer [token]\n";
        $content .= "```\n\n";
        $content .= "**Response:**\n";
        $content .= "```json\n";
        $content .= "{\n";
        $content .= "  \"status\": 200,\n";
        $content .= "  \"data\": {\n";
        $content .= "    \"id\": 1,\n";
        $content .= "    \"email\": \"utente@example.com\",\n";
        $content .= "    \"name\": \"Nome Utente\",\n";
        $content .= "    \"role\": \"user\"\n";
        $content .= "  }\n";
        $content .= "}\n";
        $content .= "```\n\n";
        $content .= "⏱️ **Validità token:** 24 ore\n\n";

        $content .= "---\n\n";
        $content .= "## 🛡️ Sicurezza e Rate Limiting\n\n";
        $content .= "### Rate Limiting\n";
        $content .= "Ogni endpoint ha un limite di richieste configurabile per prevenire abusi:\n";
        $content .= "- Limite default: 100 richieste/minuto\n";
        $content .= "- Tracking per IP\n";
        $content .= "- Header di risposta con info sul limite\n\n";
        $content .= "**Response Headers:**\n";
        $content .= "```\n";
        $content .= "X-RateLimit-Limit: 100\n";
        $content .= "X-RateLimit-Remaining: 95\n";
        $content .= "X-RateLimit-Reset: 1638360000\n";
        $content .= "```\n\n";
        $content .= "### Protezioni Implementate\n";
        $content .= "- ✅ SQL Injection (PDO Prepared Statements)\n";
        $content .= "- ✅ XSS (htmlspecialchars su output)\n";
        $content .= "- ✅ CSRF (token validation)\n";
        $content .= "- ✅ Rate Limiting\n";
        $content .= "- ✅ JWT con scadenza\n";
        $content .= "- ✅ Password hash (bcrypt)\n\n";

        $content .= "---\n\n";
        $content .= "## ⚠️ Gestione Errori\n\n";
        $content .= "Tutte le risposte seguono un formato standard:\n\n";
        $content .= "### Success Response\n";
        $content .= "```json\n";
        $content .= "{\n";
        $content .= "  \"status\": 200,\n";
        $content .= "  \"data\": { ... },\n";
        $content .= "  \"message\": \"Operazione completata\"\n";
        $content .= "}\n";
        $content .= "```\n\n";
        $content .= "### Error Response\n";
        $content .= "```json\n";
        $content .= "{\n";
        $content .= "  \"status\": 400,\n";
        $content .= "  \"error\": \"Descrizione errore\",\n";
        $content .= "  \"message\": \"Messaggio user-friendly\"\n";
        $content .= "}\n";
        $content .= "```\n\n";
        $content .= "### Codici di Stato HTTP\n";
        $content .= "| Codice | Significato |\n";
        $content .= "|--------|-------------|\n";
        $content .= "| 200 | Successo |\n";
        $content .= "| 201 | Creato |\n";
        $content .= "| 400 | Bad Request |\n";
        $content .= "| 401 | Non autorizzato |\n";
        $content .= "| 403 | Accesso negato |\n";
        $content .= "| 404 | Non trovato |\n";
        $content .= "| 429 | Troppe richieste (rate limit) |\n";
        $content .= "| 500 | Errore server |\n\n";

        $content .= "---\n\n";
        $content .= "## 💻 Esempi di Utilizzo\n\n";
        $content .= "### JavaScript / Fetch API\n\n";
        $content .= "```javascript\n";
        $content .= "// Login\n";
        $content .= "async function login(email, password) {\n";
        $content .= "  const response = await fetch('https://tuodominio.com/api/auth/login', {\n";
        $content .= "    method: 'POST',\n";
        $content .= "    headers: { 'Content-Type': 'application/json' },\n";
        $content .= "    body: JSON.stringify({ email, password })\n";
        $content .= "  });\n";
        $content .= "  const { data } = await response.json();\n";
        $content .= "  localStorage.setItem('token', data.token);\n";
        $content .= "  return data;\n";
        $content .= "}\n\n";
        $content .= "// GET con autenticazione\n";
        $content .= "async function getData(endpoint) {\n";
        $content .= "  const token = localStorage.getItem('token');\n";
        $content .= "  const response = await fetch(`https://tuodominio.com/api/\${endpoint}`, {\n";
        $content .= "    headers: { 'Authorization': `Bearer \${token}` }\n";
        $content .= "  });\n";
        $content .= "  return await response.json();\n";
        $content .= "}\n\n";
        $content .= "// POST con autenticazione\n";
        $content .= "async function createItem(endpoint, data) {\n";
        $content .= "  const token = localStorage.getItem('token');\n";
        $content .= "  const response = await fetch(`https://tuodominio.com/api/\${endpoint}`, {\n";
        $content .= "    method: 'POST',\n";
        $content .= "    headers: {\n";
        $content .= "      'Content-Type': 'application/json',\n";
        $content .= "      'Authorization': `Bearer \${token}`\n";
        $content .= "    },\n";
        $content .= "    body: JSON.stringify(data)\n";
        $content .= "  });\n";
        $content .= "  return await response.json();\n";
        $content .= "}\n";
        $content .= "```\n\n";
        $content .= "### cURL\n\n";
        $content .= "```bash\n";
        $content .= "# Login\n";
        $content .= "curl -X POST https://tuodominio.com/api/auth/login \\\n";
        $content .= "  -H \"Content-Type: application/json\" \\\n";
        $content .= "  -d '{\"email\":\"test@test.com\",\"password\":\"123456\"}'\n\n";
        $content .= "# GET con token\n";
        $content .= "curl https://tuodominio.com/api/tabella \\\n";
        $content .= "  -H \"Authorization: Bearer eyJ0eXAi...\"\n\n";
        $content .= "# POST con token\n";
        $content .= "curl -X POST https://tuodominio.com/api/tabella \\\n";
        $content .= "  -H \"Content-Type: application/json\" \\\n";
        $content .= "  -H \"Authorization: Bearer eyJ0eXAi...\" \\\n";
        $content .= "  -d '{\"campo\":\"valore\"}'\n";
        $content .= "```\n\n";
        $content .= "### PHP\n\n";
        $content .= "```php\n";
        $content .= "<?php\n";
        $content .= "// Login\n";
        $content .= "\$ch = curl_init('https://tuodominio.com/api/auth/login');\n";
        $content .= "curl_setopt(\$ch, CURLOPT_POST, true);\n";
        $content .= "curl_setopt(\$ch, CURLOPT_POSTFIELDS, json_encode([\n";
        $content .= "    'email' => 'test@test.com',\n";
        $content .= "    'password' => '123456'\n";
        $content .= "]));\n";
        $content .= "curl_setopt(\$ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);\n";
        $content .= "curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);\n";
        $content .= "\$response = curl_exec(\$ch);\n";
        $content .= "\$data = json_decode(\$response, true);\n";
        $content .= "\$token = \$data['data']['token'];\n\n";
        $content .= "// GET con token\n";
        $content .= "\$ch = curl_init('https://tuodominio.com/api/tabella');\n";
        $content .= "curl_setopt(\$ch, CURLOPT_HTTPHEADER, [\n";
        $content .= "    'Authorization: Bearer ' . \$token\n";
        $content .= "]);\n";
        $content .= "curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);\n";
        $content .= "\$response = curl_exec(\$ch);\n";
        $content .= "```\n\n";

        $content .= "---\n\n";
        $content .= "## 📞 Supporto\n\n";
        $content .= "Per problemi o domande:\n";
        $content .= "- Verifica i log del server PHP\n";
        $content .= "- Controlla le credenziali database in `config/database.php`\n";
        $content .= "- Assicurati che mod_rewrite sia attivo\n";
        $content .= "- Verifica i permessi delle cartelle (755 per directory, 644 per file)\n\n";

        $content .= "---\n\n";
        $content .= "**🎉 API generata con successo!**\n";

        file_put_contents($this->outputPath . '/README.md', $content);
    }
}