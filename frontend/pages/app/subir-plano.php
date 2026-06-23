<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
require_once PROJECT_ROOT_PATH . '/backend/app/services/SubscriptionService.php';

$type = current_user_type();
if ($type !== 'cliente') {
    redirect(dashboard_url($type) . '?erro=' . urlencode('Planos são exclusivos para clientes.'));
}

$billing = new SubscriptionService($pdo);
$plans = $billing->plans();
$currentSubscription = $billing->currentForUser(current_user_id());
$errorMessage = trim((string) ($_GET['erro'] ?? ''));
$successMessage = trim((string) ($_GET['sucesso'] ?? ''));

function pricing_money(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function pricing_features(array $plan): array
{
    $features = json_decode((string) ($plan['features_json'] ?? ''), true);
    if (is_array($features) && $features) {
        return array_map('strval', $features);
    }

    $limits = json_decode((string) ($plan['limits_json'] ?? ''), true);
    if (!is_array($limits)) {
        return ['Limites configuráveis por plano'];
    }

    return [
        ((int) ($limits['document_upload'] ?? 0)) . ' documentos por período',
        ((int) ($limits['document_ai'] ?? 0)) . ' análises com IA',
        ((int) ($limits['ai_chat'] ?? 0)) . ' mensagens com IA jurídica',
        ((int) ($limits['ocr'] ?? 0)) . ' processamentos OCR',
    ];
}

function pricing_included_prefix(array $plan): string
{
    return match ((string) ($plan['slug'] ?? '')) {
        'pro' => 'Tudo do Essencial +',
        'escritorio' => 'Tudo do Pro +',
        default => '',
    };
}

function pricing_benefit(array $plan): string
{
    return match ((string) ($plan['slug'] ?? '')) {
        'gratuito', 'free' => 'Comece sem custo e suba de plano quando precisar de mais volume.',
        'essencial' => 'Entenda documentos jurídicos sem complicação.',
        'pro' => 'Automatize tarefas jurídicas e economize horas de trabalho.',
        'escritorio' => 'Centralize documentos e aumente a produtividade da equipe.',
        default => '',
    };
}

function pricing_free_plan_fallback(): array
{
    return [
        'plan_name' => 'Gratuito',
        'plan_slug' => 'gratuito',
        'limits_json' => json_encode([
            'document_upload' => 5,
            'document_ai' => 5,
            'ai_chat' => 50,
            'datajud_cnj' => 1,
            'ocr' => 5,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'features_json' => json_encode([
            '5 documentos por mês',
            '5 análises com IA',
            '50 mensagens com IA Jurídica',
            '1 consulta CNJ por mês',
            'OCR básico para até 5 arquivos',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

$currentCycle = ($currentSubscription && ($currentSubscription['billing_cycle'] ?? '') === 'yearly') ? 'yearly' : 'monthly';
$displayCurrentPlan = $currentSubscription ?: pricing_free_plan_fallback();
$hasFreeCurrentPlan = in_array((string) ($displayCurrentPlan['plan_slug'] ?? ''), ['gratuito', 'free'], true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Subir de plano | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=pricing-current-button-align-1">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'subir-plano.php'); ?>

    <main class="app-main">
      <?php render_topbar('Subir de plano', 'Escolha o plano ideal para usar o JusTraduz com mais volume e prioridade.', current_user_name()); ?>

      <section class="pricing-page">
        <?php if ($errorMessage !== ''): ?>
          <div class="alert is-visible alert-error"><?= e($errorMessage) ?></div>
        <?php endif; ?>
        <?php if ($successMessage !== ''): ?>
          <div class="alert is-visible alert-success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <div class="pricing-hero card">
          <span class="pricing-kicker"><?= icon_svg('sparkles') ?> Planos mensais e anuais</span>
          <h2>Planos que crescem junto com sua demanda jurídica.</h2>
          <p>Compare limites, escolha a cobrança mensal ou anual e veja qual nível combina com seu momento. O checkout cria a cobrança no Asaas e ativa seus limites automaticamente após a confirmação do pagamento.</p>
          <button
            class="pricing-cycle-toggle<?= $currentCycle === 'yearly' ? ' is-yearly' : '' ?>"
            type="button"
            data-pricing-cycle-toggle
            aria-label="Alternar entre cobrança mensal e anual"
            aria-pressed="<?= $currentCycle === 'yearly' ? 'true' : 'false' ?>"
          >
            <span class="pricing-cycle-option pricing-cycle-monthly">Mensal</span>
            <span class="pricing-cycle-thumb" aria-hidden="true"></span>
            <span class="pricing-cycle-option pricing-cycle-yearly">Anual <strong>-20%</strong></span>
          </button>
        </div>

        <?php if ($currentSubscription && !$hasFreeCurrentPlan): ?>
          <section class="pricing-current-alert card">
            <span><?= icon_svg('shield') ?></span>
            <div>
              <h2>Você já tem o plano <?= e((string) ($currentSubscription['plan_name'] ?? 'atual')) ?> ativo.</h2>
              <p>Ao confirmar o pagamento de outro plano, a assinatura atual será substituída automaticamente e você receberá a confirmação da troca.</p>
            </div>
          </section>
        <?php endif; ?>

        <div class="pricing-grid">
          <?php if ($hasFreeCurrentPlan): ?>
            <article
              class="pricing-card pricing-card-current pricing-card-free"
              tabindex="0"
              role="button"
              aria-labelledby="pricing-plan-title-free"
              data-pricing-card
              data-pricing-fixed
            >
              <span class="pricing-popular pricing-current-badge">Seu plano atual</span>

              <div class="pricing-card-head">
                <div>
                  <h3 id="pricing-plan-title-free"><?= e((string) ($displayCurrentPlan['plan_name'] ?? 'Gratuito')) ?></h3>
                  <p>Seu plano atual</p>
                </div>
              </div>

              <div class="pricing-price-row">
                <div>
                  <span class="pricing-price" data-monthly-price="R$ 0,00" data-yearly-price="R$ 0,00">R$ 0,00</span>
                  <span class="pricing-period" data-pricing-period>/mês</span>
                </div>
                <small data-pricing-note data-monthly-cents="0" data-yearly-cents="0">Sem cobrança mensal</small>
              </div>

              <p class="pricing-description">Use os recursos iniciais do JusTraduz sem custo e suba de plano quando precisar de mais volume.</p>

              <ul class="pricing-features">
                <?php foreach (pricing_features($displayCurrentPlan) as $feature): ?>
                  <li><?= icon_svg('check') ?> <?= e($feature) ?></li>
                <?php endforeach; ?>
              </ul>

              <?php $freeBenefit = pricing_benefit($displayCurrentPlan); ?>
              <?php if ($freeBenefit !== ''): ?>
                <div class="pricing-benefit">
                  <span>Benefício principal</span>
                  <strong><?= e($freeBenefit) ?></strong>
                </div>
              <?php endif; ?>

              <div class="auth-form pricing-current-action">
                <button class="btn btn-outline btn-block" type="button" disabled>Seu plano atual</button>
              </div>
            </article>
          <?php endif; ?>

          <?php foreach ($plans as $plan): ?>
            <?php
              $isCurrent = $currentSubscription && (int) ($currentSubscription['plan_id'] ?? 0) === (int) $plan['id'];
              $isHighlighted = ($plan['slug'] ?? '') === 'pro';
            ?>
            <article
              class="pricing-card<?= $isHighlighted ? ' pricing-card-featured' : '' ?>"
              tabindex="0"
              role="button"
              aria-labelledby="pricing-plan-title-<?= (int) $plan['id'] ?>"
              data-pricing-card
            >
              <?php if ($isHighlighted): ?>
                <span class="pricing-popular">Mais escolhido</span>
              <?php endif; ?>

              <div class="pricing-card-head">
                <div>
                  <h3 id="pricing-plan-title-<?= (int) $plan['id'] ?>"><?= e($plan['name']) ?></h3>
                  <p><?= $isCurrent ? 'Seu plano atual' : 'Troca disponível' ?></p>
                </div>
              </div>

              <div class="pricing-price-row">
                <div>
                  <span
                    class="pricing-price"
                    data-monthly-price="<?= e(pricing_money((int) $plan['monthly_price_cents'])) ?>"
                    data-yearly-price="<?= e(pricing_money((int) $plan['yearly_price_cents'])) ?>"
                  ><?= e(pricing_money((int) ($currentCycle === 'yearly' ? $plan['yearly_price_cents'] : $plan['monthly_price_cents']))) ?></span>
                  <span class="pricing-period" data-pricing-period><?= $currentCycle === 'yearly' ? '/ano' : '/mês' ?></span>
                </div>
                <small
                  data-pricing-note
                  data-monthly-cents="<?= (int) $plan['monthly_price_cents'] ?>"
                  data-yearly-cents="<?= (int) $plan['yearly_price_cents'] ?>"
                ><?= $currentCycle === 'yearly' ? 'Cobrança anual com desconto aplicado' : e(pricing_money((int) $plan['yearly_price_cents'])) . '/ano · desconto anual' ?></small>
              </div>

              <p class="pricing-description"><?= e($plan['description']) ?></p>

              <?php $includedPrefix = pricing_included_prefix($plan); ?>
              <?php if ($includedPrefix !== ''): ?>
                <p class="pricing-included-prefix"><?= e($includedPrefix) ?></p>
              <?php endif; ?>

              <ul class="pricing-features">
                <?php foreach (pricing_features($plan) as $feature): ?>
                  <li><?= icon_svg('check') ?> <?= e($feature) ?></li>
                <?php endforeach; ?>
              </ul>

              <?php $benefit = pricing_benefit($plan); ?>
              <?php if ($benefit !== ''): ?>
                <div class="pricing-benefit">
                  <span>Benefício principal</span>
                  <strong><?= e($benefit) ?></strong>
                </div>
              <?php endif; ?>

              <form class="auth-form" action="<?= e(app_url('/backend/public/index.php?rota=/billing/subscribe')) ?>" method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                <input type="hidden" name="billing_cycle" value="<?= e($currentCycle) ?>" data-billing-cycle-input>
                <button class="btn <?= $isHighlighted ? 'btn-primary' : 'btn-outline' ?> btn-block" type="submit">
                  <?= $isCurrent ? 'Renovar este plano' : 'Mudar para ' . e($plan['name']) ?>
                </button>
              </form>
            </article>
          <?php endforeach; ?>
        </div>

        <section class="pricing-note card">
          <div>
            <h2>Pagamento seguro</h2>
            <p class="text-muted">Ao assinar, você revisa o pedido na página de pagamento do JusTraduz e conclui a cobrança criada pelo Asaas.</p>
          </div>
          <span class="badge badge-success">Asaas integrado</span>
        </section>
      </section>
    </main>
  </div>

  <?php render_vlibras(); ?>
  <script src="assets/js/pricing.js?v=pricing-fixed-current-plan-1"></script>
</body>
</html>
