<?php

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/services/OrganizationService.php';
require_once dirname(__DIR__) . '/app/services/OrganizationInviteService.php';
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
assertEquals('gratuito', $current['plan_slug'], 'Plano padrao deve ser Gratuito.');
$professionalDefault = $subscriptions->ensureDefaultProfessionalSubscription(3);
assertTrue($professionalDefault !== null, 'Advogado validado deve receber assinatura profissional basica.');
assertEquals('profissional_basico', $professionalDefault['plan_slug'], 'Plano profissional padrao deve ser Profissional basico.');
assertTrue($subscriptions->ensureDefaultSubscription(3) === null, 'Advogado nao deve receber assinatura gratuita de cliente.');
assertTrue($subscriptions->changePlan(3, 2, 'monthly', 'active') === true, 'Advogado validado deve assinar plano profissional.');
assertTrue($subscriptions->changePlan(4, 2, 'monthly', 'active') === false, 'Advogado pendente nao deve assinar plano profissional.');
assertTrue($subscriptions->changePlan(3, 1, 'monthly', 'active') === false, 'Advogado nao deve assinar plano Essencial de cliente.');
$usage = new UsageLimiter($pdo);
assertTrue($usage->allow(1, 'document_upload', 5)['allowed'] === true, 'Plano Gratuito deve permitir ate 5 uploads mensais.');
assertTrue($usage->allow(1, 'document_upload', 6)['allowed'] === false, 'Plano Gratuito deve bloquear mais de 5 uploads mensais.');
assertTrue($usage->allow(1, 'document_ai', 6)['allowed'] === false, 'Plano Gratuito deve bloquear mais de 5 analises com IA.');
assertTrue($usage->allow(1, 'ai_chat', 51)['allowed'] === false, 'Plano Gratuito deve bloquear mais de 50 mensagens com IA.');
assertTrue($usage->allow(1, 'datajud_cnj', 2)['allowed'] === false, 'Plano Gratuito deve bloquear mais de 1 consulta CNJ.');
assertTrue($usage->allow(1, 'ocr', 6)['allowed'] === false, 'Plano Gratuito deve bloquear mais de 5 OCRs.');
assertTrue($subscriptions->changePlan(1, 1, 'monthly', 'active') === true, 'Troca para Essencial deve funcionar.');

$quota = $usage->allow(1, 'document_upload', 31);
assertTrue($quota['allowed'] === false, 'Limite do plano Essencial deve bloquear mais de 30 uploads mensais.');
assertStringContains('limite mensal', $usage->limitMessage('document_upload', $quota), 'Mensagem de quota deve falar em limite mensal.');
assertStringContains('30', $usage->limitMessage('document_upload', $quota), 'Mensagem de quota deve informar o limite do plano.');

assertTrue($subscriptions->changePlan(1, 2, 'monthly', 'active') === true, 'Troca para Pro deve funcionar.');
$quota = $usage->allow(1, 'document_upload', 501);
assertTrue($quota['allowed'] === false, 'Plano Pro deve bloquear mais de 500 uploads mensais.');
$quota = $usage->allow(1, 'document_upload', 500);
assertTrue($quota['allowed'] === true, 'Plano Pro deve permitir ate 500 uploads mensais.');

