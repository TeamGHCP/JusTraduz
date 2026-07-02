<?php
$pathPrefix = '';
$activePage = 'home';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'JusTraduz | Direito em linguagem simples',
      'description' => 'Simplifique contratos, notificações e termos jurídicos. Entenda os seus direitos com explicações simples geradas por IA jurídica e conecte-se a profissionais.',
      'canonical' => 'https://justraduz.com.br/',
      'robots' => 'index, follow'
    ]);
  ?>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link class="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <meta name="application-name" content="JusTraduz">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="JusTraduz">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="msapplication-TileColor" content="#008f80">
  
  <!-- JSON-LD Structured Data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Organization",
        "@id": "https://justraduz.com.br/#organization",
        "name": "JusTraduz",
        "url": "https://justraduz.com.br",
        "logo": {
          "@type": "ImageObject",
          "url": "https://justraduz.com.br/frontend/assets/img/logo.png",
          "width": 512,
          "height": 512
        },
        "sameAs": [
          "https://www.instagram.com/justraduz/"
        ]
      },
      {
        "@type": "WebSite",
        "@id": "https://justraduz.com.br/#website",
        "url": "https://justraduz.com.br",
        "name": "JusTraduz",
        "description": "Direito em linguagem simples",
        "publisher": {
          "@id": "https://justraduz.com.br/#organization"
        }
      },
      {
        "@type": "SoftwareApplication",
        "@id": "https://justraduz.com.br/#softwareapplication",
        "name": "JusTraduz",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "All",
        "url": "https://justraduz.com.br",
        "description": "Plataforma SaaS para simplificar e traduzir documentos jurídicos complexos em linguagem simples e acessível usando inteligência artificial.",
        "offers": {
          "@type": "Offer",
          "price": "0",
          "priceCurrency": "BRL"
        }
      }
    ]
  }
  </script>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="assets/img/app-mark.png" as="image" type="image/png">
  <link rel="preload" href="assets/img/app-mark-edge.png" as="image" type="image/png">
  <script>
    if ("scrollRestoration" in history) history.scrollRestoration = "manual";
    if (!window.location.hash || window.location.hash === "#top") window.scrollTo(0, 0);
    (function () {
      try {
        var navigation = performance.getEntriesByType ? performance.getEntriesByType("navigation")[0] : null;
        var isReload = navigation && navigation.type === "reload";
        var skipOpening = window.sessionStorage.getItem("jtSkipOpeningLoader") === "1";

        if (!skipOpening && !isReload && document.referrer) {
          var currentUrl = new URL(window.location.href);
          var referrerUrl = new URL(document.referrer);
          var isInternalReferrer = referrerUrl.origin === currentUrl.origin && referrerUrl.pathname.indexOf("/frontend/") !== -1;
          var isIndexReferrer = /\/frontend\/(?:index\.html)?$/.test(referrerUrl.pathname) || /\/frontend\/$/.test(referrerUrl.pathname);
          skipOpening = isInternalReferrer && !isIndexReferrer;
        }

        if (skipOpening) {
          window.sessionStorage.removeItem("jtSkipOpeningLoader");
          document.documentElement.classList.add("skip-cinematic-opening");
        }
      } catch (e) {
        // ignore storage restrictions
      }
    })();
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=DM+Serif+Display:ital@0;1&family=Manrope:wght@400;500;600;700;800;900&family=Nunito+Sans:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=2026.07.02-feedback-stable-1">
  <link rel="stylesheet" href="assets/css/chatbot.css?v=2026.06.14-06">
  <style>
    @media (max-width: 980px) {
      .home-page .home-hero {
        min-height: auto !important;
        padding: 64px 0 54px !important;
        overflow: hidden !important;
      }

      .home-page .home-hero-grid {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
      }

      .home-page .home-hero-copy {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 16px 0 24px !important;
        text-align: center !important;
      }

      .home-page .home-hero h1 {
        max-width: min(100%, 620px) !important;
        margin: 0 auto 22px !important;
        line-height: .98 !important;
      }

      .home-page .home-hero h1 .hero-title-typewriter-line {
        display: flex !important;
        align-items: baseline !important;
        justify-content: center !important;
        gap: .32em !important;
        min-height: auto !important;
        white-space: normal !important;
      }

      .home-page .home-hero h1 .hero-typewriter {
        display: inline-grid !important;
        min-width: min(16ch, 100%) !important;
        max-width: 100% !important;
        text-align: left !important;
        white-space: nowrap !important;
      }

      .home-page .home-hero-copy > p {
        max-width: min(100%, 560px) !important;
        margin: 0 auto 24px !important;
      }

      .home-page .home-hero .hero-actions {
        display: flex !important;
        flex-wrap: wrap !important;
        justify-content: center !important;
        gap: 14px !important;
        width: 100% !important;
      }

      .home-page .home-trust-row {
        justify-content: center !important;
      }

      /* Oculta o celular e a timeline de fluxo no mobile e tablet */
      .home-page .home-hero-visual,
      .home-page .hero-phone-wrap,
      .home-page .iphone-mockup,
      .home-page .home-flow-section {
        display: none !important;
      }
    }

    @media (max-width: 640px) {
      .home-page .home-hero {
        padding: 48px 0 36px !important;
      }

      .home-page .home-hero h1 {
        max-width: 360px !important;
        font-size: clamp(34px, 12.2vw, 50px) !important;
      }

      .home-page .home-hero h1 .hero-title-typewriter-line {
        display: grid !important;
        justify-items: center !important;
        gap: 0 !important;
      }

      .home-page .home-hero h1 .hero-typewriter {
        display: block !important;
        min-width: 0 !important;
        text-align: center !important;
        white-space: normal !important;
      }

      .home-page .home-hero h1 .hero-typewriter-reserve {
        display: none !important;
      }

      .home-page .home-hero .hero-actions {
        display: grid !important;
        justify-items: center !important;
      }

      .home-page .home-flow-button,
      .home-page .home-btn-outline {
        width: min(100%, 320px) !important;
      }
    }

    /* Otimizacoes globais de responsividade mobile */
    .home-page {
      max-width: 100% !important;
      overflow-x: hidden !important;
    }

    @media (max-width: 980px) {
      .mobile-toggle {
        touch-action: manipulation !important;
        cursor: pointer !important;
      }
    }
  </style>
  <script src="assets/js/cookie-consent.js?v=2026.07.02-vlibras-1"></script>
  <script src="assets/js/pwa.js?v=2026.06.20-01" defer></script>
