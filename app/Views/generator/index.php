<section class="container-fluid py-4">
        
        <?php include_once __DIR__ . '/header.php'; ?>
        
        <?php if (isset($_GET['saved'])): ?>
        <!-- MESSAGGIO DI SUCCESSO SALVATAGGIO CONFIGURAZIONE -->
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Successo!</strong> Configurazione salvata con successo!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['security_created'])): ?>
        <!-- MESSAGGIO DI SUCCESSO AGGIUNTA TABELLE -->
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Successo!</strong> Tabelle di sicurezza create con successo! Utenti di default: admin@menucrud.com
            (admin123), manager@menucrud.com (manager123)
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <!-- MESSAGGIO DI ERRORE -->
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5 class="alert-heading">Errore</h5>
            <p class="mb-0"><?= e($_GET['error']) ?></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <!-- MESSAGGIO DI ERRORE -->
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5 class="alert-heading">Errore</h5>
            <p class="mb-0"><?= e($error) ?></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php else: ?>
        <!-- SEZIONE SETUP DI SICUREZZA -->
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">Setup Database Sicurezza</h5>
            </div>

            <div class="card-body">

                <p class="text-muted">
                    Le seguenti tabelle sono necessarie per il sistema di autenticazione, rate limiting e logging di
                    sicurezza.
                </p>

                <div class="row g-3 mb-3">
                    <div class="col-md-6 col-lg-4">
                        <?php if ($securityTables['user_auth']): ?>
                        <span class="badge bg-success w-100 py-2">
                            <i class="bi bi-check-circle"></i> <strong>user_auth</strong> (Utenti)
                        </span>
                        <?php else: ?>
                        <span class="badge bg-secondary w-100 py-2">
                            <i class="bi bi-x-circle"></i> <strong>user_auth</strong> (Utenti)
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <?php if ($securityTables['banned_ips']): ?>
                        <span class="badge bg-success w-100 py-2">
                            <i class="bi bi-check-circle"></i> <strong>banned_ips</strong> (IP Bannati)
                        </span>
                        <?php else: ?>
                        <span class="badge bg-secondary w-100 py-2">
                            <i class="bi bi-x-circle"></i> <strong>banned_ips</strong> (IP Bannati)
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <?php if ($securityTables['rate_limits']): ?>
                        <span class="badge bg-success w-100 py-2">
                            <i class="bi bi-check-circle"></i> <strong>rate_limits</strong> (Rate Limiting)
                        </span>
                        <?php else: ?>
                        <span class="badge bg-secondary w-100 py-2">
                            <i class="bi bi-x-circle"></i> <strong>rate_limits</strong> (Rate Limiting)
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <?php if ($securityTables['failed_attempts']): ?>
                        <span class="badge bg-success w-100 py-2">
                            <i class="bi bi-check-circle"></i> <strong>failed_attempts</strong> (Tentativi Falliti)
                        </span>
                        <?php else: ?>
                        <span class="badge bg-secondary w-100 py-2">
                            <i class="bi bi-x-circle"></i> <strong>failed_attempts</strong> (Tentativi Falliti)
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <?php if ($securityTables['security_logs']): ?>
                        <span class="badge bg-success w-100 py-2">
                            <i class="bi bi-check-circle"></i> <strong>security_logs</strong> (Log Sicurezza)
                        </span>
                        <?php else: ?>
                        <span class="badge bg-secondary w-100 py-2">
                            <i class="bi bi-x-circle"></i> <strong>security_logs</strong> (Log Sicurezza)
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$securityTables['user_auth'] || !$securityTables['banned_ips'] || !$securityTables['rate_limits'] || !$securityTables['failed_attempts'] || !$securityTables['security_logs']): ?>
                
                <form method="POST" action="/generator/security">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-tools"></i> Crea Tabelle di Sicurezza
                    </button>
                </form>
                <?php else: ?>
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle"></i> Tutte le tabelle di sicurezza sono già state create!
                </div>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST" action="/generator/save" id="configForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
            <input type="hidden" name="config" id="configInput">

            <!-- Configurazioni per Tabelle -->
            <?php foreach ($tables as $table): ?>
            <?php 
                    // Ottieni la configurazione dalla struttura del database corrente
                    $tableConfig = (isset($currentDatabase) && isset($config[$currentDatabase][$table])) 
                        ? $config[$currentDatabase][$table] 
                        : ['enabled' => false];
                    $enabled = $tableConfig['enabled'] ?? false;
                    $isVirtual = $tableConfig['is_virtual'] ?? false;
                    ?>
            <div class="card mb-3 <?= $isVirtual ? 'border-primary' : '' ?>">
                <div
                    class="card-header d-flex justify-content-between align-items-center <?= $isVirtual ? 'bg-light' : '' ?>">
                    <h5 class="mb-0">
                        <?php if ($isVirtual): ?>
                        <span class="badge bg-primary me-2">
                            <i class="bi bi-eye"></i> <?= e($table) ?>
                        </span>
                        <small class="text-muted">(Vista Personalizzata)</small>
                        <?php else: ?>
                        <span class="badge bg-info text-dark me-2"><?= e($table) ?></span>
                        <?php endif; ?>
                    </h5>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input enable-table" id="enable_<?= e($table) ?>"
                            data-table="<?= e($table) ?>" <?= $enabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enable_<?= e($table) ?>">Abilita API</label>
                    </div>
                </div>

                <!-- Configurazioni specifiche per tabella -->
                <div class="card-body table-settings <?= !$enabled ? 'opacity-50' : '' ?>"
                    data-table="<?= e($table) ?>">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                Rate Limit
                            </label>
                            <input type="number" name="<?= $table ?>_rate_limit"
                                value="<?= $tableConfig['rate_limit'] ?? 100 ?>" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                Finestra (sec)
                            </label>
                            <input type="number" name="<?= $table ?>_rate_window"
                                value="<?= $tableConfig['rate_limit_window'] ?? 60 ?>" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                Max Risultati
                            </label>
                            <input type="number" name="<?= $table ?>_max_results"
                                value="<?= $tableConfig['max_results'] ?? 100 ?>" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="<?= $table ?>_enable_cache" class="form-check-input"
                                    id="cache_<?= e($table) ?>"
                                    <?= ($tableConfig['enable_cache'] ?? false) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="cache_<?= e($table) ?>">Cache</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                Cache TTL (sec)
                            </label>
                            <input type="number" name="<?= $table ?>_cache_ttl"
                                value="<?= $tableConfig['cache_ttl'] ?? 300 ?>" class="form-control">
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-3">Permessi Operazioni</h6>

                    <div class="table-responsive permissions-grid <?= !$enabled ? 'opacity-50' : '' ?>"
                        data-table="<?= e($table) ?>">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Operazione</th>
                                    <th>Nessuno</th>
                                    <th>Tutti</th>
                                    <th>Autenticati</th>
                                    <th>Admin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                        $operations = ['select' => 'SELECT', 'insert' => 'INSERT', 'update' => 'UPDATE', 'delete' => 'DELETE'];
                                        $levels = ['none' => 'Nessuno', 'all' => 'Tutti', 'auth' => 'Autenticati', 'admin' => 'Admin'];
                                        $badgeColors = [
                                            'select' => 'primary',
                                            'insert' => 'success',
                                            'update' => 'warning',
                                            'delete' => 'danger'
                                        ];
                                        
                                        // Default permission levels
                                        $defaultLevels = [
                                            'select' => 'all',
                                            'insert' => 'auth',
                                            'update' => 'auth',
                                            'delete' => 'admin'
                                        ];
                                        
                                        foreach ($operations as $op => $label):
                                            $currentLevel = $tableConfig[$op] ?? $defaultLevels[$op];
                                        ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?= $badgeColors[$op] ?>"><?= $label ?></span>
                                    </td>
                                    <?php foreach ($levels as $level => $levelLabel): ?>
                                    <td>
                                        <div class="form-check">
                                            <input type="radio" class="form-check-input" name="<?= $table ?>_<?= $op ?>"
                                                value="<?= $level ?>" id="<?= $table ?>_<?= $op ?>_<?= $level ?>"
                                                data-table="<?= e($table) ?>" data-operation="<?= $op ?>"
                                                <?= $currentLevel === $level ? 'checked' : '' ?>>
                                            <label class="form-check-label"
                                                for="<?= $table ?>_<?= $op ?>_<?= $level ?>">
                                                <?= $levelLabel ?>
                                            </label>
                                        </div>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> Salva Configurazione
                </button>
            </div>
        </form>
        <?php endif; ?>

        <a href="/" class="btn btn-secondary mt-4">
            <i class="bi bi-arrow-left"></i> Torna alla Home
        </a>
