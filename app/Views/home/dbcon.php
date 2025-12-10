<div class="container-fluid">
    <div class="row mb-3">
        <!-- Sidebar con le tabelle del database -->
        <div class="col-md-3">
            <div class="card position-sticky" style="top: 20px;">
                <div class="card-header bg-dark text-white">
                    <h5><i class="fas fa-database"></i> Database Tables</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if(isset($tables) && !empty($tables)): ?>
                        <?php foreach($tables as $table): ?>
                        <a href="/?table=<?= urlencode($table) ?>"
                            class="list-group-item list-group-item-action <?= (isset($_GET['table']) && $_GET['table'] === $table) ? 'active' : '' ?>">
                            <i class="fas fa-table me-2"></i>
                            <?= e($table) ?>
                            <span class="badge <?=e($apiStatus[$table]) ? 'bg-success' : 'bg-danger' ?> float-end">
                                <?php if (e($apiStatus[$table])): ?>
                                <i class="fas fa-check"></i> API
                                <?php else: ?>
                                <i class="fas fa-times"></i> No API
                                <?php endif; ?>
                            </span>
                        </a>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="list-group-item text-muted">
                            <i class="fas fa-info-circle"></i> Nessuna tabella trovata
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- GRAFICO PIE

            <div class="col-md-4">
                <div class="card position-sticky mt-3">
                    <div class="card-body text-center">
                        <canvas id="myChart"></canvas>
                    </div>
                </div>
            </div>
             -->
        </div>

        <!-- Area coi pulsanti -->
        <div class="col-md-9">
            <!-- Area dettagli della tabella selezionata -->
            <?php if(isset($tableDetails) && !empty($tableDetails)): ?>
            <?php if(isset($tableDetails['error'])): ?>
            <div class="alert alert-danger">
                <strong>Errore:</strong> <?= e($tableDetails['error']) ?>
            </div>
            <?php else: ?>
            <div id="table-details" class="card">
                <div class="card-header bg-info text-white">
                    <h5><i class="fas fa-table"></i> Dettagli Tabella:
                        <strong><?= e($tableDetails['name']) ?></strong>
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Desktop view -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Colonna</th>
                                    <th>Tipo</th>
                                    <th>Null</th>
                                    <th>Key</th>
                                    <th>Default</th>
                                    <th>Extra</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($tableDetails['columns'] as $col): ?>
                                <tr>
                                    <td><strong><?= e($col['Field']) ?></strong></td>
                                    <td><code><?= e($col['Type']) ?></code></td>
                                    <td><?= e($col['Null']) ?></td>
                                    <td>
                                        <?php if(!empty($col['Key'])): ?>
                                        <span class="badge bg-warning text-dark"><?= e($col['Key']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $col['Default'] !== null ? e($col['Default']) : '-' ?>
                                    </td>
                                    <td><?= !empty($col['Extra']) ? e($col['Extra']) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile view -->
                    <div class="d-md-none">
                        <?php foreach ($tableDetails['columns'] as $column): ?>
                        <div class="border-bottom p-3">
                            <div class="fw-bold text-dark mb-2"><?= e($column['Field']) ?></div>
                            <div class="row g-1">
                                <div class="col-6">
                                    <small class="text-muted">Tipo:</small><br>
                                    <span class="badge bg-info"><?= e($column['Type']) ?></span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Null:</small><br>
                                    <?= $column['Null'] === 'YES' ? '<span class="badge bg-warning">NULL</span>' : '<span class="badge bg-success">NOT NULL</span>' ?>
                                </div>
                                <?php if ($column['Key']): ?>
                                <div class="col-6 mt-2">
                                    <small class="text-muted">Chiave:</small><br>
                                    <span class="badge bg-primary"><?= e($column['Key']) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($column['Default']): ?>
                                <div class="col-6 mt-2">
                                    <small class="text-muted">Default:</small><br>
                                    <code><?= e($column['Default']) ?></code>
                                </div>
                                <?php endif; ?>
                                <?php if ($column['Extra']): ?>
                                <div class="col-12 mt-2">
                                    <small class="text-muted">Extra:</small><br>
                                    <span class="badge bg-secondary"><?= e($column['Extra']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="alert alert-info mt-3">
                        <strong><i class="fas fa-info-circle"></i> Totale righe:</strong>
                        <?= number_format($tableDetails['rowCount']) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
