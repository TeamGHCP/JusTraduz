<?php
require_once __DIR__ . '/includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Página não encontrada | JusTraduz',
      'description' => 'Não encontramos a página solicitada. Retorne para o JusTraduz e continue navegando com segurança.',
      'canonical' => 'https://justraduz.com.br/404',
      'robots' => 'noindex, nofollow'
    ]);
  ?>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <meta name="application-name" content="JusTraduz">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700;800&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=site-polish-20260625">
  <script src="assets/js/cookie-consent.js?v=2026.06.25-1"></script>
</head>
<body class="error-page">
  <main class="error-screen">
    <header class="error-topbar">
      <a class="error-brand" href="index.php" aria-label="JusTraduz - página inicial">
        <img src="assets/img/logo.png" alt="JusTraduz">
      </a>
      <nav class="error-nav" aria-label="Navegacao de apoio">
        <a href="index.php#recursos">Recursos</a>
        <a href="contato">Suporte</a>
      </nav>
    </header>

    <section class="error-panel" aria-labelledby="error-title">
      <div class="error-copy">
        <span class="error-kicker">Erro 404</span>
        <h1 id="error-title">Não encontramos essa página.</h1>
        <p class="error-text">O endereço pode ter mudado ou o link pode estar incompleto. Volte para uma área segura do JusTraduz e continue de onde parou.</p>

        <div class="error-actions">
          <a class="btn btn-primary" href="index.php">Voltar para o início</a>
          <a class="btn btn-outline" href="login.html">Acessar conta</a>
        </div>
      </div>

      <aside class="error-console" aria-label="Resumo do erro">
        <div class="error-console-head">
          <span class="error-dot"></span>
          <strong>JusTraduz</strong>
          <small>Verificação de rota</small>
        </div>
        <div class="error-document">
          <span class="error-code">404</span>
          <h2>Rota indisponível</h2>
          <p>Não há uma página publicada para este caminho.</p>
          <div class="error-document-lines" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
          </div>
        </div>
        <div class="error-checklist">
          <a href="index.php">Ir para a landing page</a>
          <a href="login.html?cadastro">Criar uma conta</a>
          <a href="contato">Solicitar ajuda</a>
        </div>
      </aside>
    </section>
  </main>
  <script src="assets/js/accessibility.js?v=2026.06.14-06"></script>
</body>
</html>
