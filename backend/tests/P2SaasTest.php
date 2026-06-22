<?php

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/services/OrganizationService.php';
require_once dirname(__DIR__) . '/app/services/RbacService.php';
require_once dirname(__DIR__) . '/app/services/SlaService.php';
require_once dirname(__DIR__) . '/app/services/SubscriptionService.php';
require_once dirname(__DIR__) . '/app/services/UsageLimiter.php';
require_once dirname(__DIR__) . '/app/services/payments/AsaasPaymentProvider.php';
require_once dirname(__DIR__) . '/app/services/payments/ManualPaymentProvider.php';

$pdo = test_pdo();
build_test_schema($pdo);
seed_test_data($pdo);

$subscriptions = new SubscriptionService($pdo);
$current = $subscriptions->ensureDefaultSubscription(1);
assertTrue($current !== null, 'Assinatura padrao deve ser criada.');
assertEquals('essencial', $current['plan_slug'], 'Plano padrao deve ser Essencial.');
assertTrue($subscriptions->ensureDefaultSubscription(3) === null, 'Advogado nao deve receber assinatura.');
assertTrue($subscriptions->changePlan(3, 2, 'monthly', 'active') === false, 'Advogado nao deve trocar plano.');

$usage = new UsageLimiter($pdo);
$quota = $usage->allow(1, 'document_upload', 31);
assertTrue($quota['allowed'] === false, 'Limite do plano Essencial deve bloquear mais de 30 uploads mensais.');
assertStringContains('limite mensal', $usage->limitMessage('document_upload', $quota), 'Mensagem de quota deve falar em limite mensal.');
assertStringContains('30', $usage->limitMessage('document_upload', $quota), 'Mensagem de quota deve informar o limite do plano.');

assertTrue($subscriptions->changePlan(1, 2, 'monthly', 'active') === true, 'Troca para Pro deve funcionar.');
$quota = $usage->allow(1, 'document_upload', 501);
assertTrue($quota['allowed'] === false, 'Plano Pro deve bloquear mais de 500 uploads mensais.');
$quota = $usage->allow(1, 'document_upload', 500);
assertTrue($quota['allowed'] === true, 'Plano Pro deve permitir ate 500 uploads mensais.');
assertEquals(250, $subscriptions->featureLimit(1, 'draft_generation'), 'Plano Pro deve limitar geracao de minutas.');

assertTrue($subscriptions->changePlan(1, 3, 'monthly', 'active') === true, 'Troca para Escritorio deve funcionar.');
assertTrue($usage->allow(1, 'document_upload', 5000)['allowed'] === true, 'Plano Escritorio deve permitir documentos ilimitados.');
assertTrue($usage->allow(1, 'ocr', 5000)['allowed'] === true, 'Plano Escritorio deve permitir OCR ilimitado.');
assertEquals(1000, $subscriptions->featureLimit(1, 'datajud_cnj'), 'Plano Escritorio deve limitar CNJ para controlar custo externo.');
assertEquals(10, $subscriptions->featureLimit(1, 'seats'), 'Plano Escritorio deve liberar 10 usuarios adicionais.');
assertEquals(51200, $subscriptions->featureLimit(1, 'storage_mb'), 'Plano Escritorio deve liberar 50GB de armazenamento.');
assertTrue($subscriptions->changePlan(1, 2, 'monthly', 'active') === true, 'Retorno para Pro deve funcionar.');

$payments = new ManualPaymentProvider($pdo, $subscriptions);
putenv('MAIL_LOG_ONLY=true');
$checkout = $payments->createCheckout(1, 2, 'yearly');
assertTrue($checkout->ok === true, 'Checkout manual deve concluir com sucesso.');
assertTrue($checkout->subscriptionId !== null, 'Checkout manual deve retornar assinatura.');
assertStringContains('/frontend/subir-plano.php', $checkout->redirectUrl, 'Checkout manual deve redirecionar para planos.');
assertEquals(47900, (int) $pdo->query('SELECT COALESCE(SUM(amount_cents), 0) FROM payment_events WHERE status = "paid"')->fetchColumn(), 'Checkout manual deve registrar pagamento anual com desconto.');
$latestNotification = (string) $pdo->query('SELECT mensagem FROM notifications WHERE user_id = 1 ORDER BY id DESC LIMIT 1')->fetchColumn();
assertStringContains('Pagamento confirmado', $latestNotification, 'Checkout pago deve notificar o cliente.');
assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM mail_logs WHERE recipient = 'cliente1@teste.local' AND subject LIKE 'Plano Pro confirmado%' AND status = 'sent'")->fetchColumn(), 'Checkout pago deve enviar e-mail ao cliente.');

$webhook = $payments->handleWebhook(json_encode([
    'subscription_id' => $checkout->subscriptionId,
    'event_type' => 'invoice.payment_failed',
    'status' => 'failed',
    'amount_cents' => 48000,
]), []);
assertEquals('failed', $webhook['status'], 'Webhook deve normalizar status de pagamento.');
$current = $subscriptions->currentForUser(1);
assertEquals('past_due', $current['status'], 'Webhook failed deve marcar assinatura como inadimplente.');