assertTrue($subscriptions->changePlan(1, 3, 'monthly', 'active') === false, 'Cliente nao deve assinar plano Escritorio profissional.');
assertTrue($subscriptions->changePlan(1, 7, 'monthly', 'active') === false, 'Cliente nao deve assinar Max Advogado.');
assertTrue($subscriptions->changePlan(3, 6, 'monthly', 'active') === false, 'Advogado nao deve assinar Max Cliente.');
assertTrue($subscriptions->changePlan(1, 6, 'monthly', 'active') === true, 'Cliente deve assinar Max Cliente.');
assertEquals(2000, $subscriptions->featureLimit(1, 'document_upload'), 'Max Cliente deve aplicar 2.000 documentos.');
assertEquals(20000, $subscriptions->featureLimit(1, 'ai_chat'), 'Max Cliente deve aplicar 20.000 mensagens de IA.');
assertTrue($subscriptions->changePlan(3, 7, 'monthly', 'active') === true, 'Advogado deve assinar Max Advogado.');
assertEquals(3000, $subscriptions->featureLimit(3, 'document_upload'), 'Max Advogado deve aplicar 3.000 documentos.');
assertEquals(30000, $subscriptions->featureLimit(3, 'ai_chat'), 'Max Advogado deve aplicar 30.000 mensagens de IA.');
assertTrue($subscriptions->changePlan(3, 3, 'monthly', 'active') === true, 'Advogado validado deve assinar plano Escritorio.');
assertTrue($usage->allow(3, 'document_upload', 5000)['allowed'] === true, 'Plano Escritorio deve permitir documentos ilimitados.');
assertTrue($usage->allow(3, 'ocr', 5000)['allowed'] === true, 'Plano Escritorio deve permitir OCR ilimitado.');
assertEquals(1000, $subscriptions->featureLimit(3, 'datajud_cnj'), 'Plano Escritorio deve limitar CNJ para controlar custo externo.');
assertTrue($subscriptions->changePlan(1, 2, 'monthly', 'active') === true, 'Retorno para Pro deve funcionar.');

$payments = new ManualPaymentProvider($pdo, $subscriptions);
putenv('MAIL_LOG_ONLY=true');
$pdo->prepare(
    'INSERT INTO users (id, nome, email, senha, tipo, oab_verificado, oab_status, status_cna, status, profile_completed)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, "ativo", 1)'
)->execute([7, 'Advogado Dois', 'advogado2@teste.local', password_hash('Teste@12345', PASSWORD_DEFAULT), 'advogado', 1, 'approved', 'verificado']);

$clientInviteRejected = false;
try {
    (new OrganizationInviteService($pdo))->validateOfficeInviteRequest(
        ['slug' => 'escritorio'],
        ['cliente2@teste.local']
    );
} catch (InvalidArgumentException) {
    $clientInviteRejected = true;
}
assertTrue($clientInviteRejected, 'Plano Escritorio deve rejeitar convite para conta de cliente.');
assertEquals(
    [],
    (new OrganizationInviteService($pdo))->issueForOfficeSubscription(1, ['plan_slug' => 'escritorio'], ['novo.advogado@teste.local']),
    'Conta cliente nao deve emitir convite do plano Escritorio.'
);

$legacyClientToken = 'legacy-client-token';
$pdo->prepare("INSERT INTO organization_invites (organization_id, email, role, token_hash, status, invited_by, expires_at) VALUES (?, ?, 'member', ?, 'pending', ?, ?)")
    ->execute([1, 'cliente2@teste.local', password_hash($legacyClientToken, PASSWORD_DEFAULT), 3, (new DateTimeImmutable('+2 days'))->format('Y-m-d H:i:s')]);
$legacyClientResult = (new OrganizationInviteService($pdo))->acceptToken($legacyClientToken, 2);
assertEquals('lawyer_required', $legacyClientResult['reason'] ?? '', 'Cliente nao deve aceitar nem mesmo convite legado do Escritorio.');

$checkout = $payments->createCheckout(1, 2, 'yearly');
assertTrue($checkout->ok === true, 'Checkout manual deve concluir com sucesso.');
assertTrue($checkout->subscriptionId !== null, 'Checkout manual deve retornar assinatura.');
assertStringContains('/frontend/subir-plano.php', $checkout->redirectUrl, 'Checkout manual deve redirecionar para planos.');
assertEquals(47900, (int) $pdo->query('SELECT COALESCE(SUM(amount_cents), 0) FROM payment_events WHERE status = "paid"')->fetchColumn(), 'Checkout manual deve registrar pagamento anual com desconto.');
$latestNotification = (string) $pdo->query('SELECT mensagem FROM notifications WHERE user_id = 1 ORDER BY id DESC LIMIT 1')->fetchColumn();
assertStringContains('Pagamento confirmado', $latestNotification, 'Checkout pago deve notificar o cliente.');
assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM mail_logs WHERE recipient = 'cliente1@teste.local' AND subject LIKE 'Plano Pro confirmado%' AND status = 'sent'")->fetchColumn(), 'Checkout pago deve enviar e-mail ao cliente.');

