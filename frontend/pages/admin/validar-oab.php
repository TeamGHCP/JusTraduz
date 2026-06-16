<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['admin']);

function admin_oab_validation_type_label(string $type): string
{
    return $type === 'estagiario' ? 'Estagiário' : 'Advogado';
}

function admin_oab_validation_clipboard_text(array $professional): string
{
    return implode("\n", [
        'Nome: ' . (string) ($professional['nome'] ?? ''),
        'Numero da inscricao: ' . (string) ($professional['oab'] ?? ''),
        'Seccional/UF: ' . (string) ($professional['oab_uf'] ?? ''),
        'Tipo de inscricao: ' . admin_oab_validation_type_label((string) ($professional['tipo'] ?? '')),
    ]);
}

function admin_oab_status_badge(string $status): string
{
    return match ($status) {
        'verificado' => 'badge-success',
        'invalido', 'nao_encontrado' => 'badge-danger',
        default => 'badge-warning',
    };
}

function admin_oab_status_label(string $status): string
{
    return match ($status) {
        'verificado' => 'Validado',
        'invalido' => 'Invalido',
        'nao_encontrado' => 'Não encontrado',
        default => 'Pendente',
    };
}

$successMessage = trim((string) ($_GET['sucesso'] ?? ''));
$errorMessage = trim((string) ($_GET['erro'] ?? ''));

$pendingProfessionals = fetch_all(
    $pdo,
    "SELECT id, nome, email, telefone, tipo, foto_perfil, google_picture, oab, oab_uf, oab_status, status_cna, cna_origem, cna_ultimo_erro, created_at
     FROM users
     WHERE tipo IN ('advogado', 'estagiario')
       AND status = 'ativo'
       AND oab_verificado = FALSE
       AND COALESCE(status_cna, 'pendente') = 'pendente'
       AND COALESCE(oab, '') <> ''
       AND COALESCE(oab_uf, '') <> ''
     ORDER BY created_at ASC"
);

