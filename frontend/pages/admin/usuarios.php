<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['admin']);

function admin_oab_label(array $user): string
{
    if (!in_array($user['tipo'], ['advogado', 'estagiario'], true)) {
        return 'Não se aplica';
    }

    if ((int) ($user['oab_verificado'] ?? 0) === 1) {
        return 'Verificada';
    }

    $status = (string) ($user['status_cna'] ?? 'pendente');
    return match ($status) {
        'invalido' => 'Inválida',
        'nao_encontrado' => 'Não encontrada',
        'verificado' => 'Verificada',
        default => 'Pendente',
    };
}

function admin_oab_badge_class(array $user): string
{
    if (!in_array($user['tipo'], ['advogado', 'estagiario'], true)) {
        return 'badge-info';
    }

    if ((int) ($user['oab_verificado'] ?? 0) === 1 || ($user['status_cna'] ?? '') === 'verificado') {
        return 'badge-success';
    }

    if (in_array(($user['status_cna'] ?? ''), ['invalido', 'nao_encontrado'], true)) {
        return 'badge-warning';
    }

    return 'badge-info';
}

$tipo = $_GET['tipo'] ?? '';
$status = $_GET['status'] ?? '';
$oabFilter = $_GET['oab'] ?? '';
$q = trim((string) ($_GET['q'] ?? ''));
$where = [];
$params = [];

if (in_array($tipo, ['cliente', 'advogado', 'estagiario', 'admin'], true)) {
    $where[] = 'tipo = ?';
    $params[] = $tipo;
}

if (in_array($status, ['ativo', 'inativo'], true)) {
    $where[] = 'status = ?';
    $params[] = $status;
}

