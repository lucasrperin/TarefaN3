<?php
// levels_menu.php — Layout 6: cards grid com níveis visíveis
// Recebe: $listaNiveis, $view, $q

$levelsByEquipe = [];
$listaNiveis->data_seek(0);
while ($n = $listaNiveis->fetch_assoc()) {
    $levelsByEquipe[$n['equipe']][] = $n;
}
?>

<section class="levels-menu container py-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-light text-secondary mb-0">Níveis Disponíveis por Equipe</h4>
  <a href="okr_list.php" class="btn btn-outline-primary">
    <i class="fa-solid fa-eye me-1"></i> Ver Todos os OKRs
  </a>
</div>


  <div class="row g-4">
    <?php foreach ($levelsByEquipe as $equipe => $niveis): ?>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title text-primary">
              <i class="fa-solid fa-users me-2"></i><?= htmlspecialchars($equipe) ?>
            </h5>
            <div class="mt-3 d-flex flex-wrap gap-2">
              <?php foreach ($niveis as $n): ?>
                <a
                  href="okr.php?view=<?= $view ?>&q=<?= $q ?>&nivel=<?= $n['id'] ?>"
                  class="btn btn-sm btn-outline-primary"
                >
                  <?= htmlspecialchars($n['descricao']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
