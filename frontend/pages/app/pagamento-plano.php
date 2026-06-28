<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
require_once PROJECT_ROOT_PATH . '/backend/app/services/SubscriptionService.php';
require_once PROJECT_ROOT_PATH . '/backend/app/services/OrganizationInviteService.php';

function payment_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

$type = current_user_type();
if (!in_array($type, ['cliente', 'advogado'], true)) {
    payment_redirect(dashboard_url($type) . '?erro=' . urlencode('Planos estão disponíveis para clientes e advogados verificados.'));
}

$billing = new SubscriptionService($pdo);
$currentSubscription = $billing->currentForUser(current_user_id());
$hasConfirmedPaymentPage = is_array($_SESSION['payment_confirmed'] ?? null);
$hasActiveAsaasSubscription = $currentSubscription
    && in_array((string) ($currentSubscription['status'] ?? ''), ['active', 'trialing'], true)
    && (string) ($currentSubscription['provider'] ?? '') === 'asaas'
    && trim((string) ($currentSubscription['provider_subscription_id'] ?? '')) !== '';

$checkout = is_array($_SESSION['billing_checkout'] ?? null) ? $_SESSION['billing_checkout'] : [];
$checkoutAge = time() - (int) ($checkout['created_at'] ?? 0);
if (($checkout['provider'] ?? '') !== 'asaas' || $checkoutAge > 3600) {
    if ($hasConfirmedPaymentPage || $hasActiveAsaasSubscription) {
        payment_redirect(app_url('/frontend/pagamento-confirmado.php'));
    }

    payment_redirect(app_url('/frontend/perfil.php?tab=faturamento'));
}

$planId = (int) ($checkout['plan_id'] ?? 0);
$billingCycle = (string) ($checkout['billing_cycle'] ?? 'monthly');
$metadata = is_array($checkout['metadata'] ?? null) ? $checkout['metadata'] : [];
$payUrl = trim((string) ($checkout['redirect_url'] ?? $metadata['checkout_url'] ?? ''));
$invoiceUrl = trim((string) ($metadata['invoice_url'] ?? ''));
$paymentLink = trim((string) ($metadata['payment_link'] ?? ''));
$dueDate = trim((string) ($metadata['due_date'] ?? ''));
$providerSubscriptionId = trim((string) ($metadata['provider_subscription_id'] ?? ''));
$pixQrCode = is_array($metadata['pix_qr_code'] ?? null) ? $metadata['pix_qr_code'] : [];
$pixImage = trim((string) ($pixQrCode['encoded_image'] ?? ''));
$pixPayload = trim((string) ($pixQrCode['payload'] ?? ''));
$pixExpiration = trim((string) ($pixQrCode['expiration_date'] ?? ''));
$createdPaymentMethod = (string) ($metadata['payment_method'] ?? $checkout['payment_method'] ?? '');
$isCheckoutCreated = $providerSubscriptionId !== '' && $payUrl !== '';

$checkoutUser = fetch_one($pdo, 'SELECT nome, email, cpf, telefone FROM users WHERE id = ? LIMIT 1', [current_user_id()]) ?: [];
$isPlanActive = $currentSubscription
    && $isCheckoutCreated
    && (int) ($currentSubscription['plan_id'] ?? 0) === $planId
    && (string) ($currentSubscription['billing_cycle'] ?? 'monthly') === $billingCycle
    && (string) ($currentSubscription['provider'] ?? '') === 'asaas'
    && trim((string) ($currentSubscription['provider_subscription_id'] ?? '')) === $providerSubscriptionId;
$isPlanChange = $currentSubscription
    && !$isPlanActive
    && in_array((string) ($currentSubscription['status'] ?? ''), ['active', 'trialing', 'past_due'], true);
$plans = $billing->plans($type);
$plan = null;
foreach ($plans as $candidate) {
    if ((int) ($candidate['id'] ?? 0) === $planId) {
        $plan = $candidate;
        break;
    }
}

if (!$plan) {
    payment_redirect(app_url('/frontend/subir-plano.php?erro=' . urlencode('Não foi possível recuperar a cobrança. Tente novamente.')));
}

