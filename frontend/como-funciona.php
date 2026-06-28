<?php
$pathPrefix = '';
$activePage = 'como-funciona';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Como funciona o JusTraduz | Simplificação jurídica com IA',
      'description' => 'Entenda como o JusTraduz ajuda você a traduzir o juridiquês de contratos e notificações para linguagem simples em apenas 4 passos.',
      'canonical' => 'https://justraduz.com.br/como-funciona',
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
  <link rel="stylesheet" href="assets/css/style.css?v=site-polish-20260625">
  <script src="assets/js/cookie-consent.js?v=2026.06.25-1"></script>
</head>
<body class="how-it-works-page terms-page-enhanced">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="terms-modern-hero">
      <div class="container terms-modern-hero-inner">
        <span class="contact-kicker">Guia Passo a Passo</span>
        <h1>Como funciona o <em>JusTraduz</em></h1>
        <p>
          Entenda como funciona a nossa tecnologia de simplificação de termos e como o sistema ajuda você a se preparar melhor.
        </p>

        <div class="terms-hero-actions">
          <a class="btn btn-primary home-btn-primary" href="#fluxo-etapas">
            <span class="home-btn-label">Ver as etapas</span>
            <span class="home-btn-icon" aria-hidden="true">
              <svg class="svg-icon" viewBox="0 0 24 24">
                <path d="M5 12h14"/>
                <path d="m13 6 6 6-6 6"/>
              </svg>
            </span>
          </a>
          <a class="btn btn-outline home-btn-outline" href="login.html?cadastro">Criar conta grátis</a>
        </div>
      </div>
    </section>

    <section id="fluxo-etapas" class="terms-modern-content">
      <div class="container terms-modern-grid">
        <aside class="terms-modern-aside">
          <h2>Etapas do fluxo</h2>
          <a href="#passo1" data-terms-nav>1. Upload Seguro</a>
          <a href="#passo2" data-terms-nav>2. Tradução por IA</a>
          <a href="#passo3" data-terms-nav>3. Organização da Dúvida</a>
          <a href="#passo4" data-terms-nav>4. Conexão Profissional</a>
          <a href="#responsabilidade" data-terms-nav>Uso Responsável</a>
        </aside>

        <article class="terms-modern-card">
          <div id="passo1" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <span class="contact-kicker">Passo 01</span>
            <h2>Envie seu documento com segurança</h2>
            <p>
              O primeiro passo é enviar o documento que você deseja entender melhor — pode ser um contrato de aluguel, uma notificação extrajudicial, termos de serviço ou uma intimação simples. Aceitamos arquivos em formato PDF ou imagens nítidas.
            </p>
            <p>
              Seus documentos são criptografados e armazenados em nossa infraestrutura privada. O JusTraduz segue rígidas práticas de privacidade e segurança da LGPD, garantindo que somente você (e os profissionais que você explicitamente autorizar no futuro) tenham acesso aos arquivos originais.
            </p>
          </div>

          <div id="passo2" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <span class="contact-kicker">Passo 02</span>
            <h2>Tradução inteligente do juridiquês</h2>
            <p>
              Após o upload, nossa inteligência artificial jurídica analisa o texto e gera uma explicação em linguagem comum, de fácil leitura. O sistema extrai:
            </p>
            <ul style="margin: 16px 0; padding-left: 20px; display: grid; gap: 8px;">
              <li><strong>Resumo Executivo:</strong> Uma visão geral do que o documento trata em poucas frases.</li>
              <li><strong>Pontos de Atenção:</strong> Destaque para multas, prazos importantes, obrigações ocultas e taxas extras.</li>
              <li><strong>Glossário de Termos:</strong> Explicação didática de termos complexos encontrados no texto.</li>
              <li><strong>Tabela Comparativa:</strong> O texto original da cláusula lado a lado com a versão explicada sem jargão técnico.</li>
            </ul>
          </div>

          <div id="passo3" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <span class="contact-kicker">Passo 03</span>
            <h2>Organize suas dúvidas e prepare-se</h2>
            <p>
              Com a explicação da inteligência artificial em mãos, você pode marcar pontos do documento que ainda geram dúvidas ou que exigem providências imediatas. O JusTraduz ajuda você a estruturar a sua dúvida e registrar o contexto de forma clara e cronológica.
            </p>
            <p>
              Em vez de se perder em conversas soltas ou não saber por onde começar, você reúne todo o material necessário e monta uma solicitação organizada que resume perfeitamente a sua necessidade de apoio.
            </p>
          </div>

          <div id="passo4" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <span class="contact-kicker">Passo 04</span>
            <h2>Conecte-se com um profissional</h2>
            <p>
              Se você decidir que precisa de auxílio profissional, poderá compartilhar a sua solicitação estruturada e as explicações geradas com advogados ou estagiários de direito parceiros cadastrados na plataforma.
            </p>
            <p>
              Isso economiza tempo de consulta e garante que o profissional receba as informações já organizadas, possibilitando um atendimento muito mais direcionado, ágil e focado na resolução do seu problema.
            </p>
          </div>

          <div id="responsabilidade" class="terms-modern-section-static" style="border-top: 1px solid rgba(0,0,0,0.08); padding-top: 32px;">
            <div class="feedback-card" style="border-left: 4px solid var(--primary); background: rgba(0,143,128,0.03); padding: 24px;">
              <h3 style="margin-top: 0; color: var(--primary);">⚠️ Aviso Importante</h3>
              <p style="font-size: 14px; line-height: 1.6; margin-bottom: 0;">
                O JusTraduz é uma plataforma tecnológica de suporte informativo. <strong>A inteligência artificial do JusTraduz não fornece pareceres jurídicos, não define estratégias processuais e não substitui de nenhuma forma o atendimento ou a consulta com um advogado habilitado.</strong> Use as informações do sistema para compreender melhor seus documentos e se preparar para discussões com profissionais qualificados.
              </p>
            </div>
            <div style="margin-top: 32px; display: flex; gap: 16px; flex-wrap: wrap;">
              <a class="btn btn-primary" href="login.html?cadastro">Criar minha conta</a>
              <a class="btn btn-outline" href="traduzir-juridiques">Traduzir juridiquês</a>
            </div>
          </div>
        </article>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/' . $pathPrefix . 'includes/footer.php'; ?>

  <script src="assets/js/main.js"></script>
  <script src="assets/js/accessibility.js?v=2026.06.14-06"></script>
  <script src="assets/js/vlibras-init.js?v=2026.06.25-1" defer></script>
</body>
</html>
