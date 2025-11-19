<footer class="bg-dark text-light py-3">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h5><?= $appName ?? 'My App' ?></h5>
                <p>Sistema di generazione API REST automatizzato con PHP MV.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="">
                    <a href="/generator" class="text-light me-3">Generator</a>
                    <a href="/generator/builder" class="text-light me-3">Builder</a>
                    <a href="/database" class="text-light me-3">Database</a>
                </div>
                <small>&copy; <?php echo date('Y'); ?> Tutti i diritti riservati.</small>
            </div>
        </div>
    </div>
</footer>
</body>

</html>