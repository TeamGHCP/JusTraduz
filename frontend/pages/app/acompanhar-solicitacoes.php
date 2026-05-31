<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$userId = current_user_id();

if ($type === 'cliente') {
    $cases = fetch_all($pdo, 'SELECT c.*, u.nome AS advogado FROM cases c LEFT JOIN users u ON u.id = c.advogado_id WHERE c.cliente_id = ? ORDER BY c.created_at DESC', [$userId]);
} elseif ($type === 'advogado') {
    $cases = fetch_all($pdo, 'SELECT c.*, u.nome AS cliente FROM cases c INNER JOIN users u ON u.id = c.cliente_id WHERE c.advogado_id = ? OR c.advogado_id IS NULL ORDER BY c.created_at DESC', [$userId]);
} else {
    $cases = fetch_all($pdo, 'SELECT c.*, cli.nome AS cliente, adv.nome AS advogado FROM cases c INNER JOIN users cli ON cli.id = c.cliente_id LEFT JOIN users adv ON adv.id = c.advogado_id ORDER BY c.created_at DESC');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitações | JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'acompanhar-solicitacoes.php'); ?>

    <main class="app-main">
      <?php render_topbar('Acompanhar solicitações', 'Veja status e profissional responsável.', current_user_name()); ?>

      <?php if ($type === 'cliente'): ?>
        <div class="form-actions">
          <a class="btn btn-primary" href="solicitar-ajuda.php"><?= icon_svg('help') ?> Nova solicitação</a>
        </div>
      <?php endif; ?>

      <section class="dash-section">
        <?php if (!$cases): ?>
          <?= empty_state('Nenhuma solicitação cadastrada ainda.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Caso</th><th>Cliente</th><th>Advogado</th><th>Prioridade</th><th>Status</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($cases as $case): ?>
                  <tr>
                    <td><?= e($case['titulo']) ?></td>
                    <td><?= e($case['cliente'] ?? current_user_name()) ?></td>
                    <td><?= e($case['advogado'] ?? 'Aguardando') ?></td>
                    <td><?= e($case['prioridade']) ?></td>
                    <td><?= e($case['status']) ?></td>
                    <td>
                      <?php if ($type === 'advogado' && empty($case['advogado_id'])): ?>
                        <a class="btn btn-primary btn-sm" href="../backend/public/index.php?rota=/cases/accept&id=<?= (int) $case['id'] ?>">Aceitar</a>
                      <?php else: ?>
                        <div class="action-form">
                          <a class="btn btn-outline btn-sm" href="chat.php?case_id=<?= (int) $case['id'] ?>">Chat</a>
                          <a class="btn btn-soft btn-sm" href="tarefas.php?case_id=<?= (int) $case['id'] ?>">Tarefas</a>
                          <?php if ($case['status'] !== 'finalizado' && ($type === 'cliente' || !empty($case['advogado_id']) || in_array($type, ['admin', 'estagiario'], true))): ?>
                            <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/cases/status')) ?>" method="post">
                              <?= csrf_input() ?>
                              <input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
                              <?php if ($type === 'cliente'): ?>
                                <input type="hidden" name="status" value="finalizado">
                                <button class="btn btn-soft btn-sm" type="submit">Finalizar</button>
                              <?php else: ?>
                                <select class="select select-sm" name="status" aria-label="Status do caso">
                                  <option value="aberto" <?= $case['status'] === 'aberto' ? 'selected' : '' ?>>Aberto</option>
                                  <option value="em_andamento" <?= $case['status'] === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
                                  <option value="finalizado" <?= $case['status'] === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                                </select>
                                <button class="btn btn-soft btn-sm" type="submit">Salvar</button>
                              <?php endif; ?>
                            </form>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
