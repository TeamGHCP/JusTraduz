<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
require_once PROJECT_ROOT_PATH . '/backend/app/services/SubscriptionService.php';

function confirmed_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

$type = current_user_type();
if (!in_array($type, ['cliente', 'advogado'], true)) {
    confirmed_redirect(dashboard_url($type) . '?erro=' . urlencode('Planos estão disponíveis para clientes e advogados verificados.'));
}

$billing = new SubscriptionService($pdo);
$subscription = $billing->currentForUser(current_user_id());
if (!$subscription || !in_array((string) ($subscription['status'] ?? ''), ['active', 'trialing'], true)) {
    confirmed_redirect(app_url('/frontend/perfil.php?tab=faturamento'));
}

$confirmation = is_array($_SESSION['payment_confirmed'] ?? null) ? $_SESSION['payment_confirmed'] : [];
if (!$confirmation && ((string) ($subscription['provider'] ?? '') !== 'asaas' || trim((string) ($subscription['provider_subscription_id'] ?? '')) === '')) {
    confirmed_redirect(app_url('/frontend/perfil.php?tab=faturamento'));
}
$billingCycle = (string) ($confirmation['billing_cycle'] ?? $subscription['billing_cycle'] ?? 'monthly');
if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
    $billingCycle = 'monthly';
}

$planName = (string) ($confirmation['plan_name'] ?? $subscription['plan_name'] ?? 'Plano ativo');
$amountCents = (int) ($confirmation['amount_cents'] ?? 0);
if ($amountCents <= 0) {
    $amountCents = (int) ($billingCycle === 'yearly'
        ? ($subscription['yearly_price_cents'] ?? 0)
        : ($subscription['monthly_price_cents'] ?? 0));
}

$confirmedAt = (int) ($confirmation['confirmed_at'] ?? time());
$providerPaymentId = trim((string) ($confirmation['provider_payment_id'] ?? ''));
$providerSubscriptionId = trim((string) ($confirmation['provider_subscription_id'] ?? $subscription['provider_subscription_id'] ?? ''));
$providerName = (string) ($confirmation['provider'] ?? $subscription['provider'] ?? '');
$subscriptionId = (int) ($confirmation['subscription_id'] ?? $subscription['id'] ?? 0);
$previousPlanName = trim((string) ($confirmation['previous_plan_name'] ?? ''));
$teamInvitesSent = array_values(array_filter(array_map('strval', (array) ($confirmation['team_invites_sent'] ?? []))));
$hasPlanReplacement = $previousPlanName !== '';
$replacementWarning = trim((string) ($confirmation['previous_remote_cancel_error'] ?? ''));
$protocolSeed = $providerPaymentId !== '' ? $providerPaymentId : ($providerSubscriptionId !== '' ? $providerSubscriptionId : (string) $subscriptionId);
$receiptProtocol = 'JT-' . strtoupper(substr(hash('sha256', $protocolSeed . '|' . current_user_id()), 0, 10));
$periodEnd = (string) ($subscription['current_period_end'] ?? '');

