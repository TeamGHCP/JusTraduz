<?php
$pathPrefix = '';
$activePage = 'termos';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Termos de Uso | JusTraduz',
      'description' => 'Leia os Termos de Uso do JusTraduz. Conheça as regras de utilização de nossa inteligência artificial para tradução jurídica e os limites da plataforma.',
      'canonical' => 'https://justraduz.com.br/termos',
      'robots' => 'index, follow'
    ]);
  ?>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <meta name="application-name" content="JusTraduz">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="JusTraduz">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="msapplication-TileColor" content="#008f80">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700;800&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
  <script src="assets/js/cookie-consent.js?v=2026.07.02-vlibras-1"></script>
  <link rel="stylesheet" href="assets/css/style.css?v=2026.07.05-style-cache-1">
</head>
<body class="terms-page terms-page-enhanced">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="terms-modern-hero">
      <div class="container terms-modern-hero-inner">
        <h1>Termos de Uso</h1>
        <p>
          As principais regras para usar o JusTraduz com clareza, responsabilidade e segurança.
          Leia por completo antes de utilizar a plataforma.
        </p>

        <p class="terms-updated">Última atualização: 13 de junho de 2026.</p>

        <div class="terms-hero-actions">
          <a class="btn btn-primary home-btn-primary" href="#uso">
            <span class="home-btn-label">Começar leitura</span>
            <span class="home-btn-icon" aria-hidden="true">
              <svg class="svg-icon" viewBox="0 0 24 24">
                <path d="M5 12h14"/>
                <path d="m13 6 6 6-6 6"/>
              </svg>
            </span>
          </a>
          <a class="btn btn-outline home-btn-outline" href="privacidade">Ver privacidade</a>
        </div>
      </div>
    </section>

    <section class="terms-modern-content">
      <div class="container terms-modern-grid">
        <aside class="terms-modern-aside">
          <div class="terms-progress" aria-hidden="true">
            <span data-terms-progress></span>
          </div>
          <h2>Nesta página</h2>
          <a href="#uso" data-terms-nav>Uso da plataforma</a>
          <a href="#ia" data-terms-nav>IA e Jus IA</a>
          <a href="#usuario" data-terms-nav>Responsabilidades</a>
          <a href="#profissionais" data-terms-nav>Profissionais</a>
          <a href="#limitacoes" data-terms-nav>Limitações</a>
        </aside>

        <article class="terms-modern-card" data-terms-sections>
          <details id="uso" class="terms-modern-section" open>
            <summary>
              <span>01</span>
              <strong>Uso da plataforma</strong>
            </summary>
            <div class="terms-section-body">
              <p>
                O JusTraduz tem como objetivo auxiliar usuários na compreensão de
                documentos jurídicos, organização de solicitações e conexão com
                profissionais qualificados dentro da plataforma.
              </p>
            </div>
          </details>

          <details id="ia" class="terms-modern-section">
            <summary>
              <span>02</span>
              <strong>IA, análise automática e uso do Jus IA</strong>
            </summary>
            <div class="terms-section-body">
              <p>
                A análise por IA possui caráter informativo e serve como apoio inicial
                para simplificar a linguagem de documentos. Ela não substitui orientação
                jurídica profissional, parecer técnico ou decisão de um advogado. Resultados
                podem conter erros, omissões ou informações desatualizadas e exigem revisão humana.
              </p>
              <p>
                O chat é destinado a maiores de 18 anos e oferece informações gerais sobre
                tradução e uso da plataforma. Ele não calcula prazos processuais, não avalia
                chances de vitória, não define estratégia jurídica e não redige peças como
                substituto de profissional habilitado. Parte do conteúdo pode ser processada
                pela Cloudflare AI, conforme descrito na Política de Privacidade.
              </p>
            </div>
          </details>

          <details id="usuario" class="terms-modern-section">
            <summary>
              <span>03</span>
              <strong>Responsabilidades do usuário</strong>
            </summary>
            <div class="terms-section-body">
              <p>
                O usuário deve enviar apenas documentos que possui autorização para tratar,
                manter seus dados atualizados, utilizar a plataforma de forma adequada e
                respeitar as regras de convivência nos canais de comunicação. No chat público,
                é proibido inserir senhas, CPF, dados bancários, números de processo, documentos
                pessoais ou informações protegidas por sigilo.
              </p>
            </div>
          </details>

          <details id="profissionais" class="terms-modern-section">
            <summary>
              <span>04</span>
              <strong>Profissionais</strong>
            </summary>
            <div class="terms-section-body">
              <p>
                Advogados e profissionais cadastrados devem fornecer informações verdadeiras,
                manter seus dados profissionais atualizados e poderão passar por validação
                de situação cadastral junto aos órgãos competentes.
              </p>
            </div>
          </details>

          <details id="limitacoes" class="terms-modern-section">
            <summary>
              <span>05</span>
              <strong>Limitações e uso responsável</strong>
            </summary>
            <div class="terms-section-body">
              <p>
                O JusTraduz não garante resultado jurídico específico e não deve ser usado
                como única fonte de decisão em casos importantes. Em situações complexas,
                urgentes ou que envolvam prazo, audiência, intimação, prisão ou risco à
                integridade, o usuário deve buscar imediatamente o canal público adequado
                ou orientação direta de profissional habilitado.
              </p>
            </div>
          </details>
        </article>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/' . $pathPrefix . 'includes/footer.php'; ?>

  <script src="assets/js/main.js?v=2026.07.05-main-modules-1"></script>
  <script src="assets/js/accessibility.js?v=2026.07.05-a11y-global-1"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const links = Array.from(document.querySelectorAll("[data-terms-nav]"));
      const sections = Array.from(document.querySelectorAll(".terms-modern-section"));
      const progress = document.querySelector("[data-terms-progress]");
      const lastSection = document.querySelector("#limitacoes");
      let footerScrollIntent = 0;
      let lastScrollY = window.scrollY;
      let touchStartY = 0;

      document.body.classList.add("terms-footer-gated");

      const setActive = (id) => {
        links.forEach((link) => {
          const isActive = link.getAttribute("href") === `#${id}`;
          link.classList.toggle("is-active", isActive);
          link.setAttribute("aria-current", isActive ? "true" : "false");
        });
      };

      const openSection = (target) => {
        if (target) {
          target.open = true;
        }

        if (target?.id) {
          setActive(target.id);
        }
      };

      const updateProgress = () => {
        if (!progress) return;

        const content = document.querySelector(".terms-modern-content");
        if (!content) return;

        const rect = content.getBoundingClientRect();
        const total = rect.height - window.innerHeight;
        const current = Math.min(Math.max(-rect.top, 0), Math.max(total, 1));
        progress.style.width = `${Math.round((current / Math.max(total, 1)) * 100)}%`;
      };

      const showFooter = () => {
        document.body.classList.add("is-terms-footer-visible");
      };

      const isNearPageEnd = () => (
        window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 36
      );

      const registerFooterScrollIntent = () => {
        if (document.body.classList.contains("is-terms-footer-visible")) {
          return;
        }

        if (!isNearPageEnd()) {
          footerScrollIntent = 0;
          return;
        }

        footerScrollIntent += 1;

        if (footerScrollIntent >= 3) {
          showFooter();
        }
      };

      const updateFooterGate = () => {
        if (!lastSection) {
          showFooter();
          return;
        }

        const rect = lastSection.getBoundingClientRect();
        const reachedLastTopic = rect.top <= window.innerHeight * 0.72;

        if (reachedLastTopic) {
          showFooter();
        }
      };

      const observer = "IntersectionObserver" in window
        ? new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
              if (entry.isIntersecting) {
                setActive(entry.target.id);
              }
            });
          }, { rootMargin: "-35% 0px -55% 0px", threshold: 0 })
        : null;

      sections.forEach((section) => observer?.observe(section));
      sections.forEach((section) => {
        section.addEventListener("toggle", () => {
          if (section.open) {
            openSection(section);
          }
        });
      });

      links.forEach((link) => {
        link.addEventListener("click", () => {
          const target = document.querySelector(link.getAttribute("href"));
          if (target) {
            openSection(target);
          }
        });
      });

      links[0]?.classList.add("is-active");
      window.addEventListener("scroll", () => {
        const isScrollingDown = window.scrollY > lastScrollY;

        updateProgress();
        updateFooterGate();

        if (isScrollingDown) {
          registerFooterScrollIntent();
        }

        lastScrollY = window.scrollY;
      }, { passive: true });
      window.addEventListener("wheel", (event) => {
        if (event.deltaY > 0) {
          registerFooterScrollIntent();
        }
      }, { passive: true });
      window.addEventListener("touchstart", (event) => {
        touchStartY = event.touches[0]?.clientY || 0;
      }, { passive: true });
      window.addEventListener("touchmove", (event) => {
        const currentY = event.touches[0]?.clientY || touchStartY;

        if (touchStartY - currentY > 18) {
          registerFooterScrollIntent();
        }
      }, { passive: true });
      window.addEventListener("keydown", (event) => {
        const downwardKeys = ["ArrowDown", "PageDown", "End", " "];

        if (downwardKeys.includes(event.key)) {
          registerFooterScrollIntent();
        }
      });
      window.addEventListener("resize", () => {
        updateProgress();
        updateFooterGate();
      });
      updateProgress();
      updateFooterGate();
    });
  </script>
  <script src="assets/js/vlibras-init.js?v=2026.07.05-a11y-global-1" defer></script>
</body>
</html>