$officeCheckout = $payments->createCheckout(3, 3, 'monthly', ['team_invites' => ['advogado2@teste.local']]);
assertTrue($officeCheckout->ok === true, 'Checkout manual do plano Escritorio deve concluir com sucesso.');
assertEquals(['advogado2@teste.local'], $officeCheckout->metadata['team_invites_sent'] ?? [], 'Checkout do Escritorio deve emitir convites apos ativar o plano.');
assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM organization_invites WHERE email = 'advogado2@teste.local' AND status = 'pending'")->fetchColumn(), 'Convite do Escritorio deve ficar pendente.');
assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM mail_logs WHERE recipient = 'advogado2@teste.local' AND subject LIKE 'Convite para equipe no JusTraduz%' AND status = 'sent'")->fetchColumn(), 'Convite do Escritorio deve enviar e-mail.');

$knownInviteToken = 'office-known-token';
$pdo->prepare("INSERT INTO organization_invites (organization_id, email, role, token_hash, status, invited_by, expires_at) VALUES (?, ?, 'member', ?, 'pending', ?, ?)")
    ->execute([1, 'advogado2@teste.local', password_hash($knownInviteToken, PASSWORD_DEFAULT), 3, (new DateTimeImmutable('+2 days'))->format('Y-m-d H:i:s')]);
$acceptResult = (new OrganizationInviteService($pdo))->acceptToken($knownInviteToken, 7);
assertTrue(($acceptResult['ok'] ?? false) === true, 'Advogado convidado deve aceitar o convite do Escritorio.');
assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM organization_members WHERE organization_id = 1 AND user_id = 7 AND status = 'active'")->fetchColumn(), 'Advogado convidado deve entrar na organizacao.');

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
$asaas->handleWebhook(json_encode([
    'event' => 'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED',
    'payment' => [
        'id' => 'pay_test_cartao_recusado_001',
        'subscription' => $asaasProviderSubscriptionId,
        'value' => 49.90,
    ],
]), ['asaas-access-token' => 'test-webhook-token']);
$asaasSubscription = $subscriptions->currentForUser(2);
assertEquals('past_due', $asaasSubscription['status'], 'Webhook de cartao recusado deve marcar assinatura Asaas como inadimplente.');

$inviteProviderSubscriptionId = 'sub_test_convite_escritorio_001';
$lawyerOfficeSubscription = $subscriptions->currentForUser(3);
$pdo->prepare('UPDATE subscriptions SET provider = ?, provider_subscription_id = ? WHERE id = ?')
    ->execute(['asaas', $inviteProviderSubscriptionId, (int) $lawyerOfficeSubscription['id']]);
