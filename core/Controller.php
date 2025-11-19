<?php
namespace Core;

class Controller {
    protected function view($view, $data = []) {
        // Aggiungi automaticamente il CSRF token a tutte le viste
        $data['csrf_token'] = Security::csrfToken();
        
        // Aggiungi Stile personalizzato
        $data['style'] = '';

        extract($data);
        
        // Inizia output buffering per modificare il contenuto
        ob_start();
        
        // Include Header
        if(file_exists(__DIR__ . '/../app/Views/components/Header.php')){
            require __DIR__ . '/../app/Views/components/Header.php';
        }

        // Include NavBar
        if (file_exists(__DIR__ . '/../app/Views/components/Navbar.php')) {
            require __DIR__ . '/../app/Views/components/Navbar.php';
        }
        
        // Wrapper per il contenuto della pagina
        echo '<div class="page-content-wrapper">';
        
        // Include la vista principale
        require_once __DIR__ . '/../app/Views/' . $view . '.php';
        
        // Chiudi wrapper
        echo '</div>';


        // Include Footer
        if (file_exists(__DIR__ . '/../app/Views/components/Footer.php')) {
            require __DIR__ . '/../app/Views/components/Footer.php';
        }
        
        $content = ob_get_clean();
        
        echo $content;
    }
}
