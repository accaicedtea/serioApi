<section>
    <?php // SE IL SERVER E' AVVIATO ?>
    <?php if ($data['connectionStatus'] === true): ?>
        <?php require __DIR__ . '/dbcon.php'; ?>
    <?php endif; ?>
    <?php // SE IL SERVER NON E' AVVIATO ?>
    <?php if($data['connectionStatus'] !== true): ?>
        <?php require __DIR__ . '/nocon.php'; ?>
    <?php endif; ?>
</section>