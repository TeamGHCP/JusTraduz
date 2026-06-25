(function () {
  "use strict";

  var cookieName = "justraduz_cookie_consent";
  var storageKey = "justraduz:cookie-consent";
  var version = "2026-06-25-v1";
  var maxAge = 180 * 24 * 60 * 60;
  var categories = ["preferences", "accessibility", "analytics"];
  var defaultChoices = {
    preferences: false,
    accessibility: false,
    analytics: false
  };
  var state = readState();
  var ui = {};

  function cloneChoices(choices) {
    return categories.reduce(function (result, category) {
      result[category] = !!(choices && choices[category]);
      return result;
    }, {});
  }

  function normalizeState(value) {
    if (!value || typeof value !== "object") return null;

    var choices = cloneChoices(value.choices || value.categories || {});
    return {
      version: String(value.version || version),
      choices: Object.assign({}, defaultChoices, choices),
      updatedAt: value.updatedAt || "",
      source: value.source || "site"
    };
  }

  function parseJson(value) {
    try {
      return JSON.parse(value);
    } catch (error) {
      return null;
    }
  }

  function readCookie() {
    var prefix = cookieName + "=";
    var pairs = document.cookie ? document.cookie.split(";") : [];

    for (var index = 0; index < pairs.length; index += 1) {
      var pair = pairs[index].trim();
      if (pair.indexOf(prefix) === 0) {
        return decodeURIComponent(pair.slice(prefix.length));
      }
    }

    return "";
  }

  function readState() {
    var fromCookie = normalizeState(parseJson(readCookie()));
    if (fromCookie) return fromCookie;

    try {
      return normalizeState(parseJson(window.localStorage.getItem(storageKey) || ""));
    } catch (error) {
      return null;
    }
  }

  function hasDecision() {
    return !!state && state.version === version;
  }

  function writeCookie(value) {
    var secure = window.location.protocol === "https:" ? "; Secure" : "";
    document.cookie = [
      cookieName,
      "=",
      encodeURIComponent(JSON.stringify(value)),
      "; Max-Age=",
      maxAge,
      "; Path=/; SameSite=Lax",
      secure
    ].join("");
  }

  function persist(choices, source) {
    state = {
      version: version,
      choices: Object.assign({}, defaultChoices, cloneChoices(choices)),
      updatedAt: new Date().toISOString(),
      source: source || "site"
    };

    writeCookie(state);

    try {
      window.localStorage.setItem(storageKey, JSON.stringify(state));
    } catch (error) {}

    cleanupDeniedData();
    window.dispatchEvent(new CustomEvent("justraduz:cookie-consent-changed", {
      detail: getPublicState()
    }));
    syncUi();
    return state;
  }

  function allowed(category) {
    if (category === "essential") return true;
    return !!(state && state.version === version && state.choices && state.choices[category]);
  }

  function expireCookie(name) {
    var host = window.location.hostname;
    var domains = ["", host, "." + host.replace(/^\./, "")];
    var paths = ["/", window.location.pathname || "/"];

    domains.forEach(function (domain) {
      paths.forEach(function (path) {
        var domainPart = domain ? "; Domain=" + domain : "";
        document.cookie = name + "=; Max-Age=0; Path=" + path + domainPart + "; SameSite=Lax";
      });
    });
  }

  function removeStorageKeys(keys) {
    try {
      keys.forEach(function (key) {
        window.localStorage.removeItem(key);
      });
    } catch (error) {}
  }

  function cleanupVlibras() {
    document.querySelectorAll("[vw], script[src*='vlibras.gov.br']").forEach(function (node) {
      node.remove();
    });
    document.querySelectorAll("iframe[src*='vlibras'], iframe[src*='dicionario2.vlibras']").forEach(function (node) {
      node.remove();
    });
    window.JusTraduzVlibrasStarted = false;
  }

  function cleanupDeniedData() {
    if (!allowed("preferences")) {
      removeStorageKeys([
        "justraduz-theme",
        "justraduz_sidebar_collapsed",
        "justraduz_sidebar_modules",
        "justraduz:pwa-ios-tip-dismissed-at",
        "justraduz:pwa-install-hidden"
      ]);
    }

    if (!allowed("accessibility")) {
      removeStorageKeys(["justraduz_accessibility_v1"]);
      cleanupVlibras();
    }

    if (!allowed("analytics")) {
      ["_ga", "_gid", "_gat", "_gcl_au", "_fbp", "_clck", "_clsk"].forEach(expireCookie);
    }
  }

  function getPublicState() {
    return {
      version: version,
      hasDecision: hasDecision(),
      choices: Object.assign({}, defaultChoices, state && state.choices ? state.choices : {}),
      updatedAt: state && state.updatedAt ? state.updatedAt : ""
    };
  }

  function loadScript(category, id, src, callback) {
    if (!allowed(category)) return null;

    var existing = document.getElementById(id);
    if (existing) {
      if (callback) callback(existing);
      return existing;
    }

    var script = document.createElement("script");
    script.id = id;
    script.src = src;
    script.async = true;
    if (callback) script.addEventListener("load", function () { callback(script); });
    document.body.appendChild(script);
    return script;
  }

  function openPreferences() {
    ensureUi();
    ui.modal.hidden = false;
    ui.modal.classList.add("is-open");
    document.body.classList.add("cookie-modal-open");
    syncUi();
    window.setTimeout(function () {
      var first = ui.modal.querySelector("button, input");
      if (first) first.focus();
    }, 30);
  }

  function closePreferences() {
    if (!ui.modal) return;
    ui.modal.classList.remove("is-open");
    ui.modal.hidden = true;
    document.body.classList.remove("cookie-modal-open");
    if (ui.manageButton && hasDecision()) ui.manageButton.focus();
  }

  function acceptAll() {
    persist({
      preferences: true,
      accessibility: true,
      analytics: true
    }, "accept_all");
    closePreferences();
  }

  function rejectOptional() {
    persist(defaultChoices, "reject_optional");
    closePreferences();
  }

  function saveCustom() {
    var choices = {};
    categories.forEach(function (category) {
      var input = ui.modal.querySelector("[data-cookie-toggle='" + category + "']");
      choices[category] = !!(input && input.checked);
    });
    persist(choices, "custom");
    closePreferences();
  }

  function makeIcon() {
    return [
      "<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\" focusable=\"false\">",
      "<path d=\"M12 3 5 6v5c0 4.7 3 8.5 7 10 4-1.5 7-5.3 7-10V6l-7-3Z\"></path>",
      "<path d=\"m9 12 2 2 4-5\"></path>",
      "</svg>"
    ].join("");
  }

  function buildBanner() {
    var banner = document.createElement("section");
    banner.className = "cookie-banner";
    banner.setAttribute("role", "dialog");
    banner.setAttribute("aria-modal", "false");
    banner.setAttribute("aria-labelledby", "cookie-banner-title");
    banner.innerHTML = [
      "<div class=\"cookie-banner-icon\">", makeIcon(), "</div>",
      "<div class=\"cookie-banner-copy\">",
      "<span class=\"cookie-kicker\">Privacidade JusTraduz</span>",
      "<h2 id=\"cookie-banner-title\">Controle seus cookies</h2>",
      "<p>Usamos cookies essenciais para seguranca e login. Voce escolhe se quer salvar preferencias, carregar recursos externos de acessibilidade e liberar medicao de uso.</p>",
      "</div>",
      "<div class=\"cookie-banner-actions\">",
      "<button type=\"button\" class=\"cookie-btn cookie-btn-ghost\" data-cookie-reject>Recusar opcionais</button>",
      "<button type=\"button\" class=\"cookie-btn cookie-btn-outline\" data-cookie-custom>Personalizar</button>",
      "<button type=\"button\" class=\"cookie-btn cookie-btn-primary\" data-cookie-accept>Aceitar todos</button>",
      "</div>"
    ].join("");
    document.body.appendChild(banner);
    return banner;
  }

  function buildToggle(category, title, description, locked) {
    var disabled = locked ? " disabled checked" : "";
    var checked = !locked && allowed(category) ? " checked" : "";
    var status = locked ? "Sempre ativo" : "Opcional";

    return [
      "<label class=\"cookie-option\">",
      "<span class=\"cookie-option-copy\">",
      "<strong>", title, "</strong>",
      "<small>", description, "</small>",
      "</span>",
      "<span class=\"cookie-switch-wrap\">",
      "<span class=\"cookie-option-status\">", status, "</span>",
      "<input type=\"checkbox\" data-cookie-toggle=\"", category, "\"", disabled, checked, ">",
      "<span class=\"cookie-switch\" aria-hidden=\"true\"></span>",
      "</span>",
      "</label>"
    ].join("");
  }

  function buildModal() {
    var modal = document.createElement("div");
    modal.className = "cookie-modal";
    modal.hidden = true;
    modal.innerHTML = [
      "<div class=\"cookie-modal-backdrop\" data-cookie-close></div>",
      "<section class=\"cookie-card\" role=\"dialog\" aria-modal=\"true\" aria-labelledby=\"cookie-modal-title\">",
      "<header class=\"cookie-card-head\">",
      "<div class=\"cookie-card-title\">",
      "<span class=\"cookie-card-icon\">", makeIcon(), "</span>",
      "<div><span class=\"cookie-kicker\">Central de preferencias</span><h2 id=\"cookie-modal-title\">Gerenciar cookies</h2></div>",
      "</div>",
      "<button type=\"button\" class=\"cookie-icon-btn\" data-cookie-close aria-label=\"Fechar preferencias\">",
      "<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M18 6 6 18\"></path><path d=\"m6 6 12 12\"></path></svg>",
      "</button>",
      "</header>",
      "<div class=\"cookie-card-body\">",
      buildToggle("essential", "Essenciais", "Mantem sessao, seguranca, CSRF, login e funcionamento basico do site.", true),
      buildToggle("preferences", "Preferencias", "Salva tema, menu lateral, instalacao do app e ajustes locais de interface.", false),
      buildToggle("accessibility", "Acessibilidade externa", "Permite carregar VLibras e manter preferencias locais de acessibilidade.", false),
      buildToggle("analytics", "Medicao de uso", "Reserva consentimento para ferramentas de metricas. Hoje o site nao carrega analytics sem sua autorizacao.", false),
      "</div>",
      "<footer class=\"cookie-card-actions\">",
      "<button type=\"button\" class=\"cookie-btn cookie-btn-ghost\" data-cookie-reject>Recusar opcionais</button>",
      "<button type=\"button\" class=\"cookie-btn cookie-btn-outline\" data-cookie-save>Salvar escolhas</button>",
      "<button type=\"button\" class=\"cookie-btn cookie-btn-primary\" data-cookie-accept>Aceitar todos</button>",
      "</footer>",
      "</section>"
    ].join("");
    document.body.appendChild(modal);
    return modal;
  }

  function buildManageButton() {
    var sidebarFooter = document.querySelector(".app-shell .sidebar-footer");
    var button = document.createElement("button");
    button.type = "button";
    button.className = "cookie-manage-button";
    if (sidebarFooter) {
      button.classList.add("is-app-context", "is-sidebar-item");
    }
    button.setAttribute("aria-label", "Gerenciar cookies");
    button.title = "Gerenciar cookies";
    button.innerHTML = makeIcon() + "<span>Cookies</span>";
    button.addEventListener("click", openPreferences);

    if (sidebarFooter) {
      sidebarFooter.insertBefore(button, sidebarFooter.firstChild);
    } else {
      document.body.appendChild(button);
    }

    return button;
  }

  function bindControls(root) {
    root.querySelectorAll("[data-cookie-accept]").forEach(function (button) {
      button.addEventListener("click", acceptAll);
    });
    root.querySelectorAll("[data-cookie-reject]").forEach(function (button) {
      button.addEventListener("click", rejectOptional);
    });
    root.querySelectorAll("[data-cookie-custom]").forEach(function (button) {
      button.addEventListener("click", openPreferences);
    });
    root.querySelectorAll("[data-cookie-save]").forEach(function (button) {
      button.addEventListener("click", saveCustom);
    });
    root.querySelectorAll("[data-cookie-close]").forEach(function (button) {
      button.addEventListener("click", closePreferences);
    });
  }

  function syncUi() {
    if (ui.banner) ui.banner.hidden = hasDecision();
    if (ui.manageButton) ui.manageButton.hidden = !hasDecision();
    if (ui.modal) {
      categories.forEach(function (category) {
        var input = ui.modal.querySelector("[data-cookie-toggle='" + category + "']");
        if (input) input.checked = allowed(category);
      });
    }
  }

  function ensureUi() {
    if (!ui.banner) {
      ui.banner = buildBanner();
      bindControls(ui.banner);
    }

    if (!ui.modal) {
      ui.modal = buildModal();
      bindControls(ui.modal);
      ui.modal.addEventListener("keydown", function (event) {
        if (event.key === "Escape") closePreferences();
      });
    }

    if (!ui.manageButton) {
      ui.manageButton = buildManageButton();
    }

    syncUi();
  }

  function initUi() {
    ensureUi();
    cleanupDeniedData();
  }

  window.JusTraduzCookieConsent = {
    allowed: allowed,
    hasDecision: hasDecision,
    state: getPublicState,
    open: openPreferences,
    acceptAll: acceptAll,
    rejectOptional: rejectOptional,
    save: function (choices) { return persist(choices, "api"); },
    loadScript: loadScript
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initUi);
  } else {
    initUi();
  }
}());
