<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once PROJECT_ROOT_PATH . '/backend/app/services/OrganizationService.php';
require_permission('organizations.view');

$enabled = OrganizationService::enabled($pdo);

function organization_document_display(?string $document): string
{
    $raw = trim((string) $document);
    $normalized = strtoupper((string) preg_replace('/[^0-9A-Za-z]+/', '', $raw));
    if (strlen($normalized) === 14 && preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $normalized)) {
        return substr($normalized, 0, 2)
            . '.'
            . substr($normalized, 2, 3)
            . '.'
            . substr($normalized, 5, 3)
            . '/'
            . substr($normalized, 8, 4)
            . '-'
            . substr($normalized, 12, 2);
    }

    return $raw;
}

$organizations = $enabled ? fetch_all($pdo, 'SELECT * FROM organizations ORDER BY status, nome') : [];
$professionals = $enabled
    ? fetch_all(
        $pdo,
        "SELECT u.id, u.nome, u.email, u.tipo, u.oab, u.oab_uf, u.oab_verificado, o.nome AS organizacao
         FROM users u
         LEFT JOIN organizations o ON o.id = u.organization_id
         WHERE u.tipo IN ('advogado', 'estagiario')
         ORDER BY u.tipo, u.nome"
    )
    : [];
$totalLinked = 0;
foreach ($professionals as $professional) {
    if (!empty($professional['organizacao'])) {
        $totalLinked++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Organizações | JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="manifest" href="../site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <link rel="stylesheet" href="../assets/css/style.css?v=organizations-2">
  <script src="../assets/js/pwa.js" defer></script>
  <script src="../assets/js/admin-organizations.js?v=cnpj-alfa-1" defer></script>
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'organizacoes.php', true); ?>
    <main class="app-main">
      <?php render_topbar('Organizações', 'Controle de escritórios e equipes profissionais.', current_user_name()); ?>

      <?php if (!$enabled): ?>
        <?= empty_state('O banco ainda não possui as tabelas de organizações. Importe o SQL consolidado atualizado ou aplique um script incremental equivalente.') ?>
      <?php else: ?>
        <section class="grid grid-3">
          <?= stat_card('Organizações', count($organizations), 'folder') ?>
          <?= stat_card('Profissionais', count($professionals), 'users') ?>
          <?= stat_card('Com vínculo', $totalLinked, 'shield') ?>
        </section>

        <section class="admin-dashboard-grid mt-16">
          <article class="card">
            <div class="dash-section-title">
              <h2>Como usar</h2>
            </div>
            <p class="text-muted">Organizações representam escritórios ou empresas internas de atendimento. Elas servem para agrupar somente profissionais: advogados e estagiários. Clientes continuam independentes, porque o vínculo do cliente acontece pelo caso, documento e atendimento.</p>
          </article>

          <?php if (current_user_can('organizations.manage')): ?>
            <article class="card">
              <div class="dash-section-title">
                <h2>Nova organização</h2>
              </div>
              <form class="form-grid" method="post" action="<?= e(app_url('/backend/public/index.php?rota=/admin/organizations/create')) ?>">
                <?= csrf_input() ?>
                <label>Nome
                  <input class="input" name="nome" placeholder="Ex.: Escritório Central" required maxlength="180">
                </label>
                <label>Tipo
                  <select class="input" name="tipo">
                    <option value="escritorio">Escritório</option>
                    <option value="empresa">Empresa</option>
                  </select>
                </label>
                <label>CNPJ
                  <input class="input" name="documento" placeholder="12.ABC.345/01DE-35" maxlength="18" inputmode="text" autocomplete="off" autocapitalize="characters" pattern="[0-9A-Za-z./-]{14,18}" data-cnpj-mask>
                  <small class="text-muted">Aceita o CNPJ numérico atual e o novo padrão alfanumérico da Receita Federal, com início em julho de 2026.</small>
                </label>
                <div class="form-actions">
                  <button class="btn btn-primary" type="submit">Criar organização</button>
                </div>
              </form>
            </article>
          <?php endif; ?>
        </section>

        <section class="dash-section mt-16">
          <div class="dash-section-title">
            <h2>Organizações cadastradas</h2>
          </div>
          <?php if (!$organizations): ?>
            <?= empty_state('Nenhuma organização cadastrada.') ?>
          <?php else: ?>
            <div class="table-wrap">
              <table class="table">
                <thead><tr><th>Nome</th><th>Tipo</th><th>Status</th><th>CNPJ</th></tr></thead>
                <tbody>
                  <?php foreach ($organizations as $organization): ?>
                    <tr>
                      <td><strong><?= e((string) $organization['nome']) ?></strong></td>
                      <td><?= e(ucfirst((string) $organization['tipo'])) ?></td>
                      <td><span class="badge <?= ($organization['status'] ?? '') === 'ativo' ? 'badge-success' : 'badge-warning' ?>"><?= e((string) $organization['status']) ?></span></td>
                      <td><?= e(organization_document_display($organization['documento'] ?? null) ?: 'Não informado') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>

        <section class="dash-section mt-16">
          <div class="dash-section-title">
            <h2>Vínculos profissionais</h2>
          </div>
          <p class="text-muted">Somente advogados e estagiários aparecem aqui. Clientes não podem ser vinculados a uma organização.</p>
          <div class="table-wrap mt-12">
            <table class="table">
              <thead><tr><th>Profissional</th><th>Perfil</th><th>OAB</th><th>Organização atual</th><th>Alterar vínculo</th></tr></thead>
              <tbody>
                <?php foreach ($professionals as $professional): ?>
                  <tr>
                    <td>
                      <strong><?= e((string) $professional['nome']) ?></strong><br>
                      <span class="text-muted"><?= e((string) $professional['email']) ?></span>
                    </td>
                    <td><?= e(ucfirst((string) $professional['tipo'])) ?></td>
                    <td>
                      <?= e(trim((string) ($professional['oab'] ?? '') . '/' . (string) ($professional['oab_uf'] ?? ''), '/')) ?>
                      <br><span class="badge <?= (int) ($professional['oab_verificado'] ?? 0) === 1 ? 'badge-success' : 'badge-warning' ?>"><?= (int) ($professional['oab_verificado'] ?? 0) === 1 ? 'validada' : 'pendente' ?></span>
                    </td>
                    <td><?= e((string) ($professional['organizacao'] ?? 'Sem vínculo')) ?></td>
                    <td>
                      <?php if (current_user_can('organizations.manage')): ?>
                        <form class="inline-form" method="post" action="<?= e(app_url('/backend/public/index.php?rota=/admin/organizations/assign')) ?>">
                          <?= csrf_input() ?>
                          <input type="hidden" name="user_id" value="<?= e((string) $professional['id']) ?>">
                          <select class="input select-sm" name="organization_id" aria-label="Organização do profissional">
                            <option value="">Sem vínculo</option>
                            <?php foreach ($organizations as $organization): ?>
                              <option value="<?= e((string) $organization['id']) ?>"<?= ($professional['organizacao'] ?? '') === ($organization['nome'] ?? '') ? ' selected' : '' ?>><?= e((string) $organization['nome']) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button class="btn btn-soft btn-sm" type="submit">Aplicar</button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted">Somente leitura</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>
    </main>
  </div>
  <?php render_vlibras(); ?>
</body>
</html>
