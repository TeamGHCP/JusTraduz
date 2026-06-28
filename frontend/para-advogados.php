<?php
$pathPrefix = '';
$activePage = 'para-advogados';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'JusTraduz para Advogados | Presença e Organização Digital',
      'description' => 'Aumente a eficiência do seu atendimento jurídico. Receba solicitações de clientes já organizadas, gerencie sua agenda e consolide sua presença digital.',
      'canonical' => 'https://justraduz.com.br/para-advogados',
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
<body class="seo-for-lawyers-page terms-page-enhanced">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="terms-modern-hero">
      <div class="container terms-modern-hero-inner">
        <span class="contact-kicker">Para Profissionais da Advocacia</span>
        <h1>JusTraduz para advogados</h1>
        <p>
          Otimize seus atendimentos, gerencie sua carteira de consultas de forma organizada e amplie sua presença digital de maneira ética e segura.
        </p>

        <div class="terms-hero-actions">
          <a class="btn btn-primary home-btn-primary" href="login.html?cadastro">
            <span class="home-btn-label">Cadastrar meu perfil</span>
            <span class="home-btn-icon" aria-hidden="true">
              <svg class="svg-icon" viewBox="0 0 24 24">
                <path d="M5 12h14"/>
                <path d="m13 6 6 6-6 6"/>
              </svg>
            </span>
          </a>
          <a class="btn btn-outline home-btn-outline" href="#recursos-adv">Saber mais</a>
        </div>
      </div>
    </section>

    <section id="recursos-adv" class="terms-modern-content">
      <div class="container terms-modern-grid">
        <aside class="terms-modern-aside">
          <h2>Soluções para advocacia</h2>
          <a href="#solicitacoes-estruturadas" data-terms-nav>Entradas organizadas</a>
          <a href="#perfil-digital" data-terms-nav>Presença profissional</a>
          <a href="#agenda-atendimento" data-terms-nav>Agenda integrada</a>
          <a href="#conformidade-oab" data-terms-nav>Conformidade OAB</a>
        </aside>

        <article class="terms-modern-card">
          <div id="solicitacoes-estruturadas" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <h2>Receba solicitações estruturadas e com contexto</h2>
            <p>
              Esqueça as consultas informais pelo WhatsApp que chegam sem dados básicos ou documentos organizados. No JusTraduz, o cliente anexa o documento relevante e preenche um roteiro estruturado detalhando a dúvida dele.
            </p>
            <p>
              Como profissional cadastrado, você recebe uma ficha completa do caso, contendo o documento original, a explicação didática que o cliente leu e as perguntas específicas formuladas por ele, otimizando drasticamente o seu tempo de análise prévia.
            </p>
          </div>

          <div id="perfil-digital" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <h2>Consolide sua presença digital profissional</h2>
            <p>
              Crie o seu perfil público institucional no diretório do JusTraduz. Apresente suas áreas de especialidade, sua trajetória profissional e seu canal de contato para milhares de pessoas que buscam entender seus direitos na plataforma todos os dias.
            </p>
          </div>

          <div id="agenda-atendimento" class="terms-modern-section-static" style="margin-bottom: 48px;">
            <h2>Gerenciamento de agenda e atendimentos</h2>
            <p>
              Utilize o painel integrado para configurar os seus horários de atendimento, controlar novos agendamentos e gerenciar as conversas ativas via chat de forma segura. A plataforma centraliza os prazos e compromissos acordados com os clientes para garantir organização máxima ao seu dia a dia.
            </p>
          </div>

          <div id="conformidade-oab" class="terms-modern-section-static" style="border-top: 1px solid rgba(0,0,0,0.08); padding-top: 32px;">
            <h2>Compromisso e Conformidade Ética (OAB)</h2>
            <p>
              O JusTraduz preza pelo estrito cumprimento das diretrizes contidas no Código de Ética e Disciplina da OAB. A plataforma atua de forma transparente, puramente informativa e institucional, servindo como meio tecnológico de organização para o cliente e o advogado:
            </p>
            <ul style="margin: 16px 0; padding-left: 20px; display: grid; gap: 8px;">
              <li><strong>Sem mercantilização:</strong> O sistema não realiza leilão de honorários ou captação indevida de causas.</li>
              <li><strong>Validação de credenciais:</strong> Aprovamos ativamente os perfis mediante validação do número de inscrição profissional nos quadros da OAB.</li>
              <li><strong>Liberdade contratual:</strong> Toda a negociação de honorários e a execução técnica dos serviços ocorrem sob a total e exclusiva autonomia e responsabilidade do advogado parceiro e do cliente.</li>
            </ul>
            <div style="margin-top: 32px; display: flex; gap: 16px; flex-wrap: wrap;">
              <a class="btn btn-primary" href="login.html?cadastro">Criar perfil de advogado</a>
              <a class="btn btn-outline" href="como-funciona">Como funciona o fluxo</a>
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
