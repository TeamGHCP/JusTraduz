<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
require_once FRONTEND_APP_PATH . '/support/billing_documents.php';

$eventId = (int) ($_GET['id'] ?? 0);
$event = $eventId > 0 ? billing_document_event($pdo, $eventId, current_user_id()) : null;
if (!$event) {
    http_response_code(404);
    echo 'Fatura não encontrada.';
    exit;
}

[$statusLabel] = billing_status_label((string) ($event['status'] ?? 'pending'));
$payload = billing_payload($event);
$date = (string) (($event['created_at'] ?? '') ?: ($event['processed_at'] ?? ''));
$periodStart = (string) (($event['current_period_start'] ?? '') ?: ($payload['period_start'] ?? $date));
$periodEnd = (string) (($event['current_period_end'] ?? '') ?: ($payload['period_end'] ?? ''));
$planName = (string) (($event['plan_name'] ?? '') ?: 'Plano JusTraduz');
$method = billing_method_label($event);
$cycle = billing_cycle_label($event);
$amountCents = (int) ($event['amount_cents'] ?? 0);
$documentNumber = billing_document_number('JT-FAT', (int) $event['id']);
$providerPaymentId = (string) ($event['provider_event_id'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Fatura <?= e($documentNumber) ?> | JusTraduz</title>
  <style>
    :root {
      color: #111827;
      background: #eef2f7;
      font-family: Arial, Helvetica, sans-serif;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      background: #eef2f7;
      color: #111827;
    }

    .doc-actions {
      position: sticky;
      top: 0;
      z-index: 2;
      display: flex;
      justify-content: center;
      gap: 10px;
      padding: 14px;
      background: rgba(238, 242, 247, .92);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid #d6dee9;
    }

    .doc-actions a,
    .doc-actions button {
      min-height: 40px;
      padding: 0 16px;
      border: 1px solid #111827;
      border-radius: 8px;
      background: #111827;
      color: #fff;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
    }

    .doc-actions a {
      display: inline-flex;
      align-items: center;
      background: #fff;
      color: #111827;
    }

    .document {
      width: min(920px, calc(100% - 32px));
      min-height: 1180px;
      margin: 24px auto;
      padding: 56px;
      background: #fff;
      border: 1px solid #d1d5db;
      box-shadow: 0 18px 60px rgba(15, 23, 42, .12);
    }

    .doc-head,
    .doc-two-col,
    .doc-row,
    .doc-total-row {
      display: flex;
      justify-content: space-between;
      gap: 30px;
    }

    .doc-head {
      align-items: flex-start;
      margin-bottom: 48px;
    }

    h1 {
      margin: 0;
      font-size: 30px;
    }

    h2 {
      margin: 0 0 12px;
      font-size: 16px;
    }

    .brand {
      font-size: 34px;
      font-weight: 900;
      letter-spacing: 0;
    }

    .doc-meta,
    .doc-party,
    .doc-summary {
      line-height: 1.55;
    }

    .doc-meta strong,
    .doc-party strong {
      display: inline-block;
      min-width: 128px;
    }

    .doc-two-col {
      margin-bottom: 54px;
    }

    .doc-party {
      width: 48%;
    }

    .doc-summary {
      margin: 0 0 34px;
      font-size: 22px;
      font-weight: 800;
    }

    .doc-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 28px;
    }

    .doc-table th {
      border-bottom: 1px solid #111827;
      padding: 10px 0;
      font-size: 12px;
      text-align: left;
    }

    .doc-table td {
      padding: 14px 0;
      vertical-align: top;
    }

    .doc-table th:last-child,
    .doc-table td:last-child {
      text-align: right;
    }

    .doc-totals {
      width: min(420px, 100%);
      margin-left: auto;
    }

    .doc-total-row {
      padding: 8px 0;
      border-bottom: 1px solid #e5e7eb;
    }

    .doc-total-row:last-child {
      border-bottom: 0;
      font-weight: 900;
    }

    .doc-foot {
      margin-top: 72px;
      color: #4b5563;
      font-size: 12px;
      line-height: 1.6;
    }

    @media (max-width: 720px) {
      .document {
        width: 100%;
        min-height: auto;
        margin: 0;
        padding: 28px 18px;
        border: 0;
      }

      .doc-head,
      .doc-two-col {
        flex-direction: column;
      }

      .doc-party {
        width: 100%;
      }
    }

    @media print {
      body {
        background: #fff;
      }

      .doc-actions {
        display: none;
      }

      .document {
        width: 100%;
        min-height: 0;
        margin: 0;
        padding: 34px;
        border: 0;
        box-shadow: none;
      }
    }
  </style>
</head>
<body>
  <div class="doc-actions">
    <a href="<?= e(app_url('/frontend/perfil.php?tab=faturamento')) ?>">Voltar</a>
    <button type="button" onclick="window.print()">Imprimir / salvar PDF</button>
  </div>

  <main class="document">
    <header class="doc-head">
      <div>
        <h1>Fatura</h1>
        <p class="doc-meta">
          <strong>Número</strong> <?= e($documentNumber) ?><br>
          <strong>Emissão</strong> <?= e(billing_date($date)) ?><br>
          <strong>Status</strong> <?= e($statusLabel) ?>
        </p>
      </div>
      <div class="brand">JusTraduz</div>
    </header>

    <section class="doc-two-col">
      <div class="doc-party">
        <h2>Emitido por</h2>
        <p>JusTraduz<br>Plataforma jurídica digital<br>Brasil</p>
      </div>
      <div class="doc-party">
        <h2>Cliente</h2>
        <p>
          <?= e((string) ($event['user_name'] ?? current_user_name())) ?><br>
          <?= e((string) ($event['user_email'] ?? '')) ?><br>
          <?php if (!empty($event['user_cpf'])): ?>CPF <?= e((string) $event['user_cpf']) ?><br><?php endif; ?>
          <?php if (!empty($event['user_phone'])): ?><?= e((string) $event['user_phone']) ?><?php endif; ?>
        </p>
      </div>
    </section>

    <p class="doc-summary"><?= e(billing_money($amountCents)) ?> <?= $statusLabel === 'Pago' ? 'pago' : 'a vencer' ?> em <?= e(billing_date($date)) ?></p>

    <table class="doc-table">
      <thead>
        <tr>
          <th>Descrição</th>
          <th>Método</th>
          <th>Valor</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <?= e($planName) ?> - <?= e($cycle) ?><br>
            <small><?= e(billing_date($periodStart)) ?><?= $periodEnd !== '' ? ' - ' . e(billing_date($periodEnd)) : '' ?></small>
          </td>
          <td><?= e($method) ?></td>
          <td><?= e(billing_money($amountCents)) ?></td>
        </tr>
      </tbody>
    </table>

    <section class="doc-totals">
      <div class="doc-total-row"><span>Subtotal</span><span><?= e(billing_money($amountCents)) ?></span></div>
      <div class="doc-total-row"><span>Total</span><span><?= e(billing_money($amountCents)) ?></span></div>
      <div class="doc-total-row"><span>Valor devido</span><span><?= $statusLabel === 'Pago' ? 'R$ 0,00' : e(billing_money($amountCents)) ?></span></div>
    </section>

    <footer class="doc-foot">
      <?php if ($providerPaymentId !== ''): ?>Pagamento Asaas: <?= e($providerPaymentId) ?><br><?php endif; ?>
      Documento interno gerado a partir dos eventos de cobrança registrados no JusTraduz.
    </footer>
  </main>
</body>
</html>