$periodEndBeforeRenewal = (string) $current['current_period_end'];
$webhook = $payments->handleWebhook(json_encode([
    'subscription_id' => $checkout->subscriptionId,
    'user_id' => 1,
    'event_type' => 'invoice.payment_succeeded',
    'status' => 'paid',
    'amount_cents' => 47900,
    'provider_event_id' => 'manual-renewal-001',
]), []);
assertEquals('paid', $webhook['status'], 'Webhook paid deve normalizar status de pagamento.');
$current = $subscriptions->currentForUser(1);
assertTrue(new DateTimeImmutable((string) $current['current_period_end']) > new DateTimeImmutable($periodEndBeforeRenewal), 'Pagamento recorrente deve renovar o periodo do plano.');

$periodEndAfterRenewal = (string) $current['current_period_end'];
$payments->handleWebhook(json_encode([
    'subscription_id' => $checkout->subscriptionId,
    'user_id' => 1,
    'event_type' => 'invoice.payment_succeeded',
    'status' => 'paid',
    'amount_cents' => 47900,
    'provider_event_id' => 'manual-renewal-001',
]), []);
$current = $subscriptions->currentForUser(1);
assertEquals($periodEndAfterRenewal, (string) $current['current_period_end'], 'Webhook duplicado nao deve renovar o periodo duas vezes.');

$asaasProviderSubscriptionId = 'sub_test_recorrente_001';
assertTrue($subscriptions->changePlan(2, 2, 'monthly', 'active') === true, 'Assinatura Asaas local deve ser preparada.');
$asaasSubscription = $subscriptions->currentForUser(2);
$pdo->prepare('UPDATE subscriptions SET provider = ?, provider_subscription_id = ? WHERE id = ?')
    ->execute(['asaas', $asaasProviderSubscriptionId, (int) $asaasSubscription['id']]);
$asaasSubscription = $subscriptions->currentForUser(2);
$asaasPeriodEndBeforeRenewal = (string) $asaasSubscription['current_period_end'];
putenv('ASAAS_WEBHOOK_TOKEN=test-webhook-token');
$asaas = new AsaasPaymentProvider($pdo, $subscriptions);
$asaasWebhook = $asaas->handleWebhook(json_encode([
    'event' => 'PAYMENT_RECEIVED',
    'payment' => [
        'id' => 'pay_test_recorrente_001',
        'subscription' => $asaasProviderSubscriptionId,
        'value' => 49.90,
    ],
]), ['asaas-access-token' => 'test-webhook-token']);
assertEquals('paid', $asaasWebhook['status'], 'Webhook Asaas recorrente deve normalizar pagamento recebido.');
$asaasSubscription = $subscriptions->currentForUser(2);
assertTrue(new DateTimeImmutable((string) $asaasSubscription['current_period_end']) > new DateTimeImmutable($asaasPeriodEndBeforeRenewal), 'Webhook Asaas recorrente deve renovar periodo.');

$asaasPeriodEndAfterRenewal = (string) $asaasSubscription['current_period_end'];
$asaas->handleWebhook(json_encode([
    'event' => 'PAYMENT_RECEIVED',
    'payment' => [
        'id' => 'pay_test_recorrente_001',
        'subscription' => $asaasProviderSubscriptionId,
        'value' => 49.90,
    ],
]), ['asaas-access-token' => 'test-webhook-token']);
$asaasSubscription = $subscriptions->currentForUser(2);
assertEquals($asaasPeriodEndAfterRenewal, (string) $asaasSubscription['current_period_end'], 'Webhook Asaas duplicado nao deve renovar duas vezes.');
putenv('ASAAS_WEBHOOK_TOKEN');

$cancel = $payments->cancelSubscription(1);
assertTrue(($cancel['ok'] ?? false) === true, 'Cancelamento manual deve funcionar.');
$latestNotification = (string) $pdo->query('SELECT mensagem FROM notifications WHERE user_id = 1 ORDER BY id DESC LIMIT 1')->fetchColumn();
assertStringContains('cancelado', $latestNotification, 'Cancelamento de plano deve notificar o cliente.');
assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM mail_logs WHERE recipient = 'cliente1@teste.local' AND subject = 'Plano cancelado - JusTraduz' AND status = 'sent'")->fetchColumn(), 'Cancelamento de plano deve enviar e-mail ao cliente.');
putenv('MAIL_LOG_ONLY');

$organizations = new OrganizationService($pdo);
assertEquals(1, $organizations->currentOrganizationId(3), 'Advogado deve estar no escritorio demo.');
assertTrue($organizations->sameOrganization(1, 3), 'Cliente e advogado demo devem estar na mesma organizacao.');

$rbac = new RbacService($pdo);
assertTrue($rbac->can(1, 'documents.create', 'cliente'), 'Cliente deve criar documento por permissao default.');
assertTrue($rbac->can(3, 'cases.manage_assigned', 'advogado'), 'Advogado deve gerenciar casos atribuidos.');
assertTrue($rbac->can(5, 'qualquer.permissao', 'admin'), 'Admin deve ter wildcard.');

$due = SlaService::deadlineForPriority('alta');
assertTrue(new DateTimeImmutable($due) > new DateTimeImmutable(), 'SLA de prioridade alta deve ficar no futuro.');
assertEquals('vencido', SlaService::status('2020-01-01 00:00:00', 'aberto'), 'SLA antigo deve ficar vencido.');

echo "P2SaasTest: OK\n";
