<?php
require_once __DIR__ . '/app/bootstrap.php';

if (empty($_SESSION['google_pending_user_id'])) {
    header('Location: ' . app_url('/frontend/login.html?erro=' . urlencode('Entre com Google para completar o cadastro.')));
    exit;
}

$errorMessage = trim((string) ($_GET['erro'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Completar cadastro | JusTraduz</title>
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
  <link rel="stylesheet" href="assets/css/style.css?v=2026.07.02-vlibras-panel-1">
  <link rel="stylesheet" href="assets/css/auth-novo.css?v=custom-select-flow-8">
  <style>
    .auth-switch-page .auth-card .btn.btn-block {
      min-height: 48px !important;
      height: auto !important;
      padding: 10px 18px !important;
      border-radius: 8px !important;
      line-height: 1.2 !important;
    }

    .auth-switch-page .auth-card .btn-primary {
      background: var(--teal) !important;
      color: #fff !important;
    }

    .auth-switch-page input:focus,
    .auth-switch-page select:focus,
    .auth-switch-page button:focus {
      outline: 0 !important;
    }

    .google-complete-switch .auth-card {
      max-width: 520px;
    }

    .google-complete-switch .google-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      width: fit-content;
      margin: 0 0 16px;
      padding: 7px 10px;
      border: 1px solid rgba(17, 138, 126, .18);
      border-radius: 999px;
      background: rgba(17, 138, 126, .08);
      color: var(--teal-2);
      font-size: 11px;
      font-weight: 900;
      letter-spacing: .07em;
      text-transform: uppercase;
    }

    .google-complete-switch .google-pill svg {
      width: 15px;
      height: 15px;
      fill: none;
      stroke: currentColor;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .google-complete-switch .auth-note {
      border-radius: 8px;
    }

    .google-complete-switch .checkline {
      margin-top: 2px;
    }

    .google-complete-switch .field-help {
      display: block;
      margin: 6px 0 0;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.35;
    }

    .google-complete-switch .google-oab-grid {
      grid-template-columns: minmax(0, 1fr) 136px;
      align-items: start;
    }

    .google-complete-switch .google-oab-grid > .jt-field {
      align-self: start;
    }

    .google-complete-switch .jt-field-uf .jt-input {
      padding-left: 44px;
      padding-right: 30px;
      font-weight: 800;
      text-align: left;
      text-align-last: left;
    }

    .google-complete-switch .jt-field-uf .jt-label {
      left: 43px;
      transform: translateY(-50%);
    }

    .google-complete-switch .jt-field-uf.is-focused .jt-label,
    .google-complete-switch .jt-field-uf.has-value .jt-label {
      transform: translateY(0);
    }

    @media (max-width: 560px) {
      .google-complete-switch .google-oab-grid {
        grid-template-columns: 1fr;
      }

      .google-complete-switch .jt-field-uf .jt-label {
        left: 43px;
        transform: translateY(-50%);
      }

      .google-complete-switch .jt-field-uf.is-focused .jt-label,
      .google-complete-switch .jt-field-uf.has-value .jt-label {
        transform: translateY(0);
      }

      .google-complete-switch .jt-field-uf .jt-input {
        text-align: left;
        text-align-last: left;
      }
    }
  </style>
  <script src="assets/js/cookie-consent.js?v=2026.07.02-vlibras-1"></script>
  <script src="assets/js/pwa.js" defer></script>
</head>
<body>
<main class="auth-page auth-switch-page google-complete-switch preload" id="authPage">
  <section class="auth-panel auth-login-panel">
    <div class="auth-card">
      <span class="google-pill">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M21 12a9 9 0 1 1-2.64-6.36"/>
          <path d="M21 12h-8"/>
          <path d="M18 16a6.5 6.5 0 0 1-6 3"/>
        </svg>
        Conta Google confirmada
      </span>

      <h1>Complete seu cadastro</h1>
      <p class="subtitle">Escolha seu tipo de conta e informe os dados obrigatórios para liberar o acesso.</p>

      <?php if ($errorMessage !== ''): ?>
        <div class="alert is-visible alert-error"><?= e($errorMessage) ?></div>
      <?php endif; ?>

      <form class="auth-form" action="../backend/public/index.php?rota=/auth/google/complete-profile" method="post" novalidate>
        <?= csrf_input() ?>
        <div class="alert" data-auth-alert role="alert" aria-live="assertive" aria-atomic="true"></div>

        <div class="field jt-field">
          <span class="auth-field-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>
          </span>
          <select class="select jt-input" id="tipo" name="tipo" data-account-type required>
            <option value="cliente">Cliente</option>
            <option value="advogado">Advogado</option>
          </select>
          <label class="jt-label" for="tipo">Tipo de conta</label>
          <small class="jt-error"></small>
        </div>

        <div class="field jt-field">
          <span class="auth-field-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.2 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.32 1.78.59 2.63a2 2 0 0 1-.45 2.11L8 9.7a16 16 0 0 0 6.3 6.3l1.24-1.24a2 2 0 0 1 2.11-.45c.85.27 1.73.47 2.63.59A2 2 0 0 1 22 16.92z"/></svg>
          </span>
          <input class="input jt-input" type="tel" id="telefone" name="telefone" inputmode="tel" autocomplete="tel" maxlength="15" required>
          <label class="jt-label" for="telefone">Telefone</label>
          <small class="jt-error"></small>
        </div>

        <div class="field jt-field">
          <span class="auth-field-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
          </span>
          <input class="input jt-input" type="date" id="google_data_nascimento" name="data_nascimento" autocomplete="bday" required>
          <label class="jt-label" for="google_data_nascimento">Data de nascimento</label>
          <small class="jt-error"></small>
          <small class="field-help">Usada apenas para confirmar maioridade.</small>
        </div>

        <div class="field jt-field" data-cpf-fields>
          <span class="auth-field-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 9h4"/><path d="M8 13h2"/><path d="M15 12h2"/><path d="M15 16h2"/></svg>
          </span>
          <input class="input jt-input" type="text" id="cpf" name="cpf" inputmode="numeric" autocomplete="off" maxlength="14" required>
          <label class="jt-label" for="cpf">CPF</label>
          <small class="jt-error"></small>
          <small class="field-help">Usado para identificar documentos e processos quando a integração estiver habilitada.</small>
        </div>

        <div class="form-grid oab-fields google-oab-grid" data-oab-fields>
          <div class="field jt-field">
            <span class="auth-field-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" focusable="false"><path d="M4 7h16"/><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><rect x="4" y="7" width="16" height="13" rx="2"/><path d="M9 13h6"/></svg>
            </span>
            <input class="input jt-input" type="text" id="inscricao" name="inscricao" inputmode="numeric" autocomplete="off" maxlength="7">
            <label class="jt-label" for="inscricao">Número da OAB</label>
            <small class="jt-error"></small>
          </div>

          <div class="field jt-field jt-field-uf">
            <span class="auth-field-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" focusable="false"><path d="M12 21s7-4.35 7-11a7 7 0 0 0-14 0c0 6.65 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </span>
            <select class="select jt-input" id="oab_uf" name="oab_uf" aria-label="UF da inscrição">
              <option value=""></option>
              <option>AC</option><option>AL</option><option>AP</option><option>AM</option>
              <option>BA</option><option>CE</option><option>DF</option><option>ES</option>
              <option>GO</option><option>MA</option><option>MT</option><option>MS</option>
              <option>MG</option><option>PA</option><option>PB</option><option>PR</option>
              <option>PE</option><option>PI</option><option>RJ</option><option>RN</option>
              <option>RS</option><option>RO</option><option>RR</option><option>SC</option>
              <option>SP</option><option>SE</option><option>TO</option>
            </select>
            <label class="jt-label" for="oab_uf">UF</label>
            <small class="jt-error"></small>
          </div>
        </div>

        <div class="auth-note" data-professional-note hidden>
          A OAB/registro será usado somente para validação profissional interna. O acesso profissional fica bloqueado até a aprovação do admin.
        </div>

        <label class="checkline">
          <input type="checkbox" required>
          <span>Li e aceito os <a href="termos.php">Termos de Uso</a> e a <a href="privacidade.php">Política de Privacidade</a>.</span>
        </label>

        <label class="checkline">
          <input type="checkbox" name="maioridade_confirmada" value="1" required>
          <span>Declaro que tenho 18 anos ou mais e que as informacoes fornecidas sao verdadeiras.</span>
        </label>

        <button class="btn btn-primary btn-block" type="submit" data-loading-text="Concluindo...">Concluir cadastro</button>
      </form>

      <p class="auth-home-link">
        <a href="login.html">← Voltar para login</a>
      </p>
    </div>
  </section>

  <aside class="auth-art auth-slider" aria-label="JusTraduz">
    <svg class="auth-line-field" viewBox="0 0 904 940" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
      <defs>
        <filter id="authLineBlurGoogle">
          <feGaussianBlur stdDeviation="3"/>
        </filter>
        <linearGradient id="authLineAGoogle" x1="0" y1="760" x2="620" y2="0" gradientUnits="userSpaceOnUse">
          <stop stop-color="#00d6c7" stop-opacity=".92"/>
          <stop offset=".48" stop-color="#fff3bb" stop-opacity=".88"/>
          <stop offset="1" stop-color="#00d6c7" stop-opacity=".78"/>
        </linearGradient>
        <linearGradient id="authLineBGoogle" x1="0" y1="780" x2="850" y2="940" gradientUnits="userSpaceOnUse">
          <stop stop-color="#00d6c7" stop-opacity=".78"/>
          <stop offset=".48" stop-color="#fef0b6" stop-opacity=".9"/>
          <stop offset="1" stop-color="#00d6c7" stop-opacity=".55"/>
        </linearGradient>
      </defs>
      <path class="auth-line auth-line-main" d="M-126 782 C32 732 108 640 168 500 C242 326 356 124 586 -46" stroke="url(#authLineAGoogle)" stroke-width="3">
        <animate attributeName="d" dur="15s" repeatCount="indefinite" calcMode="spline" keyTimes="0;.33;.66;1" keySplines=".42 0 .58 1;.42 0 .58 1;.42 0 .58 1"
          values="M-126 782 C32 732 108 640 168 500 C242 326 356 124 586 -46;
                  M-126 782 C44 720 118 660 180 500 C238 338 382 142 586 -46;
                  M-126 782 C22 744 98 616 162 490 C270 314 344 102 586 -46;
                  M-126 782 C32 732 108 640 168 500 C242 326 356 124 586 -46"/>
      </path>
      <path class="auth-line auth-line-glow" d="M-126 782 C32 732 108 640 168 500 C242 326 356 124 586 -46" stroke="#07fff0" stroke-opacity=".46" stroke-width="8" filter="url(#authLineBlurGoogle)">
        <animate attributeName="d" dur="15s" repeatCount="indefinite" calcMode="spline" keyTimes="0;.33;.66;1" keySplines=".42 0 .58 1;.42 0 .58 1;.42 0 .58 1"
          values="M-126 782 C32 732 108 640 168 500 C242 326 356 124 586 -46;
                  M-126 782 C44 720 118 660 180 500 C238 338 382 142 586 -46;
                  M-126 782 C22 744 98 616 162 490 C270 314 344 102 586 -46;
                  M-126 782 C32 732 108 640 168 500 C242 326 356 124 586 -46"/>
      </path>
      <path class="auth-line auth-line-low" d="M-150 690 C74 724 246 820 408 904 C540 972 654 1010 838 986" stroke="url(#authLineBGoogle)" stroke-width="3">
        <animate attributeName="d" dur="17s" repeatCount="indefinite" calcMode="spline" keyTimes="0;.33;.66;1" keySplines=".42 0 .58 1;.42 0 .58 1;.42 0 .58 1"
          values="M-150 690 C74 724 246 820 408 904 C540 972 654 1010 838 986;
                  M-150 690 C86 704 238 836 418 892 C558 948 672 1022 838 986;
                  M-150 690 C60 748 270 800 410 916 C548 996 690 982 838 986;
                  M-150 690 C74 724 246 820 408 904 C540 972 654 1010 838 986"/>
      </path>
      <path class="auth-line auth-line-thin" d="M-80 84 C114 28 300 74 466 186 C632 298 754 470 982 402" stroke="#04dcca" stroke-opacity=".38" stroke-width="1.6"/>
      <path class="auth-line auth-line-thin" d="M-76 760 C128 646 216 552 296 398 C386 226 500 70 700 -38" stroke="#04dcca" stroke-opacity=".34" stroke-width="1.4"/>
      <path class="auth-line auth-line-thin" d="M-60 816 C146 790 326 846 492 928 C642 1002 780 1004 960 910" stroke="#04dcca" stroke-opacity=".34" stroke-width="1.4"/>
    </svg>
    <div class="auth-art-inner">
      <a href="index.php" class="slider-logo-link">
        <img class="slider-logo" src="assets/img/logo-modo-escuro-normalizado.png" alt="JusTraduz">
      </a>
    </div>
  </aside>
</main>

<script src="assets/js/phone-mask.js?v=cpf-validator-1"></script>
<script src="assets/js/auth.js?v=custom-select-flow-8"></script>
<script src="assets/js/auth-novo.js?v=opening-skip-1"></script>
<script src="assets/js/accessibility.js?v=2026.07.02-vlibras-1"></script>
<script src="assets/js/vlibras-init.js?v=2026.07.02-vlibras-1" defer></script>
</body>
</html>
