<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/services/JobQueueService.php';
require_once dirname(__DIR__) . '/app/services/MailerService.php';
require_once dirname(__DIR__) . '/app/services/ProcessRunnerService.php';
require_once dirname(__DIR__) . '/app/services/PdfTextExtractor.php';
require_once dirname(__DIR__) . '/app/services/StorageService.php';
require_once dirname(__DIR__) . '/app/services/UploadScannerService.php';
require_once dirname(__DIR__) . '/app/services/UsageLimiter.php';
require_once dirname(__DIR__) . '/app/services/DataJudService.php';
require_once dirname(__DIR__) . '/app/services/EscalationService.php';
require_once dirname(__DIR__) . '/app/services/PublicApiClientService.php';
require_once dirname(__DIR__) . '/app/controllers/PublicApiController.php';
require_once dirname(__DIR__) . '/app/controllers/IntegrationController.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/controllers/CaseController.php';
require_once dirname(__DIR__) . '/app/controllers/DocumentController.php';
require_once dirname(__DIR__) . '/app/controllers/HealthController.php';

$pdo = test_pdo();
build_test_schema($pdo);
seed_test_data($pdo);

$originalDsn = getenv('DB_DSN');
$originalHealthcheckToken = getenv('HEALTHCHECK_TOKEN');
$missingSqliteDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'justraduz-missing-' . bin2hex(random_bytes(4));
putenv('DB_DSN=sqlite:' . $missingSqliteDir . DIRECTORY_SEPARATOR . 'health.sqlite');
putenv('HEALTHCHECK_TOKEN=');
ob_start();
(new HealthController())->show();
$healthOutput = ob_get_clean();
$health = json_decode($healthOutput, true);
assertEquals('degraded', $health['status'] ?? '', 'Healthcheck deve responder degradado quando o banco esta indisponivel.');
assertTrue(($health['checks']['database'] ?? true) === false, 'Healthcheck deve marcar database como falso sem fatal error.');
putenv('DB_DSN=' . $originalDsn);
$originalHealthcheckToken === false
    ? putenv('HEALTHCHECK_TOKEN')
    : putenv('HEALTHCHECK_TOKEN=' . $originalHealthcheckToken);

$processResult = ProcessRunnerService::run([PHP_BINARY, '-r', 'sleep(2);'], 1);
assertEquals(124, (int) $processResult['exit_code'], 'ProcessRunner deve retornar codigo 124 em timeout.');
assertTrue(($processResult['timed_out'] ?? false) === true, 'ProcessRunner deve marcar timed_out quando encerrar processo lento.');

$authController = new AuthController();
assertTrue(callPrivate($authController, 'passwordValidationError', ['NovaSenha@123']) === null, 'Senha forte deve passar na validacao.');
assertTrue(callPrivate($authController, 'passwordValidationError', ['fraca']) !== null, 'Senha fraca deve ser rejeitada.');
$oldHash = (string) $pdo->query('SELECT senha FROM users WHERE id = 1')->fetchColumn();
assertTrue(password_needs_rehash($oldHash, callPrivate($authController, 'passwordHashAlgorithm'), callPrivate($authController, 'passwordHashOptions')), 'Hash antigo deve indicar necessidade de rehash.');
callPrivate($authController, 'rehashUserPasswordIfNeeded', [1, 'Senha@123', $oldHash]);
$rehash = (string) $pdo->query('SELECT senha FROM users WHERE id = 1')->fetchColumn();
assertTrue($rehash !== $oldHash, 'Rehash deve atualizar o hash salvo no banco.');
assertTrue(password_verify('Senha@123', $rehash), 'Rehash deve preservar a senha original.');

$envExample = (string) file_get_contents(dirname(__DIR__, 2) . '/backend/.env.example');
foreach (['CLAMAV_TIMEOUT_SECONDS=15', 'OCR_TIMEOUT_SECONDS=30', 'PDFTOTEXT_BINARY='] as $expectedEnvLine) {
    assertStringContains($expectedEnvLine, $envExample, '.env.example deve documentar ' . $expectedEnvLine);
}

$ci = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/ci.yml');
foreach (['curl', 'gd', 'zip'] as $extension) {
    assertStringContains($extension, $ci, 'CI deve habilitar a extensao PHP ' . $extension . '.');
}

