(function () {
  document.querySelectorAll("[data-copy-payment-link]").forEach(function (button) {
    button.addEventListener("click", function () {
      var link = button.dataset.paymentLink || "";
      if (!link || !navigator.clipboard) {
        return;
      }

      navigator.clipboard.writeText(link).then(function () {
        var original = button.innerHTML;
        button.textContent = "Link copiado";
        button.classList.add("is-copied");

        window.setTimeout(function () {
          button.innerHTML = original;
          button.classList.remove("is-copied");
        }, 1800);
      });
    });
  });
})();
