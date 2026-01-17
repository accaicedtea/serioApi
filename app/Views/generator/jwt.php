<section class="container-fluid py-4">

    <?php include_once __DIR__ . '/header.php'; ?>

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Successo!</strong> Configurazione JWT salvata con successo!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <h5 class="alert-heading">Errore</h5>
        <p class="mb-0"><?= e($_GET['error']) ?></p>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Card principale configurazione JWT -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-key me-2"></i>Configurazione JWT Token</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="/generator/jwt/save" id="jwtConfigForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                
                <!-- Campi Standard JWT -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Campi Standard JWT</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Questi campi sono sempre inclusi nel payload JWT e non possono essere rimossi.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><code>sub</code> - User ID</span>
                                        <span class="badge bg-primary">Obbligatorio</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><code>email</code> - Email utente</span>
                                        <span class="badge bg-primary">Obbligatorio</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><code>role</code> - Ruolo utente</span>
                                        <span class="badge bg-primary">Obbligatorio</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><code>name</code> - Nome utente</span>
                                        <span class="badge bg-primary">Obbligatorio</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><code>iat</code> - Issued At</span>
                                        <span class="badge bg-secondary">Auto</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><code>nbf</code> - Not Before</span>
                                        <span class="badge bg-secondary">Auto</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><code>exp</code> - Expiration</span>
                                        <span class="badge bg-secondary">Auto</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Campi Personalizzati -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Campi Personalizzati dalla Tabella user_auth</h5>
                        <button type="button" class="btn btn-light btn-sm" onclick="addCustomField()">
                            <i class="fas fa-plus"></i> Aggiungi Campo
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Seleziona i campi dalla tabella <code>user_auth</code> da includere nel payload JWT.
                            <?php if (empty($availableFields)): ?>
                            <br><strong>Nota:</strong> Tutti i campi disponibili sono già campi standard o non ci sono altri campi nella tabella.
                            <?php endif; ?>
                        </div>

                        <div id="customFieldsContainer">
                            <?php if (isset($customFields) && count($customFields) > 0): ?>
                                <?php foreach ($customFields as $field): ?>
                                <div class="custom-field-row mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row g-3 align-items-end">
                                                <div class="col-md-10">
                                                    <label class="form-label fw-bold">Campo Database</label>
                                                    <select class="form-select" name="custom_field_name[]" required>
                                                        <option value="">-- Seleziona Campo --</option>
                                                        <?php foreach ($availableFields as $col): ?>
                                                        <option value="<?= e($col['name']) ?>" 
                                                                data-type="<?= e($col['type']) ?>"
                                                                <?= $field['name'] === $col['name'] ? 'selected' : '' ?>>
                                                            <?= e($col['name']) ?> (<?= e($col['type']) ?>)
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="hidden" name="custom_field_type[]" value="<?= e($field['type']) ?>" class="field-type-hidden">
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-danger w-100" onclick="removeCustomField(this)">
                                                        <i class="fas fa-trash"></i> Rimuovi
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-secondary text-center">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Nessun campo personalizzato configurato. Clicca su "Aggiungi Campo" per iniziare.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Esempio Payload -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-code me-2"></i>Anteprima Payload JWT</h5>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded" id="payloadPreview"><code>{
    "sub": "1",
    "email": "user@example.com",
    "role": "user",
    "name": "Mario Rossi",
    "iat": 1234567890,
    "nbf": 1234567890,
    "exp": 1234571490
}</code></pre>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Salva Configurazione JWT
                    </button>
                </div>
            </form>
        </div>
    </div>

    <a href="/generator" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Torna alla Configurazione
    </a>
</section>

<script>
// Campi disponibili dal PHP
const availableFields = <?= json_encode(array_values($availableFields)) ?>;

