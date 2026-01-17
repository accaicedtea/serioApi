<?php
namespace Core;

class Database {
    private $pdo = null;
    private $dbName = null;
    private $dbTables = [];
    private $isConnected = false;
    private $connectionType = 'Not connected';
    public function __construct() {
        if ($this->pdo === null) {
            require_once __DIR__ . '/../config/database.php';
            $config = getDatabaseConfig();
            
            if ($config === null) {
                throw new \Exception('File di configurazione non trovato.');
            }
            if (!isset($config['host'], $config['dbname'], $config['user'], $config['pass'], $config['charset'])) {
                throw new \Exception('Mancano delle configurazioni nel file di configurazione.');
            }
            if ($config['host'] == 'localhost') {
                // non so localhost mi da problemi
                $config['host'] = '127.0.0.1';
            }

            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            
            try {
                $this->pdo = new \PDO($dsn, $config['user'], $config['pass'], [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);
                
                if (php_sapi_name() === 'cli') {
                    echo "[CORE: DB] Connessione avvenuta con successo\n";
                }
                // Inizializza variabili database
                $this->dbName = $config['dbname'];
                $this->dbTables = $this->pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
                $this->isConnected = true;
                $this->connectionType = 'MySQL';

            } catch (\PDOException $e) {
                if (php_sapi_name() === 'cli') {
                    echo "[CORE: DB] Errore connessione: " . $e->getMessage() . "\n";
                }
                throw new \Exception('Connessione col DATABASE fallita: ' . $e->getMessage());
            }
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
    public function getDatabaseName() {
        return $this->dbName;
    }
    public function getDatabaseTables() {
        return $this->dbTables;
    }
    public function isConnected() {
        return $this->isConnected;
    }
    public function getConnectionType() {
        return $this->connectionType;
    }
    // Restituisce la descrizione di una tabella
    public function describeTable($tableName) {
        $query = $this->pdo->prepare("DESCRIBE `$tableName`");
        $query->execute();
        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Restituisce il numero di righe in una tabella
    public function getTableRowCount($tableName) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM `$tableName`");
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? (int)$result['count'] : 0;
    }
    

    // Verifica se il database contiene le tabelle di sicurezza essenziali
    public function hasSecurityTables(): bool {
        $requiredTables = ['user_auth', 'banned_ips', 'rate_limits', 'failed_attempts', 'security_logs'];
        foreach ($requiredTables as $table) {
            if (!in_array($table, $this->dbTables)) {
                return false;
            }
        }
        return true;
    }

    public function getSecurityTables(): array {
        $requiredTables = ['user_auth', 'banned_ips', 'rate_limits', 'failed_attempts', 'security_logs'];
        $existingTables = [];
        foreach ($requiredTables as $table) {
            if (in_array($table, $this->dbTables)) {
                $existingTables[$table] = true;
            }
        }
        return $existingTables;
    }

    // Restituisce le colonne di una tabella con nome e tipo
    public function getTableColumns($tableName) {
        if (!in_array($tableName, $this->dbTables)) {
            throw new \Exception("Tabella '$tableName' non trovata nel database.");
        }
        
        $stmt = $this->pdo->prepare("DESCRIBE `$tableName`");
        $stmt->execute();
        $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($columns as $col) {
            $result[] = [
                'name' => $col['Field'],
                'type' => $col['Type'],
                'null' => $col['Null'],
                'key' => $col['Key'],
                'default' => $col['Default'],
                'extra' => $col['Extra']
            ];
        }
        
        return $result;
    }
}
