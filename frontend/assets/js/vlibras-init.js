(function () {
  "use strict";

  var attempts = 0;

  function canLoad() {
    return !!(window.JusTraduzCookieConsent && window.JusTraduzCookieConsent.allowed("accessibility"));
  }

  function ensureWidgetContainer() {
    if (document.querySelector("[vw]")) return;

    var widget = document.createElement("div");
    widget.setAttribute("vw", "");
    widget.className = "enabled";
    widget.innerHTML = "<div vw-access-button class=\"active\"></div><div vw-plugin-wrapper><div class=\"vw-plugin-top-wrapper\"></div></div>";
    document.body.appendChild(widget);
  }

  function start() {
    if (!canLoad()) return;
    if (window.JusTraduzVlibrasStarted) return;
    if (!window.VLibras || !window.VLibras.Widget) {
      attempts += 1;
      if (attempts === 1 && window.JusTraduzCookieConsent && window.JusTraduzCookieConsent.loadScript) {
        window.JusTraduzCookieConsent.loadScript("accessibility", "justraduz-vlibras-plugin", "https://vlibras.gov.br/app/vlibras-plugin.js", start);
      }
      if (attempts < 20) window.setTimeout(start, 250);
      return;
    }

    ensureWidgetContainer();
    window.JusTraduzVlibrasStarted = true;
    new window.VLibras.Widget("https://vlibras.gov.br/app");
  }

  start();
  window.addEventListener("load", start);
  window.addEventListener("justraduz:cookie-consent-changed", start);
}());
