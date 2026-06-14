(function () {
  'use strict';

  var storageKey = 'justraduz_accessibility_v1';
  var fontSteps = [100, 110, 125, 150];
  var state = { font: 100, contrast: false, readable: false, motion: false, speechRate: 1 };
  var launcher;
  var liveRegion;
  var panelBackdrop;
  var previousFocus;
  var speechChunks = [];
  var speechIndex = 0;
  var speechStatus = 'idle';
  var activeUtterance = null;
  var speechRunId = 0;

  try {
    state = Object.assign(state, JSON.parse(localStorage.getItem(storageKey) || '{}'));
  } catch (error) {}

  if ([0.65, 1, 1.6].indexOf(Number(state.speechRate)) === -1) {
    state.speechRate = Number(state.speechRate) < 1 ? 0.65 : (Number(state.speechRate) > 1 ? 1.6 : 1);
  }

  function save() {
    try { localStorage.setItem(storageKey, JSON.stringify(state)); } catch (error) {}
  }

  function apply(announce) {
    var root = document.documentElement;
    root.dataset.a11yFont = String(state.font);
    root.dataset.a11yContrast = String(!!state.contrast);
    root.dataset.a11yReadable = String(!!state.readable);
    root.dataset.a11yMotion = state.motion ? 'reduce' : 'normal';
    save();
    syncPanel();
    if (announce) speak(announce);
  }

  function speak(message, urgent) {
    if (!liveRegion) return;
    liveRegion.setAttribute('aria-live', urgent ? 'assertive' : 'polite');
    liveRegion.textContent = '';
    window.setTimeout(function () { liveRegion.textContent = message; }, 30);
  }

  function makeSkipLink() {
    var main = document.querySelector('main');
    if (!main) return;
    if (!main.id) main.id = 'conteudo-principal';
    main.setAttribute('tabindex', '-1');
    var link = document.createElement('a');
    link.className = 'skip-link';
    link.href = '#' + main.id;
    link.textContent = 'Pular para o conteúdo principal';
    document.body.insertBefore(link, document.body.firstChild);
  }

  function buildLauncher() {
    liveRegion = document.createElement('div');
    liveRegion.className = 'a11y-live-region';
    liveRegion.setAttribute('role', 'status');
    liveRegion.setAttribute('aria-live', 'polite');
    liveRegion.setAttribute('aria-atomic', 'true');
    document.body.appendChild(liveRegion);

    launcher = document.createElement('button');
    launcher.type = 'button';
    launcher.className = 'a11y-launcher';
    launcher.setAttribute('aria-label', 'Abrir menu de acessibilidade');
    launcher.innerHTML =
      '<span class="a11y-launcher-glow" aria-hidden="true"></span>' +
      '<span class="a11y-launcher-icon" aria-hidden="true">' +
      '<svg viewBox="0 0 64 64" focusable="false"><circle cx="32" cy="14" r="6"></circle>' +
      '<path d="M17 25h30"></path><path d="M32 25v15"></path>' +
      '<path d="M32 39 23 51"></path><path d="M32 39 41 51"></path></svg></span>' +
      '<span class="a11y-launcher-label" aria-hidden="true">Acessibilidade</span>';
    launcher.setAttribute('aria-haspopup', 'dialog');
    launcher.setAttribute('aria-expanded', 'false');
    launcher.addEventListener('click', openPanel);
    document.body.appendChild(launcher);
    launcher.addEventListener('animationend', finishLauncherEntrance, { once: true });
    window.setTimeout(finishLauncherEntrance, 1300);
    alignLauncherWithVlibras();
  }

  function ensureVlibras() {
    var existingWidget = document.querySelector('[vw]');
    var existingScript = document.querySelector('script[src*="vlibras-plugin.js"]');
    var startAttempts = 0;

    if (!existingWidget) {
      var widget = document.createElement('div');
      widget.setAttribute('vw', '');
      widget.className = 'enabled';
      widget.innerHTML =
        '<div vw-access-button class="active"></div>' +
        '<div vw-plugin-wrapper><div class="vw-plugin-top-wrapper"></div></div>';
      document.body.appendChild(widget);
    }

    function startWidget() {
      if (window.JusTraduzVlibrasStarted) return;
      if (!window.VLibras || !window.VLibras.Widget) {
        startAttempts++;
        if (startAttempts < 20) window.setTimeout(startWidget, 250);
        return;
      }
      window.JusTraduzVlibrasStarted = true;
      new window.VLibras.Widget('https://vlibras.gov.br/app');
    }

    if (window.VLibras && window.VLibras.Widget) {
      startWidget();
      return;
    }

    if (existingScript) {
      startWidget();
      window.addEventListener('load', startWidget);
      return;
    }

    var script = document.createElement('script');
    script.src = 'https://vlibras.gov.br/app/vlibras-plugin.js';
    script.onload = startWidget;
    document.body.appendChild(script);
  }

  function finishLauncherEntrance() {
    if (!launcher) return;
    launcher.classList.add('has-entered');
  }

  function fitVlibrasWidget() {
    var wrapper = document.querySelector('[vw-plugin-wrapper]');
    if (!wrapper) return;

    var compact = window.innerWidth <= 720;
    var buttonSize = compact ? 48 : 52;
    var right = compact ? 12 : 18;
    var sideGap = compact ? 8 : 12;
    var widthLimit = compact ? 76 : 88;
    var heightLimit = compact ? 20 : 24;
    var width = Math.max(220, Math.min(compact ? 280 : 300, window.innerWidth - widthLimit));
    var height = Math.max(320, Math.min(compact ? 410 : 450, window.innerHeight - heightLimit));

    wrapper.style.setProperty('position', 'fixed', 'important');
    wrapper.style.setProperty('top', '50%', 'important');
    wrapper.style.setProperty('right', (right + buttonSize + sideGap) + 'px', 'important');
    wrapper.style.setProperty('bottom', 'auto', 'important');
    wrapper.style.setProperty('left', 'auto', 'important');
    wrapper.style.setProperty('width', width + 'px', 'important');
    wrapper.style.setProperty('height', height + 'px', 'important');
    wrapper.style.setProperty('min-height', '0', 'important');
    wrapper.style.setProperty('max-width', (window.innerWidth - widthLimit) + 'px', 'important');
    wrapper.style.setProperty('max-height', (window.innerHeight - heightLimit) + 'px', 'important');
    wrapper.style.setProperty('overflow', 'hidden', 'important');
    wrapper.style.setProperty('border-radius', '14px', 'important');
    wrapper.style.setProperty('transform', 'translateY(-50%)', 'important');
    wrapper.style.setProperty('z-index', '99998', 'important');
  }

  function enforceVlibrasLayout() {
    fitVlibrasWidget();
    window.setTimeout(fitVlibrasWidget, 250);
    window.setTimeout(fitVlibrasWidget, 1000);
    window.setTimeout(fitVlibrasWidget, 2500);
  }

  function bindVlibrasFallbackToggle() {
    var button = document.querySelector('[vw-access-button]');
    if (!button || button.dataset.justraduzVlibrasToggle === 'true') return;

    button.dataset.justraduzVlibrasToggle = 'true';
    button.addEventListener('click', function () {
      window.setTimeout(function () {
        var wrapper = document.querySelector('[vw-plugin-wrapper]');
        if (!wrapper) return;

        var rect = wrapper.getBoundingClientRect();
        var isClosed = !wrapper.classList.contains('active') || rect.width === 0 || rect.height === 0;
        if (isClosed) {
          wrapper.classList.add('active');
          wrapper.style.setProperty('display', 'block', 'important');
        }
        fitVlibrasWidget();
      }, 80);
    });
  }

  function alignLauncherWithVlibras() {
    if (!launcher) return;
    launcher.classList.add('is-aligned-vlibras');
    enforceVlibrasLayout();
    bindVlibrasFallbackToggle();
    window.setTimeout(bindVlibrasFallbackToggle, 1000);
    window.addEventListener('resize', fitVlibrasWidget);
  }

  function openPanel() {
    if (panelBackdrop) return;
    previousFocus = document.activeElement;
    launcher.setAttribute('aria-expanded', 'true');
    panelBackdrop = document.createElement('div');
    panelBackdrop.className = 'a11y-panel-backdrop';
    panelBackdrop.innerHTML =
      '<section class="a11y-panel" role="dialog" aria-modal="true" aria-labelledby="a11y-title" aria-describedby="a11y-description">' +
      '<div class="a11y-panel-header"><div class="a11y-panel-title"><span class="a11y-panel-symbol" aria-hidden="true">' +
      '<svg viewBox="0 0 64 64"><circle cx="32" cy="14" r="6"></circle><path d="M17 25h30"></path><path d="M32 25v15"></path><path d="M32 39 23 51"></path><path d="M32 39 41 51"></path></svg>' +
      '</span><h2 id="a11y-title">Acessibilidade</h2></div><button class="a11y-close" type="button" aria-label="Fechar acessibilidade">×</button></div>' +
      '<p id="a11y-description">Ajuste a tela do jeito que ficar mais confortável para você.</p>' +
      '<div class="a11y-panel-section"><strong>Tamanho do conteúdo</strong><div class="a11y-font-controls">' +
      '<button class="btn btn-outline" type="button" data-a11y-font-down aria-label="Diminuir conteúdo">A−</button>' +
      '<span class="a11y-font-value" aria-live="polite">100%</span>' +
      '<button class="btn btn-outline" type="button" data-a11y-font-up aria-label="Aumentar conteúdo">A+</button></div></div>' +
      '<div class="a11y-panel-section a11y-panel-actions">' +
      '<button class="btn btn-outline a11y-toggle" type="button" data-a11y-contrast aria-pressed="false">Alto contraste</button>' +
      '<button class="btn btn-outline a11y-toggle" type="button" data-a11y-readable aria-pressed="false">Leitura confortável</button>' +
      '<button class="btn btn-outline a11y-toggle" type="button" data-a11y-motion aria-pressed="false">Reduzir movimentos</button></div>' +
      '<div class="a11y-panel-section a11y-speech-section"><div class="a11y-section-heading"><span class="a11y-speech-icon" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24"><path d="M4 10v4"></path><path d="M8 7v10"></path><path d="M12 4v16"></path><path d="M16 7v10"></path><path d="M20 10v4"></path></svg>' +
      '</span><div><strong>Leitura em voz alta</strong><small>Ouça o conteúdo principal desta página.</small></div></div>' +
      '<div class="a11y-speech-controls"><button class="btn btn-primary" type="button" data-a11y-speech-start>Ouvir página</button>' +
      '<button class="btn btn-outline" type="button" data-a11y-speech-pause disabled>Pausar</button>' +
      '<button class="btn btn-outline" type="button" data-a11y-speech-stop disabled>Parar</button></div>' +
      '<label class="a11y-speed-label" for="a11y-speech-rate">Velocidade <select class="select" id="a11y-speech-rate" data-a11y-speech-rate>' +
      '<option value="0.65">Lenta (0,65×)</option><option value="1">Normal (1×)</option><option value="1.6">Rápida (1,6×)</option></select></label>' +
      '<p class="a11y-speech-status" data-a11y-speech-status role="status" aria-live="polite">Pronto para iniciar.</p></div>' +
      '<div class="a11y-panel-section"><button class="btn btn-soft" type="button" data-a11y-reset>Restaurar padrão</button></div>' +
      '</section>';
    document.body.appendChild(panelBackdrop);

    panelBackdrop.querySelector('.a11y-close').addEventListener('click', closePanel);
    panelBackdrop.addEventListener('click', function (event) { if (event.target === panelBackdrop) closePanel(); });
    panelBackdrop.querySelector('[data-a11y-font-up]').addEventListener('click', function () { changeFont(1); });
    panelBackdrop.querySelector('[data-a11y-font-down]').addEventListener('click', function () { changeFont(-1); });
    panelBackdrop.querySelector('[data-a11y-contrast]').addEventListener('click', function () { state.contrast = !state.contrast; apply('Alto contraste ' + (state.contrast ? 'ativado' : 'desativado')); });
    panelBackdrop.querySelector('[data-a11y-readable]').addEventListener('click', function () { state.readable = !state.readable; apply('Leitura confortável ' + (state.readable ? 'ativada' : 'desativada')); });
    panelBackdrop.querySelector('[data-a11y-motion]').addEventListener('click', function () { state.motion = !state.motion; apply('Redução de movimentos ' + (state.motion ? 'ativada' : 'desativada')); });
    panelBackdrop.querySelector('[data-a11y-speech-start]').addEventListener('click', startPageSpeech);
    panelBackdrop.querySelector('[data-a11y-speech-pause]').addEventListener('click', toggleSpeechPause);
    panelBackdrop.querySelector('[data-a11y-speech-stop]').addEventListener('click', function () { stopPageSpeech(true); });
    panelBackdrop.querySelector('[data-a11y-speech-rate]').addEventListener('change', function (event) {
      state.speechRate = Number(event.target.value) || 1;
      save();
      if (speechStatus !== 'idle') {
        updateSpeechStatus('Aplicando nova velocidade...');
        restartPageSpeech();
      } else {
        updateSpeechStatus('Velocidade selecionada: ' + speechRateLabel() + '.');
      }
    });
    panelBackdrop.querySelector('[data-a11y-reset]').addEventListener('click', resetAccessibilityPreferences);
    if (!supportsSpeech()) {
      panelBackdrop.querySelector('.a11y-speech-section').hidden = true;
    }
    syncPanel();
    panelBackdrop.querySelector('.a11y-close').focus();
  }

  function closePanel() {
    if (!panelBackdrop) return;
    panelBackdrop.remove();
    panelBackdrop = null;
    launcher.setAttribute('aria-expanded', 'false');
    if (previousFocus && previousFocus.focus) previousFocus.focus();
  }

  function changeFont(direction) {
    var index = fontSteps.indexOf(Number(state.font));
    state.font = fontSteps[Math.max(0, Math.min(fontSteps.length - 1, index + direction))];
    apply('Tamanho do conteúdo: ' + state.font + ' por cento');
  }

  function syncPanel() {
    if (!panelBackdrop) return;
    panelBackdrop.querySelector('.a11y-font-value').textContent = state.font + '%';
    panelBackdrop.querySelector('[data-a11y-contrast]').setAttribute('aria-pressed', String(!!state.contrast));
    panelBackdrop.querySelector('[data-a11y-readable]').setAttribute('aria-pressed', String(!!state.readable));
    panelBackdrop.querySelector('[data-a11y-motion]').setAttribute('aria-pressed', String(!!state.motion));
    var rate = panelBackdrop.querySelector('[data-a11y-speech-rate]');
    if (rate) rate.value = String(state.speechRate || 1);
    syncSpeechControls();
  }

  function supportsSpeech() {
    return 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
  }

  function pageSpeechText() {
    var main = document.querySelector('main');
    if (!main) return '';
    var excluded = 'script, style, nav, form, button, input, select, textarea, [hidden], [aria-hidden="true"], .sr-only, .help-dot, .a11y-live-region';
    var walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
    var parts = [];
    var node;

    while ((node = walker.nextNode())) {
      var parent = node.parentElement;
      if (!parent || parent.closest(excluded)) continue;
      var styles = window.getComputedStyle(parent);
      if (styles.display === 'none' || styles.visibility === 'hidden' || Number(styles.opacity) === 0) continue;
      var text = node.textContent.replace(/\s+/g, ' ').trim();
      if (text) parts.push(text);
    }

    return parts.join(' ').replace(/\s+/g, ' ').trim();
  }

  function splitSpeechText(text) {
    var sentences = text.match(/[^.!?;:]+[.!?;:]?|.+$/g) || [];
    var chunks = [];
    sentences.forEach(function (sentence) {
      sentence = sentence.trim();
      while (sentence.length > 220) {
        var breakAt = sentence.lastIndexOf(' ', 220);
        if (breakAt < 80) breakAt = 220;
        chunks.push(sentence.slice(0, breakAt).trim());
        sentence = sentence.slice(breakAt).trim();
      }
      if (sentence) chunks.push(sentence);
    });
    return chunks;
  }

  function preferredVoice() {
    var voices = window.speechSynthesis.getVoices();
    return voices.find(function (voice) { return /^pt-BR$/i.test(voice.lang); }) ||
      voices.find(function (voice) { return /^pt/i.test(voice.lang); }) || null;
  }

  function startPageSpeech() {
    if (!supportsSpeech()) return;
    stopPageSpeech(false);
    beginPageSpeech();
  }

  function restartPageSpeech() {
    stopPageSpeech(false);
    window.setTimeout(beginPageSpeech, 120);
  }

  function beginPageSpeech() {
    var text = pageSpeechText();
    if (!text) {
      updateSpeechStatus('Não encontrei conteúdo para ler.');
      return;
    }
    speechChunks = splitSpeechText(text);
    speechIndex = 0;
    speechStatus = 'speaking';
    speechRunId++;
    updateSpeechStatus('Lendo em velocidade ' + speechRateLabel() + '.');
    syncSpeechControls();
    speakNextChunk(speechRunId);
  }

  function speakNextChunk(runId) {
    if (runId !== speechRunId) return;
    if (speechStatus === 'idle' || speechIndex >= speechChunks.length) {
      if (speechStatus !== 'idle') {
        speechStatus = 'idle';
        updateSpeechStatus('Leitura concluída.');
        syncSpeechControls();
      }
      return;
    }
    activeUtterance = new SpeechSynthesisUtterance(speechChunks[speechIndex]);
    activeUtterance.lang = 'pt-BR';
    activeUtterance.rate = Number(state.speechRate) || 1;
    var voice = preferredVoice();
    if (voice) activeUtterance.voice = voice;
    activeUtterance.onend = function () {
      if (speechStatus === 'idle' || runId !== speechRunId) return;
      speechIndex++;
      speakNextChunk(runId);
    };
    activeUtterance.onerror = function (event) {
      if (event.error === 'canceled' || event.error === 'interrupted') return;
      speechStatus = 'idle';
      updateSpeechStatus('Não foi possível continuar a leitura.');
      syncSpeechControls();
    };
    window.speechSynthesis.speak(activeUtterance);
  }

  function speechRateLabel() {
    var rate = Number(state.speechRate) || 1;
    if (rate < .9) return 'lenta';
    if (rate > 1.1) return 'rápida';
    return 'normal';
  }

  function toggleSpeechPause() {
    if (!supportsSpeech() || speechStatus === 'idle') return;
    if (window.speechSynthesis.paused) {
      window.speechSynthesis.resume();
      speechStatus = 'speaking';
      updateSpeechStatus('Leitura continuada.');
    } else {
      window.speechSynthesis.pause();
      speechStatus = 'paused';
      updateSpeechStatus('Leitura pausada.');
    }
    syncSpeechControls();
  }

  function stopPageSpeech(announce) {
    speechRunId++;
    if (supportsSpeech()) window.speechSynthesis.cancel();
    speechStatus = 'idle';
    speechChunks = [];
    speechIndex = 0;
    activeUtterance = null;
    if (announce) updateSpeechStatus('Leitura interrompida.');
    syncSpeechControls();
  }

  function updateSpeechStatus(message) {
    if (!panelBackdrop) return;
    var status = panelBackdrop.querySelector('[data-a11y-speech-status]');
    if (status) status.textContent = message;
  }

  function syncSpeechControls() {
    if (!panelBackdrop) return;
    var start = panelBackdrop.querySelector('[data-a11y-speech-start]');
    var pause = panelBackdrop.querySelector('[data-a11y-speech-pause]');
    var stop = panelBackdrop.querySelector('[data-a11y-speech-stop]');
    if (!start || !pause || !stop) return;
    var active = speechStatus !== 'idle';
    start.textContent = active ? 'Reiniciar leitura' : 'Ouvir página';
    pause.disabled = !active;
    stop.disabled = !active;
    pause.textContent = speechStatus === 'paused' ? 'Continuar' : 'Pausar';
    if (active) updateSpeechStatus(speechStatus === 'paused' ? 'Leitura pausada.' : 'Lendo em velocidade ' + speechRateLabel() + '.');
  }

  function enhanceTables() {
    document.querySelectorAll('table').forEach(function (table, index) {
      if (!table.querySelector('caption')) {
        var caption = document.createElement('caption');
        var section = table.closest('section');
        var heading = section && section.querySelector('h1, h2, h3');
        caption.textContent = heading ? heading.textContent.trim() : 'Tabela de informações ' + (index + 1);
        table.insertBefore(caption, table.firstChild);
      }
      table.querySelectorAll('thead th').forEach(function (header) { header.setAttribute('scope', 'col'); });
      var wrap = table.closest('.table-wrap');
      if (wrap) {
        wrap.tabIndex = 0;
        wrap.setAttribute('role', 'region');
        wrap.setAttribute('aria-label', table.caption.textContent + '. Deslize horizontalmente se necessário.');
      }
    });
  }

  function enhancePasswords() {
    document.querySelectorAll('input[type="password"]').forEach(function (input, index) {
      if (input.dataset.a11yPassword) return;
      input.dataset.a11yPassword = 'true';
      if (!input.id) input.id = 'senha-' + index;
      var wrap = document.createElement('div');
      wrap.className = 'password-field-wrap';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'password-toggle';
      button.textContent = 'Mostrar';
      button.setAttribute('aria-controls', input.id);
      button.setAttribute('aria-pressed', 'false');
      button.addEventListener('click', function () {
        var visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.textContent = visible ? 'Mostrar' : 'Ocultar';
        button.setAttribute('aria-pressed', String(!visible));
      });
      wrap.appendChild(button);
    });
  }

  function enhanceForms() {
    document.querySelectorAll('.alert, [data-auth-alert]').forEach(function (alert) {
      alert.setAttribute('role', alert.classList.contains('alert-error') ? 'alert' : 'status');
      alert.setAttribute('aria-live', alert.classList.contains('alert-error') ? 'assertive' : 'polite');
      alert.setAttribute('aria-atomic', 'true');
    });
    document.addEventListener('invalid', function (event) {
      var field = event.target;
      field.setAttribute('aria-invalid', 'true');
      speak('Confira o campo ' + accessibleName(field) + '. ' + field.validationMessage, true);
    }, true);
    document.addEventListener('input', function (event) {
      if (event.target.matches('input, select, textarea') && event.target.checkValidity()) event.target.removeAttribute('aria-invalid');
    });
  }

  function accessibleName(field) {
    var label = field.id && document.querySelector('label[for="' + CSS.escape(field.id) + '"]');
    return label ? label.textContent.trim() : (field.getAttribute('aria-label') || field.name || 'obrigatório');
  }

  function enhanceLinksAndNavigation() {
    document.querySelectorAll('a[target="_blank"]').forEach(function (link) {
      var label = link.getAttribute('aria-label') || link.textContent.trim();
      if (label && label.indexOf('nova aba') === -1) link.setAttribute('aria-label', label + ' (abre em nova aba)');
    });
    var navToggle = document.querySelector('[data-nav-toggle]');
    var header = document.querySelector('[data-site-header]');
    if (navToggle && header) {
      navToggle.setAttribute('aria-controls', 'menu-principal');
      var nav = header.querySelector('nav');
      if (nav) nav.id = 'menu-principal';
      navToggle.setAttribute('aria-expanded', String(header.classList.contains('is-open')));
      navToggle.addEventListener('click', function () {
        window.setTimeout(function () { navToggle.setAttribute('aria-expanded', String(header.classList.contains('is-open'))); }, 0);
      });
    }
  }

  function trapDialogFocus(event) {
    if (event.ctrlKey && event.altKey && event.key === '0') {
      event.preventDefault();
      resetAccessibilityPreferences();
      return;
    }
    if (event.key === 'Escape' && panelBackdrop) { closePanel(); return; }
    if (event.key !== 'Tab') return;
    var dialogs = Array.from(document.querySelectorAll('[role="dialog"][aria-modal="true"], [role="alertdialog"][aria-modal="true"]'))
      .filter(function (item) { return item.getAttribute('aria-hidden') !== 'true' && !item.closest('[inert]'); });
    var dialog = dialogs[dialogs.length - 1];
    if (!dialog) return;
    var items = Array.from(dialog.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
    if (!items.length) return;
    var first = items[0];
    var last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }

  function resetAccessibilityPreferences() {
    stopPageSpeech(false);
    state = { font: 100, contrast: false, readable: false, motion: false, speechRate: 1 };
    apply('Preferências de acessibilidade restauradas.');
  }

  function init() {
    apply();
    makeSkipLink();
    ensureVlibras();
    buildLauncher();
    enhanceTables();
    enhancePasswords();
    enhanceForms();
    enhanceLinksAndNavigation();
    document.addEventListener('keydown', trapDialogFocus);
    window.addEventListener('pagehide', function () { stopPageSpeech(false); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
