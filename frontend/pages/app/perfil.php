<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$user = fetch_one(
    $pdo,
    'SELECT nome, email, tipo, telefone, cpf, foto_perfil, oab, oab_uf, oab_status, oab_verificado, status_cna, cna_validado_em, cna_origem, cna_ultimo_erro
     FROM users
     WHERE id = ?',
    [current_user_id()]
);
$photoUrl = !empty($user['foto_perfil']) ? '../' . ltrim((string) $user['foto_perfil'], '/') : '';

function profile_oab_status_meta(array $user): array
{
    $verified = (int) ($user['oab_verificado'] ?? 0) === 1;
    $status = (string) (($user['status_cna'] ?? '') ?: 'pendente');

    if ($verified || $status === 'verificado') {
        return [
            'label' => 'OAB validada',
            'badge' => 'badge-success',
            'alert' => 'alert-success',
            'message' => 'Seu cadastro profissional esta validado. O acesso a casos, documentos e agenda profissional esta liberado.',
        ];
    }

    if (in_array($status, ['invalido', 'nao_encontrado'], true)) {
        return [
            'label' => 'OAB com pendencia',
            'badge' => 'badge-danger',
            'alert' => 'alert-error',
            'message' => 'A administracao encontrou problema na OAB informada. Atualize seus dados com o suporte antes de tentar acessar como profissional.',
        ];
    }

    return [
        'label' => 'Aguardando validacao',
        'badge' => 'badge-warning',
        'alert' => 'alert-info',
        'message' => 'Sua OAB ainda depende de revisao administrativa. Enquanto isso, o acesso profissional completo permanece bloqueado.',
    ];
}

