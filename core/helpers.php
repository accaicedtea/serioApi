<?php
// Helper functions

use Core\Database;

/**
 * Carica variabili d'ambiente dal file .env
 */
function loadEnv(?string $path = null): void {
    static $loaded = false;
    
    if ($loaded) {
        return;
    }
    
    $envFile = $path ?? __DIR__ . '/../config/.env';
    
    if (!file_exists($envFile)) {
        return;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse key=value
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            
            // Remove inline comments
            if (strpos($value, '#') !== false) {
                $value = trim(explode('#', $value)[0]);
            }
            
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
    
    $loaded = true;
}

/**
 * Ottiene una variabile d'ambiente
 */
function env(string $key, $default = null) {
    loadEnv();
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

/**
 * Escape output per prevenire XSS
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Shortcut per echo + escape
 */
function ee($value) {
    echo e($value);
}

/**
 * Ottiene una connessione al database
 * Helper per evitare di ripetere sempre new Database()->getConnection()
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
 * Carica la configurazione API
 */
function loadApiConfig(): array {
    $apiConfigPath = __DIR__ . '/../config/api_config.json';
    
    if (!file_exists($apiConfigPath)) {
        return [];
    }
    
    return json_decode(file_get_contents($apiConfigPath), true) ?? [];
}

/**
 * Salva la configurazione API
 */
function saveApiConfig(array $config): bool {
    $apiConfigPath = __DIR__ . '/../config/api_config.json';
    return file_put_contents(
        $apiConfigPath, 
        json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

/**
 * Genera la struttura completa del database in JSON
 */
function generateDatabaseStructure($db, $tables): void {
    try {
        $databaseName = getDatabaseName();
        $apiConfig = loadApiConfig();
        
        // Se il database non esiste ancora nel config, crealo
        if (!isset($apiConfig[$databaseName])) {
            $apiConfig[$databaseName] = [];
        }
        
        // Per ogni tabella del database corrente, aggiungi/aggiorna la configurazione
        foreach ($tables as $table) {
            if (!isset($apiConfig[$databaseName][$table])) {
                // Inizializza le tabelle che non hanno ancora una configurazione
                $apiConfig[$databaseName][$table] = [
                    'enabled' => false
                ];
            }
        }
        
        // Aggiungi le viste personalizzate come tabelle virtuali
        if (isset($apiConfig[$databaseName]['_views'])) {
            foreach ($apiConfig[$databaseName]['_views'] as $viewName => $viewConfig) {
                if (!isset($apiConfig[$databaseName]['_view_' . $viewName])) {
                    $apiConfig[$databaseName]['_view_' . $viewName] = [
                        'enabled' => false,
                        'is_virtual' => true,
                        'view_name' => $viewName
                    ];
                }
            }
        }
        
        saveApiConfig($apiConfig);
        
    } catch (\Exception $e) {
        error_log("Failed to update database structure: " . $e->getMessage());
    }
}
