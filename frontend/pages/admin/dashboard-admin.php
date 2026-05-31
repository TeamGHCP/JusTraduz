<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['admin']);

$userCount = count_query($pdo, 'SELECT COUNT(*) FROM users');
$activeUserCount = count_query($pdo, "SELECT COUNT(*) FROM users WHERE status = 'ativo'");
$documentCount = count_query($pdo, 'SELECT COUNT(*) FROM documents');
$caseCount = count_query($pdo, 'SELECT COUNT(*) FROM cases');
$openCaseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status = 'aberto'");
$activeCaseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status <> 'finalizado'");
$lawyerCount = count_query($pdo, "SELECT COUNT(*) FROM users WHERE tipo = 'advogado'");
$clientCount = count_query($pdo, "SELECT COUNT(*) FROM users WHERE tipo = 'cliente'");

$recentUsers = fetch_all($pdo, 'SELECT id, nome, email, tipo, status, created_at FROM users ORDER BY created_at DESC LIMIT 6');
$recentCases = fetch_all(
    $pdo,
    'SELECT c.id, c.titulo, c.status, c.prioridade, c.created_at, cli.nome AS cliente, adv.nome AS advogado
     FROM cases c
     INNER JOIN users cli ON cli.id = c.cliente_id
     LEFT JOIN users adv ON adv.id = c.advogado_id
     ORDER BY c.created_at DESC
     LIMIT 6'
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administração | JusTraduz</title>
  <link rel="icon" href="../assets/img/logo.png">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'dashboard-admin.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Administração', 'Controle usuários, documentos e solicitações em um painel separado.', current_user_name()); ?>

      <section class="admin-hero">
        <div>
          <span class="badge badge-info">Área restrita</span>
          <h2>Operação da plataforma</h2>
          <p>Resumo rápido do uso do JusTraduz e atalhos para as rotinas administrativas mais importantes.</p>
        </div>
        <div class="admin-hero-actions">
          <a class="btn btn-primary" href="usuarios.php"><?= icon_svg('users') ?> Gerenciar usuários</a>
          <a class="btn btn-outline" href="solicitacoes.php"><?= icon_svg('case') ?> Ver solicitações</a>
          <a class="btn btn-soft" href="auditoria.php"><?= icon_svg('shield') ?> Auditoria</a>
        </div>
      </section>

      <section class="grid grid-4">
        <?= stat_card('Usuários ativos', $activeUserCount . '/' . $userCount, 'users') ?>
        <?= stat_card('Documentos enviados', $documentCount, 'folder') ?>
        <?= stat_card('Casos ativos', $activeCaseCount, 'case') ?>
        <?= stat_card('Solicitações abertas', $openCaseCount, 'help') ?>
      </section>

      <section class="grid grid-2 admin-panels">
        <article class="card">
          <div class="dash-section-title">
            <h2>Composição de usuários</h2>
            <a class="btn btn-soft btn-sm" href="usuarios.php">Abrir</a>
          </div>
          <div class="admin-metric-list">
            <div><span>Clientes</span><strong><?= e((string) $clientCount) ?></strong></div>
            <div><span>Advogados</span><strong><?= e((string) $lawyerCount) ?></strong></div>
            <div><span>Outros perfis</span><strong><?= e((string) max(0, $userCount - $clientCount - $lawyerCount)) ?></strong></div>
          </div>
        </article>

        <article class="card">
          <div class="dash-section-title">
            <h2>Fila de atendimento</h2>
            <a class="btn btn-soft btn-sm" href="solicitacoes.php">Abrir</a>
          </div>
          <div class="admin-metric-list">
            <div><span>Abertas</span><strong><?= e((string) $openCaseCount) ?></strong></div>
            <div><span>Em andamento</span><strong><?= e((string) count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status = 'em_andamento'")) ?></strong></div>
            <div><span>Finalizadas</span><strong><?= e((string) count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status = 'finalizado'")) ?></strong></div>
          </div>
        </article>
      </section>

      <section class="grid grid-2 admin-panels">
        <article class="dash-section">
          <div class="dash-section-title">
            <h2>Usuários recentes</h2>
            <a class="btn btn-soft btn-sm" href="usuarios.php">Ver todos</a>
          </div>
          <?php if (!$recentUsers): ?>
            <?= empty_state('Nenhum usuário cadastrado.') ?>
          <?php else: ?>
            <div class="table-wrap">
              <table class="table compact-table">
                <thead><tr><th>Nome</th><th>Perfil</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($recentUsers as $user): ?>
                    <tr>
                      <td><strong><?= e($user['nome']) ?></strong><span><?= e($user['email']) ?></span></td>
                      <td><?= e($user['tipo']) ?></td>
                      <td><span class="badge <?= $user['status'] === 'ativo' ? 'badge-success' : 'badge-warning' ?>"><?= e($user['status']) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </article>

        <article class="dash-section">
          <div class="dash-section-title">
            <h2>Solicitações recentes</h2>
            <a class="btn btn-soft btn-sm" href="solicitacoes.php">Ver todas</a>
          </div>
          <?php if (!$recentCases): ?>
            <?= empty_state('Nenhuma solicitação cadastrada.') ?>
          <?php else: ?>
            <div class="table-wrap">
              <table class="table compact-table">
                <thead><tr><th>Caso</th><th>Responsável</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($recentCases as $case): ?>
                    <tr>
                      <td><strong><?= e($case['titulo']) ?></strong><span><?= e($case['cliente']) ?></span></td>
                      <td><?= e($case['advogado'] ?? 'Aguardando') ?></td>
                      <td><span class="badge badge-info"><?= e($case['status']) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </article>
      </section>
    </main>
  </div>
</body>
</html>
