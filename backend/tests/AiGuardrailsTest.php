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

$prompt = callPrivate(GeminiService::class, 'buildChatPrompt', ['Quero ajuda', []]);
assertTrue(str_contains($prompt, 'Nao calcule prazos processuais'), 'Prompt deve proibir cálculo de prazos.');
assertTrue(str_contains($prompt, 'dados nao confiaveis'), 'Prompt deve tratar entrada como não confiável.');

$_SERVER['REMOTE_ADDR'] = '127.0.0.240';
$_SESSION['_ai_chat_attempts'] = [];
$rateLimitPath = dirname(__DIR__) . '/storage/rate-limits/ai-' . hash('sha256', $_SERVER['REMOTE_ADDR']) . '.json';
if (is_file($rateLimitPath)) {
    unlink($rateLimitPath);
}
$limiter = new AiRateLimiter();
for ($index = 0; $index < 10; $index++) {
    assertTrue($limiter->consume()['allowed'] === true, 'As dez primeiras mensagens deveriam ser permitidas.');
}
assertTrue($limiter->consume()['allowed'] === false, 'A décima primeira mensagem deveria ser limitada.');

echo "AiGuardrailsTest: OK\n";
