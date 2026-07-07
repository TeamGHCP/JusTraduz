<?php
if (defined('JUSTRADUZ_JUSIA_WIDGET_RENDERED')) {
  return;
}

define('JUSTRADUZ_JUSIA_WIDGET_RENDERED', true);

$jtAssetPrefix = isset($assetPrefix) ? (string) $assetPrefix : (isset($pathPrefix) ? (string) $pathPrefix : '');
$jtLinkPrefix = isset($pathPrefix) ? (string) $pathPrefix : '';

if (!function_exists('jt_jusia_attr')) {
  function jt_jusia_attr(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }
}
?>
<style id="jusia-chatbot-global-css">
  .ai-chatbot {
    position: fixed;
    right: clamp(14px, 2vw, 28px);
    bottom: clamp(16px, 2.4vw, 30px);
    z-index: 99992;
    font-family: Inter, Manrope, "Plus Jakarta Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }

  .ai-chatbot-callout {
    border: 0;
    background: transparent;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    padding: 0;
  }

  .ai-chatbot-callout-robot {
    width: 54px;
    height: 54px;
    border-radius: 18px;
    filter: drop-shadow(0 16px 26px rgba(15, 23, 42, .18));
  }

  .ai-chatbot-callout-bubble {
    display: inline-flex;
    align-items: center;
    min-height: 42px;
    padding: 0 16px;
    border-radius: 999px;
    background: #ffffff;
    color: #12213d;
    font-weight: 800;
    box-shadow: 0 16px 38px rgba(15, 23, 42, .16);
    border: 1px solid rgba(15, 23, 42, .08);
  }

  .ai-chatbot-panel {
    position: absolute;
    right: 0;
    bottom: 76px;
    width: min(390px, calc(100vw - 28px));
    max-height: min(660px, calc(100svh - 118px));
    display: none;
    flex-direction: column;
    overflow: hidden;
    border-radius: 26px;
    background: #ffffff;
    color: #12213d;
    border: 1px solid rgba(15, 23, 42, .10);
    box-shadow: 0 26px 70px rgba(15, 23, 42, .24);
  }

  .ai-chatbot.is-open .ai-chatbot-panel {
    display: flex;
  }

  .ai-chatbot-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 18px 14px;
    border-bottom: 1px solid rgba(15, 23, 42, .08);
  }

  .ai-chatbot-identity,
  .ai-chatbot-message {
    display: flex;
    align-items: flex-start;
    gap: 12px;
  }

  .ai-chatbot-avatar img,
  .ai-chatbot-avatar {
    width: 38px;
    height: 38px;
    border-radius: 14px;
    flex: 0 0 auto;
  }

  .ai-chatbot-identity span[id],
  .ai-chatbot-consent strong {
    display: block;
    font-weight: 900;
    color: #10203d;
  }

  .ai-chatbot-identity small,
  .ai-chatbot-consent small {
    color: #64748b;
    font-weight: 700;
  }

  .ai-chatbot-close,
  .ai-chatbot-form button {
    border: 0;
    cursor: pointer;
    border-radius: 14px;
    background: #e8fbf7;
    color: #008f80;
  }

  .ai-chatbot-close {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
  }

  .ai-chatbot-close .svg-icon,
  .ai-chatbot-form .svg-icon {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  .ai-chatbot-consent,
  .ai-chatbot-messages {
    padding: 18px;
  }

  .ai-chatbot-consent p,
  .ai-chatbot-message p {
    margin: 0;
    color: #475569;
    line-height: 1.55;
  }

  .ai-chatbot-consent label {
    display: flex;
    gap: 10px;
    margin-top: 12px;
    color: #334155;
    font-weight: 700;
  }

  .ai-chatbot-consent a {
    color: #008f80;
    font-weight: 900;
  }

  .ai-chatbot-consent button[data-ai-chatbot-consent-button] {
    width: 100%;
    margin: 16px 0 10px;
    min-height: 44px;
    border: 0;
    border-radius: 14px;
    background: #008f80;
    color: #ffffff;
    font-weight: 900;
    cursor: pointer;
  }

  .ai-chatbot-consent button:disabled,
  .ai-chatbot-form button:disabled {
    opacity: .55;
    cursor: not-allowed;
  }

  .ai-chatbot-messages {
    overflow-y: auto;
    display: grid;
    gap: 12px;
  }

  .ai-chatbot-message p,
  .ai-chatbot-choice-content {
    padding: 12px 14px;
    border-radius: 16px;
    background: #f4f7fb;
  }

  .ai-chatbot-message-user {
    justify-content: flex-end;
  }

  .ai-chatbot-message-user p {
    background: #008f80;
    color: #ffffff;
  }

  .ai-chatbot-choice-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
  }

  .ai-chatbot-choice-list button {
    border: 1px solid rgba(0, 143, 128, .22);
    border-radius: 999px;
    background: #ffffff;
    color: #008f80;
    font-weight: 800;
    padding: 8px 12px;
    cursor: pointer;
  }

  .ai-chatbot-form {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    padding: 14px;
    border-top: 1px solid rgba(15, 23, 42, .08);
    background: #ffffff;
  }

  .ai-chatbot-form textarea {
    flex: 1;
    min-height: 44px;
    max-height: 96px;
    resize: none;
    border: 1px solid rgba(15, 23, 42, .14);
    border-radius: 14px;
    padding: 11px 12px;
    font: inherit;
  }

  .ai-chatbot-form button {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    background: #008f80;
    color: #ffffff;
  }

  .sr-only {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
  }

  @media (max-width: 640px) {
    .ai-chatbot {
      right: 14px;
      bottom: 14px;
    }

    .ai-chatbot-callout-bubble {
      display: none;
    }

    .ai-chatbot-panel {
      right: -2px;
      bottom: 68px;
      width: calc(100vw - 24px);
      max-height: calc(100svh - 96px);
      border-radius: 22px;
    }
  }