$amountCents = (int) ($metadata['amount_cents'] ?? ($billingCycle === 'yearly' ? $plan['yearly_price_cents'] : $plan['monthly_price_cents']));
if ($isPlanActive) {
    $_SESSION['payment_confirmed'] = [
        'confirmed_at' => time(),
        'plan_id' => (int) ($currentSubscription['plan_id'] ?? $planId),
        'plan_name' => (string) ($currentSubscription['plan_name'] ?? $plan['name'] ?? 'Plano ativo'),
        'billing_cycle' => (string) ($currentSubscription['billing_cycle'] ?? $billingCycle),
        'amount_cents' => $amountCents,
        'subscription_id' => (int) ($currentSubscription['id'] ?? 0),
        'provider' => (string) ($currentSubscription['provider'] ?? 'asaas'),
        'provider_subscription_id' => (string) ($currentSubscription['provider_subscription_id'] ?? $providerSubscriptionId),
        'provider_payment_id' => (string) ($metadata['provider_payment_id'] ?? ''),
        'team_invites_sent' => (array) ($metadata['team_invites_sent'] ?? []),
    ];
    unset($_SESSION['billing_checkout']);
    payment_redirect(app_url('/frontend/pagamento-confirmado.php'));
}

$cycleLabel = $billingCycle === 'yearly' ? 'Anual' : 'Mensal';
$periodLabel = $billingCycle === 'yearly' ? '/ano' : '/mês';
$monthlyPlanCents = (int) ($plan['monthly_price_cents'] ?? 0);
$yearlyPlanCents = (int) ($plan['yearly_price_cents'] ?? 0);
$yearlyFullPriceCents = $monthlyPlanCents * 12;
$yearlyDiscountCents = $billingCycle === 'yearly' ? max(0, $yearlyFullPriceCents - $yearlyPlanCents) : 0;
$dueLabel = '';
if ($dueDate !== '') {
    $timestamp = strtotime($dueDate);
    $dueLabel = $timestamp ? date('d/m/Y', $timestamp) : $dueDate;
}

