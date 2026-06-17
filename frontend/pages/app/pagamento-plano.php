<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
require_once PROJECT_ROOT_PATH . '/backend/app/services/SubscriptionService.php';

function payment_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

$type = current_user_type();
if ($type !== 'cliente') {
    payment_redirect(dashboard_url($type) . '?erro=' . urlencode('Planos são exclusivos para clientes.'));
}

$checkout = is_array($_SESSION['billing_checkout'] ?? null) ? $_SESSION['billing_checkout'] : [];
$checkoutAge = time() - (int) ($checkout['created_at'] ?? 0);
if (($checkout['provider'] ?? '') !== 'asaas' || $checkoutAge > 3600) {
    payment_redirect(app_url('/frontend/perfil.php?tab=faturamento'));
}

$planId = (int) ($checkout['plan_id'] ?? 0);
$billingCycle = (string) ($checkout['billing_cycle'] ?? 'monthly');
$metadata = is_array($checkout['metadata'] ?? null) ? $checkout['metadata'] : [];
$payUrl = trim((string) ($checkout['redirect_url'] ?? $metadata['checkout_url'] ?? ''));
$invoiceUrl = trim((string) ($metadata['invoice_url'] ?? ''));
$bankSlipUrl = trim((string) ($metadata['bank_slip_url'] ?? ''));
$paymentLink = trim((string) ($metadata['payment_link'] ?? ''));
$dueDate = trim((string) ($metadata['due_date'] ?? ''));
$providerSubscriptionId = trim((string) ($metadata['provider_subscription_id'] ?? ''));

$billing = new SubscriptionService($pdo);
$currentSubscription = $billing->currentForUser(current_user_id());
$isPlanActive = $currentSubscription
    && (int) ($currentSubscription['plan_id'] ?? 0) === $planId
    && (
        $providerSubscriptionId === ''
        || (string) ($currentSubscription['provider_subscription_id'] ?? '') === $providerSubscriptionId
        || (string) ($currentSubscription['provider'] ?? '') !== 'asaas'
    );
$plans = $billing->plans();
$plan = null;
foreach ($plans as $candidate) {
    if ((int) ($candidate['id'] ?? 0) === $planId) {
        $plan = $candidate;
        break;
    }
}

if (!$plan || $payUrl === '') {
    payment_redirect(app_url('/frontend/subir-plano.php?erro=' . urlencode('Não foi possível recuperar a cobrança. Tente novamente.')));
}

$amountCents = (int) ($metadata['amount_cents'] ?? ($billingCycle === 'yearly' ? $plan['yearly_price_cents'] : $plan['monthly_price_cents']));
$cycleLabel = $billingCycle === 'yearly' ? 'Anual' : 'Mensal';
$periodLabel = $billingCycle === 'yearly' ? '/ano' : '/mês';
$dueLabel = '';
if ($dueDate !== '') {
    $timestamp = strtotime($dueDate);
    $dueLabel = $timestamp ? date('d/m/Y', $timestamp) : $dueDate;
}

function payment_money(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pagamento do plano | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=payment-page-1">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'subir-plano.php'); ?>

    <main class="app-main">
      <?php render_topbar('Pagamento do plano', 'Finalize sua assinatura com a cobrança criada pelo Asaas.', current_user_name()); ?>

      <section class="payment-page">
        <div class="payment-panel">
          <section class="payment-checkout">
            <span class="pricing-kicker"><?= icon_svg($isPlanActive ? 'check' : 'shield') ?> <?= $isPlanActive ? 'Plano ativo' : 'Checkout seguro' ?></span>
            <h2><?= $isPlanActive ? 'Seu plano ' . e($plan['name']) . ' está ativo.' : 'Seu plano ' . e($plan['name']) . ' está pronto para pagamento.' ?></h2>
            <p><?= $isPlanActive ? 'O pagamento foi confirmado e seus limites já estão disponíveis no JusTraduz.' : 'Confira os dados da assinatura e conclua o pagamento pelo ambiente seguro do Asaas. Assim que o webhook ou a verificação confirmar o pagamento, seus limites são ativados automaticamente no JusTraduz.' ?></p>

            <div class="payment-summary-grid">
              <div>
                <span>Plano</span>
                <strong><?= e($plan['name']) ?></strong>
              </div>
              <div>
                <span>Cobrança</span>
                <strong><?= e($cycleLabel) ?></strong>
              </div>
              <div>
                <span>Valor</span>
                <strong><?= e(payment_money($amountCents)) ?><?= e($periodLabel) ?></strong>
              </div>
              <div>
                <span>Status</span>
                <strong><?= $isPlanActive ? 'Ativo' : 'Pendente' ?></strong>
              </div>
            </div>

            <div class="payment-actions">
              <?php if ($isPlanActive): ?>
                <a class="btn btn-primary" href="<?= e(app_url('/frontend/perfil.php?tab=faturamento')) ?>">
                  <?= icon_svg('check') ?> Ver faturamento
                </a>
                <a class="btn btn-outline" href="<?= e(app_url('/frontend/dashboard-cliente.php')) ?>">
                  Ir para o painel
                </a>
              <?php else: ?>
                <a class="btn btn-primary" href="<?= e($payUrl) ?>" rel="noopener">
                  <?= icon_svg('lock') ?> Pagar com segurança
                </a>
                <form action="<?= e(app_url('/backend/public/index.php?rota=/billing/sync')) ?>" method="post">
                  <?= csrf_input() ?>
                  <button class="btn btn-outline" type="submit"><?= icon_svg('check') ?> Já paguei, verificar agora</button>
                </form>
                <a class="btn btn-outline" href="<?= e(app_url('/frontend/subir-plano.php')) ?>">
                  Voltar aos planos
                </a>
              <?php endif; ?>
            </div>
          </section>

          <aside class="payment-aside">
            <div class="payment-status-card">
              <span class="badge badge-success">Asaas sandbox</span>
              <h3><?= $isPlanActive ? 'Pagamento confirmado' : 'Cobrança criada' ?></h3>
              <p><?= $isPlanActive ? 'A assinatura foi ativada no JusTraduz.' : 'A assinatura já foi registrada no Asaas. O JusTraduz aguarda o webhook ou a verificação do pagamento para ativar o plano.' ?></p>
              <?php if ($dueLabel !== ''): ?>
                <div class="payment-due">
                  <span>Vencimento</span>
                  <strong><?= e($dueLabel) ?></strong>
                </div>
              <?php endif; ?>
            </div>

            <div class="payment-links">
              <h3>Opções da cobrança</h3>
              <?php if ($invoiceUrl !== '' && $invoiceUrl !== $payUrl): ?>
                <a href="<?= e($invoiceUrl) ?>" rel="noopener"><?= icon_svg('file') ?> Abrir fatura</a>
              <?php endif; ?>
              <?php if ($bankSlipUrl !== ''): ?>
                <a href="<?= e($bankSlipUrl) ?>" rel="noopener"><?= icon_svg('download') ?> Abrir boleto</a>
              <?php endif; ?>
              <?php if ($paymentLink !== '' && $paymentLink !== $payUrl): ?>
                <a href="<?= e($paymentLink) ?>" rel="noopener"><?= icon_svg('lock') ?> Link de pagamento</a>
              <?php endif; ?>
              <button type="button" data-copy-payment-link data-payment-link="<?= e($payUrl) ?>">
                <?= icon_svg('paperclip') ?> Copiar link
              </button>
            </div>
          </aside>
        </div>
      </section>
    </main>
  </div>

  <?php render_vlibras(); ?>
  <script src="assets/js/payment-page.js?v=payment-page-1"></script>
</body>
</html>