$pendingTotal = count_query(
    $pdo,
    "SELECT COUNT(*)
     FROM users
     WHERE tipo IN ('advogado', 'estagiario')
       AND status = 'ativo'
       AND oab_verificado = FALSE
       AND COALESCE(status_cna, 'pendente') = 'pendente'
       AND COALESCE(oab, '') <> ''
       AND COALESCE(oab_uf, '') <> ''"
);
$validatedTotal = count_query($pdo, "SELECT COUNT(*) FROM users WHERE tipo IN ('advogado', 'estagiario') AND status = 'ativo' AND oab_verificado = TRUE");
$professionalTotal = count_query($pdo, "SELECT COUNT(*) FROM users WHERE tipo IN ('advogado', 'estagiario') AND status = 'ativo'");
$recentReviews = fetch_all(
    $pdo,
    "SELECT l.acao, l.status_anterior, l.status_novo, l.mensagem, l.justificativa, l.created_at,
            p.nome AS profissional_nome, p.tipo AS profissional_tipo, p.oab, p.oab_uf,
            a.nome AS admin_nome
     FROM cna_validacao_logs l
     LEFT JOIN users p ON p.id = l.profissional_id
     LEFT JOIN users a ON a.id = l.admin_id
     WHERE l.acao LIKE 'admin_%'
       AND NOT EXISTS (
           SELECT 1
           FROM cna_validacao_logs newer
           WHERE newer.profissional_id = l.profissional_id
             AND newer.acao LIKE 'admin_%'
             AND (
                 newer.created_at > l.created_at
                 OR (newer.created_at = l.created_at AND newer.id > l.id)
             )
       )
     ORDER BY l.created_at DESC, l.id DESC
     LIMIT 8"
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Validar OAB | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../assets/css/style.css?v=sidebar-open-button-1">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'validar-oab.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Validar OAB', 'Aprove profissionais reais e rejeite cadastros inconsistentes com motivo registrado.', current_user_name()); ?>

      <?php if ($successMessage !== ''): ?>
        <div class="alert is-visible alert-success"><?= e($successMessage) ?></div>
      <?php endif; ?>
      <?php if ($errorMessage !== ''): ?>
        <div class="alert is-visible alert-error"><?= e($errorMessage) ?></div>
      <?php endif; ?>

      <section class="grid grid-3">
        <?= stat_card('Pendentes', $pendingTotal, 'help') ?>
        <?= stat_card('Validados', $validatedTotal, 'shield') ?>
        <?= stat_card('Profissionais ativos', $professionalTotal, 'users') ?>
      </section>

      <section class="admin-policy-strip">
        <article>
          <?= icon_svg('shield') ?>
          <strong>1. Conferir no CNA</strong>
          <span>Abra a consulta oficial, cole os dados copiados e resolva o captcha manualmente.</span>
        </article>
        <article>
          <?= icon_svg('check') ?>
          <strong>2. Decidir com evidência</strong>
          <span>Aprove apenas quando a inscricao estiver ativa e compatível com nome, UF e tipo.</span>
        </article>
        <article>
          <?= icon_svg('lock') ?>
          <strong>3. Justificar tudo</strong>
          <span>Aprovacao ou rejeicao precisa de justificativa para auditoria e defesa tecnica.</span>
        </article>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Fila de validação</h2>
          <span class="badge badge-info"><?= e((string) count($pendingProfessionals)) ?> aguardando</span>
        </div>

        <?php if (!$pendingProfessionals): ?>
          <?= empty_state('Nenhum profissional aguardando validação de OAB.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table admin-users-table">
              <thead>
                <tr>
                  <th>Profissional</th>
                  <th>Contato</th>
                  <th>OAB</th>
                  <th>Cadastro</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pendingProfessionals as $professional): ?>
                  <?php
                    $oabTypeLabel = admin_oab_validation_type_label((string) $professional['tipo']);
                    $oabClipboardText = admin_oab_validation_clipboard_text($professional);
                    $statusCna = (string) (($professional['status_cna'] ?? '') ?: 'pendente');
                  ?>
                  <tr>
                    <td>
                      <div class="table-user">
                        <span class="table-avatar">
                          <?php
                            $initial = mb_strtoupper(mb_substr((string) $professional['nome'], 0, 1));
                            $localAvatar = trim((string) ($professional['foto_perfil'] ?? ''));
                            $googleAvatar = trim((string) ($professional['google_picture'] ?? ''));
                            $avatar = ($localAvatar !== '' && (preg_match('#^https?://#i', $localAvatar) || is_file(PROJECT_ROOT_PATH . '/' . ltrim($localAvatar, '/'))))
                                ? $localAvatar
                                : $googleAvatar;
                          ?>
                          <span class="avatar-initial"><?= e($initial) ?></span>
                          <?php if ($avatar !== ''): ?>
                            <img src="<?= e(preg_match('#^https?://#i', $avatar) ? $avatar : '../../' . ltrim($avatar, '/')) ?>" alt="" referrerpolicy="no-referrer" onerror="this.remove()">
                          <?php endif; ?>
                        </span>
                        <span>
                          <strong><?= e($professional['nome']) ?></strong>
                          <span class="table-subtext"><?= e($professional['tipo'] === 'advogado' ? 'Advogado' : 'Estagiário') ?></span>
                        </span>
                      </div>
                    </td>
                    <td>
                      <?= e($professional['email']) ?>
                      <span class="table-subtext"><?= e($professional['telefone'] ?: 'Sem telefone') ?></span>
                    </td>
                    <td>
                      <strong>OAB/<?= e($professional['oab_uf'] ?: '-') ?> <?= e($professional['oab'] ?: '-') ?></strong>
                      <span class="table-subtext"><?= e($professional['oab_status'] ?: 'Aguardando validação administrativa.') ?></span>
                      <span class="badge <?= e(admin_oab_status_badge($statusCna)) ?> mt-8"><?= e(admin_oab_status_label($statusCna)) ?></span>
                      <?php if (!empty($professional['cna_origem'])): ?>
                        <span class="table-subtext">Origem: <?= e($professional['cna_origem']) ?></span>
                      <?php endif; ?>
                      <?php if (!empty($professional['cna_ultimo_erro'])): ?>
                        <span class="table-subtext">Ultimo erro: <?= e($professional['cna_ultimo_erro']) ?></span>
                      <?php endif; ?>
                      <div class="oab-validation-box">
                        <span>Consulta CNA</span>
                        <strong><?= e($professional['nome']) ?></strong>
                        <small>Inscricao <?= e($professional['oab'] ?: '-') ?> - UF <?= e($professional['oab_uf'] ?: '-') ?> - <?= e($oabTypeLabel) ?></small>
                        <div class="oab-validation-actions">
                          <a
                            class="btn btn-soft btn-sm"
                            href="https://cna.oab.org.br/"
                            target="_blank"
                            rel="noopener"
                            data-oab-copy-open
                            data-copy-text="<?= e($oabClipboardText) ?>"
                          ><?= icon_svg('shield') ?> Abrir CNA</a>
                          <button
                            class="btn btn-outline btn-sm"
                            type="button"
                            data-oab-copy
                            data-copy-text="<?= e($oabClipboardText) ?>"
                          >Copiar dados</button>
                        </div>
                      </div>
                    </td>
                    <td><?= e(date('d/m/Y H:i', strtotime($professional['created_at']))) ?></td>
                    <td>
                      <div class="stacked-actions oab-review-actions">
                        <form class="action-form" action="<?= e(app_url('/backend/public/index.php?rota=/admin/professionals/oab')) ?>" method="post">
                          <?= csrf_input() ?>
                          <input type="hidden" name="user_id" value="<?= (int) $professional['id'] ?>">
                          <input type="hidden" name="action" value="approve">
                          <input class="input admin-reason-input" name="justificativa" placeholder="Fonte da validação" required>
                          <button class="btn btn-success btn-sm" type="submit"><?= icon_svg('check') ?> Aprovar</button>
                        </form>

                        <form class="action-form action-form-stack" action="<?= e(app_url('/backend/public/index.php?rota=/admin/professionals/oab')) ?>" method="post" onsubmit="return confirm('Rejeitar este cadastro manterá a conta bloqueada e enviará o motivo ao usuário. Continuar?');">
                          <?= csrf_input() ?>
                          <input type="hidden" name="user_id" value="<?= (int) $professional['id'] ?>">
                          <input type="hidden" name="action" value="reject">
                          <input class="input admin-reason-input" name="justificativa" placeholder="Motivo da reprovacao" required>
                          <button class="btn btn-outline btn-sm" type="submit">Rejeitar cadastro</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <div>
            <h2>Ultimas decisoes OAB</h2>
            <p class="text-muted">Registro administrativo das revisoes recentes.</p>
          </div>
          <a class="btn btn-outline btn-sm" href="auditoria.php">Ver auditoria</a>
        </div>

        <?php if (!$recentReviews): ?>
          <?= empty_state('Nenhuma decisao OAB registrada ainda.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table compact-table">
              <thead>
                <tr>
                  <th>Profissional</th>
                  <th>Status</th>
                  <th>Justificativa</th>
                  <th>Admin</th>
                  <th>Data</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentReviews as $review): ?>
                  <?php $reviewStatus = (string) (($review['status_novo'] ?? '') ?: 'pendente'); ?>
                  <tr>
                    <td>
                      <strong><?= e($review['profissional_nome'] ?: 'Conta removida') ?></strong>
                      <span class="table-subtext">
                        <?= e(($review['profissional_tipo'] ?? '') ?: '-') ?>
                        <?php if (!empty($review['oab']) || !empty($review['oab_uf'])): ?>
                          - OAB/<?= e($review['oab_uf'] ?: '-') ?> <?= e($review['oab'] ?: '-') ?>
                        <?php endif; ?>
                      </span>
                    </td>
                    <td><span class="badge <?= e(admin_oab_status_badge($reviewStatus)) ?>"><?= e(admin_oab_status_label($reviewStatus)) ?></span></td>
                    <td>
                      <?= e(($review['justificativa'] ?? '') ?: ($review['mensagem'] ?? '-')) ?>
                      <span class="table-subtext"><?= e($review['acao'] ?? '-') ?></span>
                    </td>
                    <td><?= e(($review['admin_nome'] ?? '') ?: '-') ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime((string) $review['created_at']))) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <script src="../assets/js/admin-oab-validation.js"></script>
  <?php render_vlibras(); ?>
</body>
</html>
