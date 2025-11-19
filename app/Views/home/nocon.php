<div class="container-fluid">
    <div class="alert alert-warning text-center">
        <h4 class="text-danger">Server Database Non Avviato</h4>
        <p>Per utilizzare l'applicazione è necessario avviare un server database locale.</p>

        <div class="row mt-4">
            <div class="col-md-4">
                <h5><i class="fab fa-windows"></i> Windows</h5>
                <p>Scarica e installa XAMPP:</p>
                <a href="https://www.apachefriends.org/download.html" target="_blank" class="btn btn-primary btn-sm">
                    Download XAMPP
                </a>
            </div>

            <div class="col-md-4">
                <h5><i class="fab fa-apple"></i> macOS</h5>
                <p>Installa MAMP o usa Homebrew:</p>
                <a href="https://www.mamp.info/en/downloads/" target="_blank" class="btn btn-primary btn-sm">
                    Download MAMP
                </a>
                <br><small class="text-muted mt-2 d-block">O usa: brew install mysql</small>
            </div>

            <div class="col-md-4">
                <h5><i class="fab fa-linux"></i> Linux</h5>
                <p>Installa LAMP stack:</p>
                <code class="d-block bg-dark text-light p-2">
                        sudo apt install apache2 mysql-server php
                    </code>
                <small class="text-muted">Ubuntu/Debian</small>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-6">
                <h5><i class="fas fa-code"></i> VS Code</h5>
                <p>Configura il database direttamente in VS Code:</p>
                <p><code class="d-block bg-dark text-light p-2">
                        config/database.php
                    </code></p>
            </div>
            <div class="col-6">
                <h5><i class="fas fa-code"></i> Linux</h5>
                <p>Avvia lampp</p>
                <p><code class="d-block bg-dark text-light p-2">
                        sudo /opt/lampp/lampp start
                    </code></p>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col text-center">
            <button onclick="location.href='/settings'" class="btn btn-success me-2">
                <i class="fas fa-sync-alt"></i> Configura Database
            </button>
            <button onclick="location.reload()" class="btn btn-danger">
                <i class="fas fa-sync-alt"></i> Riprova Connessione
            </button>
            </div>
        </div>
    </div>
</div>