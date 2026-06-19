(function () {
  "use strict";

  var attempts = 0;

  function start() {
    if (window.JusTraduzVlibrasStarted) return;
    if (!window.VLibras || !window.VLibras.Widget) {
      attempts += 1;
      if (attempts < 20) window.setTimeout(start, 250);
      return;
    }

    window.JusTraduzVlibrasStarted = true;
    new window.VLibras.Widget("https://vlibras.gov.br/app");
  }

  start();
  window.addEventListener("load", start);
}());