</head>
<body id="top" class="home-page has-opening-loader">
  <div class="jt-cinematic-loader" data-opening-loader aria-hidden="true">
    <div class="jt-cinematic-loader__mark">
      <img class="jt-cinematic-loader__logo jt-cinematic-loader__logo--dim" src="assets/img/app-mark.png" alt="">
      <span class="jt-cinematic-loader__logo jt-cinematic-loader__logo--lit jt-cinematic-loader__logo--lit-fill"></span>
    </div>
  </div>
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section id="recursos" class="home-hero">
      <div class="home-hero-lines" aria-hidden="true"></div>
      <div class="home-hero-backdrop" aria-hidden="true">
        <span class="hero-legal-sheet hero-legal-sheet-main" data-hero-depth="0.18"></span>
        <span class="hero-legal-sheet hero-legal-sheet-soft" data-hero-depth="-0.12"></span>
        <span class="hero-legal-thread hero-legal-thread-a" data-hero-depth="0.24"></span>
        <span class="hero-legal-thread hero-legal-thread-b" data-hero-depth="-0.16"></span>
      </div>
      <div class="container home-hero-grid">
        <div class="home-hero-copy">

          <h1>
            <span class="hero-title-line">Transforme</span>
            <em>documentos jurídicos</em>
            <span class="hero-title-line hero-title-typewriter-line">
              em
              <span class="hero-typewriter" data-hero-typewriter data-words="linguagem simples.|decisões claras.|orientação segura.|prazos entendíveis.|próximos passos.">
                <span class="hero-typewriter-reserve" aria-hidden="true">orientação segura.</span>
                <span class="hero-typewriter-live">
                  <span class="hero-typewriter-text" data-hero-typewriter-text>linguagem simples.</span><span class="hero-typewriter-cursor" aria-hidden="true"></span>
                </span>
              </span>
            </span>
          </h1>
          <p>Conecte o cidadão à justiça com clareza, agilidade e segurança. Entenda, aja e resolva.</p>

          <div class="hero-actions">
            <a class="home-flow-button" href="login.html?cadastro">
              <svg class="home-flow-button-arrow home-flow-button-arrow-left svg-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 12h14"/>
                <path d="m13 6 6 6-6 6"/>
              </svg>
              <span>Começar agora</span>
              <span class="home-flow-button-circle" aria-hidden="true"></span>
              <svg class="home-flow-button-arrow home-flow-button-arrow-right svg-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 12h14"/>
                <path d="m13 6 6 6-6 6"/>
              </svg>
            </a>
            <a class="btn btn-outline home-btn-outline" href="login.html">Entrar na plataforma</a>
          </div>

          <div class="home-trust-row" aria-label="Diferenciais">
            <span>
              <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="5" y="11" width="14" height="10" rx="2"/>
                <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
              </svg>
              Seguro e confidencial
            </span>
            <span>
              <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="m13 2-8 13h7l-1 7 8-13h-7z"/>
              </svg>
              Rápido e inteligente
            </span>
            <span>
              <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
              Feito para todos
            </span>
          </div>
        </div>

        <div class="home-hero-visual" aria-label="Prévia do sistema JusTraduz">
          <div class="hero-phone-wrap">
            <div class="iphone-mockup iphone-15-pro iphone-space-black" style="--phone-scale: .9; --phone-shadow: 0 28px 70px rgba(23,32,51,.25), 0 12px 30px rgba(17,138,126,.18); --screen-bg: #f4f6f9;">
              <div class="iphone-frame">
                <div class="iphone-buttons" aria-hidden="true">
                  <span class="iphone-button volume"></span>
                  <span class="iphone-button action"></span>
                  <span class="iphone-button power"></span>
                </div>
                <div class="iphone-screen">
                  <div class="phone-boot-mark" aria-hidden="true"></div>
                  <div class="iphone-island" aria-hidden="true"></div>
                  <div class="jt-phone-screen" data-phone-demo>
                    <div class="jt-phone-top">
                      <h3 class="phone-logo"><span class="logo-jus">Jus</span><span class="logo-traduz">Traduz</span></h3>
                      <span class="phone-ai-label">IA jurídica</span>
                    </div>

                    <button class="jt-phone-card phone-card" type="button" data-phone-open="document" aria-controls="phone-sheet-document" aria-expanded="false">
                      <span class="phone-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M10 12h5M10 16h5"/></svg>
                      </span>
                      <span class="phone-card-content">
                        <small>Documento</small>
                        <strong>Contrato analisado</strong>
                        <span>Resumo pronto em linguagem simples.</span>
                      </span>
                      <span class="phone-chevron" aria-hidden="true">›</span>
                    </button>

                    <button class="jt-phone-card phone-card" type="button" data-phone-open="explanation" aria-controls="phone-sheet-explanation" aria-expanded="false">
                      <span class="phone-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M5 5h14v11H9l-4 4z"/><path d="M9 9h1M14 9h1M9 12h6"/></svg>
                      </span>
                      <span class="phone-card-content">
                        <small>Explicação</small>
                        <strong>Sem juridiquês</strong>
                        <span>Cláusulas importantes traduzidas com clareza.</span>
                      </span>
                      <span class="phone-chevron" aria-hidden="true">›</span>
                    </button>

                    <div class="jt-phone-confidence phone-confidence">
                      <div>
                        <span>Confiança</span>
                        <strong class="phone-confidence-number" data-confidence-number>0%</strong>
                        <p>Análise confiável para apoiar decisões com segurança.</p>
                      </div>
                      <div class="phone-confidence-ring" aria-hidden="true"><i>✦</i></div>
                    </div>

                    <button class="jt-phone-btn phone-button" type="button" data-phone-open="request" aria-controls="phone-sheet-request" aria-expanded="false">
                      Solicitar orientação <span aria-hidden="true">→</span>
                    </button>

                    <div class="phone-toast" data-phone-toast role="status" aria-live="polite">
                      <span class="phone-toast-check" aria-hidden="true">✓</span>
                      <strong>Solicitação enviada com segurança</strong>
                      <button type="button" data-toast-close aria-label="Fechar notificação">×</button>
                    </div>

                    <div class="phone-sheet-backdrop" data-sheet-backdrop aria-hidden="true"></div>

                    <section class="phone-bottom-sheet" id="phone-sheet-document" data-phone-sheet="document" aria-hidden="true" aria-labelledby="document-sheet-title">
                      <span class="sheet-handle" aria-hidden="true"></span>
                      <button class="sheet-close" type="button" data-sheet-close aria-label="Fechar resumo do documento">×</button>
                      <h4 class="sheet-title" id="document-sheet-title">Resumo do documento</h4>
                      <p class="sheet-subtitle">Veja os principais pontos do contrato analisado.</p>
                      <div class="sheet-list">
                        <div class="sheet-item"><span class="sheet-item-icon">▣</span><div><strong>Prazo: 12 meses</strong><p>Vigência de 01/06/2024 a 31/05/2025.</p></div></div>
                        <div class="sheet-item"><span class="sheet-item-icon">$</span><div><strong>Multa rescisória</strong><p>Equivalente a 2 meses de aluguel.</p></div></div>
                        <div class="sheet-item"><span class="sheet-item-icon">↗</span><div><strong>Reajuste anual</strong><p>Índice: IPCA acumulado no período.</p></div></div>
                        <div class="sheet-item"><span class="sheet-item-icon">♙</span><div><strong>Responsabilidades do locatário</strong><p>Manutenção, conservação e despesas de consumo.</p></div></div>
                      </div>
                    </section>

                    <section class="phone-bottom-sheet phone-bottom-sheet--explanation" id="phone-sheet-explanation" data-phone-sheet="explanation" aria-hidden="true" aria-labelledby="explanation-sheet-title">
                      <span class="sheet-handle" aria-hidden="true"></span>
                      <button class="sheet-close" type="button" data-sheet-close aria-label="Fechar explicação">×</button>
                      <h4 class="sheet-title" id="explanation-sheet-title">Em linguagem simples</h4>
                      <p class="sheet-subtitle">Entenda as cláusulas mais importantes.</p>
                      <div class="sheet-comparison">
                        <div class="sheet-comparison-head"><strong>Cláusula original</strong><strong>O que isso significa</strong></div>
                        <div class="sheet-comparison-row"><p>O LOCATÁRIO arcará com todas as despesas ordinárias do imóvel.</p><span>→</span><p>Você paga contas como água, luz, gás e condomínio.</p></div>
                        <div class="sheet-comparison-row"><p>Fica vedada a sublocação do imóvel sem autorização expressa do LOCADOR.</p><span>→</span><p>Você não pode alugar para outra pessoa sem permissão do proprietário.</p></div>
                        <div class="sheet-comparison-row"><p>O contrato será reajustado anualmente pelo IPCA.</p><span>→</span><p>O valor do aluguel será ajustado todo ano conforme a inflação.</p></div>
                      </div>
                    </section>

                    <section class="phone-bottom-sheet phone-bottom-sheet--actions" id="phone-sheet-request" data-phone-sheet="request" aria-hidden="true" aria-labelledby="request-sheet-title">
                      <span class="sheet-handle" aria-hidden="true"></span>
                      <button class="sheet-close" type="button" data-sheet-close aria-label="Fechar próximos passos">×</button>
                      <h4 class="sheet-title" id="request-sheet-title">O que você gostaria de fazer agora?</h4>
                      <p class="sheet-subtitle">Sua solicitação foi recebida com sucesso.</p>
                      <div class="sheet-actions">
                        <button class="sheet-action" type="button"><span class="sheet-item-icon">◉</span><span><strong>Falar com especialista</strong><small>Converse agora por chat com um advogado.</small></span><b>›</b></button>
                        <button class="sheet-action" type="button"><span class="sheet-item-icon">▣</span><span><strong>Agendar atendimento</strong><small>Escolha um melhor horário para falar com um especialista.</small></span><b>›</b></button>
                        <button class="sheet-action" type="button"><span class="sheet-item-icon">≡</span><span><strong>Acompanhar solicitação</strong><small>Veja o status e detalhes da sua solicitação.</small></span><b>›</b></button>
                      </div>
                      <button class="sheet-home" type="button" data-sheet-home>← Voltar para o início</button>
                    </section>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="fluxo" class="page-section home-flow-section">
      <div class="home-flow-scroll" data-home-feature-flow>
        <div class="home-flow-sticky">
          <div class="container home-flow-pin-inner">
            <div class="section-head home-section-head">
              <h2>Um fluxo completo para entender e agir</h2>
              <p>Documento, explicação, solicitação, chat, agenda e auditoria no mesmo sistema.</p>
            </div>

            <div class="home-flow-experience" data-flow-scene>
              <div class="home-flow-timeline home-flow-pill-timeline" data-flow-progress-timeline aria-label="Progresso do fluxo JusTraduz">
                <div class="home-flow-timeline-track" aria-hidden="true">
                  <span data-flow-timeline-fill></span>
                </div>

                <button class="home-flow-timeline-step is-active" type="button" data-timeline-step="0" aria-label="Etapa 01: Envie seu documento" aria-current="step">
                  <span class="home-flow-step-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                      <path d="M12 16V4"></path>
                      <path d="m7 9 5-5 5 5"></path>
                      <path d="M5 16v3a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3"></path>
                    </svg>
                  </span>
                  <small>Envie seu documento</small>
                </button>

                <span class="home-flow-timeline-chevron" aria-hidden="true">&gt;</span>

                <button class="home-flow-timeline-step" type="button" data-timeline-step="1" aria-label="Etapa 02: Entenda em segundos">
                  <span class="home-flow-step-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                      <path d="M4 6.5c2.6-1.1 5.1-1.1 8 0v13c-2.9-1.1-5.4-1.1-8 0z"></path>
                      <path d="M12 6.5c2.9-1.1 5.4-1.1 8 0v13c-2.6-1.1-5.1-1.1-8 0z"></path>
                      <path d="M12 6.5v13"></path>
                    </svg>
                  </span>
                  <small>Entenda em segundos</small>
                </button>

                <span class="home-flow-timeline-chevron" aria-hidden="true">&gt;</span>

                <button class="home-flow-timeline-step" type="button" data-timeline-step="2" aria-label="Etapa 03: Peça o que precisa">
                  <span class="home-flow-step-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                      <path d="M9 5h6"></path>
                      <path d="M9 4.5A2.5 2.5 0 0 0 6.5 7v12A1.5 1.5 0 0 0 8 20.5h8A1.5 1.5 0 0 0 17.5 19V7A2.5 2.5 0 0 0 15 4.5"></path>
                      <path d="M9 11h6"></path>
                      <path d="M9 15h4"></path>
                    </svg>
                  </span>
                  <small>Peça o que precisa</small>
                </button>

                <span class="home-flow-timeline-chevron" aria-hidden="true">&gt;</span>

                <button class="home-flow-timeline-step" type="button" data-timeline-step="3" aria-label="Etapa 04: Acompanhe tudo">
                  <span class="home-flow-step-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                      <path d="M4 19V5"></path>
                      <path d="M4 19h16"></path>
                      <path d="m7 15 4-4 3 3 5-7"></path>
                      <path d="M16 7h3v3"></path>
                    </svg>
                  </span>
                  <small>Acompanhe tudo</small>
                </button>
              </div>

              <div class="home-flow-stage" aria-live="polite">
                <article class="home-flow-panel is-active" data-flow-panel="0">
                  <div class="home-flow-panel-copy">
                    <span class="home-flow-kicker">Etapa 01</span>
                    <h3>Envie seu documento</h3>
                    <span class="home-flow-title-line" aria-hidden="true"></span>
                    <p>O documento entra protegido, organizado e pronto para análise.</p>
                    <ul class="home-flow-feature-list" aria-label="Destaques desta etapa">
                      <li><span>↥</span> Upload guiado e seguro</li>
                      <li><span>✓</span> Documento protegido</li>
                      <li><span>▦</span> Pronto para análise</li>
                    </ul>
                  </div>

                  <div class="home-flow-system-preview" aria-hidden="true">
                    <div class="home-flow-system-sidebar">
                      <span></span><span class="is-active"></span><span></span><span></span><span></span>
                    </div>
                    <div class="home-flow-system-main">
                      <div class="home-flow-system-top"><strong>JusTraduz</strong><span>Documento enviado</span></div>
                      <div class="home-flow-file-card"><b>notificacao_extrajudicial.pdf</b><small>8 páginas · 1.8 MB</small></div>
                      <div class="home-flow-system-grid">
                        <div class="home-flow-system-card is-large">
                          <strong>Upload seguro</strong>
                          <i></i><i></i><i></i><i></i>
                        </div>
                        <div class="home-flow-system-card">
                          <strong>Proteção</strong>
                          <em>Criptografado</em>
                          <div class="home-flow-meter"><span style="width: 84%"></span></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </article>

                <article class="home-flow-panel" data-flow-panel="1">
                  <div class="home-flow-panel-copy">
                    <span class="home-flow-kicker">Etapa 02</span>
                    <h3>Entenda em segundos</h3>
                    <span class="home-flow-title-line" aria-hidden="true"></span>
                    <p>A IA resume os pontos importantes e traduz o juridiquês para linguagem simples.</p>
                    <ul class="home-flow-feature-list" aria-label="Destaques desta etapa">
                      <li><span>✦</span> Linguagem simplificada</li>
                      <li><span>•</span> Pontos de atenção detectados</li>
                      <li><span>▤</span> Resumo pronto para você</li>
                    </ul>
                  </div>

                  <div class="home-flow-system-preview" aria-hidden="true">
                    <div class="home-flow-system-sidebar">
                      <span></span><span class="is-active"></span><span></span><span></span><span></span>
                    </div>
                    <div class="home-flow-system-main">
                      <div class="home-flow-system-top"><strong>JusTraduz</strong><span>Análise concluída ✓</span></div>
                      <div class="home-flow-file-card"><b>Contrato_Servico.pdf</b><small>12 páginas · 2.4 MB</small></div>
                      <div class="home-flow-system-grid">
                        <div class="home-flow-system-card is-large">
                          <strong>Resumo do documento</strong>
                          <i></i><i></i><i></i><i></i><i></i>
                        </div>
                        <div class="home-flow-system-card">
                          <strong>Pontos de atenção</strong>
                          <em>Cláusula de multa</em>
                          <em>Prazo de renovação</em>
                          <em>Rescisão contratual</em>
                        </div>
                        <div class="home-flow-system-card">
                          <strong>Linguagem detectada</strong>
                          <em>Jurídica</em>
                          <div class="home-flow-meter"><span style="width: 92%"></span></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </article>

                <article class="home-flow-panel" data-flow-panel="2">
                  <div class="home-flow-panel-copy">
                    <span class="home-flow-kicker">Etapa 03</span>
                    <h3>Peça o que precisa</h3>
                    <span class="home-flow-title-line" aria-hidden="true"></span>
                    <p>Sua dúvida vira uma solicitação estruturada, com contexto para o profissional agir melhor.</p>
                    <ul class="home-flow-feature-list" aria-label="Destaques desta etapa">
                      <li><span>↗</span> Solicitação com contexto</li>
                      <li><span>◷</span> Prioridade organizada</li>
                      <li><span>✓</span> Profissional recebe tudo claro</li>
                    </ul>
                  </div>

                  <div class="home-flow-system-preview" aria-hidden="true">
                    <div class="home-flow-system-sidebar">
                      <span></span><span></span><span class="is-active"></span><span></span><span></span>
                    </div>
                    <div class="home-flow-system-main">
                      <div class="home-flow-system-top"><strong>JusTraduz</strong><span>Solicitação criada</span></div>
                      <div class="home-flow-file-card"><b>Solicitação #JT-2031</b><small>Contexto anexado · 3 itens</small></div>
                      <div class="home-flow-system-grid">
                        <div class="home-flow-system-card is-large">
                          <strong>Pedido estruturado</strong>
                          <i></i><i></i><i></i>
                          <em>Quero entender se a cobrança é válida.</em>
                        </div>
                        <div class="home-flow-system-card">
                          <strong>Área sugerida</strong>
                          <em>Contratos</em>
                          <em>Consumidor</em>
                        </div>
                        <div class="home-flow-system-card">
                          <strong>Contexto</strong>
                          <div class="home-flow-meter"><span style="width: 76%"></span></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </article>

                <article class="home-flow-panel" data-flow-panel="3">
                  <div class="home-flow-panel-copy">
                    <span class="home-flow-kicker">Etapa 04</span>
                    <h3>Acompanhe tudo</h3>
                    <span class="home-flow-title-line" aria-hidden="true"></span>
                    <p>Chat, agenda, status e histórico ficam conectados até o atendimento terminar.</p>
                    <ul class="home-flow-feature-list" aria-label="Destaques desta etapa">
                      <li><span>●</span> Status sempre visível</li>
                      <li><span>□</span> Agenda conectada</li>
                      <li><span>↺</span> Histórico completo</li>
                    </ul>
                  </div>

                  <div class="home-flow-system-preview" aria-hidden="true">
                    <div class="home-flow-system-sidebar">
                      <span></span><span></span><span></span><span class="is-active"></span><span></span>
                    </div>
                    <div class="home-flow-system-main">
                      <div class="home-flow-system-top"><strong>JusTraduz</strong><span>Acompanhamento ativo</span></div>
                      <div class="home-flow-file-card"><b>Atendimento em andamento</b><small>Agenda · chat · histórico</small></div>
                      <div class="home-flow-system-grid">
                        <div class="home-flow-system-card is-large">
                          <strong>Linha do tempo</strong>
                          <i></i><i></i><i></i><i></i>
                        </div>
                        <div class="home-flow-system-card">
                          <strong>Próximo passo</strong>
                          <em>Reunião agendada</em>
                          <em>Hoje · 15:30</em>
                        </div>
                        <div class="home-flow-system-card">
                          <strong>Status</strong>
                          <em>Em atendimento</em>
                          <div class="home-flow-meter"><span style="width: 68%"></span></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </article>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="container home-flow-compact" aria-label="Resumo do fluxo JusTraduz">
        <article class="home-flow-compact-card">
          <strong>01</strong>
          <h3>Envie</h3>
          <p>Suba PDF ou imagem com consentimento claro e proteção dos dados.</p>
        </article>
        <article class="home-flow-compact-card">
          <strong>02</strong>
          <h3>Entenda</h3>
          <p>A IA resume pontos importantes e traduz juridiquês para linguagem simples.</p>
        </article>
        <article class="home-flow-compact-card">
          <strong>03</strong>
          <h3>Solicite</h3>
          <p>Sua dúvida vira uma solicitação organizada, com contexto para atendimento.</p>
        </article>
        <article class="home-flow-compact-card">
          <strong>04</strong>
          <h3>Acompanhe</h3>
          <p>Chat, agenda, status e histórico ficam juntos até a resolução.</p>
        </article>
      </div>
    </section>

    <section id="feedbacks" class="page-section home-feedback-section" aria-labelledby="feedback-title">
      <div class="container home-feedback-grid">
        <div class="home-feedback-copy">
          <span class="home-eyebrow">Feedbacks</span>
          <h2 id="feedback-title">Quem usa o JusTraduz recomenda.</h2>
          <p>Relatos de pessoas que transformaram documentos difíceis em próximos passos claros, com mais segurança para conversar, decidir e agir.</p>
        </div>

        <div class="feedback-columns" data-feedback-marquee aria-label="Depoimentos de usuários">
          <div class="feedback-column" style="--feedback-speed: 58s;">
            <div class="feedback-track">
              <div class="feedback-group">
                <article class="feedback-card">
                  <div class="feedback-person">
                    <img src="assets/img/depoimentos/ana-ribeiro.jpg" alt="Foto de Ana Ribeiro" loading="lazy" decoding="async">
                    <div>
                      <strong>Ana Ribeiro</strong>
                      <span>Curitiba, PR</span>
                    </div>
                  </div>
                  <p>"Enviei uma notificação que eu não entendia e consegui separar os pontos importantes antes de pedir ajuda."</p>
                  <div class="feedback-stars" aria-label="Avaliação 5 de 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                </article>

                <article class="feedback-card">
                  <div class="feedback-person">
                    <img src="assets/img/depoimentos/bruno-martins.jpg" alt="Foto de Bruno Martins" loading="lazy" decoding="async">
                    <div>
                      <strong>Bruno Martins</strong>
                      <span>Belo Horizonte, MG</span>
                    </div>
                  </div>
                  <p>"A linguagem ficou bem mais simples. Algumas partes ainda precisei reler, mas já cheguei mais preparado."</p>
                  <div class="feedback-stars" aria-label="Avaliação 4 de 5">&#9733;&#9733;&#9733;&#9733;<span class="feedback-star-empty">&#9734;</span></div>
                </article>

                <article class="feedback-card">
                  <div class="feedback-person">
                    <img src="assets/img/depoimentos/carolina-lima.jpg" alt="Foto de Carolina Lima" loading="lazy" decoding="async">
                    <div>
                      <strong>Carolina Lima</strong>
                      <span>São Paulo, SP</span>
                    </div>
                  </div>
                  <p>"Gostei do passo a passo. Ele me ajudou a organizar documentos e perguntas sem ficar perdida no jurídico."</p>
                  <div class="feedback-stars" aria-label="Avaliação 5 de 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                </article>
              </div>
            </div>
          </div>

          <div class="feedback-column" style="--feedback-speed: 68s;">
            <div class="feedback-track">
              <div class="feedback-group">
                <article class="feedback-card">
                  <div class="feedback-person">
                    <img src="assets/img/depoimentos/diego-souza.jpg" alt="Foto de Diego Souza" loading="lazy" decoding="async">
                    <div>
                      <strong>Diego Souza</strong>
                      <span>Recife, PE</span>
                    </div>
                  </div>
                  <p>"Antes eu travava com qualquer termo jurídico. O resumo não resolveu tudo, mas deixou o essencial claro."</p>
                  <div class="feedback-stars" aria-label="Avaliação 4 de 5">&#9733;&#9733;&#9733;&#9733;<span class="feedback-star-empty">&#9734;</span></div>
                </article>

                <article class="feedback-card">
                  <div class="feedback-person">
                    <img src="assets/img/depoimentos/elisa-nogueira.jpg" alt="Foto de Elisa Nogueira" loading="lazy" decoding="async">
                    <div>
                      <strong>Elisa Nogueira</strong>
                      <span>Florianópolis, SC</span>
                    </div>
                  </div>
                  <p>"A parte do histórico foi a que mais gostei. Ficou fácil lembrar o que já tinha sido enviado e respondido."</p>
                  <div class="feedback-stars" aria-label="Avaliação 5 de 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                </article>

                <article class="feedback-card">
                  <div class="feedback-person">
                    <img src="assets/img/depoimentos/felipe-azevedo.jpg" alt="Foto de Felipe Azevedo" loading="lazy" decoding="async">
                    <div>
                      <strong>Felipe Azevedo</strong>
                      <span>Goiânia, GO</span>
                    </div>
                  </div>
                  <p>"Achei útil para ter uma primeira noção. Para meu caso faltou mais detalhe, mas ajudou a começar."</p>
                  <div class="feedback-stars" aria-label="Avaliação 3 de 5">&#9733;&#9733;&#9733;<span class="feedback-star-empty">&#9734;&#9734;</span></div>
                </article>
              </div>
            </div>
          </div>

          <div class="feedback-column" style="--feedback-speed: 62s;">
            <div class="feedback-track">
              <div class="feedback-group">
                <article class="feedback-card">
                  <div class="feedback-person">
                    <img src="assets/img/depoimentos/gabriela-rocha.jpg" alt="Foto de Gabriela Rocha" loading="lazy" decoding="async">
                    <div>
                      <strong>Gabriela Rocha</strong>
                      <span>Salvador, BA</span>
                    </div>
                  </div>
                  <p>"Usei para entender um contrato. O resumo destacou prazos, multa e pontos que eu não percebido."</p>
                  <div class="feedback-stars" aria-label="Avaliação 5 de 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                </article>

                <article class="feedback-card">
                  <div class="feedback-person">
                    <img src="assets/img/depoimentos/helena-duarte.jpg" alt="Foto de Helena Duarte" loading="lazy" decoding="async">
                    <div>
                      <strong>Helena Duarte</strong>
                      <span>Campinas, SP</span>
                    </div>
                  </div>
                  <p>"A experiência ficou bonita e objetiva. O que mais ajudou foi ver os próximos passos em linguagem clara."</p>
                  <div class="feedback-stars" aria-label="Avaliação 4 de 5">&#9733;&#9733;&#9733;&#9733;<span class="feedback-star-empty">&#9734;</span></div>
                </article>

                <article class="feedback-card">
                  <div class="feedback-person">
                    <img src="assets/img/depoimentos/igor-almeida.jpg" alt="Foto de Igor Almeida" loading="lazy" decoding="async">
                    <div>
                      <strong>Igor Almeida</strong>
                      <span>Porto Alegre, RS</span>
                    </div>
                  </div>
                  <p>"O atendimento ficou mais objetivo. Consegui explicar meu problema com menos insegurança na conversa."</p>
                  <div class="feedback-stars" aria-label="Avaliação 5 de 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                </article>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="seguranca" class="page-section home-security-section">
      <div class="container">
        <div class="section-head home-section-head">
          <h2>Segurança como argumento de produto</h2>
          <p>O JusTraduz não substitui advogado. Ele organiza a compreensão inicial, registra consentimentos e mantém trilha de auditoria.</p>
        </div>

        <div class="home-security-panel" data-security-panel>
          <div class="home-security-tabs" role="list" aria-label="Pontos de segurança">
            <button class="home-security-tab is-active" type="button" data-security-tab="consentimento" aria-pressed="true">
              <span>01</span>
              <strong>Consentimento claro</strong>
              <small>Dados e documentos entram com aceite registrado.</small>
            </button>
            <button class="home-security-tab" type="button" data-security-tab="auditoria" aria-pressed="false">
              <span>02</span>
              <strong>Trilha de auditoria</strong>
              <small>Ações importantes ficam organizadas para consulta.</small>
            </button>
            <button class="home-security-tab" type="button" data-security-tab="limites" aria-pressed="false">
              <span>03</span>
              <strong>Limites da IA</strong>
              <small>O sistema informa quando precisa de revisão humana.</small>
            </button>
          </div>

          <div class="home-security-preview" aria-live="polite">
            <div class="home-security-preview-top">
              <span data-security-preview-kicker>Proteção active</span>
              <strong data-security-preview-title>Consentimento antes do envio</strong>
            </div>
            <p data-security-preview-text>O usuário entende o uso da plataforma antes de enviar documentos, com regras visíveis e registro do aceite.</p>
            <div class="home-security-checks" aria-label="Controles destacados">
              <span data-security-preview-check-one>Maioridade confirmada</span>
              <span data-security-preview-check-two>Termos aceitos</span>
              <span data-security-preview-check-three>Dados tratados com finalidade</span>
            </div>
          </div>
        </div>

        <div class="form-actions justify-center">
          <a class="home-flow-button" href="login.html?cadastro">
            <svg class="home-flow-button-arrow home-flow-button-arrow-left svg-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14"/>
              <path d="m13 6 6 6-6 6"/>
            </svg>
            <span>Criar minha conta</span>
            <span class="home-flow-button-circle" aria-hidden="true"></span>
            <svg class="home-flow-button-arrow home-flow-button-arrow-right svg-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14"/>
              <path d="m13 6 6 6-6 6"/>
            </svg>
          </a>
        </div>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/' . $pathPrefix . 'includes/footer.php'; ?>

  <aside class="ai-chatbot" data-ai-chatbot aria-label="Assistente virtual JusTraduz">
    <button class="ai-chatbot-callout" type="button" data-ai-chatbot-toggle aria-label="Abrir chat com IA" aria-expanded="false">
      <img class="ai-chatbot-callout-robot" src="assets/img/chat-bot-logo.png" alt="Avatar da JusTraduz IA">
      <span class="ai-chatbot-callout-bubble">Consulte o JusIA</span>
    </button>

    <section class="ai-chatbot-panel" data-ai-chatbot-panel role="dialog" aria-modal="false" aria-labelledby="ai-chatbot-title" aria-hidden="true" inert>
      <header class="ai-chatbot-header">
        <div class="ai-chatbot-identity">
          <span class="ai-chatbot-avatar" aria-hidden="true">
            <img src="assets/img/chat-bot-logo.png" alt="Avatar da JusTraduz IA">
          </span>
          <div>
            <span id="ai-chatbot-title">JusTraduz IA</span>
            <small>Assistente informativo</small>
          </div>
        </div>

        <button class="ai-chatbot-close" type="button" data-ai-chatbot-close aria-label="Fechar chat">
          <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M18 6 6 18"/>
            <path d="m6 6 12 12"/>
          </svg>
        </button>
      </header>

      <div class="ai-chatbot-consent" data-ai-chatbot-consent>
        <strong>Antes de conversar</strong>
        <p>
          Este chat usa inteligência artificial e pode enviar sua mensagem ao Google Gemini.
          Não informe nomes completos, CPF, dados de processos, documentos, senhas ou informações sigilosas.
        </p>
        <label>
          <input type="checkbox" data-ai-chatbot-age>
          <span>Confirmo que tenho 18 anos ou mais.</span>
        </label>
        <label>
          <input type="checkbox" data-ai-chatbot-terms>
          <span>Li e aceito os <a href="termos" target="_blank" rel="noopener">Termos de Uso</a> e a <a href="privacidade" target="_blank" rel="noopener">Política de Privacidade</a>.</span>
        </label>
        <button type="button" data-ai-chatbot-consent-button disabled>Continuar</button>
        <small>O Jus IA não substitui advogado e não calcula prazos processuais.</small>
      </div>

      <div class="ai-chatbot-messages" data-ai-chatbot-messages aria-live="polite" hidden></div>

      <form class="ai-chatbot-form" data-ai-chatbot-form hidden>
        <label class="sr-only" for="ai-chatbot-input">Mensagem para a IA</label>
        <textarea id="ai-chatbot-input" data-ai-chatbot-input rows="2" maxlength="1200" placeholder="Digite sua dúvida..."></textarea>
        <button type="submit" aria-label="Enviar mensagem">
          <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="m22 2-7 20-4-9-9-4Z"/>
            <path d="M22 2 11 13"/>
          </svg>
        </button>
      </form>
    </section>
  </aside>

  <script src="assets/js/main.js?v=2026.07.02-feedback-stable-1"></script>
  <script src="assets/js/chatbot.js?v=2026.06.18-02"></script>
  <script src="assets/js/vlibras-init.js?v=2026.07.02-vlibras-1" defer></script>
  <script src="assets/js/accessibility.js?v=2026.07.02-vlibras-1"></script>
</body>
</html>
