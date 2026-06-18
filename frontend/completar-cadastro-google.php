<?php
require_once __DIR__ . '/app/bootstrap.php';

if (empty($_SESSION['google_pending_user_id'])) {
    header('Location: ' . app_url('/frontend/login.html?erro=' . urlencode('Entre com Google para completar o cadastro.')));
    exit;
}

$errorMessage = trim((string) ($_GET['erro'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-BR" class="auth-google-complete-html">
<head>
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
  <link rel="stylesheet" href="assets/css/style.css?v=theme-slow-3">
  <link rel="stylesheet" href="assets/css/auth-novo.css?v=google-complete-4">
  <script src="assets/js/pwa.js" defer></script>
</head>
<body class="auth-google-complete-body">
  <main class="auth-page auth-google-complete-page">
    <section class="auth-panel">
      <a class="auth-brand google-complete-brand" href="index.html">
        <img src="assets/img/logo.png" alt="JusTraduz">
      </a>

      <div class="auth-card google-complete-card">
        <h1>Complete seu cadastro</h1>
        <p class="subtitle">O Google confirmou seu e-mail. Falta só escolher o tipo de conta e informar os dados obrigatórios.</p>

        <?php if ($errorMessage !== ''): ?>
          <div class="alert is-visible alert-error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <form class="auth-form" action="../backend/public/index.php?rota=/auth/google/complete-profile" method="post">
          <?= csrf_input() ?>
          <div class="alert" data-auth-alert></div>

          <div class="field jt-field">
            <select class="select jt-input" id="tipo" name="tipo" data-account-type required>
              <option value="cliente">Cliente</option>
              <option value="advogado">Advogado</option>
              <option value="estagiario">Estagiário</option>
            </select>
            <label class="jt-label" for="tipo">Tipo de conta</label>
            <small class="jt-error"></small>
          </div>

          <div class="field jt-field">
            <input class="input jt-input" type="tel" id="telefone" name="telefone" inputmode="tel" autocomplete="tel" maxlength="15" required>
            <label class="jt-label" for="telefone">Telefone</label>
            <small class="jt-error"></small>
          </div>

          <div class="field jt-field" data-cpf-fields>
            <input class="input jt-input" type="text" id="cpf" name="cpf" inputmode="numeric" autocomplete="off" maxlength="14" required>
            <label class="jt-label" for="cpf">CPF</label>
            <small class="jt-error"></small>
            <small class="field-help">Usado para identificar documentos e processos quando a integração estiver habilitada.</small>
          </div>

          <div class="form-grid oab-fields google-oab-grid" data-oab-fields>
            <div class="field jt-field">
              <input class="input jt-input" type="text" id="inscricao" name="inscricao" inputmode="numeric" autocomplete="off" maxlength="7">
              <label class="jt-label" for="inscricao">Número da OAB</label>
              <small class="jt-error"></small>
            </div>

            <div class="field jt-field jt-field-uf">
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
            <span>Li e aceito os <a href="termos.html">Termos de Uso</a> e a <a href="privacidade.html">Política de Privacidade</a>.</span>
          </label>

          <button class="btn btn-primary btn-block" type="submit" data-loading-text="Concluindo...">Concluir cadastro</button>
        </form>
      </div>
    </section>

    <aside class="auth-art auth-google-art" aria-label="JusTraduz">
      <div class="auth-art-inner">
        <a href="index.html" class="slider-logo-link">
          <img class="slider-logo" src="assets/img/logo.png" alt="JusTraduz">
        </a>
        <div class="auth-art-text">
          <h2>Seu acesso fica do jeito certo.</h2>
          <p>Clientes entram direto. Profissionais informam OAB e aguardam validação interna antes de acessar as áreas protegidas.</p>
        </div>
      </div>
    </aside>
  </main>

  <script src="assets/js/phone-mask.js?v=cpf-validator-1"></script>
  <script src="assets/js/auth.js?v=cpf-validator-1"></script>
  <script src="assets/js/accessibility.js?v=2026.06.16-a11y-stack-7"></script>
  <div vw class="enabled">
    <div vw-access-button class="active"></div>
    <div vw-plugin-wrapper>
      <div class="vw-plugin-top-wrapper"></div>
    </div>
  </div>
  <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
  <script>
    if (!window.JusTraduzVlibrasStarted && window.VLibras && window.VLibras.Widget) {
      window.JusTraduzVlibrasStarted = true;
      new window.VLibras.Widget('https://vlibras.gov.br/app');
    }
  </script>
</body>
</html>