$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/api.php');
assertStringContains("'/api/v1' . \$path", $routes, 'Rotas devem registrar alias versionado /api/v1.');
assertStringContains("'/admin/reports/summary'", $routes, 'Rotas devem expor resumo gerencial.');
assertStringContains("'/admin/reports/export'", $routes, 'Rotas devem expor exportacao CSV gerencial.');
assertStringContains("'/openapi.json'", $routes, 'Rotas devem expor contrato OpenAPI.');

$wcagMatrix = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/MATRIZ_WCAG_AA.md');
assertStringContains('2.4.7 Foco visivel', $wcagMatrix, 'Matriz WCAG deve registrar foco visivel.');

$manual = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/MANUAL_OPERACIONAL_INTERNO.md');
assertStringContains('operational-health-report.php', $manual, 'Manual operacional deve orientar relatorio de saude.');
assertStringContains('cleanup-orphan-storage.php', $manual, 'Manual operacional deve orientar limpeza controlada de orfaos.');

$apiPublica = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/API_PUBLICA.md');
assertStringContains('/api/v1/health', $apiPublica, 'Documentacao da API deve registrar rota versionada de health.');
assertStringContains('OpenAPI', $apiPublica, 'Documentacao da API deve registrar requisito antes de abertura publica.');

$readiness = (string) file_get_contents(dirname(__DIR__, 2) . '/scripts/check-production-readiness.php');
assertStringContains('scripts/operational-health-report.php', $readiness, 'Readiness deve exigir relatorio operacional.');
assertStringContains('scripts/cleanup-orphan-storage.php', $readiness, 'Readiness deve exigir limpeza controlada de storage.');

foreach ([
    'database/justraduz_completo_com_demo.sql',
    'database/justraduz_completo_sem_demo.sql',
    'frontend/pages/admin/organizacoes.php',
    'frontend/pages/admin/permissoes.php',
    'backend/app/services/EscalationService.php',
    'backend/app/services/PublicApiClientService.php',
    'backend/app/controllers/PublicApiController.php',
    'backend/app/controllers/IntegrationController.php',
    'scripts/create-api-client.php',
] as $expectedFile) {
    assertTrue(is_file(dirname(__DIR__, 2) . '/' . $expectedFile), 'Arquivo de produto futuro ausente: ' . $expectedFile);
}

$tmpFile = tempnam(sys_get_temp_dir(), 'scan_');
file_put_contents($tmpFile, '<?php echo "malicioso";');
$scanner = new UploadScannerService();
assertTrue($scanner->scan($tmpFile, 'teste.pdf', 'application/pdf') === false, 'Scanner deve bloquear conteudo executavel.');
unlink($tmpFile);

$tmpFile = tempnam(sys_get_temp_dir(), 'scan_');
file_put_contents($tmpFile, '%PDF-1.4 documento seguro');
assertTrue($scanner->scan($tmpFile, 'teste.pdf', 'application/pdf') === true, 'Scanner deve permitir arquivo sem assinatura suspeita.');
unlink($tmpFile);

$originalClamavBinary = getenv('CLAMAV_BINARY');
putenv('CLAMAV_BINARY=/bin/command-that-does-not-exist');
$tmpFile = tempnam(sys_get_temp_dir(), 'scan_');
file_put_contents($tmpFile, '%PDF-1.4 documento seguro');
assertTrue($scanner->scan($tmpFile, 'teste.pdf', 'application/pdf') === true, 'Scanner deve cair em fallback seguro quando o ClamAV estiver indisponivel.');
unlink($tmpFile);
$originalClamavBinary === false
    ? putenv('CLAMAV_BINARY')
    : putenv('CLAMAV_BINARY=' . $originalClamavBinary);

