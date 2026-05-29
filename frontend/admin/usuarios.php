<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_role(['admin']);

$tipo = $_GET['tipo'] ?? '';
$status = $_GET['status'] ?? '';
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

$sql = 'SELECT id, nome, email, tipo, telefone, oab, oab_uf, oab_status, oab_verificado, status, created_at FROM users';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';

$users = fetch_all($pdo, $sql, $params);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Usuários | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/logo.png">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'usuarios.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Usuários', 'Consulte contas, perfis e situação cadastral.', current_user_name()); ?>

      <form class="card admin-filter" method="get">
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
            <table class="table">
              <thead><tr><th>Usuário</th><th>Contato</th><th>Perfil</th><th>OAB</th><th>Status</th><th>Criado em</th></tr></thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                  <tr>
                    <td><strong><?= e($user['nome']) ?></strong><span class="table-subtext">#<?= (int) $user['id'] ?></span></td>
                    <td><?= e($user['email']) ?><span class="table-subtext"><?= e($user['telefone'] ?: 'Sem telefone') ?></span></td>
                    <td><?= e($user['tipo']) ?></td>
                    <td>
                      <?= e(trim(($user['oab'] ?? '') . ' ' . ($user['oab_uf'] ?? '')) ?: 'Não informado') ?>
                      <span class="table-subtext"><?= $user['oab_verificado'] ? 'Verificada' : e($user['oab_status'] ?: 'Pendente') ?></span>
                    </td>
                    <td><span class="badge <?= $user['status'] === 'ativo' ? 'badge-success' : 'badge-warning' ?>"><?= e($user['status']) ?></span></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($user['created_at']))) ?></td>
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
