<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$userId = current_user_id();
$caseId = (int) ($_GET['case_id'] ?? 0);
$errorMessage = trim((string) ($_GET['erro'] ?? ''));

function chat_cases_has_document_id(PDO $pdo): bool
{
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM cases WHERE Field = 'document_id'");
    $hasColumn = (bool) $stmt->fetch();
    return $hasColumn;
}

function chat_status_badge_class(string $status): string
{
    return match ($status) {
        'finalizado' => 'badge-success',
        'em_andamento' => 'badge-info',
        default => 'badge-warning',
    };
}

function chat_priority_badge_class(string $priority): string
{
    return $priority === 'alta' ? 'badge-warning' : 'badge-info';
}

function chat_other_party(array $case, string $type): string
{
    if ($type === 'cliente') {
        return (string) ($case['advogado'] ?? 'Aguardando profissional');
    }

    if ($type === 'advogado') {
        return (string) ($case['cliente'] ?? 'Cliente');
    }

    return trim((string) ($case['cliente'] ?? 'Cliente') . ' / ' . (string) ($case['advogado'] ?? 'sem profissional'));
}

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

$hasDocumentColumn = chat_cases_has_document_id($pdo);
$documentSelect = $hasDocumentColumn ? ', c.document_id, d.nome_arquivo AS document_name' : ', NULL AS document_id, NULL AS document_name';
$documentJoin = $hasDocumentColumn ? ' LEFT JOIN documents d ON d.id = c.document_id' : '';
$where = [];
$params = [];

if ($type === 'cliente') {
    $where[] = 'c.cliente_id = ?';
    $params[] = $userId;
} elseif ($type === 'advogado') {
    $where[] = 'c.advogado_id = ?';
    $params[] = $userId;
} elseif ($type !== 'admin') {
    $where[] = '0 = 1';
}

$sql = "SELECT c.id, c.cliente_id, c.advogado_id, c.titulo, c.descricao, c.status, c.prioridade, c.created_at,
               cli.nome AS cliente, adv.nome AS advogado,
               (SELECT COUNT(*) FROM messages m WHERE m.case_id = c.id) AS message_count,
               (SELECT MAX(m.created_at) FROM messages m WHERE m.case_id = c.id) AS last_message_at,
               (SELECT COUNT(*) FROM tasks t WHERE t.case_id = c.id) AS task_count,
               (SELECT COUNT(*) FROM appointments a WHERE a.case_id = c.id AND a.status <> 'cancelado') AS appointment_count
               $documentSelect
        FROM cases c
        INNER JOIN users cli ON cli.id = c.cliente_id
        LEFT JOIN users adv ON adv.id = c.advogado_id
        $documentJoin";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " ORDER BY FIELD(c.prioridade, 'alta', 'media', 'baixa'), FIELD(c.status, 'aberto', 'em_andamento', 'finalizado'), c.created_at DESC";
$cases = fetch_all($pdo, $sql, $params);

if (!$caseId && $cases) {
    $caseId = (int) $cases[0]['id'];
}

$allowedCaseIds = array_map(static fn (array $case): int => (int) $case['id'], $cases);
if ($caseId && !in_array($caseId, $allowedCaseIds, true)) {
    $caseId = 0;
}

$selectedCase = null;
foreach ($cases as $case) {
    if ((int) $case['id'] === $caseId) {
        $selectedCase = $case;
        break;
    }
}

$messages = $caseId ? fetch_all(
    $pdo,
    'SELECT m.*, u.nome, u.tipo
     FROM messages m
     INNER JOIN users u ON u.id = m.sender_id
     WHERE m.case_id = ?
     ORDER BY m.created_at ASC',
    [$caseId]
) : [];

