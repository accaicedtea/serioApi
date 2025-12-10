<style>
    /* Scoped styles for Navbar component */
    .app-navbar .nav-link.active {
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 0.25rem;
        font-weight: 500;
    }
    
    .db-status {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.75rem;
        border-radius: 0.25rem;
        font-size: 0.875rem;
    }
    
    .db-status.connected {
        background-color: rgba(40, 167, 69, 0.2);
        color: #28a745;
    }
    
    .db-status.disconnected {
        background-color: rgba(220, 53, 69, 0.2);
        color: #dc3545;
    }
    
    .db-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
    
    .db-status.connected .db-status-dot {
        background-color: #28a745;
    }
    
    .db-status.disconnected .db-status-dot {
        background-color: #dc3545;
    }
    
    /* animazione */
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
</style>

<?php
// Ottieni il path corrente per evidenziare la voce attiva
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$currentPath = rtrim($currentPath, '/');
if (empty($currentPath)) $currentPath = '/';

//TODO: da completare Verifica connessione database
$dbConnected = false;
$dbName = 'N/A';

?>

<nav class="navbar navbar-expand-lg navbar-dark app-navbar bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="/">
            <span class="db-status <?= $dbConnected ? 'connected' : 'disconnected' ?>">
                <span class="db-status-dot"></span>
                <?= $dbConnected ? "DB: {$dbName}" : 'DB Disconnesso' ?>
            </span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">

                <li class="nav-item">
                    <a class="nav-link <?= $currentPath === '/' ? 'active' : '' ?>" href="/">Home</a>
                </li>
                <?php if ($dbConnected): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPath === '/generator' ? 'active' : '' ?>" href="/generator">Generator</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $currentPath === '/generator/builder' ? 'active' : '' ?>" href="/generator/builder">Builder</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPath === '/deploy' ? 'active' : '' ?>" href="/deploy">Deploy</a>
                </li>
                
                <?php else: ?>
                    <li class="nav-item">
                    <a class="nav-link <?= $currentPath === '/settings' ? 'active' : '' ?>" href="/settings">Settings</a>
                </li>
                <?php endif;?>
            </ul>
        </div>
    </div>
</nav>