$caseController = new CaseController();
$documentController = new DocumentController();
assertTrue(callPrivate($caseController, 'isAllowedAttachmentMime', ['pdf', 'application/zip']) === false, 'Anexo PDF nao deve aceitar MIME application/zip.');
assertTrue(callPrivate($documentController, 'isAllowedUploadMime', ['pdf', 'application/zip']) === false, 'Upload PDF nao deve aceitar MIME application/zip.');
assertTrue(callPrivate($caseController, 'isAllowedAttachmentMime', ['docx', 'application/zip']) === true, 'Anexo DOCX pode aceitar MIME application/zip com validacao estrutural.');
assertTrue(callPrivate($documentController, 'isAllowedUploadMime', ['docx', 'application/zip']) === true, 'Upload DOCX pode aceitar MIME application/zip com validacao estrutural.');
$fakeDocx = tempnam(sys_get_temp_dir(), 'docx_');
file_put_contents($fakeDocx, 'PK arquivo zip falso sem estrutura docx');
assertTrue(callPrivate($caseController, 'hasValidDocxStructure', [$fakeDocx]) === false, 'ZIP generico nao deve passar como DOCX.');
assertTrue(callPrivate($documentController, 'hasValidDocxStructure', [$fakeDocx]) === false, 'Upload de documento deve bloquear ZIP generico como DOCX.');
unlink($fakeDocx);

