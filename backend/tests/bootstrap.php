<?php

putenv('APP_DEBUG=false');
putenv('APP_BASE_PATH=JusTraduz');
putenv('DB_DSN=sqlite::memory:');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'JusTraduzTest/1.0';
$_SERVER['SCRIPT_NAME'] = '/JusTraduz/backend/public/index.php';

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/core/RedirectException.php';

function test_pdo(): PDO
{
    return database_connection();
}

function reset_test_state(): void
{
    $_GET = [];
    $_POST = [];
    $_FILES = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'JusTraduzTest/1.0';
    $_SERVER['HTTP_ACCEPT'] = '';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = '';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
    }
    $_SESSION = [];
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertEquals($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

function assertStringContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ' Procurado: ' . $needle . ' Em: ' . $haystack);
    }
}

function callPrivate(object|string $target, string $method, array $arguments = [])
{
    $reflection = new ReflectionMethod($target, $method);
    $reflection->setAccessible(true);
    return $reflection->invoke(is_object($target) ? $target : null, ...$arguments);
}

function expectRedirect(callable $callback): string
{
    try {
        $callback();
    } catch (RedirectException $exception) {
        return $exception->getUrl();
    }

    throw new RuntimeException('Era esperado um redirect.');
}

function build_test_schema(PDO $pdo): void
{
    $schema = [
        'CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            senha TEXT NOT NULL,
            tipo TEXT NOT NULL,
            oab TEXT,
            oab_uf TEXT,
            oab_status TEXT,
            oab_rejection_reason TEXT,
            oab_submitted_at TEXT,
            oab_validated_at TEXT,
            oab_validated_by INTEGER,
            oab_parametro TEXT,
            oab_verificado INTEGER DEFAULT 0,
            oab_tipo TEXT,
            status_cna TEXT,
            cna_validado_em TEXT,
            cna_origem TEXT,
            cna_payload_cache TEXT,
            cna_ultimo_erro TEXT,
            cna_tentativas INTEGER DEFAULT 0,
            telefone TEXT,
            cpf TEXT,
            foto_perfil TEXT,
            google_sub TEXT UNIQUE,
            google_picture TEXT,
            google_linked_at TEXT,
            provider TEXT,
            profile_completed INTEGER DEFAULT 1,
            email_verified_at TEXT,
            status TEXT DEFAULT "ativo",
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT
        )',
        'CREATE TABLE documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            nome_arquivo TEXT,
            tipo_arquivo TEXT,
            caminho TEXT,
            texto_extraido TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE ai_results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            resumo TEXT,
            explicacao TEXT,
            confianca REAL,
            modelo TEXT,
            prompt_versao TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE cases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER NOT NULL,
            advogado_id INTEGER,
            document_id INTEGER,
            titulo TEXT,
            descricao TEXT,
            status TEXT DEFAULT "aberto",
            prioridade TEXT DEFAULT "media",
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            case_id INTEGER NOT NULL,
            sender_id INTEGER NOT NULL,
            mensagem TEXT NOT NULL,
            attachment_original_name TEXT,
            attachment_path TEXT,
            attachment_mime TEXT,
            attachment_size INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            case_id INTEGER NOT NULL,
            titulo TEXT,
            descricao TEXT,
            status TEXT DEFAULT "pendente",
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE schedule_slots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            professional_id INTEGER NOT NULL,
            starts_at TEXT NOT NULL,
            ends_at TEXT NOT NULL,
            status TEXT DEFAULT "livre",
            titulo TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT
        )',
        'CREATE TABLE appointments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slot_id INTEGER NOT NULL,
            client_id INTEGER NOT NULL,
            case_id INTEGER,
            assunto TEXT NOT NULL,
            observacoes TEXT,
            status TEXT DEFAULT "agendado",
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT
        )',
        'CREATE TABLE notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            mensagem TEXT,
            lida INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            entity_type TEXT,
            entity_id INTEGER,
            details TEXT,
            ip_address TEXT,
            user_agent TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE password_reset_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            email TEXT NOT NULL,
            code_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            used_at TEXT,
            attempts INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE cna_validacao_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            profissional_id INTEGER NOT NULL,
            admin_id INTEGER,
            acao TEXT NOT NULL,
            status_anterior TEXT,
            status_novo TEXT,
            origem TEXT,
            mensagem TEXT,
            erro_resumido TEXT,
            justificativa TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE external_processes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            owner_type TEXT NOT NULL,
            source TEXT NOT NULL DEFAULT "datajud",
            query_type TEXT NOT NULL,
            query_value TEXT NOT NULL,
            process_number TEXT NOT NULL,
            tribunal TEXT,
            uf TEXT,
            comarca TEXT,
            tipo_processo TEXT,
            classe_processual TEXT,
            assunto TEXT,
            status_inferido TEXT,
            status_normalizado TEXT,
            link TEXT,
            data_ultima_atualizacao TEXT,
            data_andamento_mais_recente TEXT,
            payload_json TEXT,
            last_synced_at TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT
        )',
        'CREATE TABLE job_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "pending",
            payload_json TEXT NOT NULL,
            user_id INTEGER,
            priority INTEGER NOT NULL DEFAULT 0,
            attempts INTEGER NOT NULL DEFAULT 0,
            max_attempts INTEGER NOT NULL DEFAULT 3,
            last_error TEXT,
            available_at TEXT NOT NULL,
            locked_at TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE usage_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            feature TEXT NOT NULL,
            units INTEGER NOT NULL DEFAULT 1,
            entity_id INTEGER,
            metadata_json TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE mail_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            recipient TEXT NOT NULL,
            subject TEXT NOT NULL,
            transport TEXT NOT NULL,
            status TEXT NOT NULL,
            error_message TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )',
    ];

    foreach ($schema as $statement) {
        $pdo->exec($statement);
    }
}