// Template per nuovo campo personalizzato
function addCustomField() {
    const container = document.getElementById('customFieldsContainer');
    
    // Rimuovi il messaggio "nessun campo" se presente
    const emptyAlert = container.querySelector('.alert-secondary');
    if (emptyAlert) {
        emptyAlert.remove();
    }
    
    // Genera le option per i campi disponibili
    let optionsHTML = '<option value="">-- Seleziona Campo --</option>';
    availableFields.forEach(col => {
        optionsHTML += `<option value="${col.name}" data-type="${col.type}">${col.name} (${col.type})</option>`;
    });
    
    const fieldHTML = `
        <div class="custom-field-row mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-10">
                            <label class="form-label fw-bold">Campo Database</label>
                            <select class="form-select field-select" name="custom_field_name[]" required onchange="autoSetType(this)">
                                ${optionsHTML}
                            </select>
                            <input type="hidden" name="custom_field_type[]" class="field-type-hidden">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-danger w-100" onclick="removeCustomField(this)">
                                <i class="fas fa-trash"></i> Rimuovi
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
    updatePayloadPreview();
}

// Auto-imposta il tipo basato sul campo database
function autoSetType(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const dbType = selectedOption.getAttribute('data-type');
    const typeHidden = selectElement.closest('.row').querySelector('.field-type-hidden');
    
    if (dbType && typeHidden) {
        // Mappa i tipi MySQL ai tipi JWT
        let jwtType = 'string'; // default
        if (dbType.includes('int') || dbType.includes('INT')) {
            jwtType = 'int';
        } else if (dbType.includes('bool') || dbType.includes('tinyint(1)')) {
            jwtType = 'bool';
        } else if (dbType.includes('float') || dbType.includes('double') || dbType.includes('decimal')) {
            jwtType = 'float';
        }
        
        typeHidden.value = jwtType;
    }
    
    updatePayloadPreview();
}

function removeCustomField(button) {
    const fieldRow = button.closest('.custom-field-row');
    fieldRow.remove();
    
    // Se non ci sono più campi, mostra il messaggio
    const container = document.getElementById('customFieldsContainer');
    if (container.querySelectorAll('.custom-field-row').length === 0) {
        container.innerHTML = `
            <div class="alert alert-secondary text-center">
                <i class="fas fa-info-circle me-2"></i>
                Nessun campo personalizzato configurato. Clicca su "Aggiungi Campo" per iniziare.
            </div>
        `;
    }
    
    updatePayloadPreview();
}

// Aggiorna anteprima payload in tempo reale
function updatePayloadPreview() {
    const fieldSelects = document.querySelectorAll('select[name="custom_field_name[]"]');
    const types = document.querySelectorAll('input[name="custom_field_type[]"]');
    
    const payload = {
        "sub": "1",
        "email": "user@example.com",
        "role": "user",
        "name": "Mario Rossi"
    };
    
    // Aggiungi campi personalizzati
    fieldSelects.forEach((select, index) => {
        if (select.value) {
            const type = types[index] ? types[index].value : 'string';
            const selectedOption = select.options[select.selectedIndex];
            const dbType = selectedOption.getAttribute('data-type');
            
            let value;
            // Usa il tipo del database per determinare il valore di esempio
            if (dbType.includes('int') || type === 'int') {
                value = 123;
            } else if (dbType.includes('bool') || dbType.includes('tinyint(1)') || type === 'bool') {
                value = true;
            } else if (dbType.includes('float') || dbType.includes('double') || dbType.includes('decimal') || type === 'float') {
                value = 123.45;
            } else {
                value = "example_value";
            }
            
            payload[select.value] = value;
        }
    });
    
    // Aggiungi campi timestamp
    payload.iat = 1234567890;
    payload.nbf = 1234567890;
    payload.exp = 1234571490;
    
    document.getElementById('payloadPreview').innerHTML = 
        '<code>' + JSON.stringify(payload, null, 4) + '</code>';
}

// Aggiorna preview quando cambiano i campi
document.addEventListener('change', function(e) {
    if (e.target.name && e.target.name.includes('custom_field_name')) {
        updatePayloadPreview();
    }
});

// Inizializza preview al caricamento
document.addEventListener('DOMContentLoaded', updatePayloadPreview);
</script>

<style>
.custom-field-row {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

pre code {
    color: #2d3748;
    font-family: 'Courier New', monospace;
}
</style>