if ($q !== '') {
    $where[] = '(nome LIKE ? OR email LIKE ? OR telefone LIKE ? OR oab LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

if ($oabFilter === 'verificado') {
    $where[] = "tipo IN ('advogado', 'estagiario') AND oab_verificado = TRUE";
} elseif ($oabFilter === 'pendente') {
    $where[] = "tipo IN ('advogado', 'estagiario') AND oab_verificado = FALSE AND COALESCE(status_cna, 'pendente') = 'pendente' AND COALESCE(oab, '') <> '' AND COALESCE(oab_uf, '') <> ''";
} elseif ($oabFilter === 'invalido') {
    $where[] = "tipo IN ('advogado', 'estagiario') AND COALESCE(status_cna, '') IN ('invalido', 'nao_encontrado')";
} elseif ($oabFilter === 'sem_oab') {
    $where[] = "tipo IN ('advogado', 'estagiario') AND (COALESCE(oab, '') = '' OR COALESCE(oab_uf, '') = '')";
}

$sql = 'SELECT id, nome, email, tipo, telefone, oab, oab_uf, oab_status, oab_verificado, status_cna, cna_validado_em, cna_origem, cna_ultimo_erro, status, created_at FROM users';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';

$users = fetch_all($pdo, $sql, $params);

$userTotal = count_query($pdo, 'SELECT COUNT(*) FROM users');
$activeTotal = count_query($pdo, "SELECT COUNT(*) FROM users WHERE status = 'ativo'");
$verifiedProfessionalTotal = count_query($pdo, "SELECT COUNT(*) FROM users WHERE tipo IN ('advogado', 'estagiario') AND oab_verificado = TRUE");
$pendingProfessionalTotal = count_query(
    $pdo,
    "SELECT COUNT(*) FROM users
     WHERE tipo IN ('advogado', 'estagiario')
       AND oab_verificado = FALSE
       AND COALESCE(status_cna, 'pendente') = 'pendente'
       AND COALESCE(oab, '') <> ''
       AND COALESCE(oab_uf, '') <> ''"
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Usuários | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../assets/css/style.css?v=sidebar-open-button-1">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'usuarios.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Usuários', 'Consulte contas, perfis, status e validação profissional.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Usuários totais', $userTotal, 'users') ?>
        <?= stat_card('Contas ativas', $activeTotal, 'check') ?>
        <?= stat_card('Profissionais validados', $verifiedProfessionalTotal, 'shield') ?>
        <?= stat_card('OAB pendentes', $pendingProfessionalTotal, 'help') ?>
      </section>

      <form class="card admin-filter admin-filter-wide" method="get">
        <div class="field">
          <label for="q">Busca</label>
          <input class="input" id="q" name="q" value="<?= e($q) ?>" placeholder="Nome, e-mail, telefone ou OAB">
        </div>
        <div class="field">
          <label for="tipo">Perfil</label>
          <select class="select" id="tipo" name="tipo">
            <option value="">Todos</option>
            <?php foreach (['cliente' => 'Cliente', 'advogado' => 'Advogado', 'estagiario' => 'Estagiário', 'admin' => 'Admin'] as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $tipo === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="status">Status</label>
          <select class="select" id="status" name="status">
            <option value="">Todos</option>
            <option value="ativo" <?= $status === 'ativo' ? 'selected' : '' ?>>Ativo</option>
            <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativo</option>
          </select>
        </div>
        <div class="field">
          <label for="oab">OAB</label>
          <select class="select" id="oab" name="oab">
            <option value="">Todos</option>
            <option value="pendente" <?= $oabFilter === 'pendente' ? 'selected' : '' ?>>Pendente</option>
            <option value="verificado" <?= $oabFilter === 'verificado' ? 'selected' : '' ?>>Verificada</option>
            <option value="invalido" <?= $oabFilter === 'invalido' ? 'selected' : '' ?>>Inválida</option>
            <option value="sem_oab" <?= $oabFilter === 'sem_oab' ? 'selected' : '' ?>>Sem OAB</option>
          </select>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Filtrar</button>
          <a class="btn btn-outline" href="usuarios.php">Limpar</a>
        </div>
      </form>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Contas cadastradas</h2>
          <span class="badge badge-info"><?= e((string) count($users)) ?> registros</span>
        </div>
        <?php if (!$users): ?>
          <?= empty_state('Nenhum usuário encontrado para os filtros selecionados.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table admin-users-table">
              <thead><tr><th>Usuário</th><th>Contato</th><th>Perfil</th><th>OAB</th><th>Status</th><th>Criado em</th><th>Ações</th></tr></thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                  <?php $isProfessional = in_array($user['tipo'], ['advogado', 'estagiario'], true); ?>
                  <tr>
                    <td><strong><?= e($user['nome']) ?></strong><span class="table-subtext">#<?= (int) $user['id'] ?></span></td>
                    <td><?= e($user['email']) ?><span class="table-subtext"><?= e($user['telefone'] ?: 'Sem telefone') ?></span></td>
                    <td><?= e($user['tipo']) ?></td>
                    <td>
                      <?= e(trim(($user['oab'] ?? '') . ' ' . ($user['oab_uf'] ?? '')) ?: 'Não informado') ?>
                      <span class="table-subtext"><?= e($user['oab_status'] ?: admin_oab_label($user)) ?></span>
                      <span class="badge <?= e(admin_oab_badge_class($user)) ?> mt-8"><?= e(admin_oab_label($user)) ?></span>
                      <?php if (!empty($user['cna_validado_em'])): ?>
                        <span class="table-subtext">Validado em <?= e(date('d/m/Y H:i', strtotime($user['cna_validado_em']))) ?></span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <form class="action-form action-form-stack" action="<?= e(app_url('/backend/public/index.php?rota=/admin/users/status')) ?>" method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                        <select class="select select-sm" name="status" aria-label="Status de <?= e($user['nome']) ?>">
                          <option value="ativo" <?= $user['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                          <option value="inativo" <?= $user['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                        <button class="btn btn-soft btn-sm" type="submit">Salvar</button>
                      </form>
                    </td>
                    <td><?= e(date('d/m/Y H:i', strtotime($user['created_at']))) ?></td>
                    <td>
                      <?php if (!$isProfessional): ?>
                        <span class="text-muted">Sem ação OAB</span>
                      <?php else: ?>
                        <div class="stacked-actions">
                          <form class="action-form" action="<?= e(app_url('/backend/public/index.php?rota=/admin/professionals/oab')) ?>" method="post">
                            <?= csrf_input() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <input class="input admin-reason-input" name="justificativa" placeholder="Fonte da validacao" required>
                            <button class="btn btn-success btn-sm" type="submit"><?= icon_svg('check') ?> Aprovar</button>
                          </form>
                          <form class="action-form" action="<?= e(app_url('/backend/public/index.php?rota=/admin/professionals/oab')) ?>" method="post">
                            <?= csrf_input() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                            <input type="hidden" name="action" value="pending">
                            <button class="btn btn-soft btn-sm" type="submit">Revisar</button>
                          </form>
                          <form class="action-form action-form-stack" action="<?= e(app_url('/backend/public/index.php?rota=/admin/professionals/oab')) ?>" method="post" onsubmit="return confirm('Reprovar esta OAB vai excluir a conta do profissional. Continuar?');">
                            <?= csrf_input() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <input class="input admin-reason-input" name="justificativa" placeholder="Motivo da reprovação" required>
                            <button class="btn btn-outline btn-sm" type="submit">Excluir por OAB inválida</button>
                          </form>
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
