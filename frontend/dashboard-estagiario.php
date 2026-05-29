<?php
require_once __DIR__ . '/app/bootstrap.php';
require_role(['estagiario']);

$messageCount = count_query($pdo, 'SELECT COUNT(*) FROM messages');
$documentCount = count_query($pdo, 'SELECT COUNT(*) FROM documents');
$openCaseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status = 'aberto'");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard do estagiário | JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar('estagiario', 'dashboard-estagiario.php'); ?>

    <main class="app-main">
      <?php render_topbar('Área do estagiário', 'Auxilie em dúvidas simples e visualize informações sem alterar dados críticos.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Casos abertos', $openCaseCount, 'case') ?>
        <?= stat_card('Documentos', $documentCount, 'file') ?>
        <?= stat_card('Mensagens', $messageCount, 'chat') ?>
        <?= stat_card('Permissão', 'Restrita', 'lock') ?>
      </section>

      <section class="dash-section">
        <?= empty_state('Nenhuma fila de suporte específica foi atribuída ao seu usuário ainda.') ?>
      </section>
    </main>
  </div>
</body>
</html>
