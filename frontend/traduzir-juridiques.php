<?php
$pathPrefix = '';
$activePage = 'traduzir-juridiques';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Traduzir juridiquês online | Entenda Termos Jurídicos',
      'description' => 'Traduza juridiquês online e entenda documentos em linguagem simples. O JusTraduz descomplica contratos, notificações e termos jurídicos usando IA.',
      'canonical' => 'https://justraduz.com.br/traduzir-juridiques',
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
  <link rel="stylesheet" href="assets/css/style.css?v=2026.07.05-style-cache-1">
  <script src="assets/js/cookie-consent.js?v=2026.07.02-vlibras-1"></script>
</head>
<body class="seo-translate-page terms-page-enhanced terms-story-page">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="terms-modern-hero">
      <div class="container terms-modern-hero-inner">
        <span class="contact-kicker">Tradução Jurídica Simples</span>
        <h1>Traduzir <em>juridiquês</em> online</h1>
        <p>
          Entenda termos jurídicos complexos e traduza a linguagem jurídica de qualquer documento para palavras fáceis e diretas.
        </p>

        <div class="terms-hero-actions">
          <a class="home-flow-button" href="login.html?cadastro">
            <svg class="home-flow-button-arrow home-flow-button-arrow-left svg-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14"/>
              <path d="m13 6 6 6-6 6"/>
            </svg>
            <span>Traduzir agora</span>
            <span class="home-flow-button-circle" aria-hidden="true"></span>
            <svg class="home-flow-button-arrow home-flow-button-arrow-right svg-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14"/>
              <path d="m13 6 6 6-6 6"/>
            </svg>
          </a>
          <a class="btn btn-outline home-btn-outline" href="#sobre-juridiques">Saber mais</a>
        </div>
      </div>
    </section>

    <section id="sobre-juridiques" class="terms-modern-content">
      <div class="container terms-modern-grid">
        <aside class="terms-modern-aside">
          <h2>Tópicos explicativos</h2>
          <a href="#oque-e" data-terms-nav>O que é Juridiquês?</a>
          <a href="#por-que" data-terms-nav>Por que é difícil?</a>
          <a href="#como-ajudamos" data-terms-nav>Como o JusTraduz ajuda</a>
          <a href="#documentos" data-terms-nav>O que simplificar</a>
          <a href="#limites" data-terms-nav>Limitações da IA</a>
        </aside>

        <article class="terms-modern-card">
          <div id="oque-e" class="terms-modern-section-static how-step" style="margin-bottom: 48px;">
            <h2>O que é o juridiquês?</h2>
            <p>
              O "juridiquês" é o termo informal usado para descrever o jargão técnico excessivo, frases prolixas e termos em latim frequentemente utilizados por advogados, juízes e outros profissionais do Direito.
            </p>
            <p>
              Embora o vocabulário técnico seja importante para a precisão técnica no tribunal, o uso excessivo fora dele cria barreiras de comunicação, impedindo que cidadãos comuns compreendam seus próprios direitos e deveres em documentos cotidianos.
            </p>
          </div>

          <div id="por-que" class="terms-modern-section-static how-step" style="margin-bottom: 48px;">
            <h2>Por que os documentos jurídicos são difíceis?</h2>
            <p>
              Documentos como contratos de prestação de serviços, termos de adesão, acordos de confidencialidade e notificações extrajudiciais costumam ser construídos com sentenças extremamente longas, referências cruzadas a leis antigas e termos ambíguos.
            </p>
            <p>
              Muitas vezes, uma única cláusula que poderia ser dita em uma linha simples como "Se você cancelar antes do tempo, pagará R$ 100 de multa" é redigida em dezenas de palavras técnicas como: "O distrato antecipado ensejará a cominação de multa compensatória líquida e certa estipulada em valor equivalente a...".
            </p>
          </div>

          <div id="como-ajudamos" class="terms-modern-section-static how-step" style="margin-bottom: 48px;">
            <h2>Como o JusTraduz ajuda você a traduzir linguagem jurídica</h2>
            <p>
              O JusTraduz foi criado para ser uma ponte. Nossa tecnologia de Inteligência Artificial processa o documento e decompõe a linguagem técnica e intimidadora em uma explicação didática.
            </p>
            <ul style="margin: 16px 0; padding-left: 20px; display: grid; gap: 8px;">
              <li><strong>Tradução lado a lado:</strong> Você clica na cláusula complexa e lê a tradução imediata em português simples.</li>
              <li><strong>Detecção de obrigações e multas:</strong> O robô destaca de forma automática as cláusulas que exigem o seu dinheiro ou a sua assinatura.</li>
              <li><strong>Orientação sobre prazos:</strong> Nosso sistema lista de forma limpa todas as datas e prazos de renovação que você precisa respeitar.</li>
            </ul>
          </div>

          <div id="documentos" class="terms-modern-section-static how-step" style="margin-bottom: 48px;">
            <h2>Exemplos de documentos que podem ser simplificados</h2>
            <p>
              Você pode usar o JusTraduz para ter um entendimento preliminar de diversos tipos de textos:
            </p>
            <ul style="margin: 16px 0; padding-left: 20px; display: grid; gap: 8px;">
              <li>Contratos de locação (aluguel de imóveis)</li>
              <li>Contratos de prestação de serviços individuais</li>
              <li>Notificações extrajudiciais</li>
              <li>Políticas de privacidade e termos de uso de aplicativos</li>
              <li>Acordos civis simples</li>
            </ul>
          </div>

          <div id="limites" class="terms-modern-section-static how-step how-step-warning" style="border-top: 1px solid rgba(0,0,0,0.08); padding-top: 32px;">
            <div class="feedback-card how-warning-card" style="border-left: 4px solid var(--primary); background: rgba(0,143,128,0.03); padding: 24px;">
              <h3 style="margin-top: 0; color: var(--primary);">⚠️ Limitações Importantes</h3>
              <p style="font-size: 14px; line-height: 1.6; margin-bottom: 12px;">
                A inteligência artificial ajuda a clarear a leitura, mas ela não analisa validade jurídica de contratos, não prevê desfechos processuais e não substitui o trabalho profissional de um advogado.
              </p>
              <p style="font-size: 14px; line-height: 1.6; margin-bottom: 0;">
                O JusTraduz não realiza consultoria jurídica direta e recomenda que, antes de assinar qualquer documento importante ou tomar medidas judiciais, você consulte formalmente um profissional habilitado da advocacia ou da Defensoria Pública.
              </p>
            </div>
            <div style="margin-top: 32px; display: flex; gap: 16px; flex-wrap: wrap;">
              <a class="btn btn-primary" href="login.html?cadastro">Criar conta e começar</a>
              <a class="btn btn-outline" href="como-funciona">Ver como funciona</a>
            </div>
          </div>
        </article>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/' . $pathPrefix . 'includes/footer.php'; ?>

  <script src="assets/js/main.js?v=2026.07.05-main-modules-1"></script>
  <script src="assets/js/accessibility.js?v=2026.07.05-a11y-global-1"></script>
  <script src="assets/js/vlibras-init.js?v=2026.07.05-a11y-global-1" defer></script>
</body>
</html>
