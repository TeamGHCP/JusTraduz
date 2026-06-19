(function () {
  var page = document.querySelector("[data-payment-confirmed]");
  if (!page) {
    return;
  }

  window.requestAnimationFrame(function () {
    page.classList.add("is-ready");
  });

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

      var original = button.innerHTML;
      navigator.clipboard.writeText(text).then(function () {
        button.textContent = "Resumo copiado";
        button.classList.add("is-copied");

        window.setTimeout(function () {
          button.innerHTML = original;
          button.classList.remove("is-copied");
        }, 1800);
      });
    });
  });
})();
