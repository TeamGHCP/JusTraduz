<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
require_once PROJECT_ROOT_PATH . '/backend/app/services/SubscriptionService.php';
require_once FRONTEND_APP_PATH . '/support/billing_documents.php';

$type = current_user_type();
$user = fetch_one(
    $pdo,
    'SELECT nome, email, tipo, telefone, cpf, foto_perfil, google_picture, oab, oab_uf, oab_status, oab_verificado, status_cna, cna_validado_em, cna_origem, cna_ultimo_erro
     FROM users
     WHERE id = ?',
    [current_user_id()]
);
$localProfilePhoto = trim((string) ($user['foto_perfil'] ?? ''));
$googleProfilePhoto = trim((string) ($user['google_picture'] ?? ''));
$profilePhotoPath = ($localProfilePhoto !== '' && (preg_match('#^https?://#i', $localProfilePhoto) || is_file(PROJECT_ROOT_PATH . '/' . ltrim($localProfilePhoto, '/'))))
    ? $localProfilePhoto
    : $googleProfilePhoto;
$photoUrl = $profilePhotoPath !== ''
    ? (preg_match('#^https?://#i', $profilePhotoPath) ? $profilePhotoPath : '../' . ltrim($profilePhotoPath, '/'))
    : '';

function profile_oab_status_meta(array $user): array
{
    $verified = (int) ($user['oab_verificado'] ?? 0) === 1;
    $status = (string) (($user['status_cna'] ?? '') ?: 'pendente');

    if ($verified || $status === 'verificado') {
        return [
            'label' => 'OAB validada',
            'badge' => 'badge-success',
            'alert' => 'alert-success',
            'message' => 'Seu cadastro profissional está validado. O acesso a casos, documentos e agenda profissional está liberado.',
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
        'label' => 'Aguardando validação',
        'badge' => 'badge-warning',
        'alert' => 'alert-info',
        'message' => 'Sua OAB ainda depende de revisão administrativa. Enquanto isso, o acesso profissional completo permanece bloqueado.',
    ];
}

function profile_billing_renewal_message(?array $subscription): string
{
    if (!$subscription) {
        return '';
    }

    if ((string) ($subscription['status'] ?? '') === 'past_due') {
        return 'Seu plano está com pagamento em aberto. Regularize para evitar bloqueios.';
    }

    $periodEnd = trim((string) ($subscription['current_period_end'] ?? ''));
    if ($periodEnd === '') {
        return '';
    }

    try {
        $today = new DateTimeImmutable('today');
        $renewalDay = (new DateTimeImmutable($periodEnd))->setTime(0, 0);
    } catch (Throwable) {
        return '';
    }

    $daysRemaining = (int) $today->diff($renewalDay)->format('%r%a');
    if ($daysRemaining > 1) {
        return 'Faltam ' . $daysRemaining . ' dias para a renovação do seu plano.';
    }

    if ($daysRemaining === 1) {
        return 'Falta 1 dia para a renovação do seu plano.';
    }

    if ($daysRemaining === 0) {
        return 'Seu plano renova hoje.';
    }

    return 'O período do plano terminou. Verifique o pagamento.';
}

$isProfessional = in_array($user['tipo'] ?? '', ['advogado', 'estagiario'], true);
$oabStatus = $isProfessional ? profile_oab_status_meta($user) : null;
$activeProfileTab = (string) ($_GET['tab'] ?? 'conta');
$allowedProfileTabs = ['conta', 'faturamento', 'seguranca', 'privacidade'];
if (!in_array($activeProfileTab, $allowedProfileTabs, true)) {
    $activeProfileTab = 'conta';
}

$subscriptionService = new SubscriptionService($pdo);
$currentSubscription = ($type === 'cliente') ? $subscriptionService->currentForUser(current_user_id()) : null;
$isFreeSubscription = $currentSubscription
    && in_array((string) ($currentSubscription['plan_slug'] ?? ''), ['gratuito', 'free'], true);
$paymentEvents = [];
if ($type === 'cliente' && database_table_exists($pdo, 'payment_events')) {
    $stmt = $pdo->prepare(
        'SELECT id, subscription_id, user_id, provider, provider_event_id, event_type, amount_cents, status, payload_json, processed_at, created_at
         FROM payment_events
         WHERE user_id = ?
         ORDER BY COALESCE(processed_at, created_at) DESC, id DESC
         LIMIT 8'
    );
    $stmt->execute([current_user_id()]);
    $paymentEvents = $stmt->fetchAll();
}

$billingPlanName = $currentSubscription ? (string) ($currentSubscription['plan_name'] ?? 'Plano ativo') : 'Grátis';
$billingCycle = (string) ($currentSubscription['billing_cycle'] ?? 'monthly');
$billingRenewalMessage = profile_billing_renewal_message($currentSubscription);
$billingPrice = 0;
if ($currentSubscription) {
    $billingPrice = (int) ($billingCycle === 'yearly'
        ? ($currentSubscription['yearly_price_cents'] ?? 0)
        : ($currentSubscription['monthly_price_cents'] ?? 0));
}
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
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <meta name="application-name" content="JusTraduz">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="JusTraduz">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="msapplication-TileColor" content="#008f80">
  <link rel="stylesheet" href="assets/css/style.css?v=alert-dismiss-1">
  <script src="assets/js/pwa.js" defer></script>
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'perfil.php'); ?>

    <main class="app-main">
      <?php render_topbar('Meu perfil', 'Dados cadastrados no sistema.', current_user_name()); ?>

      <section class="profile-layout">
        <aside class="card text-center">
          <div class="profile-photo">
            <span class="avatar-initial"><?= e(substr($user['nome'] ?? 'U', 0, 1)) ?></span>
            <?php if ($photoUrl): ?>
              <img src="<?= e($photoUrl) ?>" alt="<?= e($user['nome'] ?? 'Foto de perfil') ?>" referrerpolicy="no-referrer" onerror="this.remove()">
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
          <nav class="profile-tabs" aria-label="Configurações da conta">
            <a class="<?= $activeProfileTab === 'conta' ? 'active' : '' ?>" href="<?= e(app_url('/frontend/perfil.php?tab=conta')) ?>"><?= icon_svg('user') ?> Geral</a>
            <a class="<?= $activeProfileTab === 'faturamento' ? 'active' : '' ?>" href="<?= e(app_url('/frontend/perfil.php?tab=faturamento')) ?>"><?= icon_svg('chart') ?> Faturamento</a>
            <a class="<?= $activeProfileTab === 'seguranca' ? 'active' : '' ?>" href="<?= e(app_url('/frontend/perfil.php?tab=seguranca')) ?>"><?= icon_svg('shield') ?> Segurança</a>
            <a class="<?= $activeProfileTab === 'privacidade' ? 'active' : '' ?>" href="<?= e(app_url('/frontend/perfil.php?tab=privacidade')) ?>"><?= icon_svg('download') ?> Dados</a>
          </nav>

          <?php if ($activeProfileTab === 'conta'): ?>
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
                    <p class="text-muted">Validação manual da OAB vinculada ao seu cadastro.</p>
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
                    <span>Última validação</span>
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
          <?php elseif ($activeProfileTab === 'faturamento'): ?>
            <section class="card profile-billing-card">
              <div class="profile-billing-section">
                <div>
                  <h2>Cobrança</h2>
                  <p class="text-muted">Plano, renovação e cancelamento da sua assinatura.</p>
                </div>
              </div>

              <div class="profile-billing-plan">
                <div>
                  <h3><?= e($billingPlanName) ?></h3>
                  <?php if ($currentSubscription): ?>
                    <p><?= e($isFreeSubscription ? 'Modo gratuito' : ($billingCycle === 'yearly' ? 'Cobrança anual' : 'Cobrança mensal')) ?> · <?= e(billing_money($billingPrice)) ?></p>
                    <?php if (!$isFreeSubscription && (string) ($currentSubscription['status'] ?? '') === 'past_due'): ?>
                      <p>Pagamento pendente</p>
                    <?php elseif ($isFreeSubscription): ?>
                      <p>Cota gratuita ativa</p>
                    <?php else: ?>
                      <p>Renova em <?= e(billing_date((string) ($currentSubscription['current_period_end'] ?? ''))) ?></p>
                    <?php endif; ?>
                    <?php if ($billingRenewalMessage !== ''): ?>
                      <p><?= e($billingRenewalMessage) ?></p>
                    <?php endif; ?>
                  <?php else: ?>
                    <p>Você está usando o modo gratuito. Assine um plano para liberar mais volume e prioridade.</p>
                  <?php endif; ?>
                </div>
                <div class="profile-billing-actions">
                  <a class="btn btn-outline" href="<?= e(app_url('/frontend/subir-plano.php')) ?>"><?= ($currentSubscription && !$isFreeSubscription) ? 'Alterar plano' : 'Subir de plano' ?></a>
                  <?php if ($currentSubscription && !$isFreeSubscription): ?>
                    <form action="<?= e(app_url('/backend/public/index.php?rota=/billing/cancel')) ?>" method="post" data-billing-cancel-form>
                      <?= csrf_input() ?>
                      <button class="btn btn-outline" type="submit">Cancelar plano</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>

              <div class="profile-billing-section">
                <div>
                  <h2>Histórico de faturas</h2>
                  <p class="text-muted">Eventos recentes recebidos pelo checkout e pelo webhook.</p>
                </div>
              </div>

              <div class="profile-billing-history">
                <?php if (!$paymentEvents): ?>
                  <p class="text-muted">Nenhuma cobrança registrada ainda.</p>
                <?php endif; ?>
                <?php foreach ($paymentEvents as $event): ?>
                  <?php
                    $eventId = (int) ($event['id'] ?? 0);
                    $eventDate = (string) (($event['processed_at'] ?? '') ?: ($event['created_at'] ?? ''));
                    [$statusLabel, $statusBadge] = billing_status_label((string) ($event['status'] ?? 'pending'));
                    $eventTitle = billing_event_title((string) ($event['event_type'] ?? ''), (string) ($event['status'] ?? 'pending'));
                    $eventMethod = billing_method_label($event);
                    $eventCycle = billing_cycle_label($event);
                    $eventAmount = billing_money((int) ($event['amount_cents'] ?? 0));
                  ?>
                  <div class="profile-billing-row">
                    <span><?= e(billing_date($eventDate)) ?></span>
                    <div class="profile-billing-row-main">
                      <strong><?= e($eventTitle) ?></strong>
                      <small><?= e($eventMethod) ?> · <?= e($eventCycle) ?></small>
                    </div>
                    <strong><?= e($eventAmount) ?></strong>
                    <em class="badge <?= e($statusBadge) ?>"><?= e($statusLabel) ?></em>
                    <button
                      class="btn btn-outline profile-billing-view"
                      type="button"
                      data-billing-detail-open
                      data-event-id="<?= e((string) $eventId) ?>"
                      data-event-title="<?= e($eventTitle) ?>"
                      data-event-date="<?= e(billing_datetime($eventDate)) ?>"
                      data-event-amount="<?= e($eventAmount) ?>"
                      data-event-status="<?= e($statusLabel) ?>"
                      data-event-status-class="<?= e($statusBadge) ?>"
                      data-event-method="<?= e($eventMethod) ?>"
                      data-event-cycle="<?= e($eventCycle) ?>"
                      data-event-provider="<?= e(ucfirst((string) ($event['provider'] ?? 'asaas'))) ?>"
                      data-event-payment-id="<?= e((string) ($event['provider_event_id'] ?? '')) ?>"
                      data-invoice-url="<?= e(app_url('/frontend/fatura.php?id=' . $eventId . '&download=1')) ?>"
                      data-receipt-url="<?= e(app_url('/frontend/recibo.php?id=' . $eventId . '&download=1')) ?>"
                    >Exibir</button>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>
          <?php elseif ($activeProfileTab === 'seguranca'): ?>
            <section class="card">
              <div class="dash-section-title"><h2>Segurança</h2></div>
              <div class="form-actions">
                <button class="btn btn-outline" type="button" data-password-modal-open><?= icon_svg('lock') ?> Redefinir senha</button>
              </div>
            </section>
          <?php else: ?>
            <section class="card">
              <div class="dash-section-title">
                <div>
                  <h2>Privacidade e LGPD</h2>
                  <p class="text-muted">Exporte seus dados ou solicite o encerramento da conta.</p>
                </div>
              </div>
              <div class="form-actions">
                <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/privacy/export')) ?>" method="post">
                  <?= csrf_input() ?>
                  <button class="btn btn-outline" type="submit"><?= icon_svg('download') ?> Baixar meus dados</button>
                </form>
              </div>
              <form class="auth-form mt-16" action="<?= e(app_url('/backend/public/index.php?rota=/privacy/delete-account')) ?>" method="post" onsubmit="return confirm('Encerrar a conta remove dados pessoais e nao pode ser desfeito. Continuar?');">
                <?= csrf_input() ?>
                <div class="field">
                  <label for="privacy_delete_confirm">Digite EXCLUIR para encerrar a conta</label>
                  <input class="input" id="privacy_delete_confirm" name="confirmacao" autocomplete="off" placeholder="EXCLUIR">
                </div>
                <button class="btn btn-outline" type="submit"><?= icon_svg('trash') ?> Encerrar conta</button>
              </form>
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
          <?php endif; ?>
        </div>
      </section>

      <div class="billing-cancel-modal" data-billing-cancel-modal hidden>
        <section class="billing-cancel-dialog" role="alertdialog" aria-modal="true" aria-labelledby="billing-cancel-title" aria-describedby="billing-cancel-description">
          <button class="billing-cancel-close" type="button" data-billing-cancel-close aria-label="Fechar">×</button>
          <div class="billing-cancel-icon"><?= icon_svg('shield') ?></div>
          <span class="pricing-kicker"><?= icon_svg('x') ?> Cancelamento</span>
          <h2 id="billing-cancel-title">Cancelar seu plano atual?</h2>
          <p id="billing-cancel-description">Ao confirmar, sua conta volta para o modo gratuito e os limites do plano deixam de ficar disponíveis.</p>
          <div class="billing-cancel-plan">
            <span>Plano atual</span>
            <strong><?= e($billingPlanName) ?></strong>
          </div>
          <div class="billing-cancel-actions">
            <button class="btn btn-outline" type="button" data-billing-cancel-close>Manter plano</button>
            <button class="btn btn-primary billing-cancel-confirm" type="button" data-billing-cancel-confirm><?= icon_svg('x') ?> Cancelar plano</button>
          </div>
        </section>
      </div>

      <div class="billing-detail-modal" data-billing-detail-modal hidden>
        <section class="billing-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="billing-detail-title">
          <button class="billing-cancel-close" type="button" data-billing-detail-close aria-label="Fechar">×</button>
          <div class="billing-detail-receipt">
            <div class="billing-detail-icon"><?= icon_svg('download') ?></div>
            <span class="pricing-kicker">Documento fiscal</span>
            <h2 id="billing-detail-title" data-billing-detail-title>Fatura</h2>
            <strong data-billing-detail-amount>R$ 0,00</strong>
            <em class="badge badge-info" data-billing-detail-status>Pendente</em>
          </div>

          <dl class="billing-detail-grid">
            <div>
              <dt>Data</dt>
              <dd data-billing-detail-date>-</dd>
            </div>
            <div>
              <dt>Método</dt>
              <dd data-billing-detail-method>-</dd>
            </div>
            <div>
              <dt>Ciclo</dt>
              <dd data-billing-detail-cycle>-</dd>
            </div>
            <div>
              <dt>Provedor</dt>
              <dd data-billing-detail-provider>-</dd>
            </div>
            <div class="billing-detail-grid-wide">
              <dt>Identificador do pagamento</dt>
              <dd data-billing-detail-payment-id>-</dd>
            </div>
          </dl>

          <div class="billing-detail-actions">
            <a class="btn btn-outline" href="#" target="_blank" rel="noopener" data-billing-invoice-link><?= icon_svg('download') ?> Baixar fatura</a>
            <a class="btn btn-primary" href="#" target="_blank" rel="noopener" data-billing-receipt-link><?= icon_svg('download') ?> Baixar recibo</a>
          </div>
        </section>
      </div>

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
  <script src="assets/js/phone-mask.js?v=cpf-validator-1"></script>
  <script src="assets/js/profile-password.js"></script>
  <script src="assets/js/profile-billing.js?v=billing-documents-1"></script>
  <?php render_onboarding_assets($profileTourKey, '2026.06.11', $type, false); ?>
  <?php render_vlibras(); ?>
</body>
</html>
