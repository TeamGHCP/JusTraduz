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

function chat_file_size(?int $bytes): string
{
    if (!$bytes || $bytes <= 0) {
        return '';
    }

    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }

    return number_format($bytes / 1024, 1, ',', '.') . ' KB';
}

function chat_attachment_preview_type(?string $mime, ?string $filename): string
{
    $mime = strtolower((string) $mime);
    $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));

    if (str_starts_with($mime, 'image/') || in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        return 'image';
    }

    if ($mime === 'application/pdf' || $extension === 'pdf') {
        return 'pdf';
    }

    return '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chat | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=theme-slow-3">
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
                    <?php if ((string) ($message['mensagem'] ?? '') !== ''): ?>
                      <?= nl2br(e($message['mensagem'])) ?>
                    <?php endif; ?>
                    <?php if (!empty($message['attachment_path'])): ?>
                      <?php
                        $attachmentUrl = app_url('/backend/public/index.php?rota=/messages/attachment&id=' . (int) $message['id']);
                        $downloadUrl = $attachmentUrl . '&download=1';
                        $attachmentName = (string) ($message['attachment_original_name'] ?? 'Anexo');
                        $attachmentSize = chat_file_size(isset($message['attachment_size']) ? (int) $message['attachment_size'] : null);
                        $previewType = chat_attachment_preview_type($message['attachment_mime'] ?? null, $attachmentName);
                      ?>
                      <div
                        class="message-attachment <?= $previewType !== '' ? 'is-previewable' : '' ?>"
                        <?= $previewType !== '' ? 'role="button" tabindex="0"' : '' ?>
                        <?= $previewType !== '' ? 'aria-label="Visualizar anexo ' . e($attachmentName) . '"' : '' ?>
                        <?php if ($previewType !== ''): ?>
                          data-attachment-url="<?= e($attachmentUrl) ?>"
                          data-attachment-name="<?= e($attachmentName) ?>"
                          data-attachment-type="<?= e($previewType) ?>"
                          title="Clique para visualizar"
                        <?php endif; ?>
                      >
                        <?= icon_svg('paperclip') ?>
                        <span>
                          <strong><?= e($attachmentName) ?></strong>
                          <?php if ($attachmentSize !== ''): ?><small><?= e($attachmentSize) ?></small><?php endif; ?>
                        </span>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <form class="chat-compose" action="<?= e(app_url('/backend/public/index.php?rota=/messages/send')) ?>" method="post" enctype="multipart/form-data" data-chat-form>
              <?= csrf_input() ?>
              <input type="hidden" name="case_id" value="<?= $caseId ?>">
              <label class="btn btn-outline chat-attach-btn" title="Anexar arquivo">
                <?= icon_svg('paperclip') ?>
                <span>Anexar</span>
                <input type="file" name="anexo" accept=".pdf,.png,.jpg,.jpeg,.webp,.txt,.doc,.docx" data-chat-file>
              </label>
              <div class="chat-input-stack">
                <input class="input" type="text" name="mensagem" placeholder="Digite sua mensagem" data-chat-input>
                <span class="chat-file-name" data-chat-file-name></span>
              </div>
              <button class="btn btn-primary" type="submit"><?= icon_svg('mail') ?> Enviar</button>
            </form>
          </div>
        </section>
      <?php endif; ?>
    </main>
  </div>
  <div class="attachment-modal" data-attachment-modal hidden>
    <div class="attachment-modal-backdrop" data-attachment-close></div>
    <section class="attachment-dialog" role="dialog" aria-modal="true" aria-labelledby="attachment-title">
      <header class="attachment-dialog-head">
        <div>
          <span>Anexo do chat</span>
          <h2 id="attachment-title" data-attachment-title>Arquivo</h2>
        </div>
        <div class="attachment-dialog-actions">
          <a class="btn btn-outline btn-sm" href="#" data-attachment-download>
            <?= icon_svg('download') ?> Baixar
          </a>
          <button class="btn btn-soft btn-sm attachment-close" type="button" data-attachment-close title="Fechar preview">
            <?= icon_svg('x') ?>
          </button>
        </div>
      </header>
      <div class="attachment-preview" data-attachment-preview></div>
    </section>
  </div>
  <script src="assets/js/chat.js"></script>
</body>
</html>
