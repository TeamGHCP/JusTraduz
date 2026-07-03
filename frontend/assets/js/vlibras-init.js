(function () {
  "use strict";

  var attempts = 0;

  function canLoad() {
    if (!window.JusTraduzCookieConsent) return true;
    if (window.JusTraduzCookieConsent.hasDecision && !window.JusTraduzCookieConsent.hasDecision()) return true;
    return !!window.JusTraduzCookieConsent.allowed("accessibility");
  }

  function loadVlibrasPlugin(callback) {
    var existing = document.getElementById("justraduz-vlibras-plugin");
    if (existing) {
      if (callback) existing.addEventListener("load", callback, { once: true });
      return existing;
    }

    var script = document.createElement("script");
    script.id = "justraduz-vlibras-plugin";
    script.src = "https://vlibras.gov.br/app/vlibras-plugin.js";
    script.async = true;
    if (callback) script.addEventListener("load", callback, { once: true });
    document.body.appendChild(script);
    return script;
  }

  function ensureWidgetContainer() {
    if (document.querySelector("[vw]")) return;

    var widget = document.createElement("div");
    widget.setAttribute("vw", "");
    widget.className = "enabled";

    var accessButton = document.createElement("div");
    accessButton.setAttribute("vw-access-button", "");
    accessButton.className = "active";

    var pluginWrapper = document.createElement("div");
    pluginWrapper.setAttribute("vw-plugin-wrapper", "");

    var topWrapper = document.createElement("div");
    topWrapper.className = "vw-plugin-top-wrapper";

    pluginWrapper.appendChild(topWrapper);
    widget.appendChild(accessButton);
    widget.appendChild(pluginWrapper);
    document.body.appendChild(widget);
  }

  function start() {
    if (!canLoad()) return;
    if (window.JusTraduzVlibrasStarted) return;
    if (!window.VLibras || !window.VLibras.Widget) {
      attempts += 1;
      if (attempts === 1 && window.JusTraduzCookieConsent && window.JusTraduzCookieConsent.hasDecision && window.JusTraduzCookieConsent.hasDecision() && window.JusTraduzCookieConsent.loadScript) {
        window.JusTraduzCookieConsent.loadScript("accessibility", "justraduz-vlibras-plugin", "https://vlibras.gov.br/app/vlibras-plugin.js", start);
      } else if (attempts === 1) {
        loadVlibrasPlugin(start);
      }
      if (attempts < 20) window.setTimeout(start, 250);
      return;
    }

    ensureWidgetContainer();
    window.JusTraduzVlibrasStarted = true;
    new window.VLibras.Widget("https://vlibras.gov.br/app");
  }

  window.JusTraduzStartVlibras = start;
  window.addEventListener("justraduz:vlibras-request", start);
  window.addEventListener("justraduz:cookie-consent-changed", function () {
    if (window.JusTraduzVlibrasStarted && !canLoad()) {
      document.querySelectorAll('[vw], script[src*="vlibras.gov.br"]').forEach(function (node) {
        node.remove();
      });
      window.JusTraduzVlibrasStarted = false;
    }
  });
}());