$canCompose = $selectedCase && (($selectedCase['status'] ?? '') !== 'finalizado' || $type === 'admin');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chat | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <meta name="application-name" content="JusTraduz">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="JusTraduz">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="msapplication-TileColor" content="#008f80">
  <link rel="stylesheet" href="assets/css/style.css?v=sidebar-open-button-1">
  <script src="assets/js/pwa.js" defer></script>
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'chat.php'); ?>

    <main class="app-main chat-main">
      <?php render_topbar('Chat por caso', 'Conversa vinculada ao atendimento selecionado.', current_user_name()); ?>

      <?php if ($errorMessage !== ''): ?>
        <div class="alert is-visible alert-error"><?= e($errorMessage) ?></div>
      <?php endif; ?>

      <?php if (!$cases): ?>
        <?= empty_state($type === 'cliente' ? 'Nenhum chat disponível. Abra uma solicitação para iniciar o atendimento.' : 'Nenhum caso aceito para conversar no momento.') ?>
      <?php else: ?>
        <section class="chat-layout">
          <aside class="chat-list">
            <?php foreach ($cases as $case): ?>
              <a class="chat-item <?= (int) $case['id'] === $caseId ? 'active' : '' ?>" href="chat.php?case_id=<?= (int) $case['id'] ?>">
                <strong><?= e($case['titulo']) ?></strong>
                <span><?= e(chat_other_party($case, $type)) ?></span>
                <small>
                  <?= e(status_label($case['status'] ?? '')) ?>
                  <?php if (!empty($case['last_message_at'])): ?>
                    | <?= e(date('d/m H:i', strtotime((string) $case['last_message_at']))) ?>
                  <?php endif; ?>
                </small>
              </a>
            <?php endforeach; ?>
          </aside>

          <div class="chat-panel">
            <?php if (!$selectedCase): ?>
              <div class="chat-case-context">
                <?= empty_state('Selecione um caso para abrir o chat.') ?>
              </div>
            <?php else: ?>
              <section class="chat-case-context">
                <div class="chat-case-bar">
                  <div class="chat-case-title">
                    <h2><?= e($selectedCase['titulo']) ?></h2>
                    <div class="chat-head-badges">
                      <span class="badge <?= e(chat_status_badge_class((string) $selectedCase['status'])) ?>"><?= e(status_label($selectedCase['status'] ?? '')) ?></span>
                      <span class="badge <?= e(chat_priority_badge_class((string) $selectedCase['prioridade'])) ?>"><?= e(status_label($selectedCase['prioridade'] ?? '')) ?></span>
                      <span class="chat-party"><?= e(chat_other_party($selectedCase, $type)) ?></span>
                    </div>
                  </div>

                  <div class="chat-context-actions">
                    <a class="btn btn-outline btn-sm" href="acompanhar-solicitacoes.php"><?= icon_svg('case') ?> Casos</a>
                    <details class="chat-more-actions">
                      <summary>Mais ações</summary>
                      <div>
                        <a class="btn btn-outline btn-sm" href="tarefas.php?case_id=<?= $caseId ?>"><?= icon_svg('check') ?> Tarefas (<?= e((string) (int) $selectedCase['task_count']) ?>)</a>
                        <a class="btn btn-soft btn-sm" href="agenda.php"><?= icon_svg('calendar') ?> Agenda (<?= e((string) (int) $selectedCase['appointment_count']) ?>)</a>
                        <?php if (!empty($selectedCase['document_id'])): ?>
                          <a class="btn btn-outline btn-sm" href="visualizar-documento.php?id=<?= (int) $selectedCase['document_id'] ?>"><?= icon_svg('file') ?> Documento</a>
                        <?php endif; ?>
                      </div>
                    </details>
                  </div>
                </div>

                <details class="chat-case-details">
                  <summary>Detalhes do caso</summary>
                  <p><?= e($selectedCase['descricao'] ?: 'Sem descrição cadastrada.') ?></p>
                  <div class="chat-context-grid">
                    <div><span>Cliente</span><strong><?= e($selectedCase['cliente'] ?? '-') ?></strong></div>
                    <div><span>Responsável</span><strong><?= e($selectedCase['advogado'] ?? 'Aguardando') ?></strong></div>
                    <div><span>Mensagens</span><strong><?= e((string) (int) $selectedCase['message_count']) ?></strong></div>
                    <div><span>Criado em</span><strong><?= e(date('d/m/Y H:i', strtotime((string) $selectedCase['created_at']))) ?></strong></div>
                  </div>
                </details>
              </section>

              <div class="chat-messages" data-chat-messages>
                <?php if (!$messages): ?>
                  <div class="message">Nenhuma mensagem enviada ainda. Comece com uma pergunta objetiva sobre o caso.</div>
                <?php else: ?>
                  <?php foreach ($messages as $message): ?>
                    <div class="message <?= (int) $message['sender_id'] === $userId ? 'out' : '' ?>">
                      <div class="message-meta">
                        <strong><?= e($message['nome']) ?></strong>
                        <time><?= e(date('d/m/Y H:i', strtotime((string) $message['created_at']))) ?></time>
                      </div>
                      <?php if ((string) ($message['mensagem'] ?? '') !== ''): ?>
                        <div><?= nl2br(e($message['mensagem'])) ?></div>
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
                          <a class="attachment-download" href="<?= e($downloadUrl) ?>" title="Baixar anexo">
                            <?= icon_svg('download') ?>
                            <span>Baixar</span>
                          </a>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <?php if ($canCompose): ?>
                <form class="chat-compose" action="<?= e(app_url('/backend/public/index.php?rota=/messages/send')) ?>" method="post" enctype="multipart/form-data" data-chat-form>
                  <?= csrf_input() ?>
                  <input type="hidden" name="case_id" value="<?= $caseId ?>">
                  <label class="btn btn-outline chat-attach-btn" title="Anexar arquivo">
                    <?= icon_svg('paperclip') ?>
                    <span>Anexar</span>
                    <input type="file" name="anexo" accept=".pdf,.png,.jpg,.jpeg,.webp,.txt,.doc,.docx" data-chat-file>
                  </label>
                  <div class="chat-input-stack">
                    <input class="input" type="text" name="mensagem" placeholder="Digite uma mensagem sobre este caso" data-chat-input>
                    <span class="chat-file-name" data-chat-file-name></span>
                  </div>
                  <button class="btn btn-primary" type="submit"><?= icon_svg('mail') ?> Enviar</button>
                </form>
              <?php else: ?>
                <div class="chat-compose chat-compose-locked">
                  <span class="badge badge-success">Caso finalizado</span>
                  <p>Novas mensagens estão bloqueadas para manter o histórico encerrado.</p>
                </div>
              <?php endif; ?>
            <?php endif; ?>
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
  <?php render_vlibras(); ?>
</body>
</html>
