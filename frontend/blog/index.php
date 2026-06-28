<?php
$pathPrefix = '../';
$activePage = 'blog';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Blog do JusTraduz | Simplificação e Informação Jurídica',
      'description' => 'Acompanhe artigos didáticos sobre direito, contratos e explicações simples de termos jurídicos complexos no blog oficial do JusTraduz.',
      'canonical' => 'https://justraduz.com.br/blog',
      'robots' => 'index, follow'
    ]);
  ?>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
  <link rel="manifest" href="../site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <meta name="application-name" content="JusTraduz">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700;800&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css?v=site-polish-20260625">
  <script src="../assets/js/cookie-consent.js?v=2026.06.25-1"></script>
</head>
<body class="blog-index-page terms-page-enhanced">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="terms-modern-hero">
      <div class="container terms-modern-hero-inner">
        <span class="contact-kicker">Conteúdo e Artigos</span>
        <h1>Blog do <em>JusTraduz</em></h1>
        <p>
          Artigos úteis e informativos para descomplicar termos jurídicos e ajudar você a ler qualquer documento sem jargões.
        </p>
      </div>
    </section>

    <section class="terms-modern-content">
      <div class="container" style="max-width: 1000px;">
        <div style="display: grid; gap: 32px; margin-bottom: 72px;">
          
          <article class="feedback-card" style="padding: 32px; display: grid; gap: 16px; border: 1px solid rgba(0,0,0,0.08); background: #ffffff;">
            <span style="font-size: 13px; text-transform: uppercase; color: var(--primary); font-weight: 700; letter-spacing: 0.05em;">Dicionário Didático</span>
            <h2 style="margin: 0; font-size: 24px;"><a href="o-que-e-juridiques" style="color: #172033; text-decoration: none; transition: color 0.2s;">O que é juridiquês e por que ele atrapalha a sua vida?</a></h2>
            <p style="margin: 0; font-size: 15px; color: rgba(0,0,0,0.65); line-height: 1.6;">
              Entenda a origem do jargão técnico excessivo do Direito e conheça estratégias práticas para ler termos e documentos no cotidiano sem se intimidar.
            </p>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; flex-wrap: wrap; gap: 12px;">
              <span style="font-size: 13px; color: rgba(0,0,0,0.48);">Publicado em: 28 de Junho de 2026.</span>
              <a href="o-que-e-juridiques" class="btn btn-outline btn-sm">Ler artigo completo</a>
            </div>
          </article>

          <article class="feedback-card" style="padding: 32px; display: grid; gap: 16px; border: 1px solid rgba(0,0,0,0.08); background: #ffffff;">
            <span style="font-size: 13px; text-transform: uppercase; color: var(--primary); font-weight: 700; letter-spacing: 0.05em;">Guia Prático</span>
            <h2 style="margin: 0; font-size: 24px;"><a href="como-entender-contrato" style="color: #172033; text-decoration: none; transition: color 0.2s;">Como entender um contrato sem precisar de dicionário</a></h2>
            <p style="margin: 0; font-size: 15px; color: rgba(0,0,0,0.65); line-height: 1.6;">
              Aprenda a analisar as principais cláusulas de um contrato comercial ou residencial (objeto, multa e foro) de forma descomplicada.
            </p>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; flex-wrap: wrap; gap: 12px;">
              <span style="font-size: 13px; color: rgba(0,0,0,0.48);">Publicado em: 28 de Junho de 2026.</span>
              <a href="como-entender-contrato" class="btn btn-outline btn-sm">Ler artigo completo</a>
            </div>
          </article>

          <article class="feedback-card" style="padding: 32px; display: grid; gap: 16px; border: 1px solid rgba(0,0,0,0.08); background: #ffffff;">
            <span style="font-size: 13px; text-transform: uppercase; color: var(--primary); font-weight: 700; letter-spacing: 0.05em;">Terminologia</span>
            <h2 style="margin: 0; font-size: 24px;"><a href="termos-juridicos-mais-comuns" style="color: #172033; text-decoration: none; transition: color 0.2s;">Os termos jurídicos mais comuns explicados em português simples</a></h2>
            <p style="margin: 0; font-size: 15px; color: rgba(0,0,0,0.65); line-height: 1.6;">
              Dicionário prático com tradução didática dos termos mais frequentes de contratos e notificações, como "sucumbência", "trânsito em julgado" e "outorgante".
            </p>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; flex-wrap: wrap; gap: 12px;">
              <span style="font-size: 13px; color: rgba(0,0,0,0.48);">Publicado em: 28 de Junho de 2026.</span>
              <a href="termos-juridicos-mais-comuns" class="btn btn-outline btn-sm">Ler artigo completo</a>
            </div>
          </article>

        </div>

        <div class="feedback-card" style="border-left: 4px solid var(--primary); background: rgba(0,143,128,0.03); padding: 32px; text-align: center; margin-bottom: 48px;">
          <h3 style="margin-top: 0; color: var(--primary); font-size: 20px;">Precisa traduzir um documento agora?</h3>
          <p style="font-size: 15px; line-height: 1.6; max-width: 600px; margin: 0 auto 24px;">
            Envie seu documento e deixe nossa Inteligência Artificial organizar as informações e resumir em linguagem simples.
          </p>
          <a class="btn btn-primary" href="../login.html?cadastro">Criar minha conta e começar</a>
        </div>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/' . $pathPrefix . 'includes/footer.php'; ?>

  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/accessibility.js?v=2026.06.14-06"></script>
  <script src="../assets/js/vlibras-init.js?v=2026.06.25-1" defer></script>
</body>
</html>
