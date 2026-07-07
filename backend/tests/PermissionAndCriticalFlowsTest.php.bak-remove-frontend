<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/middlewares/CsrfMiddleware.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/controllers/DocumentController.php';
require_once dirname(__DIR__) . '/app/controllers/CaseController.php';
require_once dirname(__DIR__) . '/app/controllers/ScheduleController.php';
require_once dirname(__DIR__) . '/app/controllers/ProcessController.php';
require_once dirname(__DIR__) . '/app/controllers/AdminController.php';
require_once dirname(__DIR__) . '/app/controllers/PrivacyController.php';
require_once dirname(__DIR__) . '/app/services/OrganizationService.php';

$pdo = test_pdo();
build_test_schema($pdo);
seed_test_data($pdo);
putenv('MAIL_LOG_ONLY=true');

reset_test_state();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['email' => 'cliente1@teste.local', 'senha' => 'Senha@123'];
$redirect = expectRedirect(static fn () => (new AuthController())->login());
assertStringContains('/frontend/dashboard-cliente.php', $redirect, 'Login de cliente ativo deve redirecionar para dashboard.');
assertEquals(1, (int) $_SESSION['id'], 'Login deve gravar id do usuario na sessao.');
assertEquals('cliente', $_SESSION['tipo'] ?? '', 'Login deve gravar perfil do usuario.');
assertTrue(!empty($_SESSION['_csrf_token']), 'Login deve emitir token CSRF.');

$auth = new AuthController();
assertTrue(callPrivate($auth, 'isValidCpf', ['529.982.247-25']) === true, 'CPF valido deve passar pelo digito verificador.');
assertTrue(callPrivate($auth, 'isValidCpf', ['111.111.111-11']) === false, 'CPF com digitos repetidos deve falhar.');
assertTrue(callPrivate($auth, 'isValidCpf', ['123.456.789-00']) === false, 'CPF com digito verificador invalido deve falhar.');
putenv('PROFILE_PHOTO_STORAGE_PATH=backend/storage/profile_photos_test');
$profileStorage = callPrivate($auth, 'profilePhotoStorage', []);
assertEquals('backend/storage/profile_photos_test', $profileStorage['relative_dir'] ?? '', 'Foto de perfil deve respeitar PROFILE_PHOTO_STORAGE_PATH.');
putenv('PROFILE_PHOTO_STORAGE_PATH');

reset_test_state();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'nome' => 'Perfil Removido',
    'email' => 'perfil.removido@teste.local',
    'telefone' => '(11) 99999-9999',
    'data_nascimento' => '1990-01-01',
    'maioridade_confirmada' => '1',
    'senha' => 'Senha@12345',
    'senha2' => 'Senha@12345',
    'tipo' => 'estagiario',
];
$redirect = expectRedirect(static fn () => (new AuthController())->registrar());
assertStringContains('Cliente ou Advogado', urldecode($redirect), 'Cadastro deve rejeitar o perfil removido.');

reset_test_state();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['email' => 'pendente@teste.local', 'senha' => 'Senha@123'];
$redirect = expectRedirect(static fn () => (new AuthController())->login());
assertStringContains('/frontend/login.html', $redirect, 'Profissional com OAB pendente nao deve entrar.');
assertStringContains('aguardando', urldecode($redirect), 'Bloqueio de OAB pendente deve orientar o usuario.');

reset_test_state();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['email' => 'admin@teste.local', 'senha' => 'Senha@123'];
$redirect = expectRedirect(static fn () => (new AuthController())->login());
assertStringContains('/frontend/login.html', $redirect, 'Admin nao deve entrar pelo login comum.');
assertStringContains('Email ou senha incorretos.', urldecode($redirect), 'Login comum deve mostrar erro generico para conta admin.');
assertTrue(empty($_SESSION['logado']), 'Login comum nao deve abrir sessao administrativa.');

