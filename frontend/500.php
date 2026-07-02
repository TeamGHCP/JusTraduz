<?php
require_once __DIR__ . '/includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Erro interno | JusTraduz',
      'description' => 'Ocorreu um erro interno do sistema. Estamos trabalhando para estabilizar a plataforma o quanto antes.',
      'canonical' => 'https://justraduz.com.br/500',
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
  <link rel="stylesheet" href="assets/css/style.css?v=2026.07.02-vlibras-panel-1">
  <script src="assets/js/cookie-consent.js?v=2026.07.02-vlibras-1"></script>
</head>
<body class="error-page">
  <main class="error-screen">
    <header class="error-topbar">
      <a class="error-brand" href="index.php" aria-label="JusTraduz - página inicial">
        <img src="assets/img/logo.png" alt="JusTraduz">
      </a>
      <nav class="error-nav" aria-label="Navegacao de apoio">
        <a href="index.php#seguranca">Segurança</a>
        <a href="contato">Suporte</a>
      </nav>
    </header>

    <section class="error-panel error-panel-server" aria-labelledby="error-title">
      <div class="error-copy">
        <span class="error-kicker">Erro 500</span>
        <h1 id="error-title">Estamos ajustando uma instabilidade.</h1>
        <p class="error-text">A solicitação não foi concluída agora. Espere alguns instantes e tente novamente. Se o problema continuar, fale com o suporte.</p>

        <div class="error-actions">
          <a class="btn btn-primary" href="index.php">Voltar para o início</a>
          <a class="btn btn-outline" href="contato">Contatar suporte</a>
        </div>
      </div>

      <aside class="error-console" aria-label="Resumo do erro">
        <div class="error-console-head">
          <span class="error-dot error-dot-warning"></span>
          <strong>JusTraduz</strong>
          <small>Status do sistema</small>
        </div>
        <div class="error-document">
          <span class="error-code">500</span>
          <h2>Processamento interrompido</h2>
          <p>Evite reenviar dados sensíveis até iniciar uma nova tentativa.</p>
          <div class="error-document-lines" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
          </div>
        </div>
        <div class="error-checklist">
          <a href="login.html">Tentar acessar novamente</a>
          <a href="contato">Reportar problema</a>
          <a href="privacidade">Ver privacidade</a>
        </div>
      </aside>
    </section>
  </main>
  <script src="assets/js/accessibility.js?v=2026.07.02-vlibras-1"></script>
</body>
</html>