if (class_exists(ZipArchive::class)) {
    $validDocx = tempnam(sys_get_temp_dir(), 'docx_');
    $zip = new ZipArchive();
    $zip->open($validDocx, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<Types></Types>');
    $zip->addFromString('_rels/.rels', '<Relationships></Relationships>');
    $zip->addFromString('word/document.xml', '<w:document></w:document>');
    $zip->close();
    assertTrue(callPrivate($caseController, 'hasValidDocxStructure', [$validDocx]) === true, 'DOCX com estrutura minima deve ser aceito.');
    assertTrue(callPrivate($documentController, 'hasValidDocxStructure', [$validDocx]) === true, 'Upload de documento deve aceitar DOCX com estrutura minima.');
    unlink($validDocx);
}

$privateRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'justraduz-private-' . bin2hex(random_bytes(4));
mkdir($privateRoot . DIRECTORY_SEPARATOR . '7', 0777, true);
putenv('DOCUMENT_STORAGE_PATH=' . $privateRoot);
$storage = new StorageService();
$reference = $storage->documentReference(7, 'arquivo.pdf');
assertStringContains('private://documents/7/arquivo.pdf', $reference, 'Storage fora do projeto deve usar referencia private.');
file_put_contents($privateRoot . DIRECTORY_SEPARATOR . '7' . DIRECTORY_SEPARATOR . 'arquivo.pdf', 'ok');
assertTrue(is_file((string) $storage->documentPathFromReference($reference)), 'Storage deve resolver private:// para caminho real seguro.');

$projectPrivateRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage-private' . DIRECTORY_SEPARATOR . 'documents';
putenv('DOCUMENT_STORAGE_PATH=' . $projectPrivateRoot);
$storage = new StorageService();
assertEquals('private://documents/7/arquivo.pdf', $storage->documentReference(7, 'arquivo.pdf'), 'Storage privado do projeto deve usar referencia private.');

$legacyRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documents';
putenv('DOCUMENT_STORAGE_PATH=' . $legacyRoot);
$storage = new StorageService();
assertEquals('backend/storage/documents/7/arquivo.pdf', $storage->documentReference(7, 'arquivo.pdf'), 'Storage legado deve manter caminho antigo para compatibilidade.');

$usage = new UsageLimiter($pdo);
putenv('USAGE_DAILY_DOCUMENT_AI=1');
assertTrue($usage->allow(1, 'document_ai')['allowed'] === true, 'Primeiro uso deve caber na quota.');
$usage->record(1, 'document_ai', 1, 1);
assertTrue($usage->allow(1, 'document_ai')['allowed'] === false, 'Quota mensal deve bloquear uso excedente.');

$queue = new JobQueueService($pdo);
$jobId = $queue->enqueue('document_analysis', ['document_id' => 1], 1);
assertTrue($jobId > 0, 'Fila deve criar job.');
$job = $queue->reserveNext();
assertEquals($jobId, (int) $job['id'], 'Fila deve reservar o proximo job pendente.');
$queue->fail($job, 'erro teste');
$failedJob = $pdo->query('SELECT status, attempts, last_error FROM job_queue WHERE id = ' . $jobId)->fetch();
assertEquals('pending', $failedJob['status'], 'Job deve voltar para pending antes do maximo de tentativas.');
assertEquals(1, (int) $failedJob['attempts'], 'Job deve incrementar tentativas.');

putenv('MAIL_LOG_ONLY=true');
$mailer = new MailerService();
assertTrue($mailer->send('cliente1@teste.local', 'Teste P1', 'Mensagem') === true, 'MAIL_LOG_ONLY deve simular envio com sucesso.');
assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM mail_logs WHERE recipient = 'cliente1@teste.local' AND status = 'sent'")->fetchColumn(), 'Envio deve ser registrado em mail_logs.');

$pdo->exec("INSERT INTO external_processes (user_id, owner_type, source, query_type, query_value, process_number, last_synced_at) VALUES (1, 'cliente', 'datajud', 'cnj', '12345678920248260100', '1234567-89.2024.8.26.0100', '" . date('Y-m-d H:i:s') . "')");
$result = (new DataJudService($pdo))->syncProcessByCnj(1, '52998224725', '1234567-89.2024.8.26.0100', true);
assertTrue(($result['cached'] ?? false) === true, 'DataJud deve reutilizar cache CNJ recente antes da API.');

$pdo->exec("UPDATE cases SET created_at = '" . date('Y-m-d H:i:s', time() - 90000) . "', advogado_id = 3, prioridade = 'alta' WHERE id = 1");
$pdo->exec("UPDATE cases SET status = 'finalizado' WHERE id = 2");
$escalations = (new EscalationService($pdo))->run(10);
assertEquals(1, $escalations, 'Escalonamento deve notificar caso vencido uma vez.');
$escalationsAgain = (new EscalationService($pdo))->run(10);
assertEquals(0, $escalationsAgain, 'Anti-spam deve evitar escalonamento repetido na janela.');
assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM case_escalations WHERE case_id = 1 AND state = 'overdue'")->fetchColumn(), 'Escalonamento deve persistir historico.');

ob_start();
(new PublicApiController())->openApi();
$openApiJson = ob_get_clean();
$openApi = json_decode((string) $openApiJson, true);
assertEquals('3.0.3', $openApi['openapi'] ?? '', 'OpenAPI deve declarar versao 3.0.3.');
assertTrue(isset($openApi['paths']['/api/v1/admin/reports/export']), 'OpenAPI deve documentar exportacao CSV.');
assertTrue(isset($openApi['paths']['/api/v1/integrations/reports/summary']), 'OpenAPI deve documentar endpoint externo com token.');

foreach (glob((getenv('RATE_LIMIT_STORAGE_PATH') ?: sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'public-api-*.json') ?: [] as $rateLimitFile) {
    @unlink($rateLimitFile);
}
$apiClient = (new PublicApiClientService($pdo))->create('Teste externo', ['health:read', 'reports:read']);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $apiClient['token'];
ob_start();
(new IntegrationController())->reportsSummary();
$integrationJson = ob_get_clean();
$integrationPayload = json_decode((string) $integrationJson, true);
assertTrue(isset($integrationPayload['cases_open']), 'API externa autenticada deve retornar resumo operacional.');
assertEquals(1, (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'public_api.request' AND entity_type = 'public_api_client'")->fetchColumn(), 'API externa autenticada deve registrar auditoria.');

putenv('PUBLIC_API_RATE_LIMIT_PER_MINUTE=1');
ob_start();
(new IntegrationController())->reportsSummary();
$rateLimitedJson = ob_get_clean();
$rateLimitedPayload = json_decode((string) $rateLimitedJson, true);
assertEquals('rate_limited', $rateLimitedPayload['error'] ?? '', 'API externa deve aplicar rate limit por cliente.');
putenv('PUBLIC_API_RATE_LIMIT_PER_MINUTE');
$_SERVER['HTTP_AUTHORIZATION'] = '';

$pdfFixture = tempnam(sys_get_temp_dir(), 'justraduz-pdf-');
file_put_contents($pdfFixture, "%PDF-1.4\nstream\nconteudo-incompativel (Texto recuperado) Tj\nendstream\n%%EOF");
set_error_handler(static function (int $level, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $level, $file, $line);
});
try {
    $pdfText = PdfTextExtractor::extract($pdfFixture);
} finally {
    restore_error_handler();
    @unlink($pdfFixture);
}
assertTrue(str_contains($pdfText, 'Texto recuperado'), 'Stream PDF incompativel nao deve causar erro 500.');

putenv('DOCUMENT_STORAGE_PATH');
putenv('USAGE_DAILY_DOCUMENT_AI');
putenv('MAIL_LOG_ONLY');

echo "P1OperationsTest: OK\n";
