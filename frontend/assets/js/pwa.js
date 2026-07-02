(function registerJusTraduzPwa() {
  var deferredInstallPrompt = null;
  var installTipKey = "justraduz:pwa-ios-tip-dismissed-at";
  var installHiddenKey = "justraduz:pwa-install-hidden";
  var updateRegistration = null;

  function canUsePreferences() {
    return !!(window.JusTraduzCookieConsent && window.JusTraduzCookieConsent.allowed("preferences"));
  }

  function hasCookieDecision() {
    return !!(window.JusTraduzCookieConsent && window.JusTraduzCookieConsent.hasDecision());
  }

  function isStandalone() {
    return window.matchMedia("(display-mode: standalone)").matches
      || window.navigator.standalone === true;
  }

  function isIosSafari() {
    var ua = window.navigator.userAgent || "";
    var isIos = /iPad|iPhone|iPod/.test(ua) || (ua.includes("Macintosh") && "ontouchend" in document);
    var isWebKit = /WebKit/.test(ua);
    var isCriOs = /CriOS|FxiOS|EdgiOS/.test(ua);
    return isIos && isWebKit && !isCriOs;
  }

  function addStyles() {
    if (document.getElementById("justraduz-pwa-style")) return;

    var style = document.createElement("style");
    style.id = "justraduz-pwa-style";
    style.textContent = [
      ".pwa-toast{position:fixed;left:16px;right:16px;bottom:16px;z-index:99999;display:flex;gap:12px;align-items:center;justify-content:space-between;max-width:680px;margin:0 auto;padding:14px 14px 14px 16px;border-radius:10px;background:#102033;color:#fff;box-shadow:0 18px 45px rgba(16,32,51,.22);font:500 14px/1.45 Inter,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}",
      ".pwa-toast strong{display:block;margin:0 0 2px;font-size:14px}",
      ".pwa-toast span{display:block;color:rgba(255,255,255,.76)}",
      ".pwa-toast-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;flex:0 0 auto}",
      ".pwa-toast button{min-height:38px;border:0;border-radius:8px;padding:0 12px;font:700 13px Inter,system-ui,sans-serif;cursor:pointer}",
      ".pwa-toast .pwa-primary{background:#00a896;color:#fff}",
      ".pwa-toast .pwa-ghost{background:rgba(255,255,255,.1);color:#fff}",
      ".pwa-toast.is-offline{background:#7a3b13}",
      ".pwa-toast.is-online{background:#006b5f}",
      "@media(max-width:520px){.pwa-toast{align-items:flex-start;flex-direction:column}.pwa-toast-actions{width:100%}.pwa-toast button{flex:1}}"
    ].join("");
    document.head.appendChild(style);
  }

  function removeToast(id) {
    var existing = document.getElementById(id);
    if (existing) existing.remove();
  }

  function isInstallPromptHidden() {
    if (!canUsePreferences()) return false;

    try {
      return window.localStorage.getItem(installHiddenKey) === "1";
    } catch (error) {
      return false;
    }
  }

  function hideInstallPromptPermanently() {
    if (!canUsePreferences()) return;

    try {
      window.localStorage.setItem(installHiddenKey, "1");
    } catch (error) {}
  }

  function showToast(options) {
    addStyles();
    removeToast(options.id);

    var toast = document.createElement("section");
    toast.id = options.id;
    toast.className = "pwa-toast" + (options.kind ? " " + options.kind : "");
    toast.setAttribute("role", "status");

    var copy = document.createElement("div");
    var title = document.createElement("strong");
    var message = document.createElement("span");
    var actions = document.createElement("div");

    title.textContent = String(options.title || "");
    message.textContent = String(options.message || "");
    actions.className = "pwa-toast-actions";

    copy.appendChild(title);
    copy.appendChild(message);
    toast.appendChild(copy);
    toast.appendChild(actions);

    (options.actions || []).forEach(function (action) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = action.primary ? "pwa-primary" : "pwa-ghost";
      button.textContent = action.label;
      button.addEventListener("click", function () {
        action.onClick();
        if (action.close !== false) removeToast(options.id);
      });
      actions.appendChild(button);
    });

    document.body.appendChild(toast);

    if (options.timeout) {
      window.setTimeout(function () {
        removeToast(options.id);
      }, options.timeout);
    }
  }

  function showInstallPrompt() {
    if (!hasCookieDecision() || !deferredInstallPrompt || isStandalone() || isInstallPromptHidden()) return;

    showToast({
      id: "justraduz-pwa-install",
      title: "Instalar JusTraduz",
      message: "Abra como aplicativo e acesse mais rápido pela tela inicial.",
      actions: [
        {
          label: "Não mostrar novamente",
          onClick: function () {
            hideInstallPromptPermanently();
          }
        },
        {
          label: "Agora não",
          onClick: function () {}
        },
        {
          label: "Instalar",
          primary: true,
          close: false,
          onClick: function () {
            window.JusTraduzPwa.install().then(function () {
              removeToast("justraduz-pwa-install");
            });
          }
        }
      ]
    });
  }

  function maybeShowIosTip() {
    if (!hasCookieDecision() || !isIosSafari() || isStandalone()) return;

    var dismissedAt = 0;
    if (canUsePreferences()) {
      try {
        dismissedAt = Number(window.localStorage.getItem(installTipKey) || 0);
      } catch (error) {
        dismissedAt = 0;
      }
    }
    var sevenDays = 7 * 24 * 60 * 60 * 1000;
    if (dismissedAt && Date.now() - dismissedAt < sevenDays) return;

    showToast({
      id: "justraduz-pwa-ios-tip",
      title: "Adicionar ao iPhone",
      message: "No Safari, toque em Compartilhar e depois em Adicionar à Tela de Início.",
      actions: [
        {
          label: "Entendi",
          primary: true,
          onClick: function () {
            if (canUsePreferences()) {
              try {
                window.localStorage.setItem(installTipKey, String(Date.now()));
              } catch (error) {}
            }
          }
        }
      ],
      timeout: 14000
    });
  }

  function showUpdatePrompt(registration) {
    updateRegistration = registration;
    showToast({
      id: "justraduz-pwa-update",
      title: "Atualização disponível",
      message: "Uma versão nova do JusTraduz está pronta para usar.",
      actions: [
        {
          label: "Depois",
          onClick: function () {}
        },
        {
          label: "Atualizar",
          primary: true,
          onClick: function () {
            if (updateRegistration && updateRegistration.waiting) {
              updateRegistration.waiting.postMessage({ type: "SKIP_WAITING" });
            }
          }
        }
      ]
    });
  }

  function showConnectionState(online) {
    showToast({
      id: "justraduz-pwa-connection",
      title: online ? "Conexão restaurada" : "Sem conexão",
      message: online ? "Você já pode continuar usando o JusTraduz." : "Algumas áreas podem depender da internet.",
      kind: online ? "is-online" : "is-offline",
      actions: [
        {
          label: "OK",
          primary: true,
          onClick: function () {}
        }
      ],
      timeout: online ? 5000 : 0
    });
  }

  window.addEventListener("beforeinstallprompt", function (event) {
    event.preventDefault();
    deferredInstallPrompt = event;
    window.dispatchEvent(new CustomEvent("justraduz:pwa-install-available"));
    window.setTimeout(showInstallPrompt, 900);
  });

  window.addEventListener("appinstalled", function () {
    deferredInstallPrompt = null;
    removeToast("justraduz-pwa-install");
    removeToast("justraduz-pwa-ios-tip");
  });

  window.addEventListener("justraduz:cookie-consent-changed", function () {
    window.setTimeout(function () {
      showInstallPrompt();
      maybeShowIosTip();
    }, 500);
  });

  window.addEventListener("offline", function () {
    showConnectionState(false);
  });

  window.addEventListener("online", function () {
    showConnectionState(true);
  });

  window.JusTraduzPwa = {
    canInstall: function () {
      return !!deferredInstallPrompt;
    },
    install: function () {
      if (!deferredInstallPrompt) {
        return Promise.resolve(false);
      }

      deferredInstallPrompt.prompt();
      return deferredInstallPrompt.userChoice.then(function (choice) {
        deferredInstallPrompt = null;
        return choice.outcome === "accepted";
      });
    }
  };

  if (!("serviceWorker" in navigator)) {
    document.addEventListener("DOMContentLoaded", maybeShowIosTip);
    return;
  }

  var script = document.currentScript;
  var scriptUrl = script && script.src ? script.src : new URL("assets/js/pwa.js", window.location.href).href;
  var workerUrl = new URL("../../service-worker.js", scriptUrl);
  var scopeUrl = new URL("../../", scriptUrl);

  window.addEventListener("load", function () {
    navigator.serviceWorker.register(workerUrl.href, {
      scope: scopeUrl.href,
      updateViaCache: "none"
    }).then(function (registration) {
      if (registration.waiting && navigator.serviceWorker.controller) {
        showUpdatePrompt(registration);
      }

      registration.addEventListener("updatefound", function () {
        var worker = registration.installing;
        if (!worker) return;

        worker.addEventListener("statechange", function () {
          if (worker.state === "installed" && navigator.serviceWorker.controller) {
            showUpdatePrompt(registration);
          }
        });
      });
    }).catch(function (error) {
      if (window.console && window.console.warn) {
        window.console.warn("Não foi possível registrar o PWA do JusTraduz.", error);
      }
    });

    var reloadedForUpdate = false;
    navigator.serviceWorker.addEventListener("controllerchange", function () {
      if (reloadedForUpdate) return;
      reloadedForUpdate = true;
      window.location.reload();
    });

    maybeShowIosTip();
  });
}());
