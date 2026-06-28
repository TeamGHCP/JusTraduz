<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once PROJECT_ROOT_PATH . '/backend/app/services/OrganizationService.php';
require_permission('permissions.manage');

$enabled = OrganizationService::tableExists($pdo, 'role_permission_overrides');
$roles = PermissionService::roles();
$permissions = PermissionService::availablePermissions();
$overrides = [];

if ($enabled) {
    foreach (fetch_all($pdo, 'SELECT role_name, permission, effect FROM role_permission_overrides') as $row) {
        $overrides[(string) $row['role_name']][(string) $row['permission']] = (string) $row['effect'];
    }
}

$permissionLabels = [
    'admin.access' => ['Acesso ao painel admin', 'Permite entrar e navegar pela área administrativa.'],
    'api.manage' => ['Gerenciar API pública', 'Permite criar e administrar credenciais de integração externa.'],
    'audit.view' => ['Ver auditoria', 'Permite consultar eventos sensíveis e trilhas de segurança.'],
    'cases.create' => ['Criar solicitações', 'Permite abrir novos pedidos de ajuda.'],
    'cases.manage' => ['Gerenciar todas as solicitações', 'Permite alterar status, prioridade e responsável de qualquer caso.'],
    'cases.manage_assigned' => ['Gerenciar casos atribuídos', 'Permite atuar nos casos vinculados ao próprio profissional.'],
    'cases.view_all' => ['Ver todas as solicitações', 'Permite consultar casos de todos os usuários.'],
    'cases.view_assigned' => ['Ver casos atribuídos', 'Permite consultar casos ligados ao profissional.'],
    'cases.view_own' => ['Ver próprias solicitações', 'Permite ao cliente acompanhar seus casos.'],
    'documents.delete_all' => ['Excluir documentos gerais', 'Permite remover documentos de qualquer usuário.'],
    'documents.delete_own' => ['Excluir próprios documentos', 'Permite ao usuário remover seus arquivos.'],
    'documents.view_all' => ['Ver todos os documentos', 'Permite consultar documentos de toda a operação.'],
    'documents.view_assigned' => ['Ver documentos atribuídos', 'Permite ao profissional acessar documentos vinculados aos casos.'],
    'documents.view_own' => ['Ver próprios documentos', 'Permite ao cliente consultar seus arquivos.'],
    'oab.validate' => ['Validar OAB', 'Permite aprovar, reprovar ou devolver cadastro profissional para revisão.'],
    'organizations.manage' => ['Gerenciar organizações', 'Permite criar organizações e vincular profissionais.'],
    'organizations.view' => ['Ver organizações', 'Permite consultar escritórios, empresas e vínculos profissionais.'],
    'permissions.manage' => ['Editar permissões', 'Permite alterar esta matriz dinâmica. Use com cuidado.'],
    'profile.manage_own' => ['Gerenciar próprio perfil', 'Permite alterar dados da própria conta.'],
    'reports.export' => ['Exportar relatórios', 'Permite baixar CSVs gerenciais.'],
    'reports.view' => ['Ver relatórios', 'Permite acessar indicadores gerenciais.'],
    'schedule.manage_all' => ['Gerenciar agenda geral', 'Permite administrar horários de todos os profissionais.'],
    'schedule.manage_own' => ['Gerenciar própria agenda', 'Permite criar e editar horários do próprio profissional.'],
    'schedule.view_own' => ['Ver própria agenda', 'Permite consultar compromissos próprios.'],
    'tasks.manage_assigned' => ['Gerenciar tarefas atribuídas', 'Permite criar e concluir tarefas dos casos atribuídos.'],
    'users.manage' => ['Gerenciar usuários', 'Permite ativar/inativar contas e fazer manutenção administrativa.'],
    'users.view' => ['Ver usuários', 'Permite listar contas cadastradas.'],
];

$groups = [
    'Administração' => ['admin.', 'users.', 'permissions.', 'organizations.', 'oab.', 'audit.'],
    'Documentos e casos' => ['documents.', 'cases.', 'tasks.'],
    'Agenda e relatórios' => ['schedule.', 'reports.'],
    'Integrações e conta' => ['api.', 'profile.'],
];

