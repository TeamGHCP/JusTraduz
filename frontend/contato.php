<?php
$pathPrefix = '';
$activePage = 'contato';
require_once __DIR__ . '/' . $pathPrefix . 'includes/public-path.php';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Contato | JusTraduz',
      'description' => 'Fale com o time JusTraduz. Envie suas dúvidas, suporte, propostas de parceria ou sugestões para a nossa plataforma de simplificação jurídica.',
      'canonical' => 'https://justraduz.com.br/contato',
      'robots' => 'index, follow'
    ]);
  ?>
  <link rel="icon" href="<?= $assetPrefix ?>assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="<?= $assetPrefix ?>assets/img/apple-touch-icon.png">
  <link rel="manifest" href="<?= $assetPrefix ?>site.webmanifest">
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
  <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/style.css?v=2026.07.05-hero-first-view-1">
  <style>
    /* Ajuste local: contato em seções de tela cheia, sem depender de alterações globais. */
    html {
      scroll-behavior: smooth;
    }

    body.contact-page {
      overflow-x: hidden;
    }

    .contact-page main {
      overflow: visible;
    }

    .contact-page main > section {
      position: relative;
      isolation: isolate;
      box-sizing: border-box;
      width: 100%;
      min-height: 100svh;
      display: flex;
      align-items: center;
      padding-top: clamp(90px, 10vh, 132px);
      padding-bottom: clamp(48px, 7vh, 96px);
    }

    .contact-page main > section > .container,
    .contact-page .contact-hero-grid.container,
    .contact-page .contact-compose-grid.container,
    .contact-page .contact-trust-grid.container {
      width: min(100% - 32px, var(--container, 1180px));
      margin-inline: auto;
    }

    .contact-page .contact-hero,
    .contact-page .contact-section,
    .contact-page .contact-compose-section {
      min-height: 100svh;
    }

    .contact-page .contact-trust-section,
    .contact-page .contact-faq-section {
      min-height: auto;
    }

    .contact-page .contact-hero {
      padding-top: clamp(72px, 8vh, 108px);
      padding-bottom: clamp(52px, 9vh, 120px);
    }

    .contact-page .contact-hero-grid {
      min-height: auto;
      align-items: center;
    }

    .contact-page .contact-hero-copy {
      margin-top: clamp(-52px, -5vh, -16px);
    }

    .contact-page .contact-compose-grid,
    .contact-page .contact-trust-grid,
    .contact-page .contact-faq-section > .container {
      min-height: auto;
    }

    .contact-page .contact-grid {
      align-items: stretch;
    }

    .contact-page .contact-card {
      min-height: clamp(270px, 34vh, 430px);
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .contact-page .contact-compose-grid {
      align-items: center;
    }

    .contact-page .contact-trust-section {
      padding-top: clamp(24px, 3vh, 40px);
      padding-bottom: 18px;
    }

    .contact-page .contact-trust-grid {
      align-items: stretch;
    }

    .contact-page .contact-trust-grid > div {
      min-height: clamp(180px, 24vh, 290px);
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .contact-page .contact-faq-section {
      min-height: calc(100svh - 240px);
      padding-top: 18px;
      padding-bottom: clamp(56px, 8vh, 90px);
    }

    .contact-page .contact-faq-section .section-head {
      margin-bottom: clamp(24px, 4vh, 48px);
    }

    .contact-page .contact-faq {
      width: min(920px, 100%);
      margin-inline: auto;
    }

    @media (min-width: 900px) {
      .contact-page main {
        scroll-snap-type: y proximity;
      }

      .contact-page .contact-hero,
      .contact-page .contact-section,
      .contact-page .contact-compose-section {
        scroll-snap-align: start;
      }
    }

    @media (max-width: 899px) {
      .contact-page main > section {
        min-height: 100svh;
        align-items: center;
        padding-top: clamp(84px, 12vh, 116px);
        padding-bottom: clamp(40px, 7vh, 72px);
      }

      .contact-page .contact-hero-copy {
        margin-top: 0;
      }

      .contact-page .contact-card,
      .contact-page .contact-trust-grid > div {
        min-height: auto;
      }

      .contact-page .contact-trust-section,
      .contact-page .contact-faq-section {
        min-height: auto;
      }

      .contact-page .contact-faq-section {
        padding-top: 20px;
      }
    }

    @media (max-width: 520px) {
      .contact-page main > section > .container,
      .contact-page .contact-hero-grid.container,
      .contact-page .contact-compose-grid.container,
      .contact-page .contact-trust-grid.container {
        width: min(100% - 24px, var(--container, 1180px));
      }

      .contact-page main > section {
        padding-top: 88px;
        padding-bottom: 44px;
      }

      .contact-page .contact-trust-section {
        padding-top: 18px;
        padding-bottom: 12px;
      }

      .contact-page .contact-faq-section {
        padding-top: 12px;
      }
    }
  </style>
  <script src="<?= $assetPrefix ?>assets/js/cookie-consent.js?v=2026.07.02-vlibras-1"></script>
</head>
<body class="contact-page">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <section class="contact-hero contact-hero-simple">
      <div class="contact-hero-grid container">
        <div class="contact-hero-copy">
          <span class="contact-kicker">Canais oficiais</span>
          <h1>Fale com o <em>JusTraduz</em></h1>
          <p>Escolha o melhor caminho para suporte, parcerias, ideias ou para acompanhar a evolução do projeto.</p>

          <div class="contact-hero-actions">
            <a class="btn btn-primary home-btn-primary" href="#mensagem">
              <span class="home-btn-label">Montar mensagem</span>
              <span class="home-btn-icon" aria-hidden="true">
                <svg class="svg-icon" viewBox="0 0 24 24">
                  <path d="M5 12h14"/>
                  <path d="m13 6 6 6-6 6"/>
                </svg>
              </span>
            </a>
            <a class="btn btn-outline home-btn-outline" href="mailto:contatoghcp@gmail.com">Enviar e-mail</a>
          </div>
        </div>
      </div>
    </section>

    <section class="contact-section">
      <div class="container contact-grid">
        <article class="contact-card contact-card-email">
          <span class="contact-card-icon">
            <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true">
              <rect x="3" y="5" width="18" height="14" rx="2"/>
              <path d="m3 7 9 6 9-6"/>
            </svg>
          </span>
          <h2>E-mail</h2>
          <p>Canal principal para suporte, dúvidas gerais, propostas e mensagens institucionais.</p>
          <div class="contact-email-copy">
            <code id="contact-email">contatoghcp@gmail.com</code>
            <button type="button" data-copy-email aria-label="Copiar e-mail">
              <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="9" y="9" width="13" height="13" rx="2"/>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
              </svg>
            </button>
          </div>
          <a class="btn btn-primary" href="mailto:contatoghcp@gmail.com">Abrir e-mail</a>
        </article>

        <article class="contact-card">
          <span class="contact-card-icon">
            <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M9 19c-5 1.5-5-2.5-7-3"/>
              <path d="M15 22v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 19 4.77 5.07 5.07 0 0 0 18.91 1S17.73.65 15 2.48a13.38 13.38 0 0 0-6 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/>
            </svg>
          </span>
          <h2>GitHub</h2>
          <p>Acompanhe o desenvolvimento, melhorias, correções e evolução técnica do projeto.</p>
          <a class="btn btn-outline" href="https://github.com/TeamGHCP/justraduz" target="_blank" rel="noopener">Abrir GitHub</a>
        </article>

        <article class="contact-card">
          <span class="contact-card-icon">
            <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true">
              <rect x="2" y="2" width="20" height="20" rx="5"/>
              <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
              <path d="M17.5 6.5h.01"/>
            </svg>
          </span>
          <h2>Instagram</h2>
          <p>Receba novidades, atualizações, bastidores e comunicados oficiais do JusTraduz.</p>
          <a class="btn btn-outline" href="https://www.instagram.com/justraduz/" target="_blank" rel="noopener">Abrir Instagram</a>
        </article>
      </div>
    </section>

    <section id="mensagem" class="contact-section contact-compose-section">
      <div class="container contact-compose-grid">
        <div class="contact-compose-copy">
          <span class="contact-kicker">Mensagem rápida</span>
          <h2>Monte seu e-mail em poucos cliques</h2>
          <p>Selecione o assunto e escreva uma breve descrição. O botão abre seu aplicativo de e-mail com tudo preenchido.</p>
        </div>

        <form class="contact-composer" data-contact-form>
          <label for="contact-subject">Assunto</label>
          <select id="contact-subject" name="subject">
            <option value="Dúvida sobre o JusTraduz">Dúvida sobre o JusTraduz</option>
            <option value="Preciso de suporte">Preciso de suporte</option>
            <option value="Proposta de parceria">Proposta de parceria</option>
            <option value="Sugestão para o projeto">Sugestão para o projeto</option>
          </select>

          <label for="contact-message">Mensagem</label>
          <textarea id="contact-message" name="message" rows="6" minlength="10" required placeholder="Conte em poucas linhas como podemos ajudar."></textarea>

          <div class="contact-composer-actions">
            <button class="btn btn-primary" type="submit">Preparar e-mail</button>
            <span data-contact-feedback aria-live="polite"></span>
          </div>
        </form>
      </div>
    </section>

    <section class="contact-trust-section" aria-label="Compromissos de contato">
      <div class="container contact-trust-grid">
        <div>
          <strong>48h</strong>
          <span>Resposta média em até 48 horas.</span>
        </div>
        <div>
          <strong>Feedback</strong>
          <span>Sugestões ajudam a melhorar o JusTraduz.</span>
        </div>
        <div>
          <strong>Aberto</strong>
          <span>Canal para dúvidas, parcerias e feedbacks.</span>
        </div>
        <div>
          <strong>Acesso</strong>
          <span>Projeto focado em democratizar a informação jurídica.</span>
        </div>
      </div>
    </section>

    <section class="contact-section contact-faq-section">
      <div class="container">
        <div class="section-head">
          <h2>Dúvidas frequentes</h2>
          <p>Algumas respostas rápidas antes de enviar sua mensagem.</p>
        </div>

        <div class="contact-faq" data-contact-faq>
          <details>
            <summary>O JusTraduz oferece atendimento jurídico direto?</summary>
            <p>O projeto ajuda a explicar documentos em linguagem simples. Quando necessário, a plataforma orienta a busca por profissionais habilitados.</p>
          </details>
          <details>
            <summary>Posso enviar uma sugestão de melhoria?</summary>
            <p>Sim. Sugestões de experiência, acessibilidade, textos e fluxo do sistema são bem-vindas pelo e-mail oficial.</p>
          </details>
          <details>
            <summary>Onde acompanho as atualizações?</summary>
            <p>As atualizações públicas aparecem no GitHub e os comunicados mais leves aparecem no Instagram do JusTraduz.</p>
          </details>
        </div>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/' . $pathPrefix . 'includes/footer.php'; ?>

  <script src="<?= $assetPrefix ?>assets/js/main.js?v=2026.07.05-main-modules-1"></script>
  <script src="<?= $assetPrefix ?>assets/js/accessibility.js?v=2026.07.05-a11y-global-1"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const email = "contatoghcp@gmail.com";
      const copyButton = document.querySelector("[data-copy-email]");
      const feedback = document.querySelector("[data-contact-feedback]");
      const form = document.querySelector("[data-contact-form]");
      const previewButtons = document.querySelectorAll("[data-contact-preview]");
      const messageField = form?.querySelector("#contact-message");

      copyButton?.addEventListener("click", async () => {
        try {
          await navigator.clipboard.writeText(email);
          copyButton.classList.add("is-copied");
          copyButton.setAttribute("aria-label", "E-mail copiado");
          window.setTimeout(() => {
            copyButton.classList.remove("is-copied");
            copyButton.setAttribute("aria-label", "Copiar e-mail");
          }, 1800);
        } catch (error) {
          window.location.href = `mailto:${email}`;
        }
      });

      previewButtons.forEach((button) => {
        button.addEventListener("click", () => {
          previewButtons.forEach((item) => item.classList.remove("is-active"));
          button.classList.add("is-active");
        });
      });

      messageField?.addEventListener("input", () => {
        form?.classList.remove("is-submitting");

        if (messageField.validity.valid) {
          form?.classList.remove("was-validated");

          if (feedback) {
            feedback.textContent = "";
          }
        }
      });

      form?.addEventListener("submit", (event) => {
        event.preventDefault();

        if (!form.checkValidity()) {
          form.classList.add("was-validated");

          if (feedback) {
            feedback.textContent = "Escreva uma mensagem com pelo menos 10 caracteres.";
          }

          messageField?.focus();
          return;
        }

        const data = new FormData(form);
        const subject = String(data.get("subject") || "Contato JusTraduz");
        const message = String(data.get("message") || "").trim();
        const body = message || "Olá, time JusTraduz. Gostaria de conversar com vocês.";

        form.classList.add("is-submitting");

        window.location.href = `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;

        if (feedback) {
          feedback.textContent = "Abrindo seu aplicativo de e-mail...";
        }
      });
    });
  </script>
  <script src="<?= $assetPrefix ?>assets/js/vlibras-init.js?v=2026.07.05-a11y-global-1" defer></script>
</body>
</html>
