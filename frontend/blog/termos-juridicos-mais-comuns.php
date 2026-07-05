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
      'title' => 'Os termos jurídicos mais comuns explicados em português simples | Blog JusTraduz',
      'description' => 'Glossário de termos jurídicos comuns traduzidos de forma didática. Entenda o que significam palavras difíceis de contratos e processos.',
      'canonical' => 'https://justraduz.com.br/blog/termos-juridicos-mais-comuns',
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
  <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/style.css?v=2026.07.05-hero-first-view-1">
  <script src="<?= $assetPrefix ?>assets/js/cookie-consent.js?v=2026.07.02-vlibras-1"></script>
</head>
<body class="blog-post-page terms-page-enhanced">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="terms-modern-hero">
      <div class="container terms-modern-hero-inner">
        <span class="contact-kicker">Terminologia</span>
        <h1>Os termos jurídicos mais comuns explicados em português simples</h1>
        <p>Um glossário pesquisável para entender palavras que aparecem em contratos, procurações, notificações e processos.</p>
        <div class="blog-meta-row">
          <span>Time JusTraduz</span>
          <span>28 de Junho de 2026</span>
          <span>Glossário interativo</span>
        </div>
      </div>
    </section>

    <section class="terms-modern-content">
      <div class="container terms-modern-grid">
        <aside class="terms-modern-aside">
          <h2>Neste glossário</h2>
          <a href="#busca" data-terms-nav>Buscar termo</a>
          <a href="#poderes" data-terms-nav>Procuração</a>
          <a href="#processo" data-terms-nav>Processo</a>
          <a href="#contratos" data-terms-nav>Contratos</a>
          <a href="#uso" data-terms-nav>Como usar</a>
        </aside>

        <article class="terms-modern-card blog-post-content">
          <section id="busca" class="terms-modern-section-static">
            <h2>Busque uma palavra</h2>
            <p>Digite parte do termo ou da explicação. Os cards abaixo filtram automaticamente.</p>
            <div class="blog-term-tools">
              <input class="blog-term-search" type="search" placeholder="Ex: rescisão, trânsito, honorários..." data-blog-filter="#glossario-termos" aria-label="Buscar termo jurídico">
            </div>
          </section>

          <section id="poderes" class="terms-modern-section-static">
            <h2>Glossário pesquisável</h2>
            <div id="glossario-termos" class="blog-term-grid">
              <article class="blog-term-card" data-term-card data-copy-source>
                <span class="blog-pill">Procuração</span>
                <h3>Outorgante</h3>
                <span>Quem concede um direito, poder ou autorização a outra pessoa. Em linguagem simples: quem assina dando o poder.</span>
                <button class="blog-mini-button" type="button" data-blog-copy>Copiar</button>
              </article>
              <article class="blog-term-card" data-term-card data-copy-source>
                <span class="blog-pill">Procuração</span>
                <h3>Outorgado</h3>
                <span>Quem recebe a autorização para agir em nome de outra pessoa.</span>
                <button class="blog-mini-button" type="button" data-blog-copy>Copiar</button>
              </article>
              <article id="processo" class="blog-term-card" data-term-card data-copy-source>
                <span class="blog-pill">Processo</span>
                <h3>Honorários de sucumbência</h3>
                <span>Valor que a parte perdedora pode ser condenada a pagar ao advogado da parte vencedora.</span>
                <button class="blog-mini-button" type="button" data-blog-copy>Copiar</button>
              </article>
              <article class="blog-term-card" data-term-card data-copy-source>
                <span class="blog-pill">Processo</span>
                <h3>Trânsito em julgado</h3>
                <span>Momento em que uma decisão se torna definitiva porque não cabem mais recursos.</span>
                <button class="blog-mini-button" type="button" data-blog-copy>Copiar</button>
              </article>
              <article class="blog-term-card" data-term-card data-copy-source>
                <span class="blog-pill">Processo</span>
                <h3>Petição inicial</h3>
                <span>Documento que começa um processo. Ele apresenta fatos, fundamentos e pedidos ao juiz.</span>
                <button class="blog-mini-button" type="button" data-blog-copy>Copiar</button>
              </article>
              <article id="contratos" class="blog-term-card" data-term-card data-copy-source>
                <span class="blog-pill">Contrato</span>
                <h3>Rescisão</h3>
                <span>Encerramento do contrato por descumprimento ou quebra de regra por uma das partes.</span>
                <button class="blog-mini-button" type="button" data-blog-copy>Copiar</button>
              </article>
              <article class="blog-term-card" data-term-card data-copy-source>
                <span class="blog-pill">Contrato</span>
                <h3>Resilição</h3>
                <span>Encerramento por acordo ou desistência permitida, sem necessariamente haver quebra de regra.</span>
                <button class="blog-mini-button" type="button" data-blog-copy>Copiar</button>
              </article>
            </div>
          </section>

          <section id="uso" class="terms-modern-section-static">
            <h2>Como usar esse glossário</h2>
            <p>Quando encontrar uma palavra difícil, não pare na definição. Pergunte como ela afeta prazo, valor, obrigação ou possibilidade de encerrar o contrato ou responder ao processo.</p>
            <details class="blog-quick-details">
              <summary>Exemplo prático</summary>
              <div><p>Se o texto fala em "rescisão antecipada", procure logo a multa, o prazo de aviso e a forma correta de comunicar a outra parte.</p></div>
            </details>

            <div class="feedback-card">
              <strong>Aviso informativo</strong>
              <p>As definições têm finalidade educativa. Em caso real, consulte um profissional habilitado.</p>
            </div>

            <div class="terms-hero-actions">
              <a class="btn btn-primary" href="<?= $publicPathPrefix ?>login.html?cadastro">Enviar meu documento para IA</a>
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
