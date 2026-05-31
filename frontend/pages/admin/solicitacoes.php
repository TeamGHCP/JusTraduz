<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['admin']);

$status = $_GET['status'] ?? '';
$prioridade = $_GET['prioridade'] ?? '';
$where = [];
$params = [];

if (in_array($status, ['aberto', 'em_andamento', 'finalizado'], true)) {
    $where[] = 'c.status = ?';
    $params[] = $status;
}

if (in_array($prioridade, ['baixa', 'media', 'alta'], true)) {
    $where[] = 'c.prioridade = ?';
    $params[] = $prioridade;
}

$sql = 'SELECT c.id, c.titulo, c.descricao, c.status, c.prioridade, c.created_at, c.advogado_id, cli.nome AS cliente, adv.nome AS advogado
        FROM cases c
        INNER JOIN users cli ON cli.id = c.cliente_id
        LEFT JOIN users adv ON adv.id = c.advogado_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY c.created_at DESC';

$cases = fetch_all($pdo, $sql, $params);
$lawyers = fetch_all($pdo, "SELECT id, nome FROM users WHERE tipo = 'advogado' AND status = 'ativo' AND (oab_verificado = TRUE OR (status_cna = 'pendente' AND COALESCE(oab, '') <> '' AND COALESCE(oab_uf, '') <> '')) ORDER BY nome");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitações | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/logo.png">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'solicitacoes.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Solicitações', 'Acompanhe a fila de ajuda jurídica e seus responsáveis.', current_user_name()); ?>

      <form class="card admin-filter" method="get">
        <div class="field">
          <label for="status">Status</label>
          <select class="select" id="status" name="status">
            <option value="">Todos</option>
            <option value="aberto" <?= $status === 'aberto' ? 'selected' : '' ?>>Aberto</option>
            <option value="em_andamento" <?= $status === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
            <option value="finalizado" <?= $status === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
          </select>
        </div>
        <div class="field">
          <label for="prioridade">Prioridade</label>
          <select class="select" id="prioridade" name="prioridade">
            <option value="">Todas</option>
            <option value="baixa" <?= $prioridade === 'baixa' ? 'selected' : '' ?>>Baixa</option>
            <option value="media" <?= $prioridade === 'media' ? 'selected' : '' ?>>Média</option>
            <option value="alta" <?= $prioridade === 'alta' ? 'selected' : '' ?>>Alta</option>
          </select>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Filtrar</button>
          <a class="btn btn-outline" href="solicitacoes.php">Limpar</a>
        </div>
      </form>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Fila administrativa</h2>
          <span class="badge badge-info"><?= e((string) count($cases)) ?> registros</span>
        </div>
        <?php if (!$cases): ?>
          <?= empty_state('Nenhuma solicitação encontrada para os filtros selecionados.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Caso</th><th>Cliente</th><th>Responsável</th><th>Prioridade</th><th>Status</th><th>Criado em</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($cases as $case): ?>
                  <tr>
                    <td><strong><?= e($case['titulo']) ?></strong><span class="table-subtext"><?= e($case['descricao'] ?: 'Sem descrição') ?></span></td>
                    <td><?= e($case['cliente']) ?></td>
                    <td><?= e($case['advogado'] ?? 'Aguardando responsável') ?></td>
                    <td><?= e($case['prioridade']) ?></td>
                    <td><span class="badge badge-info"><?= e(status_label($case['status'] ?? '')) ?></span></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($case['created_at']))) ?></td>
                    <td>
                      <form class="action-form" action="<?= e(app_url('/backend/public/index.php?rota=/admin/cases/update')) ?>" method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
                        <select class="select select-sm" name="advogado_id" aria-label="Advogado responsável">
                          <option value="">Sem advogado</option>
                          <?php foreach ($lawyers as $lawyer): ?>
                            <option value="<?= (int) $lawyer['id'] ?>" <?= (int) ($case['advogado_id'] ?? 0) === (int) $lawyer['id'] ? 'selected' : '' ?>><?= e($lawyer['nome']) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <select class="select select-sm" name="prioridade" aria-label="Prioridade">
                          <?php foreach (['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $case['prioridade'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <select class="select select-sm" name="status" aria-label="Status">
                          <?php foreach (['aberto' => 'Aberto', 'em_andamento' => 'Em andamento', 'finalizado' => 'Finalizado'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $case['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <button class="btn btn-soft btn-sm" type="submit">Salvar</button>
                        <a class="btn btn-outline btn-sm" href="../chat.php?case_id=<?= (int) $case['id'] ?>">Chat</a>
                      </form>
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
