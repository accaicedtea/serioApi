<?php
// Helper functions

use Core\Database;

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
