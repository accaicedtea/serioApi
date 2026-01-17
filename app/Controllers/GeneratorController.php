<?php
namespace App\Controllers;

use Core\Controller;
use Core\Security;
use Core\Database;

class GeneratorController extends Controller {
    
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
        public function index() {
            //inizializza i dati della pagina
            $data = [
                'title' => 'API Generator - Configurazione',
                'databaseType' => $this->db->getConnectionType(),
                'connectionStatus' => $this->db->isConnected(),
                'databaseName' => $this->db->getDatabaseName(),
                'tables' => [],
                'config' => [],
                'currentDatabase' => '',
                'securityTables' => [],
                'hasSecurityTables' => count($this->db->getSecurityTables()) === 5,
                'error' => null
            ];
    
            $tables = $this->db->getDatabaseTables();
            
            // Verifica se le tabelle di sicurezza esistono
            $securityTables = $this->db->getSecurityTables();
            // Carica la configurazione esistente
            $config = loadApiConfig();
            
            // Ottieni il nome del database corrente
            $databaseName = $this->db->getDatabaseName();
            
            // Aggiungi le viste virtuali alla lista delle tabelle
            if (isset($config[$databaseName])) {
                foreach ($config[$databaseName] as $tableName => $tableConfig) {
                    if (isset($tableConfig['is_virtual']) && $tableConfig['is_virtual'] === true) {
                        $tables[] = $tableName;
                    }
                }
            }
            // Dati se funziona
            $data['tables'] = $tables;
            $data['config'] = $config;
            $data['currentDatabase'] = $databaseName;
            $data['securityTables'] = $securityTables;
            
        $this->view('generator/index', $data);
    }
    
public function views() {
        $data = [
            'title' => 'API Generator - Viste Personalizzate',
            'databaseType' => $this->db->getConnectionType(),
            'connectionStatus' => $this->db->isConnected(),
            'databaseName' => $this->db->getDatabaseName(),
            'viewsConfig' => [],
            'viewsEnabled' => [],
            'currentDatabase' => '',
            'tables' => [],
            'error' => null
        ];
        
        // Recupera tutte le tabelle del database
        $tables = $this->db->getDatabaseTables();
        
        // Ottieni il nome del database corrente
        $databaseName = $this->db->getDatabaseName();
        
        // Carica la configurazione esistente delle viste
        $config = loadApiConfig();
        
        // Recupera le viste del database corrente
        $viewsConfig = [];
        if (isset($config[$databaseName]['_views'])) {
            $viewsConfig = $config[$databaseName]['_views'];
        }
        
        // Recupera gli stati enabled delle viste
        $viewsEnabled = [];
        foreach ($viewsConfig as $viewName => $viewData) {
            $viewsEnabled[$viewName] = $config[$databaseName]['_view_' . $viewName]['enabled'] ?? false;
        }
        
        // Dati se funziona
        $data['viewsConfig']=$viewsConfig;
        $data['viewsEnabled'] = $viewsEnabled;
        $data['currentDatabase'] = $databaseName;
        $data['tables'] = $tables;
        
        
        $this->view('generator/views', $data);
    }
    

