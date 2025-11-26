<?php
namespace App\Controllers;

use Core\Controller;
use Core\Security;
// TODO: non funziona
class FtpDeployController extends Controller {
    private $generatedApiPath = __DIR__ . '/../../generated-api';
    
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
        return [
            'ftp_host' => env('FTP_HOST'),
            'ftp_port' => env('FTP_PORT'),
            'ftp_user' => env('FTP_USERNAME'),
            'ftp_pass' => env('FTP_PASSWORD'),
            'ftp_path' => env('FTP_REMOTE_PATH'),
            'ftp_ssl' => env('FTP_SSL'),
        ];
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
        try {
            // Sopprime i warning SSL durante la connessione
            error_reporting(E_ERROR | E_PARSE);
            
            // Connessione FTP
            $useSsl ? $conn = @ftp_ssl_connect($host, $port, 30) : $conn = @ftp_connect($host, $port, 30);


            !$conn ? throw new \Exception('Impossibile connettersi al server FTP') : null;

            // Login
            !@ftp_login($conn, $username, $password)?
                throw new \Exception('Login FTP fallito. Verifica username e password') : null;
            
            
            // Modalità passiva (risolve problemi con alcuni firewall)
            ftp_pasv($conn, true);
            
            // Ripristina error reporting
            error_reporting(E_ALL);
            
            // Crea la cartella remota se non esiste
            $this->createRemoteDir($conn, $remotePath);
            
            // Upload ricorsivo
            $uploadedFiles = $this->uploadDirectory($conn, $this->generatedApiPath, $remotePath);
            
            @ftp_close($conn);
            
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
            // Sopprime i warning SSL durante la connessione
            error_reporting(E_ERROR | E_PARSE);
            
            $useSsl ? $conn = @ftp_ssl_connect($host, $port, 10) : $conn = @ftp_connect($host, $port, 10);


            !$conn ? throw new \Exception('Impossibile connettersi al server') : null;

            !@ftp_login($conn, $username, $password) ? throw new \Exception('Login fallito') : null;
            
            
            $pwd = ftp_pwd($conn);
            @ftp_close($conn);
            
            // Ripristina error reporting
            error_reporting(E_ALL);
            
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