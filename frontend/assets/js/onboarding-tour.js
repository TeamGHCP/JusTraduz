(function () {
  'use strict';

  function readConfig() {
    if (window.JusTraduzOnboarding) return window.JusTraduzOnboarding;

    var node = document.getElementById('justraduz-onboarding-config');
    if (!node) return null;

    try {
      return JSON.parse(node.textContent || '{}');
    } catch (error) {
      return null;
    }
  }

  var config = readConfig();
  var state = { steps: [], index: 0, manual: false, active: false };
  var overlay;
  var spotlight;
  var popover;
  var previousFocus;
  var scrollTimer;
  var mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 720px)') : null;

  function request(url, data, method) {
    var options = { method: method || 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (options.method === 'POST') {
      var body = new URLSearchParams(data || {});
      body.set('_csrf', config.csrfToken);
      options.body = body;
      options.headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
    }
    return fetch(url, options).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (result) {
        if (!response.ok || result.ok === false) {
          throw new Error(result.error || 'Falha na API de onboarding.');
        }
        return result;
      });
    });
  }

  function payload(extra) {
    return Object.assign({
      tour_key: config.tourKey,
      tour_version: config.tourVersion,
      dashboard_profile: config.profile,
      last_seen_step: state.index + 1
    }, extra || {});
  }

  function storageKey() {
    return 'justraduz_onboarding_' + config.userId + '_' + config.tourKey + '_' + config.tourVersion;
  }

  function collectSteps() {
    state.steps = Array.prototype.slice.call(document.querySelectorAll('[data-tour-step]'))
      .sort(function (a, b) { return Number(a.dataset.tourStep) - Number(b.dataset.tourStep); });
  }

  function start(manual, startAt) {
    collectSteps();
    if (!state.steps.length) return;
    state.manual = !!manual;
    state.index = Math.max(0, Math.min(Number(startAt || 0), state.steps.length - 1));
    state.active = true;
    previousFocus = document.activeElement;
    if (state.index === 0) scrollPageToTop();
    build();
    show();
    request(config.startUrl, payload({ manual: state.manual ? '1' : '0' })).catch(function () {});
  }

  function build() {
    overlay = document.createElement('div');
    overlay.className = 'onboarding-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    spotlight = document.createElement('div');
    spotlight.className = 'onboarding-spotlight';
    spotlight.setAttribute('aria-hidden', 'true');
    popover = document.createElement('section');
    popover.className = 'onboarding-popover';
    popover.setAttribute('role', 'dialog');
    popover.setAttribute('aria-modal', 'true');
    popover.setAttribute('aria-labelledby', 'onboarding-step-title');
    popover.setAttribute('aria-describedby', 'onboarding-step-description');
    popover.style.visibility = 'hidden';
    document.body.appendChild(overlay);
    document.body.appendChild(spotlight);
    document.body.appendChild(popover);
  }

  function scrollPageToTop() {
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });

    document.querySelectorAll('.app-shell, .app-main').forEach(function (container) {
      if (container.scrollTop) container.scrollTop = 0;
    });
  }

  function prefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function isMobileTour() {
    return mobileQuery ? mobileQuery.matches : window.innerWidth <= 720;
  }

  function profileLabel() {
    var labels = { cliente: 'Cliente', advogado: 'Advogado', admin: 'Admin' };
    return labels[config.profile] || 'JusTraduz';
  }

  function setSidebarOpen(shell, isOpen) {
    if (!shell) return;
    shell.classList.toggle('is-sidebar-mobile-open', isOpen);
    document.body.classList.toggle('sidebar-mobile-open', isOpen);
    var button = shell.querySelector('[data-sidebar-mobile-toggle]');
    if (button) button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }

  function syncSidebarForStep(element) {
    var shell = element.closest('.app-shell') || document.querySelector('.app-shell');
    var isSidebarStep = !!element.closest('[data-sidebar]');
    if (!isMobileTour()) {
      setSidebarOpen(shell, false);
      return;
    }
    setSidebarOpen(shell, isSidebarStep);
  }

  function appendText(parent, tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = text;
    parent.appendChild(node);
    return node;
  }

  function tourButton(className, text, dataAttribute) {
    var button = document.createElement('button');
    button.className = className;
    button.type = 'button';
    button.textContent = text;
    if (dataAttribute) button.setAttribute(dataAttribute, '');
    return button;
  }

  function iconSvg(viewBox, paths) {
    var node = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    node.setAttribute('viewBox', viewBox);
    node.setAttribute('aria-hidden', 'true');
    paths.forEach(function (pathData) {
      var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', pathData);
      node.appendChild(path);
    });
    return node;
  }

  function buildPopoverContent(element) {
    var fragment = document.createDocumentFragment();
    var title = appendText(fragment, 'h2', '', element.dataset.tourTitle || 'Conheca esta area');
    var description = appendText(fragment, 'p', '', element.dataset.tourDescription || '');
    var count = appendText(fragment, 'span', 'onboarding-count', 'Passo ' + (state.index + 1) + ' de ' + state.steps.length);
    var progress = document.createElement('div');
    var progressBar = document.createElement('span');
    var actions = document.createElement('div');

    title.id = 'onboarding-step-title';
    description.id = 'onboarding-step-description';
    progress.className = 'onboarding-progress';
    progressBar.style.width = (((state.index + 1) / state.steps.length) * 100) + '%';
    progress.appendChild(progressBar);
    actions.className = 'onboarding-actions';
    actions.appendChild(tourButton('btn btn-outline btn-sm', 'Pular', 'data-tour-skip'));
    if (state.index) {
      actions.appendChild(tourButton('btn btn-soft btn-sm', 'Voltar', 'data-tour-back'));
    }
    actions.appendChild(tourButton(
      'btn btn-primary btn-sm',
      state.index === state.steps.length - 1 ? 'Finalizar' : 'Proximo',
      'data-tour-next'
    ));

    fragment.appendChild(title);
    fragment.appendChild(description);
    fragment.appendChild(count);
    fragment.appendChild(progress);
    fragment.appendChild(actions);
    return fragment;
  }

  function isMostlyVisible(element) {
    var rect = element.getBoundingClientRect();
    var margin = 28;
    return rect.top >= margin &&
      rect.left >= margin &&
      rect.bottom <= window.innerHeight - margin &&
      rect.right <= window.innerWidth - margin;
  }

  function scrollStepIntoView(element) {
    if (state.index === 0 || isMostlyVisible(element)) return;
    element.scrollIntoView({
      behavior: prefersReducedMotion() ? 'auto' : 'smooth',
      block: isMobileTour() ? 'nearest' : 'center',
      inline: 'nearest'
    });
  }

  function schedulePosition(delay) {
    window.clearTimeout(scrollTimer);
    window.requestAnimationFrame(position);
    scrollTimer = window.setTimeout(position, delay || 360);
  }

  function show() {
    var element = state.steps[state.index];
    syncSidebarForStep(element);
    var sidebarModule = element.closest('[data-sidebar-module]');
    if (sidebarModule) {
      var shell = element.closest('.app-shell');
      var sidebar = element.closest('[data-sidebar]');
      if (shell) shell.classList.remove('is-sidebar-collapsed');
      if (sidebar) sidebar.classList.remove('is-collapsed');
      sidebarModule.classList.add('is-open');
      var toggle = sidebarModule.querySelector('[data-sidebar-module-toggle]');
      if (toggle) toggle.setAttribute('aria-expanded', 'true');
    }
    document.querySelectorAll('.onboarding-highlight').forEach(function (item) {
      item.classList.remove('onboarding-highlight');
    });
    element.classList.add('onboarding-highlight');
    scrollStepIntoView(element);
    popover.replaceChildren(buildPopoverContent(element));
    enhancePopover();
    bindPopover();
    schedulePosition();
    popover.querySelector('[data-tour-next]').focus();
  }

  function enhancePopover() {
    var title = popover.querySelector('#onboarding-step-title');
    var progress = popover.querySelector('.onboarding-progress');

    if (title && !popover.querySelector('.onboarding-popover-head')) {
      var head = document.createElement('div');
      head.className = 'onboarding-popover-head';
      appendText(head, 'span', 'onboarding-step-kicker', profileLabel() + ' tour');
      appendText(head, 'span', 'onboarding-count', 'Passo ' + (state.index + 1) + ' de ' + state.steps.length);
      title.parentNode.insertBefore(head, title);
    }

    Array.prototype.slice.call(popover.children).forEach(function (node) {
      if (node.classList && node.classList.contains('onboarding-count')) node.remove();
    });

    if (progress) {
      progress.setAttribute('aria-label', 'Progresso do tour');
      progress.parentNode.insertBefore(progressDots(), progress.nextSibling);
    }
  }

  function progressDots() {
    var dots = document.createElement('div');
    dots.className = 'onboarding-step-dots';
    dots.setAttribute('aria-hidden', 'true');
    state.steps.forEach(function (_, index) {
      var dot = document.createElement('span');
      if (index === state.index) dot.classList.add('is-active');
      if (index < state.index) dot.classList.add('is-complete');
      dots.appendChild(dot);
    });
    return dots;
  }

  function bindPopover() {
    var back = popover.querySelector('[data-tour-back]');
    if (back) back.addEventListener('click', function () { state.index--; show(); });
    popover.querySelector('[data-tour-skip]').addEventListener('click', confirmSkipModern);
    popover.querySelector('[data-tour-next]').addEventListener('click', function () {
      if (state.index >= state.steps.length - 1) {
        request(config.completeUrl, payload()).catch(function () {});
        close();
        return;
      }
      state.index++;
      show();
    });
  }

  function position() {
    if (!state.active) return;
    var rect = state.steps[state.index].getBoundingClientRect();
    var padding = 6;
    var hiddenTarget = rect.width <= 0 || rect.height <= 0 ||
      rect.bottom < 0 || rect.top > window.innerHeight ||
      rect.right < 0 || rect.left > window.innerWidth;

    spotlight.classList.toggle('is-hidden', hiddenTarget);
    spotlight.style.top = Math.max(4, rect.top - padding) + 'px';
    spotlight.style.left = Math.max(4, rect.left - padding) + 'px';
    spotlight.style.width = Math.max(1, Math.min(window.innerWidth - 8, rect.width + (padding * 2))) + 'px';
    spotlight.style.height = Math.max(1, Math.min(window.innerHeight - 8, rect.height + (padding * 2))) + 'px';
    popover.classList.toggle('is-mobile', isMobileTour());
    if (isMobileTour()) {
      popover.style.top = '';
      popover.style.left = '';
      popover.style.visibility = 'visible';
      return;
    }
    var box = popover.getBoundingClientRect();
    var viewportPadding = 12;
    var gap = 14;
    var top = rect.bottom + gap;
    if (top + box.height > window.innerHeight - viewportPadding) top = rect.top - box.height - gap;
    top = Math.max(viewportPadding, Math.min(top, window.innerHeight - box.height - viewportPadding));
    var left = Math.max(viewportPadding, Math.min(rect.left, window.innerWidth - box.width - viewportPadding));
    popover.style.top = top + 'px';
    popover.style.left = left + 'px';
    popover.style.visibility = 'visible';
  }

  function skip(mode, modal) {
    var buttons = modal.querySelectorAll('button');
    var message = modal.querySelector('[data-skip-message]');
    buttons.forEach(function (button) { button.disabled = true; });
    message.hidden = false;
    message.textContent = 'Salvando sua preferência...';

    request(config.skipUrl, payload({ skip_mode: mode, manual: state.manual ? '1' : '0' }))
      .then(function () {
        try {
          sessionStorage.setItem(storageKey(), mode);
        } catch (error) {}
        modal.remove();
        close();
      })
      .catch(function (error) {
        message.textContent = error.message || 'Não foi possível salvar. Tente novamente.';
        buttons.forEach(function (button) { button.disabled = false; });
      });
  }

  function buildSkipOption(className, iconPaths, title, description, arrow, dataAttribute) {
    var button = document.createElement('button');
    var icon = document.createElement('span');
    var copy = document.createElement('span');
    var arrowNode = document.createElement('span');

    button.className = 'onboarding-skip-option ' + className;
    button.type = 'button';
    button.setAttribute(dataAttribute, '');
    icon.className = 'onboarding-option-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.appendChild(iconSvg('0 0 24 24', iconPaths));
    appendText(copy, 'strong', '', title);
    appendText(copy, 'small', '', description);
    arrowNode.className = 'onboarding-option-arrow';
    arrowNode.setAttribute('aria-hidden', 'true');
    arrowNode.textContent = arrow;

    button.appendChild(icon);
    button.appendChild(copy);
    button.appendChild(arrowNode);
    return button;
  }

  function buildSkipDialog() {
    var card = document.createElement('section');
    var head = document.createElement('div');
    var icon = document.createElement('span');
    var copy = document.createElement('div');
    var message = document.createElement('p');
    var options = document.createElement('div');
    var continueButton = document.createElement('button');

    card.className = 'onboarding-skip-card';
    card.setAttribute('role', 'alertdialog');
    card.setAttribute('aria-modal', 'true');
    card.setAttribute('aria-labelledby', 'onboarding-skip-title');
    head.className = 'onboarding-skip-head';
    icon.className = 'onboarding-skip-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.appendChild(iconSvg('0 0 24 24', [
      'M12 3.25a8.75 8.75 0 0 0-7.62 13.05l-1.13 4.45 4.45-1.13A8.75 8.75 0 1 0 12 3.25Z',
      'M9.35 9.45a2.75 2.75 0 1 1 4.85 1.78c-.95.82-1.7 1.3-1.7 2.77',
      'M12.5 17.15h.01'
    ]));
    appendText(copy, 'span', 'onboarding-skip-eyebrow', 'Preferencia do tour');
    var title = appendText(copy, 'h2', '', 'Quer ver este tour depois?');
    title.id = 'onboarding-skip-title';
    appendText(copy, 'p', '', 'Escolha uma opcao. Voce podera abrir o tour novamente pela sidebar.');
    head.appendChild(icon);
    head.appendChild(copy);

    message.className = 'onboarding-save-message';
    message.setAttribute('data-skip-message', '');
    message.hidden = true;

    options.className = 'onboarding-skip-options';
    options.appendChild(buildSkipOption(
      'is-later',
      ['M12 8v5l3 2'],
      'Sim, mostrar depois',
      'O tour aparecera novamente no proximo acesso.',
      '>',
      'data-skip-later'
    ));
    options.firstChild.querySelector('svg').appendChild((function () {
      var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      circle.setAttribute('cx', '12');
      circle.setAttribute('cy', '12');
      circle.setAttribute('r', '9');
      return circle;
    }()));
    options.appendChild(buildSkipOption(
      'is-never',
      ['m8.5 8.5 7 7'],
      'Nao mostrar novamente',
      'O tour ficara oculto ate voce inicia-lo manualmente.',
      '>',
      'data-skip-never'
    ));
    options.lastChild.querySelector('svg').insertBefore((function () {
      var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      circle.setAttribute('cx', '12');
      circle.setAttribute('cy', '12');
      circle.setAttribute('r', '9');
      return circle;
    }()), options.lastChild.querySelector('svg').firstChild);

    continueButton.className = 'onboarding-skip-continue';
    continueButton.type = 'button';
    continueButton.setAttribute('data-skip-cancel', '');
    continueButton.appendChild(iconSvg('0 0 24 24', ['m9 18 6-6-6-6']));
    continueButton.appendChild(document.createTextNode('Continuar tour'));

    card.appendChild(head);
    card.appendChild(message);
    card.appendChild(options);
    card.appendChild(continueButton);
    return card;
  }

  function confirmSkipModern() {
    var modal = document.createElement('div');
    modal.className = 'onboarding-skip-dialog';
    modal.appendChild(buildSkipDialog());
    document.body.appendChild(modal);
    modal.querySelector('[data-skip-cancel]').addEventListener('click', function () { modal.remove(); });
    modal.querySelector('[data-skip-later]').addEventListener('click', function () { skip('remind_later', modal); });
    modal.querySelector('[data-skip-never]').addEventListener('click', function () { skip('dont_show_again', modal); });
    modal.querySelector('[data-skip-cancel]').focus();
  }

  function close() {
    state.active = false;
    document.querySelectorAll('.onboarding-highlight').forEach(function (item) {
      item.classList.remove('onboarding-highlight');
    });
    if (overlay) overlay.remove();
    if (spotlight) spotlight.remove();
    if (popover) popover.remove();
    setSidebarOpen(document.querySelector('.app-shell'), false);
    window.clearTimeout(scrollTimer);
    if (previousFocus && previousFocus.focus) previousFocus.focus();
  }

  function initReset() {
    document.querySelectorAll('[data-tour-reset]').forEach(function (button) {
      button.addEventListener('click', function () {
        button.disabled = true;
        request(config.resetUrl, payload()).then(function () {
          try {
            sessionStorage.removeItem(storageKey());
          } catch (error) {}
          var message = document.querySelector('[data-tour-reset-message]');
          if (message) { message.hidden = false; message.textContent = 'Tour resetado. Ele aparecerá no próximo acesso à dashboard.'; }
        }).catch(function () {
          var message = document.querySelector('[data-tour-reset-message]');
          if (message) { message.hidden = false; message.textContent = 'Não foi possível resetar o tour agora.'; }
        }).finally(function () { button.disabled = false; });
      });
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && state.active && !document.querySelector('.onboarding-skip-dialog')) confirmSkipModern();
    if (!state.active) return;
    if (event.key === 'ArrowRight') popover.querySelector('[data-tour-next]').click();
    if (event.key === 'ArrowLeft' && popover.querySelector('[data-tour-back]')) popover.querySelector('[data-tour-back]').click();
  });
  window.addEventListener('resize', position);
  window.addEventListener('scroll', position, true);

  document.addEventListener('DOMContentLoaded', function () {
    initReset();
    if (!config) return;
    var replay = new URLSearchParams(window.location.search).get('tour') === 'replay';
    document.querySelectorAll('[data-tour-replay]').forEach(function (link) {
      link.addEventListener('click', function (event) {
        if (!document.body.matches('[data-tour-page="' + config.tourKey + '"]')) return;
        collectSteps();
        if (!state.steps.length) return;
        event.preventDefault();
        start(true, 0);
      });
    });
    if (replay && document.body.matches('[data-tour-page="' + config.tourKey + '"]')) {
      start(true, 0);
    } else if (config.autoStart) {
      var url = config.stateUrl + '&tour_key=' + encodeURIComponent(config.tourKey) + '&tour_version=' + encodeURIComponent(config.tourVersion);
      request(url, null, 'GET').then(function (result) {
        var fallback = '';
        try {
          fallback = sessionStorage.getItem(storageKey()) || '';
        } catch (error) {}
        if (result.should_start && fallback !== 'dont_show_again') {
          start(false, Math.max(0, Number(result.last_seen_step || 1) - 1));
        }
      }).catch(function () {});
    }
  });
}());


