<?php
$pathPrefix = '../';
$activePage = 'blog';
require_once __DIR__ . '/' . $pathPrefix . 'includes/public-path.php';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'O que é juridiquês e por que ele atrapalha a sua vida? | Blog JusTraduz',
      'description' => 'Entenda o que é o juridiquês, jargões jurídicos complexos e latim no direito. Descubra como decifrar termos difíceis em português simples.',
      'canonical' => 'https://justraduz.com.br/blog/o-que-e-juridiques',
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
<body class="blog-post-page terms-page-enhanced">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="terms-modern-hero">
      <div class="container terms-modern-hero-inner">
        <span class="contact-kicker">Dicionário Didático</span>
        <h1>O que é juridiquês e por que ele atrapalha a sua vida?</h1>
        <p>Entenda por que a linguagem jurídica parece tão distante e veja caminhos práticos para transformar termos difíceis em decisões mais seguras.</p>
        <div class="blog-meta-row">
          <span>Time JusTraduz</span>
          <span>28 de Junho de 2026</span>
          <span>Leitura guiada</span>
        </div>
      </div>
    </section>

    <section class="terms-modern-content">
      <div class="container terms-modern-grid">
        <aside class="terms-modern-aside">
          <h2>Neste artigo</h2>
          <a href="#resumo" data-terms-nav>Resumo rápido</a>
          <a href="#origem" data-terms-nav>Origem</a>
          <a href="#impactos" data-terms-nav>Impactos</a>
          <a href="#decifrar" data-terms-nav>Como decifrar</a>
          <a href="#teste" data-terms-nav>Teste rápido</a>
        </aside>

        <article class="terms-modern-card blog-post-content">
          <section id="resumo" class="terms-modern-section-static" data-copy-source>
            <h2>Resumo rápido</h2>
            <p>Juridiquês é o uso excessivo de formalidade, jargões e expressões técnicas em documentos, contratos e comunicações jurídicas. O problema não é a precisão: é quando a linguagem impede a pessoa de entender seus próprios direitos.</p>
            <div class="blog-highlight-grid">
              <div class="blog-highlight-card"><strong>O que é</strong><span>Termos técnicos usados sem tradução para o cotidiano.</span></div>
              <div class="blog-highlight-card"><strong>Por que atrapalha</strong><span>Dificulta decisões sobre prazos, multas, obrigações e riscos.</span></div>
              <div class="blog-highlight-card"><strong>Como resolver</strong><span>Ler por partes, identificar papéis e reescrever em português simples.</span></div>
            </div>
            <button class="blog-copy-button" type="button" data-blog-copy>Copiar resumo</button>
          </section>

          <section id="origem" class="terms-modern-section-static">
            <h2>A origem histórica da linguagem jurídica complexa</h2>
            <p>O juridiquês tem raízes históricas. No Brasil, o ordenamento jurídico herdou muito da tradição do Direito Romano e das ordenações portuguesas coloniais. Por séculos, o domínio do latim e da linguagem técnica foi visto como símbolo de prestígio e autoridade.</p>
            <p>Embora a formalidade possa ajudar na precisão, o uso desmedido de arcaísmos e jargões fora dos autos judiciais afasta o cidadão comum da compreensão de seus próprios direitos.</p>
          </section>

          <section id="impactos" class="terms-modern-section-static">
            <h2>Os impactos do juridiquês na sociedade</h2>
            <p>Quando uma pessoa não entende um contrato, uma notificação ou um prazo, ela pode tomar decisões no escuro.</p>
            <ul>
              <li><strong>Assinaturas desinformadas:</strong> aceitar multas ou renovações sem entender o alcance da cláusula.</li>
              <li><strong>Ansiedade e medo:</strong> comunicações simples parecem ameaçadoras por causa do tom formal.</li>
              <li><strong>Perda de prazos:</strong> não identificar datas limite para responder ou contestar algo.</li>
            </ul>
          </section>

          <section id="decifrar" class="terms-modern-section-static">
            <h2>Como decifrar e ler documentos de forma simples?</h2>
            <ol>
              <li>Divida o texto por parágrafos ou cláusulas menores.</li>
              <li>Identifique quem são os atores: locador, locatário, credor, devedor, autor ou réu.</li>
              <li>Troque termos técnicos por palavras do dia a dia, como "adimplir" por "pagar".</li>
            </ol>
            <div class="blog-action-panel">
              <h3>Atalho mental</h3>
              <p>Se uma frase parece impossível, procure responder: quem faz o quê, até quando, sob qual consequência?</p>
            </div>
          </section>

          <section id="teste" class="terms-modern-section-static">
            <h2>Teste rápido</h2>
            <details class="blog-quick-details">
              <summary>O que significa "adimplemento" em português simples?</summary>
              <div><p>Geralmente significa cumprir uma obrigação, especialmente pagar o que foi combinado.</p></div>
            </details>
            <details class="blog-quick-details">
              <summary>Por que "outorgante" e "outorgado" confundem?</summary>
              <div><p>Porque os nomes são parecidos. Outorgante dá poderes; outorgado recebe poderes.</p></div>
            </details>

            <div class="feedback-card">
              <strong>Aviso informativo</strong>
              <p>Os conteúdos deste blog são educacionais e não substituem orientação jurídica individual.</p>
            </div>

            <div class="terms-hero-actions">
              <a class="btn btn-primary" href="<?= $publicPathPrefix ?>login.html?cadastro">Criar conta e analisar documento</a>
              <a class="btn btn-outline" href="<?= $publicPathPrefix ?>blog/">Voltar ao Blog</a>
            </div>
          </section>
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
