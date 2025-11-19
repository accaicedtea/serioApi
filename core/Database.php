<?php
namespace Core;

class Database {
    private $pdo = null;

    public function __construct() {
        if ($this->pdo === null) {
            $config = require __DIR__ . '/../config/database.php';
            
            if ($config === null) {
                throw new \Exception('Database configuration file not found.');
            }
            if (!isset($config['host'], $config['dbname'], $config['user'], $config['pass'], $config['charset'])) {
                throw new \Exception('Database configuration is incomplete.');
            }
            if ($config['host'] == 'localhost') {
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
                throw new \Exception('Database connection failed: ' . $e->getMessage());
            }
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