    public function createSecurityTables() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /generator');
            exit;
        }
        
        if (!e($_POST['csrf_token'])) {
            die('Token CSRF non valido');
        }
        
        try {
            $query = $this->db->getConnection();
            // Crea tabella user_auth
            $query->exec("
                CREATE TABLE IF NOT EXISTS user_auth (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(191) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    role ENUM('admin', 'manager') DEFAULT 'manager',
                    site_url VARCHAR(255) DEFAULT 'http://test.org',
                    is_active BOOLEAN DEFAULT TRUE,
                    last_login TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            
            // Inserisci utenti di default (password hasciata con password_hash)
            $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
            $managerPass = password_hash('manager123', PASSWORD_DEFAULT);
            
            $query->exec("
                INSERT IGNORE INTO user_auth (email, password, name, role, site_url) VALUES
                ('admin@menucrud.com', '$adminPass', 'Amministratore', 'admin', 'http://test.org'),
                ('manager@menucrud.com', '$managerPass', 'Manager', 'manager', 'http://test.org')
            ");
            
            // Crea tabella banned_ips
            $query->exec("
                CREATE TABLE IF NOT EXISTS banned_ips (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    reason VARCHAR(255),
                    banned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NULL,
                    created_by VARCHAR(100),
                    INDEX idx_ip_expires (ip_address, expires_at)
                )
            ");
            
            // Crea tabella rate_limits
            $query->exec("
                CREATE TABLE IF NOT EXISTS rate_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    endpoint VARCHAR(100) NOT NULL,
                    requests_count INT DEFAULT 1,
                    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_ip_endpoint (ip_address, endpoint),
                    INDEX idx_window_start (window_start)
                )
            ");
            
            // Crea tabella failed_attempts
            $query->exec("
                CREATE TABLE IF NOT EXISTS failed_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    identifier VARCHAR(100) NOT NULL,
                    attempt_type VARCHAR(50) NOT NULL,
                    client_ip VARCHAR(45) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_identifier (identifier(50)),
                    INDEX idx_attempt_type (attempt_type),
                    INDEX idx_created_at (created_at)
                )
            ");
            
            // Crea tabella security_logs
            $query->exec("
                CREATE TABLE IF NOT EXISTS security_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_ip VARCHAR(45) NOT NULL,
                    event_type VARCHAR(100) NOT NULL,
                    endpoint_type VARCHAR(100),
                    details TEXT,
                    user_agent TEXT,
                    request_uri VARCHAR(500),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_client_ip (client_ip),
                    INDEX idx_event_type (event_type),
                    INDEX idx_endpoint_type (endpoint_type),
                    INDEX idx_created_at (created_at)
                )
            ");
            
            header('Location: /generator?security_created=1');
            exit;
        } catch (\PDOException $e) {
            header('Location: /generator?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
    
    
    public function testView() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /generator/views');
            exit;
        }
        
        if (!Security::checkCsrf($_POST['csrf_token'] ?? '')) {
            die('Token CSRF non valido');
        }
        
        try {
            $db = $this->db;
            $query = $_POST['query'] ?? '';
            
            if (stripos($query, 'SELECT') !== 0) {
                throw new \Exception('Solo query SELECT sono permesse');
            }
            
            $result = $db->getConnection()->query($query)->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $result,
                'count' => count($result)
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    public function saveView() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /generator/views');
            exit;
        }
        
        if (!Security::checkCsrf($_POST['csrf_token'] ?? '')) {
            die('Token CSRF non valido');
        }
        
        $viewData = json_decode($_POST['view_data'] ?? '{}', true);
        
        // Carica configurazione esistente
        $config = loadApiConfig();
        
        // Ottieni il nome del database corrente
        require_once __DIR__ . '/../../config/database.php';
        $dbConfig = getDatabaseConfig();
        $databaseName = $dbConfig['dbname'] ?? 'unknown';
        
        // Assicurati che il database esista nella config
        if (!isset($config[$databaseName])) {
            $config[$databaseName] = [];
        }
        
        // Assicurati che _views esista per questo database
        if (!isset($config[$databaseName]['_views'])) {
            $config[$databaseName]['_views'] = [];
        }
        
        // Salva o aggiorna la vista nel database corrente
        $viewName = $viewData['name'];
        $config[$databaseName]['_views'][$viewName] = $viewData;
        
        // Aggiungi anche la vista come tabella virtuale nel database corrente con tutti i parametri
        $config[$databaseName]['_view_' . $viewName] = [
            'enabled' => true,
            'is_virtual' => true,
            'view_name' => $viewName,
            'rate_limit' => $viewData['rate_limit'] ?? 100,
            'rate_limit_window' => $viewData['rate_limit_window'] ?? 60,
            'max_results' => $viewData['max_results'] ?? 100,
            'enable_cache' => $viewData['enable_cache'] ?? false,
            'cache_ttl' => $viewData['cache_ttl'] ?? 300,
            'select' => 'all',  // Le viste sono sempre read-only
            'insert' => 'none',
            'update' => 'none',
            'delete' => 'none'
        ];
        
        saveApiConfig($config);
        
        header('Location: /generator/views?saved=1');
        exit;
    }
    
    public function toggleView() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        header('Content-Type: application/json');
        
        if (!Security::checkCsrf($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token CSRF non valido']);
            exit;
        }
        
        $viewName = $_POST['view_name'] ?? '';
        
        if (empty($viewName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nome vista mancante']);
            exit;
        }
        
        $config = loadApiConfig();
        
        // Ottieni il nome del database corrente
        $databaseName = $this->db->getDatabaseName();
        
        // Verifica che la vista esista
        if (!isset($config[$databaseName]['_views'][$viewName])) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Vista non trovata']);
            exit;
        }
        
        // Assicurati che esista la voce virtuale
        if (!isset($config[$databaseName]['_view_' . $viewName])) {
            $config[$databaseName]['_view_' . $viewName] = [
                'enabled' => false,
                'is_virtual' => true,
                'view_name' => $viewName
            ];
        }
        
        // Toggle dello stato enabled
        $currentState = $config[$databaseName]['_view_' . $viewName]['enabled'] ?? false;
        $newState = !$currentState;
        $config[$databaseName]['_view_' . $viewName]['enabled'] = $newState;
        
        // Salva la configurazione
        saveApiConfig($config);
        
        echo json_encode([
            'success' => true,
            'enabled' => $newState,
            'message' => $newState ? 'Vista abilitata' : 'Vista disabilitata'
        ]);
        exit;
    }
    
    public function deleteView() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /generator/views');
            exit;
        }
        
        if (!Security::checkCsrf($_POST['csrf_token'] ?? '')) {
            die('Token CSRF non valido');
        }
        
        $viewName = $_POST['view_name'] ?? '';
        
        $config = loadApiConfig();
        
        // Ottieni il nome del database corrente
        $databaseName = $this->db->getDatabaseName();
        
        // Rimuovi la vista dalla sezione _views del database corrente
        if (isset($config[$databaseName]['_views'][$viewName])) {
            unset($config[$databaseName]['_views'][$viewName]);
        }
        
        // Rimuovi anche la tabella virtuale dal database corrente
        if (isset($config[$databaseName]['_view_' . $viewName])) {
            unset($config[$databaseName]['_view_' . $viewName]);
        }
        
        saveApiConfig($config);
        
        header('Location: /generator/views?deleted=1');
        exit;
    }
    
    public function save(): never {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /generator');
            exit;
        }
        
        if (!e($_POST['csrf_token'])) {
            die('Token CSRF non valido');
        }
        
        $newConfig = json_decode($_POST['config'] ?? '{}', true);
        
        // Carica la configurazione esistente per preservare _views
        $existingConfig = loadApiConfig();
        
        // Ottieni il nome del database corrente
        $databaseName = $this->db->getDatabaseName();
        
        // Preserva _views del database corrente e ricostruisci le tabelle virtuali
        if (isset($existingConfig[$databaseName]['_views'])) {
            $newConfig[$databaseName]['_views'] = $existingConfig[$databaseName]['_views'];
            
            // Assicurati che tutte le viste siano presenti anche come tabelle virtuali
            foreach ($existingConfig[$databaseName]['_views'] as $viewName => $viewData) {
                $virtualTableName = '_view_' . $viewName;
                if (!isset($newConfig[$databaseName][$virtualTableName])) {
                    $newConfig[$databaseName][$virtualTableName] = [
                        'enabled' => $existingConfig[$databaseName][$virtualTableName]['enabled'] ?? false,
                        'is_virtual' => true,
                        'view_name' => $viewName
                    ];
                }
            }
        }
        
        saveApiConfig($newConfig);
        
        header('Location: /generator?saved=101');
        exit;
    }
}