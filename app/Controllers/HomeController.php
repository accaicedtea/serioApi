<?php
namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Exception;

class HomeController extends Controller {
    private Database $db;
    public function __construct(Database $db) {
        $this->db = $db;
    }
    public function index() {

        $tables = null;
        $selectedTableDetails = null;
        
        if(!$this->db->isConnected()) {
            throw new Exception("Database non connesso");
        }      

        $tables = $this->db->getDatabaseTables();
        
        // Genera la struttura del database in JSON
        generateDatabaseStructure($this->db, $tables);
        
        // Crea un array con lo stato API di tutte le tabelle
        $apiStatus = [];
        
        foreach ($tables as $table) {
            // Controlla se ha l'API
            $apiStatus[$table] = $this->hasApi($table);
        }
        // Se è stata selezionata una tabella, carica i dettagli
        // Altrimenti seleziona la prima tabella di default
        $selectedTable = $_GET['table'] ?? ($tables[0] ?? null);
        
        if ($selectedTable && in_array($selectedTable, $tables)) {
            try {
                $columns = $this->db->describeTable($selectedTable);
                $rowCount = $this->db->getTableRowCount($selectedTable);
                
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

        $title = $this->db->isConnected() ? 'Home - Connesso' : 'Home - Disconnesso';
        
        // DATI PER LA PAGINA
        $data = [
            'title' => $title,
            'databaseType' => $this->db->getConnectionType(),
            'connectionStatus' => $this->db->isConnected() ? 'Connected' : 'Disconnected',
            'tables' => $tables,
            'tableDetails' => $selectedTableDetails,
            'apiStatus' => $apiStatus ?? [],
            'totalTables' => isset($tables) ? count($tables) : 0,
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