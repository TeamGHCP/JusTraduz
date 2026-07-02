(function () {
  var page = document.querySelector("[data-payment-confirmed]");
  if (!page) {
    return;
  }

  window.requestAnimationFrame(function () {
    page.classList.add("is-ready");
  });

  initPaymentConfetti();

  document.querySelectorAll(".payment-confirmed-card").forEach(function (card) {
    card.addEventListener("pointermove", function (event) {
      var rect = card.getBoundingClientRect();
      var x = ((event.clientX - rect.left) / rect.width) * 100;
      var y = ((event.clientY - rect.top) / rect.height) * 100;

      card.classList.add("is-active");
      card.style.setProperty("--confirmed-glow-x", x.toFixed(2) + "%");
      card.style.setProperty("--confirmed-glow-y", y.toFixed(2) + "%");
    });

    card.addEventListener("pointerleave", function () {
      card.classList.remove("is-active");
      card.style.removeProperty("--confirmed-glow-x");
      card.style.removeProperty("--confirmed-glow-y");
    });
  });

  document.querySelectorAll("[data-copy-receipt]").forEach(function (button) {
    button.addEventListener("click", function () {
      var text = button.dataset.copyReceipt || "";
      if (!text || !navigator.clipboard) {
        return;
      }

      var original = Array.prototype.map.call(button.childNodes, function (node) {
        return node.cloneNode(true);
      });
      navigator.clipboard.writeText(text).then(function () {
        button.textContent = "Resumo copiado";
        button.classList.add("is-copied");

        window.setTimeout(function () {
          button.replaceChildren.apply(button, original.map(function (node) {
            return node.cloneNode(true);
          }));
          button.classList.remove("is-copied");
        }, 1800);
      });
    });
  });

  function initPaymentConfetti() {
    var layer = document.querySelector("[data-payment-confetti]");
    if (!layer || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      return;
    }

    var colors = [
      "rgba(255, 255, 255, .96)",
      "rgba(232, 246, 244, .94)",
      "rgba(94, 234, 212, .92)",
      "rgba(45, 212, 191, .9)",
      "rgba(17, 138, 126, .88)",
      "rgba(23, 32, 51, .36)"
    ];
    var shapes = ["rectangle", "circle", "star", "diamond"];
    var count = window.innerWidth < 700 ? 70 : 124;
    var longestDuration = 0;

    for (var index = 0; index < count; index += 1) {
      var piece = document.createElement("span");
      var shape = shapes[Math.floor(Math.random() * shapes.length)];
      var width = Math.random() * 12 + 7;
      var height = shape === "rectangle" ? width * (Math.random() * .45 + .55) : width;
      var duration = Math.random() * 1.7 + 3.1;
      var delay = Math.random() * .5;
      var scale = Math.random() * .72 + .58;

      longestDuration = Math.max(longestDuration, duration + delay);
      piece.className = "payment-confetti-piece is-" + shape;
      piece.style.setProperty("--confetti-x", (-12 + Math.random() * 124).toFixed(2) + "vw");
      piece.style.setProperty("--confetti-width", width.toFixed(2) + "px");
      piece.style.setProperty("--confetti-height", height.toFixed(2) + "px");
      piece.style.setProperty("--confetti-color", colors[Math.floor(Math.random() * colors.length)]);
      piece.style.setProperty("--confetti-rotate", Math.floor(Math.random() * 360) + "deg");
      piece.style.setProperty("--confetti-spin", (Math.random() > .5 ? 1 : -1) * (Math.random() * 520 + 240).toFixed(0) + "deg");
      piece.style.setProperty("--confetti-scale", scale.toFixed(2));
      piece.style.setProperty("--confetti-opacity", (Math.random() * .35 + .62).toFixed(2));
      piece.style.setProperty("--confetti-duration", duration.toFixed(2) + "s");
      piece.style.setProperty("--confetti-sway-duration", (duration * .58).toFixed(2) + "s");
      piece.style.setProperty("--confetti-delay", delay.toFixed(2) + "s");
      piece.style.setProperty("--confetti-drift", ((Math.random() - .5) * 52).toFixed(2) + "vw");
      var sway = Math.random() * 22 + 8;
      piece.style.setProperty("--confetti-sway", sway.toFixed(2) + "px");
      piece.style.setProperty("--confetti-sway-negative", (-sway).toFixed(2) + "px");
      layer.appendChild(piece);
    }

    window.setTimeout(function () {
      layer.remove();
    }, Math.ceil((longestDuration + .3) * 1000));
  }
})();