</style>

<aside class="ai-chatbot" data-ai-chatbot aria-label="Assistente virtual JusTraduz">
  <button class="ai-chatbot-callout" type="button" data-ai-chatbot-toggle aria-label="Abrir chat com IA" aria-expanded="false">
    <img class="ai-chatbot-callout-robot" src="<?= jt_jusia_attr($jtAssetPrefix) ?>assets/img/chat-bot-logo-small.png" width="54" height="54" loading="lazy" decoding="async" alt="Avatar da JusTraduz IA">
    <span class="ai-chatbot-callout-bubble">Consulte o JusIA</span>
  </button>

  <section class="ai-chatbot-panel" data-ai-chatbot-panel role="dialog" aria-modal="false" aria-labelledby="ai-chatbot-title" aria-hidden="true" inert>
    <header class="ai-chatbot-header">
      <div class="ai-chatbot-identity">
        <span class="ai-chatbot-avatar" aria-hidden="true">
          <img src="<?= jt_jusia_attr($jtAssetPrefix) ?>assets/img/chat-bot-logo-small.png" width="48" height="48" loading="lazy" decoding="async" alt="Avatar da JusTraduz IA">
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
      <p>Este chat usa inteligência artificial e pode enviar sua mensagem ao Google Gemini. Não informe nomes completos, CPF, dados de processos, documentos, senhas ou informações sigilosas.</p>
      <label>
        <input type="checkbox" data-ai-chatbot-age>
        <span>Confirmo que tenho 18 anos ou mais.</span>
      </label>
      <label>
        <input type="checkbox" data-ai-chatbot-terms>
        <span>Li e aceito os <a href="<?= jt_jusia_attr($jtLinkPrefix) ?>termos" target="_blank" rel="noopener">Termos de Uso</a> e a <a href="<?= jt_jusia_attr($jtLinkPrefix) ?>privacidade" target="_blank" rel="noopener">Política de Privacidade</a>.</span>
      </label>
      <button type="button" data-ai-chatbot-consent-button disabled>Continuar</button>
      <small>O Jus IA não substitui advogado e não calcula prazos processuais.</small>
    </div>

    <div class="ai-chatbot-messages" data-ai-chatbot-messages aria-live="polite" hidden></div>

    <form class="ai-chatbot-form" data-ai-chatbot-form hidden>
      <label class="sr-only" for="ai-chatbot-input">Mensagem para a IA</label>
      <textarea id="ai-chatbot-input" data-ai-chatbot-input rows="2" maxlength="1200" placeholder="Digite sua dúvida."></textarea>
      <button type="submit" aria-label="Enviar mensagem">
        <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="m22 2-7 20-4-9-9-4Z"/>
          <path d="M22 2 11 13"/>
        </svg>
      </button>
    </form>
  </section>
</aside>

<script>
  window.JUSTRADUZ_ASSET_PREFIX = <?= json_encode($jtAssetPrefix, JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= jt_jusia_attr($jtAssetPrefix) ?>assets/js/chatbot.js?v=2026.07.07-jusia-global-1" defer></script>
