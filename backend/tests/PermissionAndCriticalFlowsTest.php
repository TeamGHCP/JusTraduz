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

$pdo = test_pdo();
build_test_schema($pdo);
seed_test_data($pdo);

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

reset_test_state();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['email' => 'pendente@teste.local', 'senha' => 'Senha@123'];
$redirect = expectRedirect(static fn () => (new AuthController())->login());
assertStringContains('/frontend/login.html', $redirect, 'Profissional com OAB pendente nao deve entrar.');
assertStringContains('aguardando', urldecode($redirect), 'Bloqueio de OAB pendente deve orientar o usuario.');

reset_test_state();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['email' => 'admin@teste.local', 'senha' => 'Senha@123'];
$redirect = expectRedirect(static fn () => (new AuthController())->adminLogin());
assertStringContains('/frontend/admin/dashboard-admin.php', $redirect, 'Admin ativo deve entrar no painel administrativo.');
assertEquals('admin', $_SESSION['tipo'] ?? '', 'Admin login deve manter perfil admin na sessao.');

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
$_POST = ['_csrf' => 'token-delete', 'confirmacao' => 'EXCLUIR'];
$redirect = expectRedirect(static fn () => (new PrivacyController())->deleteAccount());
assertStringContains('/frontend/login.html', $redirect, 'Encerramento LGPD deve encerrar sessao e voltar ao login.');
$deleted = $pdo->query('SELECT nome, email, status, cpf FROM users WHERE id = 2')->fetch();
assertEquals('Usuario removido', $deleted['nome'] ?? '', 'Encerramento LGPD deve anonimizar nome.');
assertEquals('deleted+2@justraduz.invalid', $deleted['email'] ?? '', 'Encerramento LGPD deve anonimizar e-mail.');
assertEquals('inativo', $deleted['status'] ?? '', 'Encerramento LGPD deve inativar conta.');
assertTrue(($deleted['cpf'] ?? null) === null, 'Encerramento LGPD deve remover CPF.');
assertEquals(0, (int) $pdo->query('SELECT COUNT(*) FROM documents WHERE user_id = 2')->fetchColumn(), 'Encerramento LGPD deve excluir documentos do titular.');

echo "PermissionAndCriticalFlowsTest: OK\n";
