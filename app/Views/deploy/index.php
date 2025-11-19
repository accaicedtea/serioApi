<section>
    <div class="container">
        <h1> <?= e($title) ?></h1>
        <p class="subtitle">Carica automaticamente le API generate sul tuo server via FTP</p>

        <?php if (isset($_GET['success'])): ?>
        <div class="success-box">
            <h3> Upload completato con successo!</h3>
            <p><?= isset($_GET['files']) ? e($_GET['files']) . ' file caricati' : 'API caricate sul server' ?></p>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="error-box">
            <h3> Errore durante l'upload</h3>
            <p><?= e($_GET['error']) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!$apiExists): ?>
        <div class="warning-box">
            <h3> Cartella API non trovata</h3>
            <p>Prima di caricare sul server, devi <a href="/builder">generare le API</a>.</p>
        </div>
        <?php else: ?>
        <div class="info-box">
            <h3> Cartella da caricare</h3>
            <p><code><?= e($apiPath) ?></code></p>
            <p style="margin-top: 10px;">Tutti i file in questa cartella verranno caricati sul server FTP.</p>
        </div>

        <form method="POST" action="/deploy/upload" id="ftpForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

            <h2 style="margin-top: 30px; color: #667eea;">Configurazione FTP</h2>

            <div class="form-group">
                <label>Host FTP *</label>
                <input type="text" name="ftp_host" id="ftp_host" placeholder="ftp.tuosito.com"
                    value="<?= e($savedCredentials['ftp_host'] ?? '') ?>" required>
                <small>Indirizzo del server FTP (es: ftp.tuosito.com o 192.168.1.100)</small>
            </div>

            <div class="form-group">
                <label>Porta</label>
                <input type="number" name="ftp_port" id="ftp_port"
                    value="<?= e($savedCredentials['ftp_port'] ?? 21) ?>">
                <small>Porta FTP (di solito 21 per FTP normale, 22 per SFTP)</small>
            </div>

            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="ftp_user" id="ftp_user" value="<?= e($savedCredentials['ftp_user'] ?? '') ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="ftp_pass" id="ftp_pass" required>
                <small style="color: #856404;"> Per sicurezza, la password non viene salvata. Reinseriscila ad ogni
                    deploy.</small>
            </div>

            <div class="form-group">
                <label>Percorso Remoto</label>
                <input type="text" name="ftp_path" id="ftp_path"
                    value="<?= e($savedCredentials['ftp_path'] ?? '/public_html/api') ?>"
                    placeholder="/public_html/api">
                <small>Cartella dove caricare le API sul server (verrà creata se non esiste)</small>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" name="ftp_ssl" id="ftp_ssl"
                    <?= ($savedCredentials['ftp_ssl'] ?? false) ? 'checked' : '' ?>>
                <label for="ftp_ssl">Usa connessione SSL/TLS (FTPS)</label>
            </div>

            <div style="margin-top: 30px;">
                <button type="button" class="btn btn-secondary" onclick="testConnection()"> Testa Connessione</button>
                <button type="submit" class="btn btn-success"> Carica sul Server</button>
            </div>

            <div id="testResult" class="test-result"></div>

            <div id="loading" class="loading">
                <div class="spinner"></div>
                <p style="margin-top: 15px;">Caricamento in corso...</p>
            </div>
        </form>
        <?php endif; ?>

        <div style="margin-top: 40px; text-align: center;">
            <a href="/builder" class="back-link"> Genera API</a>
            <a href="/generator" class="back-link"> Configurazione</a>
        </div>
    </div>
</section>

<script>

async function testConnection() {

    const host = document.getElementById('ftp_host').value;
    const user = document.getElementById('ftp_user').value;
    const pass = document.getElementById('ftp_pass').value;
    const port = document.getElementById('ftp_port').value;
    const ssl = document.getElementById('ftp_ssl').checked;

    if (!host || !user || !pass) {
        alert('Compila host, username e password');
        return;
    }

    const resultDiv = document.getElementById('testResult');
    resultDiv.textContent = 'Test in corso...';
    resultDiv.className = 'test-result';
    resultDiv.style.display = 'block';

    const formData = new FormData();
    formData.append('csrf_token', '<?= e($csrf_token) ?>');
    formData.append('ftp_host', host);
    formData.append('ftp_user', user);
    formData.append('ftp_pass', pass);
    formData.append('ftp_port', port);
    if (ssl) formData.append('ftp_ssl', '1');

    try {
        const response = await fetch('/deploy/test', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            resultDiv.textContent = ' ' + result.message + ' - Directory: ' + result.directory;
            resultDiv.className = 'test-result success';
        } else {
            resultDiv.textContent = ' ' + result.error;
            resultDiv.className = 'test-result error';
        }
    } catch (error) {
        resultDiv.textContent = ' Errore di connessione: ' + error.message;
        resultDiv.className = 'test-result error';
    }
}

document.getElementById('ftpForm')?.addEventListener('submit', function() {
    document.getElementById('loading').classList.add('show');
});
</script>