<?php
namespace Core;

class Database {
    private $pdo = null;

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
}
