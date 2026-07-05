<?php
$pathPrefix = '';
$activePage = 'ajuda-juridica-online';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Ajuda Jurídica Online | JusTraduz',
      'description' => 'Organize sua dúvida e encontre ajuda jurídica online. O JusTraduz conecta você a profissionais de forma estruturada, ética e segura.',
      'canonical' => 'https://justraduz.com.br/ajuda-juridica-online',
      'robots' => 'index, follow'
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
  <link rel="stylesheet" href="assets/css/style.css?v=2026.07.02-feedback-stable-1">
  <script src="assets/js/cookie-consent.js?v=2026.07.02-vlibras-1"></script>
</head>
<body class="seo-help-online-page terms-page-enhanced terms-story-page">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="terms-modern-hero">
      <div class="container terms-modern-hero-inner">
        <span class="contact-kicker">Organização e Conexão Ética</span>
        <h1>Ajuda jurídica online</h1>
        <p>
          Organize sua dúvida com clareza antes de falar com um especialista. Evite o desperdício de tempo e garanta um atendimento objetivo.
        </p>

        <div class="terms-hero-actions">
          <a class="home-flow-button" href="login.html?cadastro">
            <svg class="home-flow-button-arrow home-flow-button-arrow-left svg-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14"/>
              <path d="m13 6 6 6-6 6"/>
            </svg>
            <span>Pedir orientação</span>
            <span class="home-flow-button-circle" aria-hidden="true"></span>
            <svg class="home-flow-button-arrow home-flow-button-arrow-right svg-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14"/>
              <path d="m13 6 6 6-6 6"/>
            </svg>
          </a>
          <a class="btn btn-outline home-btn-outline" href="#como-conectar">Como conectar</a>
        </div>
      </div>
    </section>

    <section id="como-conectar" class="terms-modern-content">
      <div class="container terms-modern-grid">
        <aside class="terms-modern-aside">
          <h2>Conexão com profissionais</h2>
          <a href="#como-funciona-ajuda" data-terms-nav>Como funciona?</a>
          <a href="#papel-plataforma" data-terms-nav>Papel do sistema</a>
          <a href="#diretrizes-eticas" data-terms-nav>Diretrizes éticas</a>
          <a href="#limites-consulta" data-terms-nav>Limitações importantes</a>
        </aside>

        <article class="terms-modern-card">
          <div id="como-funciona-ajuda" class="terms-modern-section-static how-step" style="margin-bottom: 48px;">
            <h2>Como funciona a ajuda jurídica no sistema?</h2>
            <p>
              Muitas pessoas têm dificuldade em explicar o seu problema para um profissional do direito ou acabam esquecendo detalhes cruciais. O JusTraduz ajuda a evitar isso estruturando a sua solicitação em poucos passos:
            </p>
            <ul style="margin: 16px 0; padding-left: 20px; display: grid; gap: 8px;">
              <li>Você envia o seu documento e lê a tradução simples por inteligência artificial.</li>
              <li>A plataforma auxilia você a formular as perguntas certas e a registrar o contexto temporal.</li>
              <li>O sistema gera uma <strong>Solicitação Estruturada</strong> (caso), pronta para ser revisada e encaminhada.</li>
            </ul>
          </div>

          <div id="papel-plataforma" class="terms-modern-section-static how-step" style="margin-bottom: 48px;">
            <h2>Qual o papel da plataforma?</h2>
            <p>
              O JusTraduz funciona como um facilitador de comunicação. Nós fornecemos ferramentas de organização de documentos e histórico para que você possa apresentar sua dúvida de forma didática.
            </p>
            <p>
              De acordo com a sua escolha, a plataforma pode conectar você a advogados parceiros cadastrados ou estagiários de direito supervisionados, que analisarão a solicitação e poderão iniciar um atendimento direto via chat ou agendamento de reuniões na própria plataforma.
            </p>
          </div>

          <div id="diretrizes-eticas" class="terms-modern-section-static how-step" style="margin-bottom: 48px;">
            <h2>Diretrizes e conformidade ética</h2>
            <p>
              O JusTraduz atua em total consonância com as regras éticas da Ordem dos Advogados do Brasil (OAB) e não realiza captação ilegal de clientela. Os profissionais cadastrados mantêm total independência técnica em sua atuação, e o cadastro na plataforma é voluntário.
            </p>
          </div>

          <div id="limites-consulta" class="terms-modern-section-static how-step how-step-warning" style="border-top: 1px solid rgba(0,0,0,0.08); padding-top: 32px;">
            <div class="feedback-card how-warning-card" style="border-left: 4px solid var(--primary); background: rgba(0,143,128,0.03); padding: 24px;">
              <h3 style="margin-top: 0; color: var(--primary);">⚠️ Termos do Serviço e Limitações</h3>
              <p style="font-size: 14px; line-height: 1.6; margin-bottom: 12px;">
                <strong>O JusTraduz não realiza consultas gratuitas e não promete qualquer decisão judicial favorável.</strong>
              </p>
              <p style="font-size: 14px; line-height: 1.6; margin-bottom: 12px;">
                Os termos financeiros de honorários e consultas são acordados de forma direta e independente entre você e o profissional parceiro conectado, observando as tabelas éticas locais.
              </p>
              <p style="font-size: 14px; line-height: 1.6; margin-bottom: 0;">
                O uso do sistema de inteligência artificial ou a abertura de um caso na plataforma não constituem relação advogado-cliente formal até que haja contratação direta com um dos profissionais.
              </p>
            </div>
            <div style="margin-top: 32px; display: flex; gap: 16px; flex-wrap: wrap;">
              <a class="btn btn-primary" href="login.html?cadastro">Iniciar solicitação</a>
              <a class="btn btn-outline" href="para-clientes">Sou cliente</a>
            </div>
          </div>
        </article>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/' . $pathPrefix . 'includes/footer.php'; ?>

  <script src="assets/js/main.js?v=2026.07.05-main-modules-1"></script>
  <script src="assets/js/accessibility.js?v=2026.07.02-vlibras-1"></script>
  <script src="assets/js/vlibras-init.js?v=2026.07.02-vlibras-1" defer></script>
</body>
</html>
