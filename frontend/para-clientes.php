<?php
$pathPrefix = '';
$activePage = 'para-clientes';
require_once __DIR__ . '/' . $pathPrefix . 'includes/public-path.php';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'JusTraduz para Clientes | Simplifique Seus Documentos',
      'description' => 'Descubra como o JusTraduz ajuda você a entender documentos e acompanhar solicitações com segurança, privacidade e linguagem simples.',
      'canonical' => 'https://justraduz.com.br/para-clientes',
      'robots' => 'index, follow'
    ]);
  ?>
  <link rel="icon" href="<?= $assetPrefix ?>assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="<?= $assetPrefix ?>assets/img/apple-touch-icon.png">
  <link rel="manifest" href="<?= $assetPrefix ?>site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <meta name="application-name" content="JusTraduz">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700;800&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/style.css?v=2026.07.05-style-cache-1">
  <script src="<?= $assetPrefix ?>assets/js/cookie-consent.js?v=2026.07.02-vlibras-1"></script>
</head>
<body class="seo-for-clients-page terms-page-enhanced">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="terms-modern-hero">
      <div class="container terms-modern-hero-inner">
        <span class="contact-kicker">Para Cidadãos e Empresas</span>
        <h1>JusTraduz para clientes</h1>
        <p>
          Entenda os seus documentos com rapidez, organize as suas dúvidas jurídicas e conecte-se com profissionais qualificados de forma segura.
        </p>

        <div class="terms-hero-actions">
          <a class="btn btn-primary home-btn-primary" href="<?= $publicPathPrefix ?>login.html?cadastro">
            <span class="home-btn-label">Criar conta grátis</span>
            <span class="home-btn-icon" aria-hidden="true">
              <svg class="svg-icon" viewBox="0 0 24 24">
                <path d="M5 12h14"/>
                <path d="m13 6 6 6-6 6"/>
              </svg>
            </span>
          </a>
          <a class="btn btn-outline home-btn-outline" href="#beneficios">Ver benefícios</a>
        </div>
      </div>
    </section>

    <section id="beneficios" class="terms-modern-content">
      <div class="container terms-modern-grid">
        <aside class="terms-modern-aside">
          <h2>Seu espaço no JusTraduz</h2>
          <a href="#entender-docs" data-terms-nav>Entendimento simples</a>
          <a href="#acompanhar" data-terms-nav>Controle de solicitações</a>
          <a href="#pedir-ajuda" data-terms-nav>Ajuda descomplicada</a>
          <a href="#historico-central" data-terms-nav>Histórico seguro</a>
          <a href="#privacidade-lgpd" data-terms-nav>Privacidade absoluta</a>
        </aside>

        <article class="terms-modern-card">
          <div id="entender-docs" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <h2>Entenda seus documentos sem complicação</h2>
            <p>
              Com a ajuda da nossa IA focada em linguagem natural, você não precisa quebrar a cabeça tentando decifrar o que significa cada cláusula complexa. O JusTraduz analisa contratos de prestação de serviços, termos de locação residencial, acordos simples e notificações judiciais e traduz o conteúdo para uma linguagem acessível e direta.
            </p>
          </div>

          <div id="acompanhar" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <h2>Acompanhe o andamento de suas solicitações</h2>
            <p>
              Toda vez que você abre um caso para obter suporte ou orientação profissional, a plataforma organiza uma linha do tempo clara e interativa. Você sabe exatamente qual o status do seu atendimento, se há respostas pendentes, agendamento de reuniões ou novos documentos a analisar.
            </p>
          </div>

          <div id="pedir-ajuda" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <h2>Peça ajuda quando precisar de suporte humano</h2>
            <p>
              A simplificação automática é um excelente ponto de partida. Mas quando o caso exige orientação jurídica de verdade, a plataforma facilita o compartilhamento seguro da sua dúvida com advogados e especialistas parceiros. O seu atendimento é feito de forma humanizada e direcionada dentro do nosso chat interno.
            </p>
          </div>

          <div id="historico-central" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <h2>Central de histórico completa</h2>
            <p>
              Todos os seus uploads anteriores, análises geradas por IA e conversas do chat ficam salvos e indexados na sua conta. Você pode consultar o que foi discutido ou baixar relatórios antigos a qualquer momento, sem perder o histórico do seu caso.
            </p>
          </div>

          <div id="privacidade-lgpd" class="terms-modern-section-static" style="border-top: 1px solid rgba(0,0,0,0.08); padding-top: 32px;">
            <h2>Privacidade absoluta e conformidade à LGPD</h2>
            <p>
              Sua privacidade é inegociável. Os dados de cadastro e os documentos enviados são protegidos por criptografia de ponta e ficam isolados em nosso banco de dados. Nós não vendemos seus dados a terceiros e você tem total autonomia para apagar arquivos e encerrar sua conta quando quiser.
            </p>
            <div class="feedback-card" style="border-left: 4px solid var(--primary); background: rgba(0,143,128,0.03); padding: 24px; margin-top: 24px;">
              <h3 style="margin-top: 0; color: var(--primary);">⚠️ Aviso importante ao cidadão</h3>
              <p style="font-size: 14px; line-height: 1.6; margin-bottom: 0;">
                O JusTraduz é uma plataforma SaaS informativa e facilitadora. Nós não prestamos consultoria advocatícia direta e a inteligência artificial não emite pareceres jurídicos. Em casos de urgência judicial ou necessidade de representação, consulte sempre um advogado devidamente credenciado junto à OAB.
              </p>
            </div>
            <div style="margin-top: 32px; display: flex; gap: 16px; flex-wrap: wrap;">
              <a class="btn btn-primary" href="<?= $publicPathPrefix ?>login.html?cadastro">Criar minha conta</a>
              <a class="btn btn-outline" href="<?= $publicPathPrefix ?>como-funciona">Ver fluxo passo a passo</a>
            </div>
          </div>
        </article>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/' . $pathPrefix . 'includes/footer.php'; ?>

  <script src="<?= $assetPrefix ?>assets/js/main.js?v=2026.07.05-main-modules-1"></script>
  <script src="<?= $assetPrefix ?>assets/js/accessibility.js?v=2026.07.05-a11y-global-1"></script>
  <script src="<?= $assetPrefix ?>assets/js/vlibras-init.js?v=2026.07.05-a11y-global-1" defer></script>
</body>
</html>
