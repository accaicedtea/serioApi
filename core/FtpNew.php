<?php
//Thanks for iYETER on http://stackoverflow.com/questions/927341/upload-entire-directory-via-php-ftp
namespace Core;

class FtpNew {
private $connectionID;
private $ftpSession = false;
private $blackList = array('.', '..', 'Thumbs.db');

public function __construct($ftpHost = "", $port = 21, $useSsl = false) {
    if ($ftpHost != "") {
        if ($useSsl) {
            $this->connectionID = @ftp_ssl_connect($ftpHost, $port, 30);
        } else {
            $this->connectionID = @ftp_connect($ftpHost, $port, 30);
        }
        
        if (!$this->connectionID) {
            throw new \Exception("Impossibile connettersi a $ftpHost sulla porta $port");
        }
    }
}

public function __destruct() {
    $this->disconnect();
}

public function connect($ftpHost, $port = 21, $useSsl = false) {     
    $this->disconnect();
    
    if ($useSsl) {
        $this->connectionID = @ftp_ssl_connect($ftpHost, $port, 30);
    } else {
        $this->connectionID = @ftp_connect($ftpHost, $port, 30);
    }
    
    if (!$this->connectionID) {
        throw new \Exception("Impossibile connettersi a $ftpHost sulla porta $port");
    }
    
    return $this->connectionID;
}

public function login($ftpUser, $ftpPass) {
    if (!$this->connectionID) throw new \Exception("Connection not established.", -1);
    $this->ftpSession = @ftp_login($this->connectionID, $ftpUser, $ftpPass);
    
    if ($this->ftpSession) {
        @ftp_pasv($this->connectionID, true);
    }
    
    return $this->ftpSession;
}

public function disconnect() {
    if (isset($this->connectionID)) {
        @ftp_close($this->connectionID);
        unset($this->connectionID);
    }
}

public function send_recursive_directory($localPath, $remotePath) {
    // Prima elimina la cartella remota se esiste
    $this->delete_directory($remotePath);
    
    // Crea tutta la struttura delle directory
    $this->create_directory_structure($localPath, $remotePath);
    
    // Poi carica tutti i file
    return $this->upload_all_files($localPath, $remotePath);
}

public function delete_directory($remotePath) {
    // Prova a cambiare nella directory
    if (!@ftp_chdir($this->connectionID, $remotePath)) {
        // La directory non esiste, niente da eliminare
        return;
    }
    
    // Torna alla root
    @ftp_cdup($this->connectionID);
    
    // Ottieni la lista dei file nella directory
    $files = @ftp_nlist($this->connectionID, $remotePath);
    
    if ($files === false || empty($files)) {
        // Directory vuota o non esiste, prova a eliminarla
        @ftp_rmdir($this->connectionID, $remotePath);
        return;
    }
    
    // Elimina ricorsivamente tutti i file e sottocartelle
    foreach ($files as $file) {
        // Salta . e ..
        if ($file === '.' || $file === '..' || basename($file) === '.' || basename($file) === '..') {
            continue;
        }
        
        // Determina se è una directory provando a cambiare
        if (@ftp_chdir($this->connectionID, $file)) {
            @ftp_cdup($this->connectionID);
            // È una directory, eliminala ricorsivamente
            $this->delete_directory($file);
        } else {
            // È un file, eliminalo
            @ftp_delete($this->connectionID, $file);
        }
    }
    
    // Elimina la directory stessa
    @ftp_rmdir($this->connectionID, $remotePath);
}

private function create_directory_structure($localPath, $remotePath) {
    if (!is_dir($localPath)) {
        throw new \Exception("Directory non valida: $localPath");
    }
    
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($localPath, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            $subPath = $iterator->getSubPathname();
            $remoteDir = $remotePath . '/' . str_replace('\\', '/', $subPath);
            
            // Crea la directory remota
            $this->make_directory($remoteDir);
        }
    }
}

private function upload_all_files($localPath, $remotePath) {
    $errorList = array();
    
    if (!is_dir($localPath)) {
        throw new \Exception("Directory non valida: $localPath");
    }
    
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($localPath, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $subPath = $iterator->getSubPathname();
            $localFile = $item->getPathname();
            $remoteFile = $remotePath . '/' . str_replace('\\', '/', $subPath);
            
            // Carica il file
            $error = $this->put_file($localFile, $remoteFile);
            if (!empty($error)) {
                $errorList[$remoteFile] = $error;
            }
        }
    }
    
    return $errorList;
}

private function recurse_directory($rootPath, $localPath, $remotePath) {
    $errorList = array();
    if (!is_dir($localPath)) throw new \Exception("Invalid directory: $localPath");
    chdir($localPath);
    $directory = opendir(".");
    while ($file = readdir($directory)) {
        if (in_array($file, $this->blackList)) continue;
        if (is_dir($file)) {
            $errorList["$remotePath/$file"] = $this->make_directory("$remotePath/$file");
            $errorList[] = $this->recurse_directory($rootPath, "$localPath/$file", "$remotePath/$file");
            chdir($localPath);
        } else {
            $errorList["$remotePath/$file"] = $this->put_file("$localPath/$file", "$remotePath/$file");
        }
    }
    return $errorList;
}

public function make_directory($remotePath) {
    $error = "";
    
    // Crea directory ricorsivamente se necessario
    $parts = explode('/', trim($remotePath, '/'));
    $currentPath = '';
    
    foreach ($parts as $part) {
        if (empty($part)) continue;
        $currentPath .= '/' . $part;
        
        // Prova a creare la directory (ignora se esiste già)
        @ftp_mkdir($this->connectionID, $currentPath);
    }
    
    return $error;
}

public function put_file($localPath, $remotePath) {
    $error = "";
    try {
        @ftp_put($this->connectionID, $remotePath, $localPath, FTP_BINARY); 
    } catch (\Exception $e) {
        if ($e->getCode() == 2) $error = $e->getMessage(); 
    }
    return $error;
}

}
