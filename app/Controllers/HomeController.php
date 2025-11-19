<?php
namespace App\Controllers;

use Core\Controller;
use Core\Security;
use Core\Database;
use Exception;

class HomeController extends Controller {
    public function index() {

        $connectionType = 'Not connected';
        $isConnected = false;
        $errorMessage = null;
        $tables = null;
        $selectedTableDetails = null;
        
        try {
            // Controllo se c'è connessione
            $db = db();
            $db->query('SELECT 1');
            $isConnected = true;
            
            $connectionType = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $tables = $db->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
            
            // Genera la struttura del database in JSON
            generateDatabaseStructure($db, $tables);
            
            // Crea un array con lo stato API di tutte le tabelle
            $apiStatus = [];
            // Calcola statistiche database
            $totalRows = 0;
            $tableStats = [];

            foreach ($tables as $table) {
                // Controlla se ha l'API
                $apiStatus[$table] = $this->hasApi($table);
                // Calcola il peso per ogni tabella (PER GRAFICO)
                $count = $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
                $totalRows += $count;
                $size = $db->query("
                    SELECT 
                        ROUND((data_length + index_length) / 1024, 2) as size_kb
                    FROM information_schema.TABLES 
                    WHERE table_schema = DATABASE()
                    AND table_name = '{$table}'
                ")->fetchColumn();
                $tableStats[] = [
                    'name' => $table,
                    'rows' => $count,
                    'size' => $size ?? 0
                ];
            }
            // Se è stata selezionata una tabella, carica i dettagli
            // Altrimenti seleziona la prima tabella di default
            $selectedTable = $_GET['table'] ?? ($tables[0] ?? null);
            
            if ($selectedTable && in_array($selectedTable, $tables)) {
                try {
                    $columns = $db->query("DESCRIBE `{$selectedTable}`")->fetchAll(\PDO::FETCH_ASSOC);
                    $rowCount = $db->query("SELECT COUNT(*) FROM `{$selectedTable}`")->fetchColumn();
                    
                    $selectedTableDetails = [
                        'name' => $selectedTable,
                        'columns' => $columns,
                        'rowCount' => $rowCount
                    ];
                } catch (\PDOException $e) {
                    $selectedTableDetails = [
                        'error' => $e->getMessage()
                    ];
                }
            }
        } catch (Exception $e) {
            $isConnected = false;
            $errorMessage = $e->getMessage();
            // Messaggio di errore se non è connesso ad un database
            error_log("Database connection failed: " . $e->getMessage());
        }
        // DATI PER LA PAGINA
        $data = [
            'title' => 'Welcome',
            'databaseType' => $connectionType,
            'connectionStatus' => $isConnected ? 'Connected' : 'Disconnected',
            'errorMessage' => $errorMessage,
            'tables' => $tables,
            'tableDetails' => $selectedTableDetails,
            'apiStatus' => $apiStatus ?? [],
            'totalTables' => isset($tables) ? count($tables) : 0,
            'totalRows' => $totalRows ?? 0,
            'tableStats' => $tableStats ?? []
        ];
        
        $this->view('home/index', $data);
    }
    


    //Funzione che vede se una tabella ha api dal file config (/config/api_config.json)
    private function hasApi(string $tableName): bool {
        $config = loadApiConfig();
        $databaseName = getDatabaseName();
        
        // Verifica se il database esiste e se la tabella ha enabled: true
        if (!$config || !isset($config[$databaseName][$tableName])) {
            return false;
        }
        
        return isset($config[$databaseName][$tableName]['enabled']) 
            && $config[$databaseName][$tableName]['enabled'] === true;
    }
}
