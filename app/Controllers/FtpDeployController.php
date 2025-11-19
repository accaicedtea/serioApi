<?php
namespace App\Controllers;

use Core\Controller;
use Core\Security;

class FtpDeployController extends Controller {
    private $generatedApiPath = __DIR__ . '/../../generated-api';
    private $credentialsFile = __DIR__ . '/../../config/ftp_credentials.json';
    
    public function index() {
        // Verifica se la cartella generated-api esiste
        $apiExists = file_exists($this->generatedApiPath);
        
        // Carica le credenziali salvate (se esistono)
        $savedCredentials = $this->loadCredentials();
        
        $data = [
            'title' => 'Deploy FTP - Carica API sul Server',
            'apiExists' => $apiExists,
            'apiPath' => $this->generatedApiPath,
            'savedCredentials' => $savedCredentials
        ];
        
        $this->view('deploy/index', $data);
    }
    
    private function loadCredentials() {
        if (file_exists($this->credentialsFile)) {
            $json = file_get_contents($this->credentialsFile);
            return json_decode($json, true);
        }
        return [
            'ftp_host' => '',
            'ftp_port' => 21,
            'ftp_user' => '',
            'ftp_path' => '/public_html/api',
            'ftp_ssl' => false
        ];
    }
    
    private function saveCredentials($data) {
        // Non salvare la password in chiaro
        $credentials = [
            'ftp_host' => $data['ftp_host'] ?? '',
            'ftp_port' => $data['ftp_port'] ?? 21,
            'ftp_user' => $data['ftp_user'] ?? '',
            'ftp_path' => $data['ftp_path'] ?? '/public_html/api',
            'ftp_ssl' => isset($data['ftp_ssl'])
        ];
        
        // Crea la cartella config se non esiste
        $configDir = dirname($this->credentialsFile);
        if (!file_exists($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        file_put_contents($this->credentialsFile, json_encode($credentials, JSON_PRETTY_PRINT));
    }
    
    public function upload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /deploy');
            exit;
        }
        
        if (!e($_POST['csrf_token'])) {
            die('Token CSRF non valido');
        }
        
        $host = $_POST['ftp_host'] ?? '';
        $username = $_POST['ftp_user'] ?? '';
        $password = $_POST['ftp_pass'] ?? '';
        $remotePath = $_POST['ftp_path'] ?? '/';
        $port = (int)($_POST['ftp_port'] ?? 21);
        $useSsl = isset($_POST['ftp_ssl']);
        
        if (empty($host) || empty($username) || empty($password)) {
            header('Location: /deploy?error=' . urlencode('Compila tutti i campi obbligatori'));
            exit;
        }
        
        // Salva le credenziali (esclusa la password)
        $this->saveCredentials($_POST);
        
        try {
            // Connessione FTP
            $useSsl ? $conn = @ftp_ssl_connect($host, $port, 30) : $conn = @ftp_connect($host, $port, 30);


            !$conn ? throw new \Exception('Impossibile connettersi al server FTP') : null;

            // Login
            !@ftp_login($conn, $username, $password)?
                throw new \Exception('Login FTP fallito. Verifica username e password') : null;
            
            
            // Modalità passiva (risolve problemi con alcuni firewall)
            ftp_pasv($conn, true);
            
            // Crea la cartella remota se non esiste
            $this->createRemoteDir($conn, $remotePath);
            
            // Upload ricorsivo
            $uploadedFiles = $this->uploadDirectory($conn, $this->generatedApiPath, $remotePath);
            
            ftp_close($conn);
            
            header('Location: /deploy?success=1&files=' . $uploadedFiles);
            exit;
            
        } catch (\Exception $e) {
            header('Location: /deploy?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
    
    public function testConnection() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Metodo non valido']);
            exit;
        }
        
        if (!e($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF non valido']);
            exit;
        }
        
        $host = $_POST['ftp_host'] ?? '';
        $username = $_POST['ftp_user'] ?? '';
        $password = $_POST['ftp_pass'] ?? '';
        $port = (int)($_POST['ftp_port'] ?? 21);
        $useSsl = isset($_POST['ftp_ssl']);
        
        try {
            $useSsl ? $conn = @ftp_ssl_connect($host, $port, 10) : $conn = @ftp_connect($host, $port, 10);


            !$conn ? throw new \Exception('Impossibile connettersi al server') : null;

            !@ftp_login($conn, $username, $password) ? throw new \Exception('Login fallito') : null;
            
            
            $pwd = ftp_pwd($conn);
            ftp_close($conn);
            
            echo json_encode([
                'success' => true,
                'message' => 'Connessione riuscita!',
                'directory' => $pwd
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    private function createRemoteDir($conn, $dir) {
        $parts = explode('/', trim($dir, '/'));
        $path = '';
        
        foreach ($parts as $part) {
            if (empty($part)) continue;
            $path .= '/' . $part;
            
            if (!@ftp_chdir($conn, $path)) {
                if (!@ftp_mkdir($conn, $path)) {
                    continue; // La cartella potrebbe già esistere
                }
            }
        }
    }
    
    private function uploadDirectory($conn, $localDir, $remoteDir, &$count = 0) {
        $files = scandir($localDir);
        
        // Assicurati che la cartella remota esista veramente
        @ftp_mkdir($conn, $remoteDir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $localPath = $localDir . '/' . $file;
            $remotePath = $remoteDir . '/' . $file;
            
            if (is_dir($localPath)) {
                // Upload ricorsivo delle sottocartelle
                $this->uploadDirectory($conn, $localPath, $remotePath, $count);
            } else {
                // Upload del file
                if (@ftp_put($conn, $remotePath, $localPath, FTP_BINARY)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
}