<?php
require_once __DIR__ . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$user = fetch_one($pdo, 'SELECT nome, email, tipo, telefone, oab, oab_uf, oab_status FROM users WHERE id = ?', [current_user_id()]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Perfil | JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'perfil.php'); ?>

    <main class="app-main">
      <?php render_topbar('Meu perfil', 'Dados cadastrados no sistema.', current_user_name()); ?>

      <section class="profile-layout">
        <aside class="card text-center">
          <div class="profile-photo"><?= e(substr($user['nome'] ?? 'U', 0, 1)) ?></div>
          <h3><?= e($user['nome'] ?? '') ?></h3>
          <p><?= e($user['email'] ?? '') ?></p>
          <p class="mt-12"><span class="badge badge-success">Conta ativa</span></p>
        </aside>
        <form class="card auth-form" action="../backend/public/index.php?rota=/profile/update" method="post">
          <div class="form-grid">
            <div class="field"><label for="nome">Nome</label><input class="input" id="nome" name="nome" value="<?= e($user['nome'] ?? '') ?>" required></div>
            <div class="field"><label for="email">E-mail</label><input class="input" id="email" name="email" type="email" value="<?= e($user['email'] ?? '') ?>" required></div>
            <div class="field"><label for="telefone">Telefone</label><input class="input" id="telefone" name="telefone" value="<?= e($user['telefone'] ?? '') ?>"></div>
            </div>
          <?php if (in_array($user['tipo'] ?? '', ['advogado', 'estagiario'], true)): ?>
            <div class="form-grid">
              <div class="field"><label>OAB</label><input class="input" value="<?= e($user['oab'] ?? '') ?>" disabled></div>
              <div class="field"><label>UF</label><input class="input" value="<?= e($user['oab_uf'] ?? '') ?>" disabled></div>
            </div>
          <?php endif; ?>
          <button class="btn btn-primary" type="submit"><?= icon_svg('user') ?> Salvar alterações</button>
        </form>
      </section>
    </main>
  </div>
</body>
</html>