reset_test_state();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['email' => 'admin@teste.local', 'senha' => 'Senha@123'];
$redirect = expectRedirect(static fn () => (new AuthController())->adminLogin());
assertStringContains('/frontend/admin/dashboard-admin.php', $redirect, 'Admin ativo deve entrar no painel administrativo.');
assertEquals('admin', $_SESSION['tipo'] ?? '', 'Admin login deve manter perfil admin na sessao.');
assertTrue(PermissionService::roleHas('admin', 'reports.view') === true, 'Admin deve ter permissao para relatorios.');
assertTrue(PermissionService::roleHas('cliente', 'reports.view') === false, 'Cliente nao deve ter permissao para relatorios.');
assertTrue(PermissionService::roleHas('estagiario', 'cases.view_assigned') === false, 'Perfil removido nao deve manter permissoes.');
assertTrue(in_array('permissions.manage', PermissionService::availablePermissions(), true), 'Permissoes dinamicas devem listar permissions.manage.');
PermissionService::setOverride($pdo, 'cliente', 'reports.view', 'allow', 5);
assertTrue(PermissionService::roleHas('cliente', 'reports.view') === true, 'Override allow deve conceder permissao ao perfil.');
PermissionService::setOverride($pdo, 'cliente', 'reports.view', 'inherit', 5);
assertTrue(PermissionService::roleHas('cliente', 'reports.view') === false, 'Override inherit deve voltar ao padrao do perfil.');
assertTrue(OrganizationService::enabled($pdo) === true, 'Multiempresa deve estar habilitado no schema novo.');
$adminController = new AdminController();
assertEquals('12ABC34501DE35', callPrivate($adminController, 'normalizeOrganizationDocument', ['12.ABC.345/01DE-35']), 'CNPJ alfanumerico oficial deve ser aceito.');
assertEquals('11222333000181', callPrivate($adminController, 'normalizeOrganizationDocument', ['11.222.333/0001-81']), 'CNPJ numerico valido deve continuar aceito.');
assertTrue(callPrivate($adminController, 'normalizeOrganizationDocument', ['12.ABC.345/01DE-00']) === null, 'CNPJ alfanumerico com DV invalido deve ser recusado.');

reset_test_state();
secure_session_start();
$_SESSION['_csrf_token'] = 'token-valido';
$_POST = ['_csrf' => 'token-valido'];
CsrfMiddleware::validate();
assertTrue(true, 'CSRF valido deve passar.');

$csrfFailed = false;
$_POST = ['_csrf' => 'token-invalido'];
try {
    CsrfMiddleware::validate();
} catch (RuntimeException $exception) {
    $csrfFailed = $exception->getCode() === 403;
}
assertTrue($csrfFailed, 'CSRF invalido deve falhar com 403.');

reset_test_state();
$_SESSION = ['logado' => true, 'id' => 1, 'tipo' => 'cliente'];
$documents = new DocumentController();
assertEquals(1, (int) callPrivate($documents, 'findDocumentForCurrentUser', [1])['id'], 'Cliente deve acessar o proprio documento.');
assertTrue(callPrivate($documents, 'findDocumentForCurrentUser', [2]) === null, 'Cliente nao deve acessar documento de outro usuario.');
assertTrue(callPrivate($documents, 'canDeleteDocument', [['user_id' => 1]]) === true, 'Cliente deve excluir seu proprio documento.');
assertTrue(callPrivate($documents, 'canDeleteDocument', [['user_id' => 2]]) === false, 'Cliente nao deve excluir documento alheio.');

reset_test_state();
$_SESSION = ['logado' => true, 'id' => 3, 'tipo' => 'advogado'];
$documents = new DocumentController();
assertEquals(1, (int) callPrivate($documents, 'findDocumentForCurrentUser', [1])['id'], 'Advogado do caso deve acessar documento vinculado ao cliente.');
assertTrue(callPrivate($documents, 'findDocumentForCurrentUser', [2]) === null, 'Advogado nao vinculado nao deve acessar documento.');

reset_test_state();
$_SESSION = ['logado' => true, 'id' => 5, 'tipo' => 'admin'];
$documents = new DocumentController();
assertEquals(2, (int) callPrivate($documents, 'findDocumentForCurrentUser', [2])['id'], 'Admin deve acessar documentos para operacao.');
assertTrue(callPrivate($documents, 'canDeleteDocument', [['user_id' => 2]]) === true, 'Admin deve poder excluir documento.');

reset_test_state();
$_SESSION = ['logado' => true, 'id' => 4, 'tipo' => 'advogado'];
$cases = new CaseController();
assertTrue(callPrivate($cases, 'currentProfessionalIsVerified') === false, 'OAB pendente deve bloquear aceite de solicitacao.');

