<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/services/JobQueueService.php';
require_once dirname(__DIR__) . '/app/services/MailerService.php';
require_once dirname(__DIR__) . '/app/services/StorageService.php';
require_once dirname(__DIR__) . '/app/services/UploadScannerService.php';
require_once dirname(__DIR__) . '/app/services/UsageLimiter.php';
require_once dirname(__DIR__) . '/app/services/DataJudService.php';
require_once dirname(__DIR__) . '/app/controllers/CaseController.php';
require_once dirname(__DIR__) . '/app/controllers/DocumentController.php';
require_once dirname(__DIR__) . '/app/controllers/HealthController.php';

$pdo = test_pdo();
build_test_schema($pdo);
seed_test_data($pdo);

$originalDsn = getenv('DB_DSN');
$missingSqliteDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'justraduz-missing-' . bin2hex(random_bytes(4));
putenv('DB_DSN=sqlite:' . $missingSqliteDir . DIRECTORY_SEPARATOR . 'health.sqlite');
ob_start();
(new HealthController())->show();
$healthOutput = ob_get_clean();
$health = json_decode($healthOutput, true);
assertEquals('degraded', $health['status'] ?? '', 'Healthcheck deve responder degradado quando o banco esta indisponivel.');
assertTrue(($health['checks']['database'] ?? true) === false, 'Healthcheck deve marcar database como falso sem fatal error.');
putenv('DB_DSN=' . $originalDsn);

$tmpFile = tempnam(sys_get_temp_dir(), 'scan_');
file_put_contents($tmpFile, '<?php echo "malicioso";');
$scanner = new UploadScannerService();
assertTrue($scanner->scan($tmpFile, 'teste.pdf', 'application/pdf') === false, 'Scanner deve bloquear conteudo executavel.');
unlink($tmpFile);

$tmpFile = tempnam(sys_get_temp_dir(), 'scan_');
file_put_contents($tmpFile, '%PDF-1.4 documento seguro');
assertTrue($scanner->scan($tmpFile, 'teste.pdf', 'application/pdf') === true, 'Scanner deve permitir arquivo sem assinatura suspeita.');
unlink($tmpFile);

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

putenv('DOCUMENT_STORAGE_PATH');
putenv('USAGE_DAILY_DOCUMENT_AI');
putenv('MAIL_LOG_ONLY');

echo "P1OperationsTest: OK\n";