$pdo->prepare(
    "INSERT INTO payment_events (subscription_id, user_id, provider, provider_event_id, event_type, amount_cents, status, payload_json, processed_at)
     VALUES (?, ?, 'asaas', ?, 'subscription.created', 9990, 'pending', ?, ?)"
)->execute([
    (int) $lawyerOfficeSubscription['id'],
    3,
    $inviteProviderSubscriptionId,
    json_encode([
        'provider_subscription_id' => $inviteProviderSubscriptionId,
        'plan_id' => 3,
        'billing_cycle' => 'monthly',
        'team_invites' => ['pendente@teste.local'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    date('Y-m-d H:i:s'),
]);
$asaasInviteWebhook = $asaas->handleWebhook(json_encode([
    'event' => 'PAYMENT_RECEIVED',
    'payment' => [
        'id' => 'pay_test_convite_escritorio_001',
        'subscription' => $inviteProviderSubscriptionId,
        'value' => 99.90,
    ],
]), ['asaas-access-token' => 'test-webhook-token']);
assertEquals(['pendente@teste.local'], $asaasInviteWebhook['team_invites_sent'] ?? [], 'Webhook pago deve enviar convites pendentes do Escritorio.');
assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM organization_invites WHERE email = 'pendente@teste.local' AND status = 'pending'")->fetchColumn(), 'Webhook deve registrar convite pendente do Escritorio.');
putenv('ASAAS_WEBHOOK_TOKEN');

$cancel = $payments->cancelSubscription(1);
assertTrue(($cancel['ok'] ?? false) === true, 'Cancelamento manual deve funcionar.');
$current = $subscriptions->currentForUser(1);
assertEquals('gratuito', $current['plan_slug'], 'Cancelamento de plano pago deve retornar ao Gratuito.');
$latestNotification = (string) $pdo->query('SELECT mensagem FROM notifications WHERE user_id = 1 ORDER BY id DESC LIMIT 1')->fetchColumn();
assertStringContains('cancelado', $latestNotification, 'Cancelamento de plano deve notificar o cliente.');
assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM mail_logs WHERE recipient = 'cliente1@teste.local' AND subject = 'Plano cancelado - JusTraduz' AND status = 'sent'")->fetchColumn(), 'Cancelamento de plano deve enviar e-mail ao cliente.');
$cancelFree = $payments->cancelSubscription(1);
assertTrue(($cancelFree['already_free'] ?? false) === true, 'Cancelamento do Gratuito deve ser ignorado.');
$lawyerCancel = $payments->cancelSubscription(3);
assertTrue(($lawyerCancel['ok'] ?? false) === true, 'Cancelamento manual do plano profissional deve funcionar.');
$lawyerCurrent = $subscriptions->currentForUser(3);
assertEquals('profissional_basico', $lawyerCurrent['plan_slug'], 'Cancelamento profissional deve retornar ao Profissional basico.');
putenv('MAIL_LOG_ONLY');

$organizations = new OrganizationService($pdo);
assertEquals(1, $organizations->currentOrganizationId(3), 'Advogado deve estar no escritorio demo.');
assertTrue(!$organizations->sameOrganization(1, 3), 'Cliente nao deve participar do escritorio de advogados.');

$rbac = new RbacService($pdo);
assertTrue($rbac->can(1, 'documents.create', 'cliente'), 'Cliente deve criar documento por permissao default.');
assertTrue($rbac->can(3, 'cases.manage_assigned', 'advogado'), 'Advogado deve gerenciar casos atribuidos.');
assertTrue($rbac->can(5, 'qualquer.permissao', 'admin'), 'Admin deve ter wildcard.');

$due = SlaService::deadlineForPriority('alta');
assertTrue(new DateTimeImmutable($due) > new DateTimeImmutable(), 'SLA de prioridade alta deve ficar no futuro.');
assertEquals('vencido', SlaService::status('2020-01-01 00:00:00', 'aberto'), 'SLA antigo deve ficar vencido.');

putenv('MAIL_LOG_ONLY=true');
$maxClientCheckout = $payments->createCheckout(1, 6, 'monthly');
assertTrue($maxClientCheckout->ok === true, 'Checkout do Max Cliente deve concluir com sucesso.');
assertEquals(7990, (int) ($maxClientCheckout->metadata['amount_cents'] ?? 0), 'Max Cliente deve cobrar R$ 79,90 no ciclo mensal.');
$maxLawyerCheckout = $payments->createCheckout(3, 7, 'monthly');
assertTrue($maxLawyerCheckout->ok === true, 'Checkout do Max Advogado deve concluir com sucesso.');
assertEquals(8990, (int) ($maxLawyerCheckout->metadata['amount_cents'] ?? 0), 'Max Advogado deve cobrar R$ 89,90 no ciclo mensal.');
putenv('MAIL_LOG_ONLY');

echo "P2SaasTest: OK\n";