reset_test_state();
$_SESSION = ['logado' => true, 'id' => 1, 'tipo' => 'cliente'];
$cases = new CaseController();
assertTrue(callPrivate($cases, 'documentBelongsToCurrentClient', [1]) === true, 'Solicitacao deve aceitar documento do cliente.');
assertTrue(callPrivate($cases, 'documentBelongsToCurrentClient', [2]) === false, 'Solicitacao deve recusar documento de outro cliente.');
assertTrue(callPrivate($cases, 'canAccessCaseId', [1, 1, 'cliente']) === true, 'Cliente participante deve acessar chat do caso.');
assertTrue(callPrivate($cases, 'canAccessCaseId', [1, 2, 'cliente']) === false, 'Cliente fora do caso nao deve acessar chat.');
assertTrue(callPrivate($cases, 'canAccessCaseId', [1, 3, 'advogado']) === true, 'Advogado responsavel deve acessar chat.');
assertTrue(callPrivate($cases, 'canAccessCaseId', [1, 5, 'admin']) === true, 'Admin deve acessar chat para suporte.');

reset_test_state();
$_SESSION = ['logado' => true, 'id' => 3, 'tipo' => 'advogado'];
$schedule = new ScheduleController();
assertTrue(callPrivate($schedule, 'canManageSlot', [['professional_id' => 3]]) === true, 'Profissional deve gerenciar seu horario.');
assertTrue(callPrivate($schedule, 'canManageSlot', [['professional_id' => 4]]) === false, 'Profissional nao deve gerenciar horario alheio.');
assertTrue(callPrivate($schedule, 'canManageAppointment', [['client_id' => 1, 'professional_id' => 3], 'concluido']) === true, 'Profissional deve concluir agendamento proprio.');

reset_test_state();
$_SESSION = ['logado' => true, 'id' => 1, 'tipo' => 'cliente'];
$schedule = new ScheduleController();
assertTrue(callPrivate($schedule, 'canManageAppointment', [['client_id' => 1, 'professional_id' => 3], 'cancelado']) === true, 'Cliente deve cancelar agendamento proprio.');
assertTrue(callPrivate($schedule, 'canManageAppointment', [['client_id' => 1, 'professional_id' => 3], 'concluido']) === false, 'Cliente nao deve concluir agendamento.');
$filter = callPrivate($schedule, 'slotCalendarFilter', ['cliente', 1, '2030-01-01 00:00:00', '2030-01-31 23:59:59', 0, '']);
assertTrue(in_array("u.oab_verificado = TRUE", $filter[0], true), 'Agenda publica deve listar apenas profissionais com OAB verificada.');