$isProfessional = in_array($user['tipo'] ?? '', ['advogado', 'estagiario'], true);
$oabStatus = $isProfessional ? profile_oab_status_meta($user) : null;
$profileTourKey = match ($type) {
    'advogado' => 'dashboard_advogado',
    'estagiario' => 'dashboard_estagiario',
    'admin' => 'dashboard_admin',
    default => 'dashboard_cliente',
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Perfil | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=sidebar-open-button-1">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'perfil.php'); ?>

    <main class="app-main">
      <?php render_topbar('Meu perfil', 'Dados cadastrados no sistema.', current_user_name()); ?>

      <section class="profile-layout">
        <aside class="card text-center">
          <div class="profile-photo">
            <?php if ($photoUrl): ?>
              <img src="<?= e($photoUrl) ?>" alt="<?= e($user['nome'] ?? 'Foto de perfil') ?>">
            <?php else: ?>
              <?= e(substr($user['nome'] ?? 'U', 0, 1)) ?>
            <?php endif; ?>
          </div>
          <h3><?= e($user['nome'] ?? '') ?></h3>
          <p><?= e($user['email'] ?? '') ?></p>
          <p class="mt-12"><span class="badge badge-success">Conta ativa</span></p>
          <?php if ($oabStatus): ?>
            <p class="mt-8"><span class="badge <?= e($oabStatus['badge']) ?>"><?= e($oabStatus['label']) ?></span></p>
          <?php endif; ?>
        </aside>
        <div class="profile-main">
          <form class="card auth-form" action="<?= e(app_url('/backend/public/index.php?rota=/profile/update')) ?>" method="post" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <div class="field">
              <label for="foto_perfil">Foto de perfil</label>
              <input class="input" id="foto_perfil" name="foto_perfil" type="file" accept=".jpg,.jpeg,.png,.webp">
            </div>
            <div class="form-grid">
              <div class="field"><label for="nome">Nome</label><input class="input" id="nome" name="nome" value="<?= e($user['nome'] ?? '') ?>" required></div>
              <div class="field"><label for="email">E-mail</label><input class="input" id="email" name="email" type="email" value="<?= e($user['email'] ?? '') ?>" required></div>
              <div class="field"><label for="telefone">Telefone</label><input class="input" id="telefone" name="telefone" type="tel" inputmode="tel" autocomplete="tel" maxlength="15" placeholder="(00) 00000-0000" value="<?= e($user['telefone'] ?? '') ?>"></div>
              <?php if (($user['tipo'] ?? '') === 'cliente'): ?>
                <div class="field"><label for="cpf">CPF</label><input class="input" id="cpf" name="cpf" type="text" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" value="<?= e($user['cpf'] ?? '') ?>"></div>
              <?php endif; ?>
              <div class="field"><label for="tipo">Tipo</label><input class="input" id="tipo" value="<?= e($user['tipo'] ?? '') ?>" disabled></div>
            </div>
            <?php if (in_array($user['tipo'] ?? '', ['advogado', 'estagiario'], true)): ?>
              <div class="form-grid">
                <div class="field"><label>OAB</label><input class="input" value="<?= e($user['oab'] ?? '') ?>" disabled></div>
                <div class="field"><label>UF</label><input class="input" value="<?= e($user['oab_uf'] ?? '') ?>" disabled></div>
              </div>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit"><?= icon_svg('user') ?> Salvar alterações</button>
          </form>

          <?php if ($oabStatus): ?>
            <section class="card profile-oab-card">
              <div class="dash-section-title">
                <div>
                  <h2>Status profissional</h2>
                  <p class="text-muted">Validacao manual da OAB vinculada ao seu cadastro.</p>
                </div>
                <span class="badge <?= e($oabStatus['badge']) ?>"><?= e($oabStatus['label']) ?></span>
              </div>

              <div class="alert is-visible <?= e($oabStatus['alert']) ?>"><?= e($oabStatus['message']) ?></div>

              <div class="profile-oab-grid">
                <div>
                  <span>OAB</span>
                  <strong><?= e(trim((string) (($user['oab_uf'] ?? '') . ' ' . ($user['oab'] ?? ''))) ?: '-') ?></strong>
                </div>
                <div>
                  <span>Origem</span>
                  <strong><?= e(($user['cna_origem'] ?? '') ?: 'admin_manual') ?></strong>
                </div>
                <div>
                  <span>Ultima validacao</span>
                  <strong><?= !empty($user['cna_validado_em']) ? e(date('d/m/Y H:i', strtotime((string) $user['cna_validado_em']))) : '-' ?></strong>
                </div>
              </div>

              <?php if (!empty($user['oab_status'])): ?>
                <p class="text-muted mt-12"><?= e($user['oab_status']) ?></p>
              <?php endif; ?>
              <?php if (!empty($user['cna_ultimo_erro'])): ?>
                <p class="text-muted mt-12"><?= e($user['cna_ultimo_erro']) ?></p>
              <?php endif; ?>
            </section>
          <?php endif; ?>

          <section class="card">
            <div class="dash-section-title"><h2>Segurança</h2></div>
            <div class="form-actions">
              <button class="btn btn-outline" type="button" data-password-modal-open><?= icon_svg('lock') ?> Redefinir senha</button>
            </div>
          </section>

          <section class="card">
            <div class="dash-section-title">
              <div>
                <h2>Tour do sistema</h2>
                <p class="text-muted">Redefina o onboarding para vê-lo novamente no próximo acesso à dashboard.</p>
              </div>
            </div>
            <div class="form-actions">
              <button class="btn btn-outline" type="button" data-tour-reset><?= icon_svg('help') ?> Resetar tour</button>
            </div>
            <p class="alert alert-success mt-12" data-tour-reset-message hidden></p>
          </section>
        </div>
      </section>

      <div class="profile-password-modal" data-profile-password-modal hidden>
        <section class="profile-password-dialog" role="dialog" aria-modal="true" aria-labelledby="profile-password-title">
          <div class="profile-password-head">
            <h2 id="profile-password-title">Redefinir senha</h2>
            <button class="profile-password-close" type="button" data-password-modal-close aria-label="Fechar">×</button>
          </div>

          <p class="text-muted">Código para <?= e($user['email'] ?? '') ?></p>
          <div class="alert" data-password-modal-alert></div>

          <form class="auth-form" action="<?= e(app_url('/backend/public/index.php?rota=/profile/password-code')) ?>" method="post" data-password-code-form>
            <?= csrf_input() ?>
            <button class="btn btn-primary btn-block" type="submit"><?= icon_svg('mail') ?> Enviar código</button>
          </form>

          <form class="auth-form" action="<?= e(app_url('/backend/public/index.php?rota=/profile/password-reset')) ?>" method="post" data-password-reset-form>
            <?= csrf_input() ?>
            <p class="profile-password-step" data-password-code-hint>Envie o código para habilitar a atualização da senha.</p>
            <div class="field">
              <label for="profile_password_code">Código recebido</label>
              <input class="input" id="profile_password_code" name="codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required>
            </div>
            <div class="form-grid">
              <div class="field">
                <label for="profile_password_new">Nova senha</label>
                <input class="input" id="profile_password_new" name="senha" type="password" minlength="6" required>
              </div>
              <div class="field">
                <label for="profile_password_confirm">Confirmar nova senha</label>
                <input class="input" id="profile_password_confirm" name="senha2" type="password" minlength="6" required>
              </div>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit" data-password-reset-submit disabled><?= icon_svg('lock') ?> Atualizar senha</button>
              <button class="btn btn-outline" type="button" data-password-modal-close>Cancelar</button>
            </div>
          </form>
        </section>
      </div>
    </main>
  </div>
  <script src="assets/js/phone-mask.js"></script>
  <script src="assets/js/profile-password.js"></script>
  <?php render_onboarding_assets($profileTourKey, '2026.06.11', $type, false); ?>
</body>
</html>
