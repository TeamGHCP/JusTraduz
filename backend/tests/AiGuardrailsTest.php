<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers/AiController.php';
require_once dirname(__DIR__) . '/app/services/AiRateLimiter.php';

build_test_schema(test_pdo());

$controller = new AiController();

assertTrue(
    callPrivate($controller, 'containsSensitiveData', ['Meu CPF é 123.456.789-00']) === true,
    'Deveria bloquear CPF.'
);
assertTrue(
    callPrivate($controller, 'containsSensitiveData', ['Escreva para pessoa@example.com']) === true,
    'Deveria bloquear e-mail.'
);
assertTrue(
    callPrivate($controller, 'containsSensitiveData', ['Processo 1234567-89.2024.8.26.0100']) === true,
    'Deveria bloquear número de processo CNJ.'
);
assertTrue(
    callPrivate($controller, 'isRestrictedLegalAdvice', ['calcule o prazo processual para recorrer']) === true,
    'Deveria recusar cálculo de prazo processual.'
);
assertTrue(
    callPrivate($controller, 'isRestrictedLegalAdvice', ['qual estrategia processual devo usar']) === true,
    'Deveria recusar estratégia processual.'
);
assertTrue(
    callPrivate($controller, 'isLegalEmergency', ['minha audiencia e amanha']) === true,
    'Deveria tratar audiência próxima como urgência.'
);
assertTrue(
    callPrivate($controller, 'isRestrictedLegalAdvice', ['quanto custa traduzir um diploma']) === false,
    'Não deveria bloquear pergunta comercial de tradução.'
);
assertTrue(
    str_contains(callPrivate($controller, 'answerLocalQuestion', ['meu documento está ruim de entender, o que fazer', []]), 'linguagem simples'),
    'Deveria responder melhor quando o documento estiver difícil de entender.'
);
assertTrue(
    str_contains(callPrivate($controller, 'answerLocalQuestion', ['ajuda para meu documento', []]), 'três formas'),
    'Deveria responder melhor a pedido genérico de ajuda com documento.'
);
assertTrue(
    str_contains(callPrivate($controller, 'answerLocalQuestion', ['tradução simples', []]), 'idioma atual'),
    'Deveria explicar tradução simples sem cair no fallback genérico.'
);
assertTrue(
    str_contains(callPrivate($controller, 'answerLocalQuestion', ['me ajude a traduzir um documento', []]), 'quatro informações'),
    'Deveria pedir dados objetivos para orientar tradução.'
);
assertTrue(
    str_contains(callPrivate($controller, 'answerLocalQuestion', ['como criar conta no site', []]), 'Criar conta'),
    'Deveria orientar cadastro de conta.'
);
assertTrue(
    str_contains(callPrivate($controller, 'answerLocalQuestion', ['esqueci minha senha', []]), 'Recuperar senha'),
    'Deveria orientar recuperação de senha.'
);
assertTrue(
    str_contains(callPrivate($controller, 'answerLocalQuestion', ['onde acompanho minha solicitação', []]), 'solicita'),
    'Deveria orientar acompanhamento de solicitações.'
);

$prompt = callPrivate(GeminiService::class, 'buildChatPrompt', ['Quero ajuda', []]);
$normalizedPrompt = normalizeTextForAssertions($prompt);
assertTrue(str_contains($normalizedPrompt, 'Nao calcule prazos processuais'), 'Prompt deve proibir c�lculo de prazos.');
assertTrue(str_contains($normalizedPrompt, 'dados nao confiaveis'), 'Prompt deve tratar entrada como n�o confi�vel.');
assertTrue(str_contains($normalizedPrompt, 'criar conta'), 'Prompt deve orientar uso da plataforma.');

$_SERVER['REMOTE_ADDR'] = '127.0.0.240';
$_SESSION['_ai_chat_attempts'] = [];
$rateLimitDirectory = getenv('RATE_LIMIT_STORAGE_PATH') ?: dirname(__DIR__) . '/storage/rate-limits';
$rateLimitPath = rtrim((string) $rateLimitDirectory, "\\/") . '/ai-' . hash('sha256', $_SERVER['REMOTE_ADDR']) . '.json';
if (is_file($rateLimitPath)) {
    @unlink($rateLimitPath);
}
$limiter = new AiRateLimiter();
for ($index = 0; $index < 10; $index++) {
    assertTrue($limiter->consume()['allowed'] === true, 'As dez primeiras mensagens deveriam ser permitidas.');
}
assertTrue($limiter->consume()['allowed'] === false, 'A décima primeira mensagem deveria ser limitada.');

echo "AiGuardrailsTest: OK\n";
