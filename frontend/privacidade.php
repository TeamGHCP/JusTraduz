<?php
$pathPrefix = '';
$activePage = 'privacidade';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Política de Privacidade | JusTraduz',
      'description' => 'Conheça a Política de Privacidade do JusTraduz. Entenda como tratamos, protegemos e armazenamos seus dados de acordo com a LGPD.',
      'canonical' => 'https://justraduz.com.br/privacidade',
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
  <link rel="stylesheet" href="assets/css/style.css?v=2026.07.02-vlibras-panel-1">
</head>
<body class="privacy-page terms-page-enhanced">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="terms-modern-hero">
      <div class="container terms-modern-hero-inner">
        <h1>Política de Privacidade</h1>
        <p>
          Saiba como o JusTraduz coleta, utiliza e protege informações dentro da plataforma.
        </p>

        <p class="terms-updated">Última atualização: 25 de junho de 2026.</p>

        <div class="terms-hero-actions">
          <a class="btn btn-primary home-btn-primary" href="#dados">
            <span class="home-btn-label">Começar leitura</span>
            <span class="home-btn-icon" aria-hidden="true">
              <svg class="svg-icon" viewBox="0 0 24 24">
                <path d="M5 12h14"/>
                <path d="m13 6 6 6-6 6"/>
              </svg>
            </span>
          </a>
          <a class="btn btn-outline home-btn-outline" href="termos">Ver termos</a>
        </div>
      </div>
    </section>

    <section class="terms-modern-content">
      <div class="container terms-modern-grid">
        <aside class="terms-modern-aside">
          <div class="terms-progress" aria-hidden="true">
            <span data-privacy-progress></span>
          </div>
          <h2>Nesta página</h2>
          <a href="#dados" data-privacy-nav>Dados tratados</a>
          <a href="#finalidade" data-privacy-nav>Finalidade</a>
          <a href="#ia" data-privacy-nav>IA e compartilhamento</a>
          <a href="#seguranca" data-privacy-nav>Segurança</a>
          <a href="#direitos" data-privacy-nav>Direitos do usuário</a>
          <a href="#cookies" data-privacy-nav>Cookies</a>
          <a href="#contato-lgpd" data-privacy-nav>Contato LGPD</a>
        </aside>

        <article class="terms-modern-card" data-privacy-sections>
          <details id="dados" class="terms-modern-section privacy-modern-section" open>
            <summary>
              <span>01</span>
              <strong>Dados tratados</strong>
            </summary>
            <div class="terms-section-body">
              <p>
                O JusTraduz pode armazenar dados de cadastro, documentos enviados,
                textos extraídos, resultados de análise por IA, solicitações,
                mensagens e notificações necessárias para o funcionamento da plataforma.
                O chat público mantém o histórico apenas no navegador durante a conversa,
                mas as mensagens digitadas podem ser processadas pelo fornecedor de IA.
              </p>
            </div>
          </details>

          <details id="finalidade" class="terms-modern-section privacy-modern-section">
            <summary>
              <span>02</span>
              <strong>Finalidade</strong>
            </summary>
            <div class="terms-section-body">
              <p>
                As informações são utilizadas para autenticação, análise de documentos,
                organização do histórico, atendimento jurídico e administração dos fluxos
                internos da plataforma. O tratamento deve se apoiar, conforme a operação,
                em consentimento, execução de contrato, cumprimento de obrigação legal,
                exercício regular de direitos ou legítimo interesse avaliado pelo controlador.
              </p>
            </div>
          </details>

          <details id="ia" class="terms-modern-section privacy-modern-section">
            <summary>
              <span>03</span>
              <strong>Inteligência artificial e compartilhamento</strong>
            </summary>
            <div class="terms-section-body">
              <p>
                O JusTraduz utiliza o Google Gemini para algumas respostas e análises.
                Mensagens, trechos de documentos e arquivos autorizados podem ser enviados
                ao Google para processamento, inclusive em infraestrutura localizada fora
                do Brasil. O usuário não deve inserir no chat público CPF, senhas, números
                de processo, dados bancários, nomes completos ou informações sigilosas.
              </p>
              <p>
                O uso de documentos na análise por IA depende de autorização específica.
                As respostas automáticas são informativas, podem conter erros e devem ser
                revistas por pessoa qualificada antes de qualquer decisão relevante.
              </p>
            </div>
          </details>

          <details id="seguranca" class="terms-modern-section privacy-modern-section">
            <summary>
              <span>04</span>
              <strong>Segurança</strong>
            </summary>
            <div class="terms-section-body">
              <p>
                O sistema aplica validação de arquivos, controle de acesso, separação por
                perfil, proteção de sessão, limitação de requisições e registros técnicos.
                Nenhum sistema é totalmente imune a incidentes; por isso, o tratamento é
                limitado ao necessário e as medidas são revisadas periodicamente.
              </p>
            </div>
          </details>

          <details id="direitos" class="terms-modern-section privacy-modern-section">
            <summary>
              <span>05</span>
              <strong>Direitos do usuário</strong>
            </summary>
            <div class="terms-section-body">
              <p>
                O titular pode solicitar confirmação e acesso, correção, anonimização,
                bloqueio ou eliminação quando aplicável, informação sobre compartilhamento,
                portabilidade nos termos da regulamentação, oposição e revogação de
                consentimento. Dados serão mantidos apenas pelo período necessário, salvo
                obrigação legal, exercício regular de direitos ou outra hipótese permitida.
              </p>
            </div>
          </details>

          <details id="cookies" class="terms-modern-section privacy-modern-section">
            <summary>
              <span>06</span>
              <strong>Cookies e preferências</strong>
            </summary>
            <div class="terms-section-body">
              <p>
                O JusTraduz usa cookies essenciais para sessão, segurança, CSRF, autenticação
                e funcionamento básico. Esses cookies não podem ser desativados pela central
                de preferências porque são necessários para entregar o serviço.
              </p>
              <p>
                O usuário pode autorizar ou recusar cookies e armazenamentos opcionais para
                preferências de interface, recursos externos de acessibilidade como VLibras
                e futuras ferramentas de medição de uso. As escolhas podem ser revistas pelo
                botão "Cookies" exibido no site.
              </p>
            </div>
          </details>

          <details id="contato-lgpd" class="terms-modern-section privacy-modern-section">
            <summary>
              <span>07</span>
              <strong>Controlador e contato LGPD</strong>
            </summary>
            <div class="terms-section-body">
              <p>
                O responsável pelas decisões de tratamento nesta plataforma é o JusTraduz.
                Solicitações relacionadas à privacidade e aos direitos dos titulares podem
                ser enviadas para <a href="mailto:contatoghcp@gmail.com">contatoghcp@gmail.com</a>.
                A identidade jurídica completa e o contato formal do encarregado devem ser
                disponibilizados antes da operação comercial definitiva da plataforma.
              </p>
            </div>
          </details>
        </article>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/' . $pathPrefix . 'includes/footer.php'; ?>

  <script src="assets/js/main.js?v=2026.07.05-main-modules-1"></script>
  <script src="assets/js/accessibility.js?v=2026.07.02-vlibras-1"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const links = Array.from(document.querySelectorAll("[data-privacy-nav]"));
      const sections = Array.from(document.querySelectorAll(".privacy-modern-section"));
      const progress = document.querySelector("[data-privacy-progress]");
      const lastSection = document.querySelector("#contato-lgpd");
      let footerScrollIntent = 0;
      let lastScrollY = window.scrollY;
      let touchStartY = 0;

      document.body.classList.add("privacy-footer-gated");

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
        document.body.classList.add("is-privacy-footer-visible");
      };

      const isNearPageEnd = () => (
        window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 36
      );

      const registerFooterScrollIntent = () => {
        if (document.body.classList.contains("is-privacy-footer-visible")) {
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
  <script src="assets/js/vlibras-init.js?v=2026.07.02-vlibras-1" defer></script>
</body>
</html>