</section>


<script>
// Gestione enable/disable tabelle
document.querySelectorAll('.enable-table').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const table = this.dataset.table;
        const permissionsGrid = document.querySelector(`.permissions-grid[data-table="${table}"]`);
        const tableSettings = document.querySelector(`.table-settings[data-table="${table}"]`);
        if (this.checked) {
            permissionsGrid.classList.remove('opacity-50');
            tableSettings.classList.remove('opacity-50');
        } else {
            permissionsGrid.classList.add('opacity-50');
            tableSettings.classList.add('opacity-50');
        }
    });
});

// Salva configurazione in JSON prima del submit
document.getElementById('configForm').addEventListener('submit', function(e) {
    const config = {
        "<?= e($currentDatabase) ?>": {}
    };

    document.querySelectorAll('.enable-table').forEach(checkbox => {
        const table = checkbox.dataset.table;
        if (checkbox.checked) {
            config["<?= e($currentDatabase) ?>"][table] = { enabled: true };

            // Specifiche tabella
            config["<?= e($currentDatabase) ?>"][table].rate_limit = parseInt(document.querySelector(
                `input[name="${table}_rate_limit"]`).value);
            config["<?= e($currentDatabase) ?>"][table].rate_limit_window = parseInt(document.querySelector(
                `input[name="${table}_rate_window"]`).value);
            config["<?= e($currentDatabase) ?>"][table].max_results = parseInt(document.querySelector(
                `input[name="${table}_max_results"]`).value);

            // RIMOSSO: require_auth
            config["<?= e($currentDatabase) ?>"][table].enable_cache = document.querySelector(
                `input[name="${table}_enable_cache"]`).checked;
            config["<?= e($currentDatabase) ?>"][table].cache_ttl = parseInt(document.querySelector(
                `input[name="${table}_cache_ttl"]`).value);

            // Permessi operazioni
            ['select', 'insert', 'update', 'delete'].forEach(op => {
                const selected = document.querySelector(`input[name="${table}_${op}"]:checked`);
                if (selected) {
                    config["<?= e($currentDatabase) ?>"][table][op] = selected.value;
                }
            });
        } else {
            config["<?= e($currentDatabase) ?>"][table] = { enabled: false };
        }
    });

    document.getElementById('configInput').value = JSON.stringify(config);
});
</script>