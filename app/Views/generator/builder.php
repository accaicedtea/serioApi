<section class="container-fluid py-4">
    <?php include_once __DIR__ . '/header.php'; ?>
    <?php if (isset($_GET['generated'])): ?>
    <div class="success-box">
        <h2> API Generate con Successo!</h2>
        <p>Le tue API sono pronte all'uso nella cartella: <strong><?= e($outputPath) ?></strong></p>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="error-box">
        <h2>Errore durante la generazione</h2>
        <p><?= e($_GET['error']) ?></p>
    </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-cogs me-2"></i>Caratteristiche del Progetto</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Struttura MVC
                                    completa</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Database
                                    connection class</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Router con
                                    supporto REST</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Security layer
                                    (rate limiting, auth)</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Modelli per ogni
                                    tabella</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Controller con
                                    CRUD completo</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Route file
                                    auto-configurato</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>File .htaccess per
                                    Apache</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>README con
                                    documentazione</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Sistema pronto per
                                    produzione</li>
                            </ul>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <span class="badge bg-info px-3 py-2">Progetto Enterprise Ready</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card text-center border-0 bg-light">
                        <div class="card-body">
                            <h2 class="text-primary mb-1"><?= $enabledCount ?></h2>
                            <p class="text-muted mb-0">Tabelle Abilitate</p>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card text-center border-0 bg-light">
                        <div class="card-body">
                            <h2 class="text-info mb-1"><?= $viewsCount ?></h2>
                            <p class="text-muted mb-0">Viste Personalizzate</p>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card text-center border-0 bg-gradient"
                        style="background: linear-gradient(45deg, #007bff, #6f42c1);">
                        <div class="card-body text-white">
                            <h2 class="mb-1"><?= ($enabledCount * 5) + $viewsCount ?></h2>
                            <p class="mb-0 opacity-75">Endpoint API Totali</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($enabledCount === 0 && $viewsCount === 0): ?>
    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
        <i class="fas fa-exclamation-triangle me-3"></i>
        <div>
            <strong>Attenzione:</strong> Non hai configurato nessuna tabella o vista.
            <a href="/generator" class="alert-link">Vai al Configuratore API</a> per abilitare le tabelle o creare viste
            personalizzate.
        </div>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white">
            <h3 class="mb-0"><i class="fas fa-rocket me-2"></i>Genera Progetto API</h3>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">Verrà creata una cartella completa e indipendente con tutte le API configurate
            </p>

            <div class="alert alert-info d-flex align-items-center mb-4">
                <i class="fas fa-folder me-2"></i>
                <span><strong>Percorso di Output:</strong> <?= e($outputPath) ?></span>
            </div>

            <form method="POST" action="/builder/generate" class="text-center"
                onsubmit="return confirm('Generare il progetto API? Eventuali file esistenti verranno sovrascritti.')">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <button type="submit" class="btn btn-success btn-lg px-5">
                    <i class="fas fa-download me-2"></i>Genera API Complete
                </button>
            </form>
        </div>
    </div>

    <?php if (isset($_GET['generated'])): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h4 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Come Procedere</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-primary"><i class="fas fa-desktop me-2"></i>Test Locale</h5>
                    <p>Entra nella cartella e avvia il server PHP:</p>
                    <div class="bg-dark text-light p-3 rounded mb-3">
                        <code>cd <?= e($outputPath) ?>/public<br>php -S localhost:8080</code>
                    </div>
                </div>
                <div class="col-md-6">
                    <h5 class="text-primary"><i class="fas fa-cloud-upload-alt me-2"></i>Deploy su Server</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-arrow-right text-muted me-2"></i>Copia la cartella sul server
                        </li>
                        <li class="mb-2"><i class="fas fa-arrow-right text-muted me-2"></i>Punta il document root a
                            <code>/public</code></li>
                        <li class="mb-2"><i class="fas fa-arrow-right text-muted me-2"></i>Leggi il file README.md</li>
                    </ul>
                </div>
            </div>

            <hr>

            <h5 class="text-primary mb-3"><i class="fas fa-code me-2"></i>Esempi di Chiamate API</h5>
            <div class="bg-dark text-light p-4 rounded">
                <div class="mb-3">
                    <small class="text-muted d-block mb-1"># Lista elementi</small>
                    <code class="text-success">curl http://localhost:8080/api/allergens</code>
                </div>

                <div class="mb-3">
                    <small class="text-muted d-block mb-1"># Singolo elemento</small>
                    <code class="text-info">curl http://localhost:8080/api/allergens/1</code>
                </div>

                <div>
                    <small class="text-muted d-block mb-1"># Crea nuovo elemento (con autenticazione)</small>
                    <code class="text-warning">curl -X POST http://localhost:8080/api/allergens \<br>
                -H "Content-Type: application/json" \<br>
                -H "Authorization: Bearer YOUR_TOKEN" \<br>
                -d '{"name":"Nuovo Item"}'</code>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>