function payment_money(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function payment_mask_document(string $document): string
{
    $digits = preg_replace('/\D+/', '', $document) ?: '';
    if (strlen($digits) !== 11) {
        return $document !== '' ? $document : 'Não informado';
    }

    return substr($digits, 0, 3) . '.***.***-' . substr($digits, -2);
}

function payment_display(string $value): string
{
    $value = trim($value);
    return $value !== '' ? $value : 'Não informado';
}

function payment_asaas_environment_label(): string
{
    $env = function_exists('database_env_values')
        ? database_env_values(PROJECT_ROOT_PATH . '/backend/.env')
        : [];
    $apiUrl = (string) (getenv('ASAAS_API_URL') ?: ($env['ASAAS_API_URL'] ?? ''));

    return str_contains($apiUrl, 'api-sandbox.asaas.com') ? 'Asaas sandbox' : 'Asaas';
}

$cardPayUrl = $invoiceUrl !== '' ? $invoiceUrl : $payUrl;
$expirationLabel = '';
if ($pixExpiration !== '') {
    $expirationTimestamp = strtotime($pixExpiration);
    $expirationLabel = $expirationTimestamp ? date('d/m/Y H:i', $expirationTimestamp) : $pixExpiration;
}
$checkoutAction = app_url('/backend/public/index.php?rota=/billing/checkout');
$cancelCheckoutAction = app_url('/backend/public/index.php?rota=/billing/checkout/cancel');
$holderName = payment_display((string) ($checkoutUser['nome'] ?? current_user_name()));
$holderEmail = payment_display((string) ($checkoutUser['email'] ?? ''));
$holderCpf = preg_replace('/\D+/', '', (string) ($checkoutUser['cpf'] ?? '')) ?: '';
$holderPhone = preg_replace('/\D+/', '', (string) ($checkoutUser['telefone'] ?? '')) ?: '';
$currentPlanName = (string) ($currentSubscription['plan_name'] ?? '');
$asaasEnvironmentLabel = payment_asaas_environment_label();
$isOfficePlan = (string) ($plan['slug'] ?? '') === 'escritorio';
$officeInviteMin = 0;
$officeInviteLimit = OrganizationInviteService::OFFICE_INVITE_LIMIT;
$officeInviteEmails = array_values(array_filter(array_map('strval', (array) ($checkout['team_invites'] ?? $metadata['team_invites'] ?? []))));
$officeInviteCount = min($officeInviteLimit, max($officeInviteMin, count($officeInviteEmails)));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pagamento do plano | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=global-responsive-20260628">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'subir-plano.php'); ?>

    <main class="app-main">
      <?php render_topbar('Pagamento do plano', 'Finalize sua assinatura com a cobrança criada pelo Asaas.', current_user_name()); ?>

      <section class="payment-page">
        <?php if ($isPlanChange): ?>
          <div class="payment-alert payment-alert-info">
            <?= icon_svg('shield') ?>
            <span>Ao confirmar este pagamento, o plano <?= e($currentPlanName !== '' ? $currentPlanName : 'atual') ?> será substituído pelo plano <?= e((string) $plan['name']) ?>.</span>
          </div>
        <?php endif; ?>

        <div class="payment-panel">
          <section class="payment-customer-card">
            <div class="payment-card-header">
              <span class="payment-step">1</span>
              <div>
                <span class="pricing-kicker"><?= icon_svg('user') ?> <?= $type === 'advogado' ? 'Dados profissionais' : 'Dados do cliente' ?></span>
                <h2>Finalizar assinatura</h2>
              </div>
            </div>

            <dl class="payment-customer-list">
              <div>
                <dt>Nome</dt>
                <dd><?= e(payment_display((string) ($checkoutUser['nome'] ?? current_user_name()))) ?></dd>
              </div>
              <div>
                <dt>E-mail</dt>
                <dd><?= e(payment_display((string) ($checkoutUser['email'] ?? ''))) ?></dd>
              </div>
              <div>
                <dt>CPF</dt>
                <dd><?= e(payment_mask_document((string) ($checkoutUser['cpf'] ?? ''))) ?></dd>
              </div>
              <div>
                <dt>Telefone</dt>
                <dd><?= e(payment_display((string) ($checkoutUser['telefone'] ?? ''))) ?></dd>
              </div>
            </dl>

            <a class="payment-back-link" href="<?= e(app_url('/frontend/subir-plano.php')) ?>">
              Voltar aos planos
            </a>
          </section>

          <section class="payment-order-card">
            <div class="payment-card-header">
              <span class="payment-step">2</span>
              <div>
                <span class="pricing-kicker"><?= icon_svg('file') ?> Plano digital</span>
                <h2>Resumo</h2>
              </div>
            </div>

            <div class="payment-order-item">
              <div>
                <strong>1x Plano <?= e($plan['name']) ?></strong>
                <span><?= e($cycleLabel) ?> · acesso digital JusTraduz</span>
              </div>
              <b><?= e(payment_money($amountCents)) ?></b>
            </div>

            <div class="payment-order-lines">
              <div><span>Subtotal</span><strong><?= e(payment_money($amountCents)) ?></strong></div>
              <?php if ($billingCycle === 'yearly'): ?>
                <div><span>Valor mensal equivalente</span><strong><?= e(payment_money((int) round($amountCents / 12))) ?>/mês</strong></div>
                <?php if ($yearlyDiscountCents > 0): ?>
                  <div><span>Preço em 12 mensalidades</span><strong><?= e(payment_money($yearlyFullPriceCents)) ?></strong></div>
                  <div><span>Desconto anual aplicado</span><strong>-<?= e(payment_money($yearlyDiscountCents)) ?></strong></div>
                <?php endif; ?>
              <?php endif; ?>
              <div><span>Entrega</span><strong>Digital</strong></div>
              <?php if ($isPlanChange): ?>
                <div><span>Plano atual</span><strong><?= e($currentPlanName !== '' ? $currentPlanName : 'Atual') ?></strong></div>
                <div><span>Após confirmação</span><strong>Substituir pelo plano <?= e((string) $plan['name']) ?></strong></div>
              <?php endif; ?>
              <?php if ($dueLabel !== ''): ?>
                <div><span>Vencimento</span><strong><?= e($dueLabel) ?></strong></div>
              <?php endif; ?>
            </div>

            <div class="payment-total">
              <span>Total</span>
              <strong><?= e(payment_money($amountCents)) ?><small><?= e($periodLabel) ?></small></strong>
            </div>

            <div class="payment-status-card">
              <span class="badge badge-success"><?= e($asaasEnvironmentLabel) ?></span>
              <h3><?= $isPlanActive ? 'Pagamento confirmado' : 'Pagamento pendente' ?></h3>
              <p><?= $isPlanActive ? 'A assinatura foi ativada no JusTraduz.' : ($isCheckoutCreated ? 'A cobrança existe no Asaas. Depois de pagar, use a verificação abaixo caso o webhook ainda não tenha atualizado automaticamente.' : 'A cobrança ainda não foi registrada no Asaas. Confirme os dados para gerar as opções de pagamento.') ?></p>
            </div>
          </section>

          <aside class="payment-methods-card">
            <div class="payment-card-header">
              <span class="payment-step">3</span>
              <div>
                <span class="pricing-kicker"><?= icon_svg($isPlanActive ? 'check' : 'shield') ?> <?= $isPlanActive ? 'Plano ativo' : 'Formas aceitas' ?></span>
                <h2>Pagamento</h2>
              </div>
            </div>

            <?php if ($isPlanActive): ?>
              <div class="payment-complete">
                <?= icon_svg('check') ?>
                <strong>Seu plano <?= e($plan['name']) ?> está ativo.</strong>
                <span>Os limites já estão disponíveis no JusTraduz.</span>
              </div>
              <a class="btn btn-primary" href="<?= e(app_url('/frontend/perfil.php?tab=faturamento')) ?>">
                <?= icon_svg('check') ?> Ver faturamento
              </a>
            <?php else: ?>
              <?php if ($isOfficePlan && !$isCheckoutCreated): ?>
                <section class="office-invite-card" data-office-invite-panel data-office-invite-min="<?= (int) $officeInviteMin ?>" data-office-invite-limit="<?= (int) $officeInviteLimit ?>">
                  <div class="office-invite-head">
                    <span><?= icon_svg('users') ?></span>
                    <div>
                      <strong>Participantes do escritório</strong>
                      <small>Escolha quantas pessoas deseja chamar. Os e-mails serão enviados depois da confirmação do pagamento.</small>
                    </div>
                  </div>

                  <div class="office-invite-stepper">
                    <div>
                      <span>Vagas</span>
                      <strong data-office-invite-count><?= (int) $officeInviteCount ?></strong>
                    </div>
                    <div class="office-invite-stepper-actions" aria-label="Quantidade de convites">
                      <button type="button" data-office-invite-decrease aria-label="Diminuir vagas">−</button>
                      <button type="button" data-office-invite-increase aria-label="Aumentar vagas">+</button>
                    </div>
                    <input type="hidden" data-office-invite-count-input value="<?= (int) $officeInviteCount ?>">
                  </div>

                  <div class="office-invite-list" data-office-invite-list>
                    <?php for ($inviteIndex = 0; $inviteIndex < $officeInviteLimit; $inviteIndex++): ?>
                      <label class="office-invite-field" data-office-invite-field data-office-invite-index="<?= (int) $inviteIndex ?>">
                        <span>Convite <?= $inviteIndex + 1 ?></span>
                        <input type="email" data-office-invite-input value="<?= e($officeInviteEmails[$inviteIndex] ?? '') ?>" placeholder="email<?= $inviteIndex + 1 ?>@exemplo.com">
                      </label>
                    <?php endfor; ?>
                  </div>
                </section>
              <?php elseif ($isOfficePlan && $isCheckoutCreated && $officeInviteEmails): ?>
                <section class="office-invite-card">
                  <div class="office-invite-head">
                    <span><?= icon_svg('users') ?></span>
                    <div>
                      <strong>Convites preparados</strong>
                      <small>Serão enviados após a confirmação do pagamento.</small>
                    </div>
                  </div>
                  <ul class="office-invite-summary">
                    <?php foreach ($officeInviteEmails as $email): ?>
                      <li><?= e($email) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </section>
              <?php endif; ?>

              <div class="payment-method-list">
                <section class="payment-method payment-method-pix <?= (!$isCheckoutCreated || $createdPaymentMethod === 'pix') ? 'is-open' : '' ?>" data-payment-method="pix">
                  <button type="button" class="payment-method-title" data-payment-method-toggle>
                    <?= icon_svg('sparkles') ?>
                    <div>
                      <strong>PIX</strong>
                      <span><?= $isCheckoutCreated && $createdPaymentMethod === 'pix' ? 'QRCode e copia e cola gerados pela Asaas.' : 'Gere o QR Code Pix sem sair do JusTraduz.' ?></span>
                    </div>
                  </button>

                  <div class="payment-method-body">
                    <?php if ($isCheckoutCreated && $createdPaymentMethod === 'pix' && ($pixImage !== '' || $pixPayload !== '')): ?>
                      <div class="payment-pix-box">
                        <?php if ($pixImage !== ''): ?>
                          <img src="data:image/png;base64,<?= e($pixImage) ?>" alt="QRCode Pix do pagamento">
                        <?php endif; ?>
                        <?php if ($pixPayload !== ''): ?>
                          <button type="button" class="payment-copy-button" data-copy-value="<?= e($pixPayload) ?>" data-copy-label="PIX copiado">
                            <?= icon_svg('paperclip') ?> Copiar PIX
                          </button>
                        <?php endif; ?>
                        <?php if ($expirationLabel !== ''): ?>
                          <small>Expira em <?= e($expirationLabel) ?></small>
                        <?php endif; ?>
                      </div>
                    <?php elseif ($isCheckoutCreated): ?>
                      <p class="payment-method-note">Já existe uma cobrança gerada. Cancele o pagamento atual para escolher PIX.</p>
                    <?php else: ?>
                      <form action="<?= e($checkoutAction) ?>" method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="payment_method" value="pix">
                        <div data-office-invite-hidden></div>
                        <button class="payment-method-action" type="submit"><?= icon_svg('sparkles') ?> Gerar QR Code Pix</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </section>

                <section class="payment-method <?= ($createdPaymentMethod === 'credit_card') ? 'is-open' : '' ?>" data-payment-method="credit-card">
                  <button type="button" class="payment-method-title" data-payment-method-toggle>
                    <?= icon_svg('lock') ?>
                    <div>
                      <strong>Cartão de Crédito / Débito</strong>
                      <span>Digite os dados aqui; a validação é feita pela Asaas.</span>
                    </div>
                    <b class="payment-card-brand" data-card-brand>
                      <img src="assets/img/payment-flags/card-placeholder.png" alt="Bandeira do cartão">
                    </b>
                  </button>

                  <div class="payment-method-body">
                    <?php if ($isCheckoutCreated && $createdPaymentMethod === 'credit_card'): ?>
                      <div class="payment-complete payment-ready">
                        <?= icon_svg('check') ?>
                        <strong>Cartão enviado para validação.</strong>
                        <span>Aguarde o webhook ou use a verificação abaixo.</span>
                      </div>
                    <?php elseif ($isCheckoutCreated): ?>
                      <p class="payment-method-note">Já existe uma cobrança gerada. Cancele o pagamento atual para escolher cartão.</p>
                    <?php else: ?>
                      <form class="payment-card-form" action="<?= e($checkoutAction) ?>" method="post" autocomplete="off">
                        <?= csrf_input() ?>
                        <input type="hidden" name="payment_method" value="credit_card">
                        <div data-office-invite-hidden></div>
                        <div class="payment-form-field payment-form-field-full">
                          <label for="card_holder_name">Nome impresso no cartão</label>
                          <input id="card_holder_name" name="card_holder_name" type="text" value="<?= e($holderName) ?>" required autocomplete="cc-name">
                        </div>
                        <div class="payment-form-field payment-form-field-full">
                          <label for="card_number">Número do cartão</label>
                          <div class="payment-card-number-wrap">
                            <input id="card_number" name="card_number" type="text" inputmode="numeric" data-card-number placeholder="0000 0000 0000 0000" required autocomplete="cc-number">
                            <span class="payment-card-brand-inline" data-card-brand-inline>
                              <img src="assets/img/payment-flags/card-placeholder.png" alt="Bandeira do cartão">
                            </span>
                          </div>
                        </div>
                        <div class="payment-form-field">
                          <label for="card_expiry_month">Mês</label>
                          <input id="card_expiry_month" name="card_expiry_month" type="text" inputmode="numeric" maxlength="2" placeholder="MM" required autocomplete="cc-exp-month">
                        </div>
                        <div class="payment-form-field">
                          <label for="card_expiry_year">Ano</label>
                          <input id="card_expiry_year" name="card_expiry_year" type="text" inputmode="numeric" maxlength="4" placeholder="AAAA" required autocomplete="cc-exp-year">
                        </div>
                        <div class="payment-form-field">
                          <label for="card_ccv">CVV</label>
                          <input id="card_ccv" name="card_ccv" type="password" inputmode="numeric" maxlength="4" required autocomplete="cc-csc">
                        </div>
                        <div class="payment-form-field payment-form-field-full">
                          <label for="holder_email">E-mail do titular</label>
                          <input id="holder_email" name="holder_email" type="email" value="<?= e($holderEmail) ?>" required autocomplete="email">
                        </div>
                        <div class="payment-form-field">
                          <label for="holder_cpf_cnpj">CPF/CNPJ</label>
                          <input id="holder_cpf_cnpj" name="holder_cpf_cnpj" type="text" inputmode="numeric" maxlength="14" value="<?= e($holderCpf) ?>" required>
                        </div>
                        <div class="payment-form-field">
                          <label for="holder_phone">Telefone</label>
                          <input id="holder_phone" name="holder_phone" type="text" inputmode="tel" maxlength="11" value="<?= e($holderPhone) ?>" required autocomplete="tel">
                        </div>
                        <div class="payment-form-field">
                          <label for="holder_postal_code">CEP</label>
                          <input id="holder_postal_code" name="holder_postal_code" type="text" inputmode="numeric" maxlength="8" required autocomplete="postal-code">
                        </div>
                        <div class="payment-form-field">
                          <label for="holder_address_number">Número</label>
                          <input id="holder_address_number" name="holder_address_number" type="text" required>
                        </div>
                        <div class="payment-form-field payment-form-field-full">
                          <label for="holder_address_complement">Complemento</label>
                          <input id="holder_address_complement" name="holder_address_complement" type="text">
                        </div>
                        <input type="hidden" name="holder_name" value="<?= e($holderName) ?>">
                        <button class="payment-method-action payment-card-submit" type="submit"><?= icon_svg('lock') ?> Validar cartão e assinar</button>
                        <div class="payment-card-flags payment-form-field-full" aria-label="Bandeiras de cartão aceitas">
                          <img src="assets/img/payment-flags/mastercard.svg" alt="Mastercard">
                          <img src="assets/img/payment-flags/visa.svg" alt="Visa">
                          <img src="assets/img/payment-flags/elo.svg" alt="Elo">
                          <img src="assets/img/payment-flags/amex.svg" alt="American Express">
                          <img src="assets/img/payment-flags/hipercard.svg" alt="Hipercard">
                        </div>
                      </form>
                    <?php endif; ?>
                  </div>
                </section>

              </div>

              <div class="payment-actions">
                <?php if ($isCheckoutCreated): ?>
                  <form action="<?= e(app_url('/backend/public/index.php?rota=/billing/sync')) ?>" method="post">
                    <?= csrf_input() ?>
                    <button class="btn btn-primary" type="submit"><?= icon_svg('check') ?> Já realizei o pagamento</button>
                  </form>
                <?php endif; ?>
                <form action="<?= e($cancelCheckoutAction) ?>" method="post">
                  <?= csrf_input() ?>
                  <button class="btn btn-outline" type="submit"><?= icon_svg('x') ?> Cancelar pagamento</button>
                </form>
              </div>

            <?php endif; ?>
          </aside>
        </div>
      </section>
    </main>
  </div>

  <?php render_vlibras(); ?>
  <script src="assets/js/payment-page.js?v=payment-page-1"></script>
</body>
</html>
