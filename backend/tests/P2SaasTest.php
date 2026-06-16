<?php

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/services/OrganizationService.php';
require_once dirname(__DIR__) . '/app/services/RbacService.php';
require_once dirname(__DIR__) . '/app/services/SlaService.php';
require_once dirname(__DIR__) . '/app/services/SubscriptionService.php';
require_once dirname(__DIR__) . '/app/services/UsageLimiter.php';
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
$quota = $usage->allow(1, 'document_upload', 11);
assertTrue($quota['allowed'] === false, 'Limite do plano Essencial deve bloquear 11 uploads.');

assertTrue($subscriptions->changePlan(1, 2, 'monthly', 'active') === true, 'Troca para Pro deve funcionar.');
$quota = $usage->allow(1, 'document_upload', 11);
assertTrue($quota['allowed'] === true, 'Plano Pro deve permitir 11 uploads.');

$payments = new ManualPaymentProvider($pdo, $subscriptions);
$checkout = $payments->createCheckout(1, 2, 'yearly');
assertTrue($checkout->ok === true, 'Checkout manual deve concluir com sucesso.');
assertTrue($checkout->subscriptionId !== null, 'Checkout manual deve retornar assinatura.');
assertStringContains('/frontend/subir-plano.php', $checkout->redirectUrl, 'Checkout manual deve redirecionar para planos.');
assertEquals(75900, (int) $pdo->query('SELECT COALESCE(SUM(amount_cents), 0) FROM payment_events WHERE status = "paid"')->fetchColumn(), 'Checkout manual deve registrar pagamento anual.');

$webhook = $payments->handleWebhook(json_encode([
    'subscription_id' => $checkout->subscriptionId,
    'event_type' => 'invoice.payment_failed',
    'status' => 'failed',
    'amount_cents' => 75900,
]), []);
assertEquals('failed', $webhook['status'], 'Webhook deve normalizar status de pagamento.');
$current = $subscriptions->currentForUser(1);
assertEquals('past_due', $current['status'], 'Webhook failed deve marcar assinatura como inadimplente.');

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