function confirmed_money(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function confirmed_date(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : $date;
}

function confirmed_datetime(int $timestamp): string
{
    return date('d/m/Y H:i', $timestamp);
}

$receiptText = implode(' | ', [
    'JusTraduz',
    'Pagamento confirmado',
    'Protocolo ' . $receiptProtocol,
    'Plano ' . $planName,
    confirmed_money($amountCents),
    $billingCycle === 'yearly' ? 'Cobrança anual' : 'Cobrança mensal',
]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pagamento confirmado | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=2026.07.05-style-cache-1">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'subir-plano.php'); ?>

    <main class="app-main">
      <?php render_topbar('Pagamento confirmado', 'Seu plano foi ativado com sucesso.', current_user_name()); ?>

      <section class="payment-confirmed-page" data-payment-confirmed>
        <div class="payment-confetti-layer" data-payment-confetti aria-hidden="true"></div>
        <?php if ($replacementWarning !== ''): ?>
          <div class="payment-alert payment-alert-info">
            <?= icon_svg('shield') ?>
            <span>Seu novo plano está ativo, mas o cancelamento remoto do plano anterior precisa ser conferido no Asaas.</span>
          </div>
        <?php endif; ?>

        <div class="payment-confirmed-hero">
          <div class="payment-confirmed-copy">
            <span class="pricing-kicker"><?= icon_svg('check') ?> Assinatura ativa</span>
            <h2>Pronto, seu plano <?= e($planName) ?> está liberado.</h2>
            <p><?= $hasPlanReplacement ? 'A confirmação foi registrada, o plano ' . e($previousPlanName) . ' foi substituído e os novos limites já estão disponíveis na sua conta JusTraduz.' : 'A confirmação foi registrada e os limites do plano já estão disponíveis na sua conta JusTraduz.' ?></p>
            <div class="payment-confirmed-actions">
              <a class="btn btn-primary" href="<?= e(dashboard_url($type)) ?>"><?= icon_svg('home') ?> Ir para dashboard</a>
              <a class="btn btn-outline" href="<?= e(app_url('/frontend/perfil.php?tab=faturamento')) ?>"><?= icon_svg('chart') ?> Ver faturamento</a>
            </div>
          </div>

          <div class="payment-confirmed-seal" aria-label="Pagamento confirmado">
            <span><?= icon_svg('check') ?></span>
          </div>
        </div>

        <div class="payment-confirmed-grid">
          <article class="payment-confirmed-card payment-confirmed-receipt">
            <div class="payment-confirmed-card-head">
              <span><?= icon_svg('file') ?></span>
              <div>
                <h3>Comprovante</h3>
                <p>Confirmação interna da ativação.</p>
              </div>
            </div>

            <dl class="payment-confirmed-list">
              <div>
                <dt>Protocolo</dt>
                <dd><?= e($receiptProtocol) ?></dd>
              </div>
              <div>
                <dt>Plano</dt>
                <dd><?= e($planName) ?></dd>
              </div>
              <?php if ($hasPlanReplacement): ?>
                <div>
                  <dt>Plano substituído</dt>
                  <dd><?= e($previousPlanName) ?></dd>
                </div>
              <?php endif; ?>
              <div>
                <dt>Valor</dt>
                <dd><?= e(confirmed_money($amountCents)) ?></dd>
              </div>
              <div>
                <dt>Ciclo</dt>
                <dd><?= e($billingCycle === 'yearly' ? 'Anual' : 'Mensal') ?></dd>
              </div>
              <div>
                <dt>Confirmado em</dt>
                <dd><?= e(confirmed_datetime($confirmedAt)) ?></dd>
              </div>
              <div>
                <dt>Próxima renovação</dt>
                <dd><?= e(confirmed_date($periodEnd)) ?></dd>
              </div>
            </dl>

            <button class="payment-confirmed-copy-button" type="button" data-copy-receipt="<?= e($receiptText) ?>">
              <?= icon_svg('paperclip') ?> Copiar resumo
            </button>
          </article>

          <article class="payment-confirmed-card payment-confirmed-next">
            <div class="payment-confirmed-card-head">
              <span><?= icon_svg('sparkles') ?></span>
              <div>
                <h3>Agora você pode continuar</h3>
                <p>Seu acesso já considera o novo plano.</p>
              </div>
            </div>

            <div class="payment-confirmed-steps">
              <div>
                <span><?= icon_svg('check') ?></span>
                <strong>Pagamento verificado</strong>
                <p>Consulta ao Asaas concluída.</p>
              </div>
              <div>
                <span><?= icon_svg('shield') ?></span>
                <strong><?= $hasPlanReplacement ? 'Plano anterior substituído' : 'Plano ativado' ?></strong>
                <p><?= $hasPlanReplacement ? 'A assinatura anterior foi cancelada no JusTraduz e substituída pelo novo plano.' : 'Assinatura registrada no JusTraduz.' ?></p>
              </div>
              <div>
                <span><?= icon_svg('file') ?></span>
                <strong>Limites liberados</strong>
                <p>Recursos disponíveis para uso.</p>
              </div>
              <?php if ($teamInvitesSent): ?>
                <div>
                  <span><?= icon_svg('users') ?></span>
                  <strong>Convites enviados</strong>
                  <p><?= e(implode(', ', $teamInvitesSent)) ?></p>
                </div>
              <?php endif; ?>
            </div>
          </article>
        </div>
      </section>
    </main>
  </div>

  <?php render_vlibras(); ?>
  <script src="assets/js/payment-confirmed.js?v=payment-confetti-2"></script>
</body>
</html>