function seed_test_data(PDO $pdo): void
{
    $password = password_hash('Senha@123', PASSWORD_DEFAULT);
    $users = [
        [1, 'Cliente Um', 'cliente1@teste.local', 'cliente', 0, 'not_required', null, '12345678901'],
        [2, 'Cliente Dois', 'cliente2@teste.local', 'cliente', 0, 'not_required', null, '10987654321'],
        [3, 'Advogado OK', 'advogado@teste.local', 'advogado', 1, 'approved', 'verificado', null],
        [4, 'Advogado Pendente', 'pendente@teste.local', 'advogado', 0, 'pending', 'pendente', null],
        [5, 'Admin', 'admin@teste.local', 'admin', 1, 'not_required', null, null],
        [6, 'Estagiario OK', 'estagiario@teste.local', 'estagiario', 1, 'approved', 'verificado', null],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO users (id, nome, email, senha, tipo, oab_verificado, oab_status, status_cna, cpf, status, profile_completed)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "ativo", 1)'
    );

    foreach ($users as $user) {
        $stmt->execute([$user[0], $user[1], $user[2], $password, $user[3], $user[4], $user[5], $user[6], $user[7]]);
    }

    $pdo->exec("INSERT INTO documents (id, user_id, nome_arquivo, tipo_arquivo, caminho) VALUES
        (1, 1, 'cliente-um.pdf', 'pdf', 'backend/storage/documents/test-fixtures/cliente-um.pdf'),
        (2, 2, 'cliente-dois.pdf', 'pdf', 'backend/storage/documents/test-fixtures/cliente-dois.pdf')");
    $pdo->exec("INSERT INTO cases (id, cliente_id, advogado_id, document_id, titulo, descricao, status, prioridade) VALUES
        (1, 1, 3, 1, 'Caso atendido', 'Descricao', 'em_andamento', 'media'),
        (2, 2, NULL, 2, 'Caso aberto', 'Descricao', 'aberto', 'media')");
    $pdo->exec("INSERT INTO messages (id, case_id, sender_id, mensagem) VALUES
        (1, 1, 1, 'Ola'),
        (2, 1, 3, 'Resposta')");
    $pdo->exec("INSERT INTO schedule_slots (id, professional_id, starts_at, ends_at, status, titulo) VALUES
        (1, 3, '2030-01-10 10:00:00', '2030-01-10 11:00:00', 'livre', 'Consulta'),
        (2, 4, '2030-01-10 12:00:00', '2030-01-10 13:00:00', 'livre', 'Pendente')");
    $pdo->exec("INSERT INTO appointments (id, slot_id, client_id, case_id, assunto, status) VALUES
        (1, 1, 1, 1, 'Reuniao', 'agendado')");
}