reset_test_state();
secure_session_start();
$_SESSION = ['logado' => true, 'id' => 3, 'tipo' => 'advogado', '_csrf_token' => 'token-processo'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['_csrf' => 'token-processo', 'process_number' => '1234567-89.2024.8.26.0100', 'lgpd_consent' => '1'];
$redirect = expectRedirect(static fn () => (new ProcessController())->sync());
assertStringContains('DataJud por CNJ', urldecode($redirect), 'Processos devem bloquear perfil nao cliente nesta versao.');

reset_test_state();
$_SESSION = ['logado' => true, 'id' => 1, 'tipo' => 'cliente'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['user_id' => '3', 'action' => 'approve', 'justificativa' => 'Documento conferido'];
$redirect = expectRedirect(static fn () => (new AdminController())->updateProfessionalOab());
assertStringContains('/frontend/admin/login-admin.html', $redirect, 'Rotas admin devem exigir perfil administrativo.');

reset_test_state();
secure_session_start();
$_SESSION = ['logado' => true, 'id' => 5, 'tipo' => 'admin'];
$pdo->exec("UPDATE users SET oab = '123456', oab_uf = 'SP' WHERE id = 4");
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['user_id' => '4', 'action' => 'approve', 'justificativa' => 'Documento conferido no CNA'];
$redirect = expectRedirect(static fn () => (new AdminController())->updateProfessionalOab());
assertStringContains('/frontend/admin/validar-oab.php', $redirect, 'Admin deve concluir revisao OAB.');
$approvedProfessional = $pdo->query('SELECT oab_verificado, oab_status, status_cna FROM users WHERE id = 4')->fetch();
assertEquals(1, (int) ($approvedProfessional['oab_verificado'] ?? 0), 'Aprovacao OAB deve marcar profissional como verificado.');
assertEquals('approved', $approvedProfessional['oab_status'] ?? '', 'Aprovacao OAB deve atualizar status da OAB.');
assertEquals('verificado', $approvedProfessional['status_cna'] ?? '', 'Aprovacao OAB deve atualizar status CNA.');
$pendingQueueCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE id = 4 AND oab_verificado = 0 AND COALESCE(status_cna, 'pendente') = 'pendente'")->fetchColumn();
assertEquals(0, $pendingQueueCount, 'Profissional aprovado nao deve continuar na fila pendente.');

reset_test_state();
secure_session_start();
$_SESSION = ['logado' => true, 'id' => 5, 'tipo' => 'admin'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['user_id' => '1', 'organization_id' => '1'];
$redirect = expectRedirect(static fn () => (new AdminController())->assignOrganization());
assertStringContains('Somente advogados', urldecode($redirect), 'Admin nao deve vincular cliente a organizacao.');
assertTrue($pdo->query('SELECT organization_id FROM users WHERE id = 1')->fetchColumn() === null, 'Cliente deve continuar sem organizacao.');

reset_test_state();
secure_session_start();
$_SESSION = ['logado' => true, 'id' => 5, 'tipo' => 'admin'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['user_id' => '3', 'organization_id' => '1'];
$redirect = expectRedirect(static fn () => (new AdminController())->assignOrganization());
assertStringContains('Vinculo atualizado', urldecode($redirect), 'Admin deve vincular advogado a organizacao.');
assertEquals(1, (int) $pdo->query('SELECT organization_id FROM users WHERE id = 3')->fetchColumn(), 'Advogado deve ficar vinculado a organizacao.');

reset_test_state();
secure_session_start();
$_SESSION = ['logado' => true, 'id' => 5, 'tipo' => 'admin'];
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
(new AdminController())->reportsSummary();
$reportsJson = ob_get_clean();
$reports = json_decode((string) $reportsJson, true);
assertTrue(is_array($reports['users_by_role'] ?? null), 'Relatorio gerencial deve retornar usuarios por perfil.');
assertTrue(isset($reports['sla']['overdue']), 'Relatorio gerencial deve retornar resumo de SLA.');
assertTrue(isset($reports['organizations']['total']), 'Relatorio gerencial deve retornar resumo multiempresa quando habilitado.');

$slaStatus = SlaService::statusForCase([
    'status' => 'aberto',
    'prioridade' => 'alta',
    'created_at' => date('Y-m-d H:i:s', time() - 90000),
]);
assertEquals('overdue', $slaStatus['state'] ?? '', 'SLA deve marcar caso antigo de alta prioridade como vencido.');

reset_test_state();
secure_session_start();
$_SESSION = ['logado' => true, 'id' => 1, 'tipo' => 'cliente'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$profileResetCode = '123456';
$pdo->prepare('INSERT INTO password_reset_codes (user_id, email, code_hash, expires_at) VALUES (?, ?, ?, ?)')
    ->execute([1, 'cliente1@teste.local', password_hash($profileResetCode, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 900)]);
$_POST = ['codigo' => $profileResetCode, 'senha' => 'NovaSenha@123', 'senha2' => 'NovaSenha@123'];
ob_start();
(new AuthController())->profilePasswordReset();
$profileResetJson = ob_get_clean();
$profileReset = json_decode((string) $profileResetJson, true);
assertTrue(($profileReset['success'] ?? false) === true, 'Reset de senha pelo perfil deve aceitar o campo codigo.');
$newPasswordHash = (string) $pdo->query('SELECT senha FROM users WHERE id = 1')->fetchColumn();
assertTrue(password_verify('NovaSenha@123', $newPasswordHash), 'Reset de senha pelo perfil deve alterar a senha do usuario.');

reset_test_state();
secure_session_start();
$_SESSION = ['logado' => true, 'id' => 1, 'tipo' => 'cliente', '_csrf_token' => 'token-lgpd'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['_csrf' => 'token-lgpd'];
ob_start();
(new PrivacyController())->export();
$exportJson = ob_get_clean();
$export = json_decode((string) $exportJson, true);
assertEquals('cliente1@teste.local', $export['user']['email'] ?? '', 'Exportacao LGPD deve incluir cadastro do titular.');
assertEquals('cliente-um.pdf', $export['documents'][0]['nome_arquivo'] ?? '', 'Exportacao LGPD deve incluir documentos do titular.');
assertEquals('Caso atendido', $export['cases_as_client'][0]['titulo'] ?? '', 'Exportacao LGPD deve incluir solicitacoes do titular.');

reset_test_state();
secure_session_start();
$_SESSION = ['logado' => true, 'id' => 2, 'tipo' => 'cliente', '_csrf_token' => 'token-delete'];
$_SERVER['REQUEST_METHOD'] = 'POST';
putenv('PAYMENT_PROVIDER=manual');
$_POST = ['_csrf' => 'token-delete', 'confirmacao' => 'EXCLUIR', 'senha_atual' => 'Senha@123'];
$redirect = expectRedirect(static fn () => (new PrivacyController())->deleteAccount());
assertStringContains('/frontend/login.html', $redirect, 'Encerramento LGPD deve bloquear a conta e voltar ao login.');
$scheduled = $pdo->query('SELECT nome, email, status, cpf, deletion_requested_at, deletion_scheduled_at FROM users WHERE id = 2')->fetch();
assertEquals('Cliente Dois', $scheduled['nome'] ?? '', 'Agendamento de exclusao deve preservar nome durante arrependimento.');
assertEquals('cliente2@teste.local', $scheduled['email'] ?? '', 'Agendamento de exclusao deve preservar e-mail durante arrependimento.');
assertEquals('inativo', $scheduled['status'] ?? '', 'Agendamento de exclusao deve bloquear a conta imediatamente.');
assertTrue(!empty($scheduled['deletion_requested_at']), 'Agendamento deve registrar quando a exclusao foi pedida.');
assertTrue(!empty($scheduled['deletion_scheduled_at']), 'Agendamento deve registrar data final de exclusao.');
assertEquals(1, (int) $pdo->query('SELECT COUNT(*) FROM documents WHERE user_id = 2')->fetchColumn(), 'Documentos devem ser preservados durante 30 dias.');

reset_test_state();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['email' => 'cliente2@teste.local', 'senha' => 'Senha@123'];
$redirect = expectRedirect(static fn () => (new AuthController())->login());
assertStringContains('/frontend/dashboard-cliente.php', $redirect, 'Login dentro do prazo deve recuperar a conta.');
assertTrue($pdo->query('SELECT deletion_scheduled_at FROM users WHERE id = 2')->fetchColumn() === null, 'Cancelamento deve limpar data final de exclusao.');
assertEquals('ativo', (string) $pdo->query('SELECT status FROM users WHERE id = 2')->fetchColumn(), 'Recuperacao deve reativar a conta.');

$pdo->exec("UPDATE users SET deletion_requested_at = datetime('now', '-31 days'), deletion_scheduled_at = datetime('now', '-1 day') WHERE id = 2");
$finalized = (new PrivacyController())->finalizeExpiredDeletions(10);
assertEquals(1, $finalized, 'Finalizacao deve processar exclusao vencida.');
$deleted = $pdo->query('SELECT nome, email, status, cpf, deletion_scheduled_at FROM users WHERE id = 2')->fetch();
assertEquals('Usuário removido', $deleted['nome'] ?? '', 'Finalizacao LGPD deve anonimizar nome.');
assertEquals('deleted+2@justraduz.invalid', $deleted['email'] ?? '', 'Finalizacao LGPD deve anonimizar e-mail.');
assertEquals('inativo', $deleted['status'] ?? '', 'Finalizacao LGPD deve inativar conta.');
assertTrue(($deleted['cpf'] ?? null) === null, 'Finalizacao LGPD deve remover CPF.');
assertTrue(($deleted['deletion_scheduled_at'] ?? null) === null, 'Finalizacao LGPD deve limpar agendamento.');
assertEquals(0, (int) $pdo->query('SELECT COUNT(*) FROM documents WHERE user_id = 2')->fetchColumn(), 'Finalizacao LGPD deve excluir documentos do titular.');

echo "PermissionAndCriticalFlowsTest: OK\n";
