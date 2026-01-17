<section>   
    <div class="row mb-4">
          <div class="col">
                <h1 class="display-5 fw-bold text-primary">
                     <i class="fas fa-eye"></i> <?= e($title) ?>
                </h1>
                <p class="text-muted fs-5">Crea viste API personalizzate con query SQL</p>
          </div>
     </div>

     <div class="row mb-4">
          <div class="btn-group" role="group">
                <a href="/generator" class="btn <?= ($currentPath ?? '') === '/generator' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                     <i class="fas fa-table"></i> Tabelle
                </a>
                <a href="/generator/views" class="btn <?= ($currentPath ?? '') === '/generator/views' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                     <i class="fas fa-eye"></i> Viste Personalizzate
                </a>
                <a href="/generator/jwt" class="btn <?= ($currentPath ?? '') === '/generator/jwt' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                     <i class="fas fa-key"></i> Configurazione JWT
                </a>
                <a href="/generator/builder" class="btn <?= ($currentPath ?? '') === '/generator/builder' ? 'btn-success' : 'btn-outline-success' ?>">
                     <i class="fas fa-cogs"></i> Genera API
                </a>
          </div>
     </div>
</section>