<?php
require_once __DIR__ . '/app/bootstrap.php';
require_login();

$lawyers = fetch_all($pdo, "SELECT id, nome, email, telefone, oab, oab_uf, oab_status, oab_verificado FROM users WHERE tipo = 'advogado' AND status = 'ativo' ORDER BY nome");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Advogados | JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <header class="site-header" data-site-header>
    <div class="container nav-bar">
      <a class="brand" href="<?= e(dashboard_url()) ?>"><img src="assets/img/logo.png" alt="JusTraduz"></a>
      <nav class="nav-links"><a href="<?= e(dashboard_url()) ?>">Dashboard</a><a href="solicitar-ajuda.php">Solicitar ajuda</a><a href="chat.php">Chat</a></nav>
      <button class="mobile-toggle" type="button" data-nav-toggle aria-label="Abrir menu">
        <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
      </button>
    </div>
  </header>
  <main class="container page-section">
    <div class="section-head">
      <h2>Advogados cadastrados</h2>
      <p>Profissionais ativos no JusTraduz.</p>
    </div>
    <?php if (!$lawyers): ?>
      <?= empty_state('Nenhum advogado cadastrado ainda.') ?>
    <?php else: ?>
      <div class="grid grid-3">
        <?php foreach ($lawyers as $lawyer): ?>
          <article class="card lawyer-card">
            <div class="lawyer-avatar"><?= e(substr($lawyer['nome'], 0, 1)) ?></div>
            <div>
              <h3><?= e($lawyer['nome']) ?></h3>
              <p><?= $lawyer['oab'] ? 'OAB/' . e($lawyer['oab_uf']) . ' ' . e($lawyer['oab']) : 'OAB não informada' ?></p>
              <p class="mt-8"><span class="badge badge-info"><?= e($lawyer['oab_status'] ?: 'Cadastro ativo') ?></span></p>
              <p class="mt-12 text-muted">
                E-mail: <?= e($lawyer['email']) ?><br>
                Telefone: <?= e($lawyer['telefone'] ?: 'Não informado') ?>
              </p>
              <?php if (current_user_type() === 'cliente'): ?>
                <a class="btn btn-primary btn-sm mt-14" href="solicitar-ajuda.php">Solicitar atendimento</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
  <script src="assets/js/main.js"></script>
</body>
</html>
