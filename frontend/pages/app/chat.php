<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$userId = current_user_id();
$caseId = (int) ($_GET['case_id'] ?? 0);

if ($type === 'cliente') {
    $cases = fetch_all($pdo, 'SELECT c.*, u.nome AS other_name FROM cases c LEFT JOIN users u ON u.id = c.advogado_id WHERE c.cliente_id = ? ORDER BY c.created_at DESC', [$userId]);
} elseif ($type === 'advogado') {
    $cases = fetch_all($pdo, 'SELECT c.*, u.nome AS other_name FROM cases c INNER JOIN users u ON u.id = c.cliente_id WHERE c.advogado_id = ? ORDER BY c.created_at DESC', [$userId]);
} elseif ($type === 'admin') {
    $cases = fetch_all(
        $pdo,
        "SELECT c.*, CONCAT(cli.nome, ' / ', COALESCE(adv.nome, 'sem advogado')) AS other_name
         FROM cases c
         INNER JOIN users cli ON cli.id = c.cliente_id
         LEFT JOIN users adv ON adv.id = c.advogado_id
         ORDER BY c.created_at DESC"
    );
} else {
    $cases = [];
}

if (!$caseId && $cases) {
    $caseId = (int) $cases[0]['id'];
}

$allowedCaseIds = array_map(static fn (array $case): int => (int) $case['id'], $cases);
if ($caseId && !in_array($caseId, $allowedCaseIds, true)) {
    $caseId = 0;
}

$messages = $caseId ? fetch_all($pdo, 'SELECT m.*, u.nome FROM messages m INNER JOIN users u ON u.id = m.sender_id WHERE m.case_id = ? ORDER BY m.created_at ASC', [$caseId]) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chat | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'chat.php'); ?>

    <main class="app-main chat-main">
      <?php render_topbar('Chat interno', 'Mensagens vinculadas aos seus casos.', current_user_name()); ?>

      <?php if (!$cases): ?>
        <?= empty_state('Nenhum chat disponível. Abra ou aceite uma solicitação para iniciar uma conversa.') ?>
      <?php else: ?>
        <section class="chat-layout">
          <aside class="chat-list">
            <?php foreach ($cases as $case): ?>
              <a class="chat-item <?= (int) $case['id'] === $caseId ? 'active' : '' ?>" href="chat.php?case_id=<?= (int) $case['id'] ?>">
                <strong><?= e($case['titulo']) ?></strong>
                <span><?= e($case['other_name'] ?? 'Aguardando profissional') ?></span>
              </a>
            <?php endforeach; ?>
          </aside>
          <div class="chat-panel">
            <div class="chat-head"><strong>Caso #<?= $caseId ?></strong></div>
            <div class="chat-messages" data-chat-messages>
              <?php if (!$messages): ?>
                <div class="message">Nenhuma mensagem enviada ainda.</div>
              <?php else: ?>
                <?php foreach ($messages as $message): ?>
                  <div class="message <?= (int) $message['sender_id'] === $userId ? 'out' : '' ?>">
                    <strong><?= e($message['nome']) ?></strong><br>
                    <?= nl2br(e($message['mensagem'])) ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <form class="chat-compose" action="<?= e(app_url('/backend/public/index.php?rota=/messages/send')) ?>" method="post" data-chat-form>
              <?= csrf_input() ?>
              <input type="hidden" name="case_id" value="<?= $caseId ?>">
              <input class="input" type="text" name="mensagem" placeholder="Digite sua mensagem" required data-chat-input>
              <button class="btn btn-primary" type="submit"><?= icon_svg('mail') ?> Enviar</button>
            </form>
          </div>
        </section>
      <?php endif; ?>
    </main>
  </div>
  <script src="assets/js/chat.js"></script>
</body>
</html>
