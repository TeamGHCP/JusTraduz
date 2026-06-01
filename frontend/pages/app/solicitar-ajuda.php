<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['cliente']);

$lawyers = fetch_all($pdo, "SELECT id, nome, oab, oab_uf FROM users WHERE tipo = 'advogado' AND status = 'ativo' AND (oab_verificado = TRUE OR (status_cna = 'pendente' AND COALESCE(oab, '') <> '' AND COALESCE(oab_uf, '') <> '')) ORDER BY nome");
$selectedLawyerId = (int) ($_GET['advogado_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitar ajuda | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=theme-slow-2">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar('cliente', 'solicitar-ajuda.php'); ?>

    <main class="app-main">
      <?php render_topbar('Solicitar ajuda jurídica', 'Escolha um advogado específico ou deixe sua solicitação aberta.', current_user_name()); ?>

      <form class="card auth-form" action="<?= e(app_url('/backend/public/index.php?rota=/cases/create')) ?>" method="post">
        <?= csrf_input() ?>
        <div class="form-grid">
          <div class="field">
            <label for="titulo">Título da solicitação</label>
            <input class="input" id="titulo" name="titulo" required>
          </div>
          <div class="field">
            <label for="prioridade">Prioridade</label>
            <select class="select" id="prioridade" name="prioridade">
              <option value="baixa">Baixa</option>
              <option value="media" selected>Média</option>
              <option value="alta">Alta</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="advogado_id">Advogado específico</label>
          <select class="select" id="advogado_id" name="advogado_id">
            <option value="">Deixar solicitação aberta</option>
            <?php foreach ($lawyers as $lawyer): ?>
              <option value="<?= (int) $lawyer['id'] ?>" <?= $selectedLawyerId === (int) $lawyer['id'] ? 'selected' : '' ?>>
                <?= e($lawyer['nome']) ?><?= $lawyer['oab'] ? ' - OAB/' . e($lawyer['oab_uf']) . ' ' . e($lawyer['oab']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="descricao">Descreva sua dúvida</label>
          <textarea class="textarea" id="descricao" name="descricao" required></textarea>
        </div>
        <div class="alert alert-success is-visible">A análise automática não substitui orientação jurídica profissional.</div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= icon_svg('help') ?> Enviar solicitação</button>
          <a class="btn btn-outline" href="lista-advogados.php"><?= icon_svg('users') ?> Ver advogados</a>
        </div>
      </form>
    </main>
  </div>
</body>
</html>