$groupedPermissions = [];
foreach ($groups as $group => $prefixes) {
    foreach ($permissions as $permission) {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($permission, $prefix)) {
                $groupedPermissions[$group][] = $permission;
                continue 2;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Permissões | JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="manifest" href="../site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <link rel="stylesheet" href="../assets/css/style.css?v=global-responsive-20260628">
  <script src="../assets/js/pwa.js" defer></script>
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'permissoes.php', true); ?>
    <main class="app-main">
      <?php render_topbar('Permissões', 'Controle dinâmico de acesso por perfil.', current_user_name()); ?>

      <?php if (!$enabled): ?>
        <?= empty_state('O banco ainda não possui a tabela de permissões dinâmicas. O sistema segue usando as permissões padrão do código.') ?>
      <?php else: ?>
        <section class="grid grid-3">
          <?= stat_card('Perfis', count($roles), 'users') ?>
          <?= stat_card('Permissões', count($permissions), 'shield') ?>
          <?= stat_card('Overrides', array_sum(array_map('count', $overrides)), 'check') ?>
        </section>

        <section class="admin-dashboard-grid mt-16">
          <article class="card">
            <div class="dash-section-title"><h2>Como decidir</h2></div>
            <p class="text-muted"><strong>Herda</strong> mantém o padrão do sistema. <strong>Permite</strong> libera uma permissão extra para o perfil. <strong>Nega</strong> bloqueia mesmo que a permissão exista no padrão. Mudanças aqui afetam novas requisições imediatamente.</p>
          </article>
          <article class="card">
            <div class="dash-section-title"><h2>Cuidados</h2></div>
            <p class="text-muted">Evite liberar permissões administrativas para cliente, advogado ou estagiário sem necessidade real. Permissões como auditoria, usuários, organizações e edição desta matriz devem ficar restritas ao admin.</p>
          </article>
        </section>

        <?php foreach ($groupedPermissions as $group => $items): ?>
          <section class="dash-section mt-16">
            <div class="dash-section-title"><h2><?= e($group) ?></h2></div>
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th>Permissão</th>
                    <?php foreach ($roles as $role): ?><th><?= e(ucfirst($role)) ?></th><?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $permission): ?>
                    <?php $meta = $permissionLabels[$permission] ?? [$permission, 'Permissão técnica do sistema.']; ?>
                    <tr>
                      <td>
                        <strong><?= e($meta[0]) ?></strong><br>
                        <span class="text-muted"><?= e($meta[1]) ?></span><br>
                        <code><?= e($permission) ?></code>
                      </td>
                      <?php foreach ($roles as $role): ?>
                        <?php
                          $current = $overrides[$role][$permission] ?? 'inherit';
                          $baseAllowed = in_array($permission, PermissionService::defaultPermissionsForRole($role), true);
                        ?>
                        <td>
                          <form method="post" action="<?= e(app_url('/backend/public/index.php?rota=/admin/permissions/update')) ?>">
                            <?= csrf_input() ?>
                            <input type="hidden" name="role" value="<?= e($role) ?>">
                            <input type="hidden" name="permission" value="<?= e($permission) ?>">
                            <select class="input select-sm" name="effect" onchange="this.form.submit()" aria-label="<?= e($meta[0] . ' para ' . $role) ?>">
                              <option value="inherit"<?= $current === 'inherit' ? ' selected' : '' ?>>Herda <?= $baseAllowed ? '(sim)' : '(não)' ?></option>
                              <option value="allow"<?= $current === 'allow' ? ' selected' : '' ?>>Permite</option>
                              <option value="deny"<?= $current === 'deny' ? ' selected' : '' ?>>Nega</option>
                            </select>
                          </form>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>
        <?php endforeach; ?>
      <?php endif; ?>
    </main>
  </div>
  <?php render_vlibras(); ?>
</body>
</html>
