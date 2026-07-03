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

  function canUsePreferences() {
    return !!(window.JusTraduzCookieConsent && window.JusTraduzCookieConsent.allowed('preferences'));
  }

  function canUseExternalAccessibility() {
    if (!window.JusTraduzCookieConsent) return true;
    if (window.JusTraduzCookieConsent.hasDecision && !window.JusTraduzCookieConsent.hasDecision()) return true;
    return !!window.JusTraduzCookieConsent.allowed('accessibility');
  }

  if (canUsePreferences()) {
    try {
      state = Object.assign(state, JSON.parse(localStorage.getItem(storageKey) || '{}'));
    } catch (error) {}
  }

  if ([0.65, 1, 1.6].indexOf(Number(state.speechRate)) === -1) {
    state.speechRate = Number(state.speechRate) < 1 ? 0.65 : (Number(state.speechRate) > 1 ? 1.6 : 1);
  }

  function save() {
    if (!canUsePreferences()) return;
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

  function svg(viewBox, paths) {
    var node = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    node.setAttribute('viewBox', viewBox);
    node.setAttribute('focusable', 'false');
    paths.forEach(function (pathData) {
      var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', pathData);
      node.appendChild(path);
    });
    return node;
  }

  function accessibilityIcon() {
    var node = svg('0 0 64 64', [
      'M17 25h30',
      'M32 25v15',
      'M32 39 23 51',
      'M32 39 41 51'
    ]);
    var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    circle.setAttribute('cx', '32');
    circle.setAttribute('cy', '14');
    circle.setAttribute('r', '6');
    node.insertBefore(circle, node.firstChild);
    return node;
  }

  function audioIcon() {
    return svg('0 0 24 24', [
      'M4 10v4',
      'M8 7v10',
      'M12 4v16',
      'M16 7v10',
      'M20 10v4'
    ]);
  }

  function appendTextElement(parent, tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = text;
    parent.appendChild(node);
    return node;
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
    launcher.title = 'Acessibilidade';
    var glow = document.createElement('span');
    var icon = document.createElement('span');
    var label = document.createElement('span');
    glow.className = 'a11y-launcher-glow';
    glow.setAttribute('aria-hidden', 'true');
    icon.className = 'a11y-launcher-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.appendChild(accessibilityIcon());
    label.className = 'a11y-launcher-label';
    label.setAttribute('aria-hidden', 'true');
    label.textContent = 'Acessibilidade';
    launcher.appendChild(glow);
    launcher.appendChild(icon);
    launcher.appendChild(label);
    launcher.setAttribute('aria-haspopup', 'dialog');
    launcher.setAttribute('aria-expanded', 'false');
    launcher.addEventListener('click', openPanel);
    document.body.appendChild(launcher);
    launcher.addEventListener('animationend', finishLauncherEntrance, { once: true });
    window.setTimeout(finishLauncherEntrance, 1300);
    alignLauncherWithVlibras();
  }

  function ensureVlibras() {
    if (!canUseExternalAccessibility()) {
      cleanupVlibras();
      return;
    }

    normalizeVlibrasWidgets();
    var existingWidget = document.querySelector('[vw]');
    var existingScript = document.querySelector('script[src*="vlibras-plugin.js"]');
    var startAttempts = 0;

    if (!existingWidget) {
      var widget = document.createElement('div');
      widget.setAttribute('vw', '');
      widget.className = 'enabled';
      var accessButton = document.createElement('div');
      var pluginWrapper = document.createElement('div');
      var topWrapper = document.createElement('div');
      accessButton.setAttribute('vw-access-button', '');
      accessButton.className = 'active';
      pluginWrapper.setAttribute('vw-plugin-wrapper', '');
      topWrapper.className = 'vw-plugin-top-wrapper';
      pluginWrapper.appendChild(topWrapper);
      widget.appendChild(accessButton);
      widget.appendChild(pluginWrapper);
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
      enforceVlibrasLayout();
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

    if (window.JusTraduzCookieConsent && window.JusTraduzCookieConsent.loadScript) {
      if (window.JusTraduzCookieConsent.hasDecision && window.JusTraduzCookieConsent.hasDecision()) {
        window.JusTraduzCookieConsent.loadScript('accessibility', 'justraduz-vlibras-plugin', 'https://vlibras.gov.br/app/vlibras-plugin.js', startWidget);
      } else {
        loadVlibrasScript(startWidget);
      }
      return;
    }

    loadVlibrasScript(startWidget);
  }

  function loadVlibrasScript(callback) {
    var existing = document.getElementById('justraduz-vlibras-plugin');
    if (existing) {
      if (callback) existing.addEventListener('load', callback, { once: true });
      return existing;
    }

    var script = document.createElement('script');
    script.id = 'justraduz-vlibras-plugin';
    script.src = 'https://vlibras.gov.br/app/vlibras-plugin.js';
    script.async = true;
    if (callback) script.addEventListener('load', callback, { once: true });
    document.body.appendChild(script);
    return script;
  }

  function cleanupVlibras() {
    document.querySelectorAll('[vw], script[src*="vlibras.gov.br"]').forEach(function (node) {
      node.remove();
    });
    document.querySelectorAll('iframe[src*="vlibras"], iframe[src*="dicionario2.vlibras"]').forEach(function (node) {
      node.remove();
    });
    window.JusTraduzVlibrasStarted = false;
  }

  function normalizeVlibrasWidgets() {
    var widgets = Array.from(document.querySelectorAll('[vw]'));
    if (!widgets.length) return;

    widgets.slice(1).forEach(function (widget) {
      widget.remove();
    });
  }

  function finishLauncherEntrance() {
    if (!launcher) return;
    launcher.classList.add('has-entered');
  }

  function fitVlibrasWidget() {
    normalizeVlibrasWidgets();
    var wrapper = document.querySelector('[vw-plugin-wrapper]');

    var compact = window.innerWidth <= 720;
    var buttonSize = 44;
    var right = compact ? 14 : 24;
    var sideGap = compact ? 8 : 12;
    var widthLimit = compact ? 76 : 88;
    var heightLimit = compact ? 20 : 24;
    var width = Math.max(220, Math.min(compact ? 280 : 300, window.innerWidth - widthLimit));
    var height = Math.max(320, Math.min(compact ? 410 : 450, window.innerHeight - heightLimit));

    if (!wrapper) return;

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

  function makeButton(className, text, dataAttribute, ariaLabel) {
    var button = document.createElement('button');
    button.className = className;
    button.type = 'button';
    button.textContent = text;
    if (dataAttribute) button.setAttribute(dataAttribute, '');
    if (ariaLabel) button.setAttribute('aria-label', ariaLabel);
    return button;
  }

  function buildAccessibilityPanel() {
    var panel = document.createElement('section');
    panel.className = 'a11y-panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-labelledby', 'a11y-title');
    panel.setAttribute('aria-describedby', 'a11y-description');

    var header = document.createElement('div');
    var titleWrap = document.createElement('div');
    var symbol = document.createElement('span');
    var title = document.createElement('h2');
    var close = makeButton('a11y-close', 'x', null, 'Fechar acessibilidade');
    header.className = 'a11y-panel-header';
    titleWrap.className = 'a11y-panel-title';
    symbol.className = 'a11y-panel-symbol';
    symbol.setAttribute('aria-hidden', 'true');
    symbol.appendChild(accessibilityIcon());
    title.id = 'a11y-title';
    title.textContent = 'Acessibilidade';
    titleWrap.appendChild(symbol);
    titleWrap.appendChild(title);
    header.appendChild(titleWrap);
    header.appendChild(close);

    var description = document.createElement('p');
    description.id = 'a11y-description';
    description.textContent = 'Ajuste a tela do jeito que ficar mais confortavel para voce.';

    var fontSection = document.createElement('div');
    var fontControls = document.createElement('div');
    fontSection.className = 'a11y-panel-section';
    fontControls.className = 'a11y-font-controls';
    appendTextElement(fontSection, 'strong', '', 'Tamanho do conteudo');
    fontControls.appendChild(makeButton('btn btn-outline', 'A-', 'data-a11y-font-down', 'Diminuir conteudo'));
    var fontValue = appendTextElement(fontControls, 'span', 'a11y-font-value', '100%');
    fontValue.setAttribute('aria-live', 'polite');
    fontControls.appendChild(makeButton('btn btn-outline', 'A+', 'data-a11y-font-up', 'Aumentar conteudo'));
    fontSection.appendChild(fontControls);

    var actions = document.createElement('div');
    actions.className = 'a11y-panel-section a11y-panel-actions';
    [
      ['data-a11y-contrast', 'Alto contraste'],
      ['data-a11y-readable', 'Leitura confortavel'],
      ['data-a11y-motion', 'Reduzir movimentos']
    ].forEach(function (item) {
      var button = makeButton('btn btn-outline a11y-toggle', item[1], item[0]);
      button.setAttribute('aria-pressed', 'false');
      actions.appendChild(button);
    });

    var speech = document.createElement('div');
    var speechHeading = document.createElement('div');
    var speechIcon = document.createElement('span');
    var speechCopy = document.createElement('div');
    var speechControls = document.createElement('div');
    speech.className = 'a11y-panel-section a11y-speech-section';
    speechHeading.className = 'a11y-section-heading';
    speechIcon.className = 'a11y-speech-icon';
    speechIcon.setAttribute('aria-hidden', 'true');
    speechIcon.appendChild(audioIcon());
    appendTextElement(speechCopy, 'strong', '', 'Leitura em voz alta');
    appendTextElement(speechCopy, 'small', '', 'Ouca o conteudo principal desta pagina.');
    speechHeading.appendChild(speechIcon);
    speechHeading.appendChild(speechCopy);
    speechControls.className = 'a11y-speech-controls';
    speechControls.appendChild(makeButton('btn btn-primary', 'Ouvir pagina', 'data-a11y-speech-start'));
    var pause = makeButton('btn btn-outline', 'Pausar', 'data-a11y-speech-pause');
    var stop = makeButton('btn btn-outline', 'Parar', 'data-a11y-speech-stop');
    pause.disabled = true;
    stop.disabled = true;
    speechControls.appendChild(pause);
    speechControls.appendChild(stop);

    var speedLabel = document.createElement('label');
    var speedSelect = document.createElement('select');
    speedLabel.className = 'a11y-speed-label';
    speedLabel.setAttribute('for', 'a11y-speech-rate');
    speedLabel.appendChild(document.createTextNode('Velocidade '));
    speedSelect.className = 'select';
    speedSelect.id = 'a11y-speech-rate';
    speedSelect.setAttribute('data-a11y-speech-rate', '');
    [
      ['0.65', 'Lenta (0,65x)'],
      ['1', 'Normal (1x)'],
      ['1.6', 'Rapida (1,6x)']
    ].forEach(function (item) {
      var option = document.createElement('option');
      option.value = item[0];
      option.textContent = item[1];
      speedSelect.appendChild(option);
    });
    speedLabel.appendChild(speedSelect);

    var status = appendTextElement(speech, 'p', 'a11y-speech-status', 'Pronto para iniciar.');
    status.setAttribute('data-a11y-speech-status', '');
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    speech.insertBefore(speechHeading, status);
    speech.insertBefore(speechControls, status);
    speech.insertBefore(speedLabel, status);

    var resetSection = document.createElement('div');
    resetSection.className = 'a11y-panel-section';
    resetSection.appendChild(makeButton('btn btn-soft', 'Restaurar padrao', 'data-a11y-reset'));

    panel.appendChild(header);
    panel.appendChild(description);
    panel.appendChild(fontSection);
    panel.appendChild(actions);
    panel.appendChild(speech);
    panel.appendChild(resetSection);
    return panel;
  }

  function openPanel() {
    if (panelBackdrop) return;
    previousFocus = document.activeElement;
    ensureVlibras();
    launcher.setAttribute('aria-expanded', 'true');
    panelBackdrop = document.createElement('div');
    panelBackdrop.className = 'a11y-panel-backdrop';
    panelBackdrop.appendChild(buildAccessibilityPanel());
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
    function eyeIcon(hidden) {
      var node;
      if (hidden) {
        node = svg('0 0 24 24', [
          'M3 3l18 18',
          'M10.6 10.6a2 2 0 0 0 2.8 2.8',
          'M9.9 4.2A10.6 10.6 0 0 1 12 4c5.5 0 9 5 10 8a13.2 13.2 0 0 1-2.2 3.7',
          'M6.6 6.7C4.3 8.2 2.8 10.5 2 12c1 3 4.5 8 10 8 1.7 0 3.2-.4 4.5-1.1'
        ]);
      } else {
        node = svg('0 0 24 24', [
          'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z'
        ]);
        var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', '12');
        circle.setAttribute('cy', '12');
        circle.setAttribute('r', '3');
        node.appendChild(circle);
      }
      node.setAttribute('class', 'password-toggle-icon');
      node.setAttribute('aria-hidden', 'true');
      return node;
    }

    function setPasswordToggleState(button, visible) {
      button.replaceChildren(eyeIcon(visible));
      button.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
      button.setAttribute('title', visible ? 'Ocultar senha' : 'Mostrar senha');
      button.setAttribute('aria-pressed', String(visible));
    }

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
      button.setAttribute('aria-controls', input.id);
      setPasswordToggleState(button, false);
      button.addEventListener('click', function () {
        var visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        setPasswordToggleState(button, !visible);
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

  function bindAutoDismissAlert(alert) {
    if (!alert || alert.dataset.dismissBound === '1') return;
    alert.dataset.dismissBound = '1';
    var delay = parseInt(alert.getAttribute('data-alert-auto-dismiss') || '10000', 10);
    if (!Number.isFinite(delay) || delay <= 0) delay = 10000;
    window.setTimeout(function () {
      alert.classList.add('is-dismissing');
      window.setTimeout(function () {
        if (alert.parentNode) alert.parentNode.removeChild(alert);
      }, 280);
    }, delay);
  }

  function enhanceAutoDismissAlerts() {
    document.querySelectorAll('[data-alert-auto-dismiss]').forEach(bindAutoDismissAlert);

    if (!('MutationObserver' in window) || document.body.dataset.alertDismissObserver === '1') return;
    document.body.dataset.alertDismissObserver = '1';
    new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (!(node instanceof Element)) return;
          if (node.matches('[data-alert-auto-dismiss]')) bindAutoDismissAlert(node);
          node.querySelectorAll?.('[data-alert-auto-dismiss]').forEach(bindAutoDismissAlert);
        });
      });
    }).observe(document.body, { childList: true, subtree: true });
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
    buildLauncher();
    enhanceTables();
    enhancePasswords();
    enhanceForms();
    enhanceAutoDismissAlerts();
    enhanceLinksAndNavigation();
    document.addEventListener('keydown', trapDialogFocus);
    window.addEventListener('pagehide', function () { stopPageSpeech(false); });
    window.addEventListener('justraduz:cookie-consent-changed', function () {
      if (!canUseExternalAccessibility()) cleanupVlibras();
      else if (panelBackdrop) ensureVlibras();
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());

