<?php
http_response_code(403);
require_once __DIR__ . '/includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Acesso negado | JusTraduz',
      'description' => 'Voce nao tem permissao para acessar esta area do JusTraduz.',
      'canonical' => 'https://justraduz.com.br/403',
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
  <link rel="stylesheet" href="assets/css/style.css?v=2026.07.05-style-cache-1">
  <script src="assets/js/cookie-consent.js?v=2026.07.02-vlibras-1"></script>
</head>
<body class="error-page">
  <main class="error-screen">
    <header class="error-topbar">
      <a class="error-brand" href="index.php" aria-label="JusTraduz - pagina inicial">
        <img src="assets/img/logo.png" alt="JusTraduz">
      </a>
      <nav class="error-nav" aria-label="Navegacao de apoio">
        <a href="index.php#seguranca">Seguranca</a>
        <a href="contato">Suporte</a>
      </nav>
    </header>

    <section class="error-panel" aria-labelledby="error-title">
      <div class="error-copy">
        <span class="error-kicker">Erro 403</span>
        <h1 id="error-title">Acesso restrito.</h1>
        <p class="error-text">Esta area exige permissao ou uma sessao valida. Entre com uma conta autorizada ou volte para uma area publica do JusTraduz.</p>

        <div class="error-actions">
          <a class="btn btn-primary" href="login.html">Entrar na conta</a>
          <a class="btn btn-outline" href="index.php">Voltar para o inicio</a>
        </div>
      </div>

      <aside class="error-console" aria-label="Resumo do erro">
        <div class="error-console-head">
          <span class="error-dot"></span>
          <strong>JusTraduz</strong>
          <small>Controle de acesso</small>
        </div>
        <div class="error-document">
          <span class="error-code">403</span>
          <h2>Permissao insuficiente</h2>
          <p>O servidor bloqueou este caminho para proteger dados e configuracoes internas.</p>
          <div class="error-document-lines" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
          </div>
        </div>
        <div class="error-checklist">
          <a href="login.html">Entrar novamente</a>
          <a href="contato">Solicitar suporte</a>
          <a href="privacidade">Ver privacidade</a>
        </div>
      </aside>
    </section>
  </main>
  <script src="assets/js/accessibility.js?v=2026.07.05-a11y-global-1"></script>
</body>
</html>
