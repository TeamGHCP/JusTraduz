<?php
$pathPrefix = '';
$activePage = 'seguranca-lgpd';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Segurança e LGPD | JusTraduz',
      'description' => 'Conheça as medidas de segurança e tratamento de dados adotadas pelo JusTraduz em conformidade com a LGPD e boas práticas de privacidade.',
      'canonical' => 'https://justraduz.com.br/seguranca-lgpd',
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
  <link rel="stylesheet" href="assets/css/style.css?v=global-responsive-20260628-2">
  <script src="assets/js/cookie-consent.js?v=2026.06.25-1"></script>
</head>
<body class="seo-security-lgpd-page terms-page-enhanced">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="terms-modern-hero">
      <div class="container terms-modern-hero-inner">
        <span class="contact-kicker">Confidencialidade e Dados Pessoais</span>
        <h1>Segurança e LGPD</h1>
        <p>
          Saiba como protegemos seus arquivos, registramos consentimentos e seguimos boas práticas de proteção de dados no ecossistema do JusTraduz.
        </p>

        <div class="terms-hero-actions">
          <a class="btn btn-primary home-btn-primary" href="#pilares">
            <span class="home-btn-label">Ver diretrizes</span>
            <span class="home-btn-icon" aria-hidden="true">
              <svg class="svg-icon" viewBox="0 0 24 24">
                <path d="M5 12h14"/>
                <path d="m13 6 6 6-6 6"/>
              </svg>
            </span>
          </a>
          <a class="btn btn-outline home-btn-outline" href="privacidade">Política de privacidade</a>
        </div>
      </div>
    </section>

    <section id="pilares" class="terms-modern-content">
      <div class="container terms-modern-grid">
        <aside class="terms-modern-aside">
          <h2>Segurança da informação</h2>
          <a href="#tratamento-dados" data-terms-nav>Tratamento de dados</a>
          <a href="#arquivos-enviados" data-terms-nav>Arquivos enviados</a>
          <a href="#consentimento-claro" data-terms-nav>Consentimento claro</a>
          <a href="#boas-praticas-ia" data-terms-nav>Uso seguro de IA</a>
          <a href="#canal-atendimento" data-terms-nav>Canal de contato</a>
        </aside>

        <article class="terms-modern-card">
          <div id="tratamento-dados" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <h2>Tratamento de dados e finalidade</h2>
            <p>
              Em conformidade com as diretrizes da Lei Geral de Proteção de Dados (Lei nº 13.709/2018 - LGPD), o JusTraduz coleta e processa apenas os dados estritamente necessários para viabilizar os serviços da plataforma SaaS. Isso inclui informações de cadastro do usuário (como nome e e-mail) e dados de histórico de uso operacional.
            </p>
          </div>

          <div id="arquivos-enviados" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <h2>Como protegemos os documentos enviados?</h2>
            <p>
              Os documentos enviados pelos clientes para fins de tradução e simplificação passam por rotinas técnicas de proteção:
            </p>
            <ul style="margin: 16px 0; padding-left: 20px; display: grid; gap: 8px;">
              <li><strong>Isolamento lógico:</strong> Os arquivos são alocados em diretórios privados do servidor, inacessíveis diretamente pela web pública.</li>
              <li><strong>Criptografia em repouso:</strong> Garantimos a proteção lógica dos repositórios internos da plataforma.</li>
              <li><strong>Controle de deleção:</strong> A qualquer momento, você pode excluir permanentemente seus documentos do nosso sistema de armazenamento e banco de dados.</li>
            </ul>
          </div>

          <div id="consentimento-claro" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <h2>Registro de consentimento explícito</h2>
            <p>
              O JusTraduz registra de forma eletrônica e segura o consentimento explícito fornecido pelos usuários no momento de cada upload e na ativação da inteligência artificial. Isso garante conformidade com a base legal exigida pela LGPD para o tratamento de informações de arquivos.
            </p>
          </div>

          <div id="boas-praticas-ia" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <h2>Boas práticas ao utilizar o JusTraduz IA</h2>
            <p>
              Embora o JusTraduz empregue criptografia e controles rigorosos, nenhum sistema de software é 100% imune a invasões ou vulnerabilidades. Por isso, orientamos nossos usuários a seguirem boas práticas preventivas:
            </p>
            <ul style="margin: 16px 0; padding-left: 20px; display: grid; gap: 8px;">
              <li><strong>Remoção de dados ultra-sensiveis:</strong> Se possível, rasure informações altamente sigilosas de cunho íntimo ou dados financeiros críticos (como chaves de senhas ou códigos bancários) antes de fazer o envio de documentos.</li>
              <li><strong>Uso informativo:</strong> Lembre-se de que a IA processa o texto para tradução didática, mas não deve ser utilizada para decisões judiciais autônomas sem a assessoria de um advogado.</li>
            </ul>
          </div>

          <div id="canal-atendimento" class="terms-modern-section-static" style="border-top: 1px solid rgba(0,0,0,0.08); padding-top: 32px;">
            <h2>Encarregado de Proteção de Dados (DPO)</h2>
            <p>
              Para exercer seus direitos como titular de dados (solicitar acesso, retificação, exclusão de dados ou esclarecer dúvidas sobre nossas práticas de conformidade), você pode entrar em contato com o nosso canal dedicado de privacidade pelo e-mail: <a href="mailto:contatoghcp@gmail.com">contatoghcp@gmail.com</a>.
            </p>
            <div style="margin-top: 32px; display: flex; gap: 16px; flex-wrap: wrap;">
              <a class="btn btn-primary" href="login.html?cadastro">Começar com segurança</a>
              <a class="btn btn-outline" href="como-funciona">Entender o fluxo</a>
            </div>
          </div>
        </article>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/' . $pathPrefix . 'includes/footer.php'; ?>

  <script src="assets/js/main.js?v=mobile-menu-20260628"></script>
  <script src="assets/js/accessibility.js?v=2026.06.14-06"></script>
  <script src="assets/js/vlibras-init.js?v=2026.06.25-1" defer></script>
</body>
</html>
