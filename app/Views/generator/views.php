<section class="container-fluid py-4">
    <?php include_once __DIR__ . '/header.php'; ?>

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> Vista salvata con successo!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-trash-alt"></i> Vista eliminata con successo!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Errore</h5>
        <p class="mb-0"><?= e($error) ?></p>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php else: ?>

    <!-- Form Builder -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h2 class="h4 mb-0"><i class="fas fa-plus-circle"></i> Crea Nuova Vista API</h2>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <!-- Colonna Sinistra: Info Base -->
                <div class="col-md-5">
                    <div class="card h-100 border-0 bg-light">
                        <div class="card-body">
                            <h5 class="card-title text-primary mb-3">
                                <i class="fas fa-info-circle"></i> Informazioni Base
                            </h5>

                            <div class="mb-3">
                                <label for="view_name" class="form-label fw-bold">Nome Vista</label>
                                <input type="text" class="form-control" id="view_name"
                                    placeholder="es: prodotti-attivi">
                                <small class="text-muted">Utilizzare solo lettere minuscole e trattini</small>
                            </div>

                            <div class="mb-0">
                                <label for="view_description" class="form-label fw-bold">Descrizione</label>
                                <textarea class="form-control" id="view_description" rows="3"
                                    placeholder="Descrivi brevemente questa vista..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Colonna Destra: Configurazione -->
                <div class="col-md-7">
                    <div class="card h-100 border-0 bg-light">
                        <div class="card-body">
                            <h5 class="card-title text-primary mb-3">
                                <i class="fas fa-cog"></i> Configurazione Parametri
                            </h5>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Rate Limit (req/min)</label>
                                    <input type="number" class="form-control" id="view_rate_limit" value="100">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Finestra (secondi)</label>
                                    <input type="number" class="form-control" id="view_rate_window" value="60">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Max Risultati</label>
                                    <input type="number" class="form-control" id="view_max_results" value="100">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Cache TTL (secondi)</label>
                                    <input type="number" class="form-control" id="view_cache_ttl" value="300">
                                </div>

                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="view_require_auth">
                                        <label class="form-check-label fw-bold" for="view_require_auth">
                                            <i class="fas fa-lock"></i> Richiedi Autenticazione
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="view_enable_cache">
                                        <label class="form-check-label fw-bold" for="view_enable_cache">
                                            <i class="fas fa-database"></i> Abilita Cache
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Query SQL Section -->
        <div class="mt-4">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <label for="view_query" class="form-label fw-bold fs-5">
                        <i class="fas fa-code"></i> Query SQL
                    </label>

                    <?php if (!empty($tables)): ?>
                    <div class="mb-3">
                        <small class="text-muted d-block mb-2">
                            <i class="fas fa-hand-pointer"></i> Clicca su una tabella per inserirla nella query:
                        </small>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($tables as $table): ?>
                            <span class="badge bg-primary fs-6" style="cursor: pointer;"
                                onclick="insertTable('<?= e($table) ?>')">
                                <i class="fas fa-table"></i> <?= e($table) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <textarea name="query" id="view_query" class="form-control font-monospace" rows="8"
                        style="font-size: 14px;"
                        placeholder="Inserisci qui la tua query SELECT...&#10;Esempio: SELECT * FROM nome_tabella WHERE status = 'active' LIMIT 10"><?= isset($_POST['query']) ? e($_POST['query']) : '' ?></textarea>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-4 d-flex gap-2">
            <button type="button" onclick="testQuery()" class="btn btn-info">
                <i class="fas fa-play"></i> Testa Query
            </button>
            <button type="button" id="saveViewBtn" onclick="saveView()" class="btn btn-success" disabled>
                <i class="fas fa-save"></i> Salva Vista
            </button>
        </div>

        <!-- Results Preview -->
        <div id="resultPreview" class="mt-4" style="display: none;">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-check-circle"></i> Risultati Anteprima</h5>
                </div>
                <div class="card-body">
                    <div id="resultContent"></div>
                </div>
            </div>
        </div>
    </div>


    <!-- Lista Viste Esistenti -->
    <div class="row mt-5">
        <div class="col">
            <h2 class="h3 mb-4">
                <i class="fas fa-list"></i> Viste Configurate
            </h2>

            <?php if (empty($viewsConfig)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Nessuna vista configurata. Crea la tua prima vista personalizzata!
            </div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($viewsConfig as $name => $view): ?>
                <?php $isEnabled = $viewsEnabled[$name] ?? false; ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm <?= $isEnabled ? 'border-success' : '' ?>">
                        <div class="card-header bg-gradient d-flex justify-content-between align-items-center"
                            style="background: linear-gradient(135deg, <?= $isEnabled ? '#28a745 0%, #20c997 100%' : '#667eea 0%, #764ba2 100%' ?>);">
                            <h5 class="text-white mb-0">
                                <i class="fas fa-eye"></i> <?= e($view['name']) ?>
                            </h5>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" 
                                    id="toggle_view_<?= e($name) ?>"
                                    <?= $isEnabled ? 'checked' : '' ?>
                                    onchange="toggleView('<?= e($name) ?>')"
                                    style="cursor: pointer; width: 3em; height: 1.5em;">
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($isEnabled): ?>
                            <div class="alert alert-success py-2 mb-3">
                                <i class="fas fa-check-circle"></i> <strong>API Abilitata</strong>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-secondary py-2 mb-3">
                                <i class="fas fa-times-circle"></i> <strong>API Disabilitata</strong>
                            </div>
                            <?php endif; ?>
                            
                            <p class="text-muted"><?= e($view['description'] ?? 'Nessuna descrizione') ?></p>

                            <div class="mb-3">
                                <code class="d-block bg-light p-2 rounded">
                                    GET /api/views/<?= e($view['name']) ?>
                                </code>
                            </div>

                            <details class="mb-3">
                                <summary class="fw-bold" style="cursor: pointer;">
                                    <i class="fas fa-code"></i> Mostra Query
                                </summary>
                                <pre class="bg-dark text-light p-3 rounded mt-2 mb-0"
                                    style="font-size: 12px;"><code><?= e($view['query']) ?></code></pre>
                            </details>

                            <div class="d-flex flex-wrap gap-1 mb-3">
                                <?php if ($view['require_auth'] ?? false): ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-lock"></i> Auth
                                </span>
                                <?php endif; ?>
                                <?php if ($view['enable_cache'] ?? false): ?>
                                <span class="badge bg-info">
                                    <i class="fas fa-database"></i> Cache <?= $view['cache_ttl'] ?>s
                                </span>
                                <?php endif; ?>
                                <span class="badge bg-secondary">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <?= $view['rate_limit'] ?>/<?= $view['rate_limit_window'] ?>s
                                </span>
                            </div>

                            <form method="POST" action="/generator/delete-view" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                <input type="hidden" name="view_name" value="<?= e($name) ?>">
                                <button type="submit" class="btn btn-danger btn-sm w-100"
                                    onclick="return confirm('Sei sicuro di voler eliminare questa vista?')">
                                    <i class="fas fa-trash"></i> Elimina
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="text-center mt-5">
        <a href="/generator" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Torna alla Configurazione Tabelle
        </a>
    </div>
</section>

<script>
// Disabilita il pulsante Salva se la query viene modificata
document.addEventListener('DOMContentLoaded', function() {
    const queryTextarea = document.getElementById('view_query');
    const saveBtn = document.getElementById('saveViewBtn');

    queryTextarea.addEventListener('input', function() {
        // Se la query viene modificata, disabilita nuovamente il pulsante
        saveBtn.disabled = true;
    });
});

function insertTable(tableName) {
    const textarea = document.getElementById('view_query');
    const cursorPos = textarea.selectionStart;
    const textBefore = textarea.value.substring(0, cursorPos);
    const textAfter = textarea.value.substring(cursorPos);

    // Inserisce il nome della tabella nella posizione del cursore
    textarea.value = textBefore + tableName + textAfter;

    // Riposiziona il cursore dopo il nome della tabella inserito
    const newCursorPos = cursorPos + tableName.length;
    textarea.setSelectionRange(newCursorPos, newCursorPos);
    textarea.focus();

    // Disabilita il pulsante Salva quando la query viene modificata
    document.getElementById('saveViewBtn').disabled = true;
}

async function toggleView(viewName) {
    const checkbox = document.getElementById('toggle_view_' + viewName);
    const originalState = checkbox.checked;
    
    try {
        const formData = new FormData();
        formData.append('csrf_token', '<?= e($csrf_token) ?>');
        formData.append('view_name', viewName);
        
        const response = await fetch('/generator/toggle-view', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Ricarica la pagina per aggiornare l'UI
            window.location.reload();
        } else {
            // Ripristina lo stato originale in caso di errore
            checkbox.checked = originalState;
            alert('Errore: ' + result.error);
        }
    } catch (error) {
        // Ripristina lo stato originale in caso di errore
        checkbox.checked = originalState;
        alert('Errore durante l\'aggiornamento: ' + error.message);
    }
}

async function testQuery() {
    const query = document.getElementById('view_query').value;
    if (!query.trim()) {
        alert('Inserisci una query SQL');
        return;
    }

    // Disabilita il pulsante Salva mentre testiamo
    const saveBtn = document.getElementById('saveViewBtn');
    saveBtn.disabled = true;

    const formData = new FormData();
    formData.append('csrf_token', '<?= e($csrf_token) ?>');
    formData.append('query', query);

    try {
        const response = await fetch('/generator/test-view', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        const preview = document.getElementById('resultPreview');
        const content = document.getElementById('resultContent');

        if (result.success) {
            let html =
                `<p class="text-success"><strong><i class="fas fa-check-circle"></i> Query eseguita con successo!</strong> ${result.count} risultati trovati.</p>`;

            if (result.data.length > 0) {
                html +=
                    '<div class="table-responsive"><table class="table table-sm table-striped"><thead class="table-light"><tr>';
                Object.keys(result.data[0]).forEach(key => {
                    html += `<th>${key}</th>`;
                });
                html += '</tr></thead><tbody>';

                result.data.slice(0, 10).forEach(row => {
                    html += '<tr>';
                    Object.values(row).forEach(val => {
                        html += `<td>${val ?? '<span class="text-muted">NULL</span>'}</td>`;
                    });
                    html += '</tr>';
                });
                html += '</tbody></table></div>';

                if (result.count > 10) {
                    html +=
                        `<p class="text-muted mt-2"><i class="fas fa-info-circle"></i> Mostrati 10 di ${result.count} risultati</p>`;
                }
            }

            content.innerHTML = html;
            preview.style.display = 'block';

            // Abilita il pulsante Salva solo se il test ha successo
            saveBtn.disabled = false;
        } else {
            content.innerHTML = `<div class="alert alert-danger"><strong>Errore:</strong> ${result.error}</div>`;
            preview.style.display = 'block';

            // Mantieni il pulsante disabilitato se c'è un errore
            saveBtn.disabled = true;
        }
    } catch (error) {
        alert('Errore durante il test della query: ' + error.message);
        saveBtn.disabled = true;
    }
}

function saveView() {
    const name = document.getElementById('view_name').value.trim();
    const description = document.getElementById('view_description').value.trim();
    const query = document.getElementById('view_query').value.trim();

    if (!name || !query) {
        alert('Nome e query sono obbligatori');
        return;
    }

    const viewData = {
        name: name,
        description: description,
        query: query,
        rate_limit: parseInt(document.getElementById('view_rate_limit').value),
        rate_limit_window: parseInt(document.getElementById('view_rate_window').value),
        max_results: parseInt(document.getElementById('view_max_results').value),
        require_auth: document.getElementById('view_require_auth').checked,
        enable_cache: document.getElementById('view_enable_cache').checked,
        cache_ttl: parseInt(document.getElementById('view_cache_ttl').value)
    };

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/generator/save-view';

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?= e($csrf_token) ?>';
    form.appendChild(csrfInput);

    const dataInput = document.createElement('input');
    dataInput.type = 'hidden';
    dataInput.name = 'view_data';
    dataInput.value = JSON.stringify(viewData);
    form.appendChild(dataInput);

    document.body.appendChild(form);
    form.submit();
}
</script>