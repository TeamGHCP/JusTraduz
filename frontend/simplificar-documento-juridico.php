<?php
$pathPrefix = '';
$activePage = 'simplificar-documento-juridico';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Simplificar Documento Jurídico | Entenda seu Contrato',
      'description' => 'Aprenda como simplificar documento jurídico ou entender notificação judicial online. Traduza cláusulas e contratos complexos em segundos.',
      'canonical' => 'https://justraduz.com.br/simplificar-documento-juridico',
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
<body class="seo-simplify-page terms-page-enhanced terms-story-page">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="terms-modern-hero">
      <div class="container terms-modern-hero-inner">
        <span class="contact-kicker">Explicação de Contratos e Notificações</span>
        <h1>Simplificar documento jurídico</h1>
        <p>
          Saiba como enviar seus documentos com privacidade e receber resumos simples e traduções de cláusulas em poucos minutos.
        </p>

        <div class="terms-hero-actions">
          <a class="home-flow-button" href="login.html?cadastro">
            <svg class="home-flow-button-arrow home-flow-button-arrow-left svg-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14"/>
              <path d="m13 6 6 6-6 6"/>
            </svg>
            <span>Enviar documento</span>
            <span class="home-flow-button-circle" aria-hidden="true"></span>
            <svg class="home-flow-button-arrow home-flow-button-arrow-right svg-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14"/>
              <path d="m13 6 6 6-6 6"/>
            </svg>
          </a>
          <a class="btn btn-outline home-btn-outline" href="#como-enviar">Ver instruções</a>
        </div>
      </div>
    </section>

    <section id="como-enviar" class="terms-modern-content">
      <div class="container terms-modern-grid">
        <aside class="terms-modern-aside">
          <h2>Conteúdo explicativo</h2>
          <a href="#como-enviar-doc" data-terms-nav>Como enviar?</a>
          <a href="#o-que-recebe" data-terms-nav>O que você recebe?</a>
          <a href="#documentos-aceitos" data-terms-nav>Tipos de arquivos</a>
          <a href="#privacidade-dados" data-terms-nav>Privacidade e LGPD</a>
        </aside>

        <article class="terms-modern-card">
          <div id="como-enviar-doc" class="terms-modern-section-static how-step" style="margin-bottom: 48px;">
            <h2>Como funciona o envio do documento?</h2>
            <p>
              Simplificar um documento jurídico no JusTraduz é simples e intuitivo. Após criar a sua conta na plataforma:
            </p>
            <ol style="margin: 16px 0; padding-left: 20px; display: grid; gap: 8px;">
              <li>Acesse o seu dashboard e clique em <strong>"Enviar documento"</strong>.</li>
              <li>Arraste seu arquivo PDF ou envie fotos nítidas do documento impresso.</li>
              <li>Revise as informações de consentimento de uso e confirme o envio.</li>
              <li>A nossa inteligência artificial processará o texto em tempo real para gerar a simplificação.</li>
            </ol>
          </div>

          <div id="o-que-recebe" class="terms-modern-section-static how-step" style="margin-bottom: 48px;">
            <h2>O que o usuário recebe na análise?</h2>
            <p>
              Ao final do processamento, a plataforma exibe uma central de leitura dividida em três pilares principais:
            </p>
            <ul style="margin: 16px 0; padding-left: 20px; display: grid; gap: 8px;">
              <li><strong>Entendimento Geral:</strong> Uma síntese que esclarece a finalidade do contrato ou notificação, identificando quem são as partes envolvidas e qual o objetivo do termo.</li>
              <li><strong>Pontos Críticos:</strong> Uma listagem objetiva de datas de expiração, obrigações financeiras, taxas de juros, penalidades contratuais e prazos para manifestação.</li>
              <li><strong>Explicador de Cláusulas:</strong> A ferramenta permite selecionar cláusulas específicas do texto original e ler, lado a lado, o significado traduzido para o português simples.</li>
            </ul>
          </div>

          <div id="documentos-aceitos" class="terms-modern-section-static how-step" style="margin-bottom: 48px;">
            <h2>Tipos de documentos aceitos</h2>
            <p>
              A nossa tecnologia é otimizada para interpretar uma grande gama de textos civis e administrativos:
            </p>
            <ul style="margin: 16px 0; padding-left: 20px; display: grid; gap: 8px;">
              <li>Contratos comerciais de prestação de serviços civis.</li>
              <li>Contratos imobiliários e termos de locação residencial ou comercial.</li>
              <li>Notificações judiciais simples ou avisos extrajudiciais recebidos de cartórios ou empresas.</li>
              <li>Políticas internas, manuais de compliance corporativo e termos de adesão.</li>
            </ul>
          </div>

          <div id="privacidade-dados" class="terms-modern-section-static how-step how-step-warning" style="border-top: 1px solid rgba(0,0,0,0.08); padding-top: 32px;">
            <h2>Cuidados com a privacidade (LGPD)</h2>
            <p>
              Sabemos que documentos jurídicos contêm informações delicadas. Por isso, o JusTraduz adota segurança rigorosa na proteção de dados pessoais:
            </p>
            <ul style="margin: 16px 0; padding-left: 20px; display: grid; gap: 8px;">
              <li><strong>Criptografia em repouso e trânsito:</strong> Os arquivos são armazenados de forma criptografada em servidores protegidos.</li>
              <li><strong>Controle de acesso restrito:</strong> Apenas você tem visualização autorizada sobre seus documentos e históricos gerados.</li>
              <li><strong>Descarte seguro:</strong> O titular da conta pode excluir definitivamente qualquer documento ou análise do banco de dados a qualquer momento.</li>
            </ul>
            <p style="font-size: 14px; color: rgba(0,0,0,0.56); margin-top: 16px;">
              * Nota legal: O JusTraduz auxilia na leitura inicial de documentos, mas as análises automáticas não substituem e não dispensam a conferência técnica profissional de um advogado.
            </p>
            <div style="margin-top: 32px; display: flex; gap: 16px; flex-wrap: wrap;">
              <a class="btn btn-primary" href="login.html?cadastro">Enviar meu documento</a>
              <a class="btn btn-outline" href="como-funciona">Conhecer o fluxo</a>
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
