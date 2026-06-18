(function () {
  var placeholderBrand = "assets/img/payment-flags/card-placeholder.png";
  var brandPatterns = [
    { name: "Visa", slug: "visa", image: "assets/img/payment-flags/visa.svg", pattern: /^4/ },
    { name: "Mastercard", slug: "mastercard", image: "assets/img/payment-flags/mastercard.svg", pattern: /^(5[1-5]|2[2-7])/ },
    { name: "Elo", slug: "elo", image: "assets/img/payment-flags/elo.svg", pattern: /^(4011|4312|4389|4514|4576|5041|5067|5090|6277|6362|6363|650|6516|6550)/ },
    { name: "Amex", slug: "amex", image: "assets/img/payment-flags/amex.svg", pattern: /^3[47]/ },
    { name: "Hipercard", slug: "hipercard", image: "assets/img/payment-flags/hipercard.svg", pattern: /^(38|60)/ },
  ];

  document.querySelectorAll("[data-payment-method-toggle]").forEach(function (toggle) {
    toggle.addEventListener("click", function () {
      var current = toggle.closest("[data-payment-method]");
      if (!current) {
        return;
      }

      document.querySelectorAll("[data-payment-method]").forEach(function (method) {
        if (method !== current) {
          method.classList.remove("is-open");
        }
      });

      current.classList.toggle("is-open");
    });
  });

  document.querySelectorAll("[data-card-number]").forEach(function (input) {
    var card = input.closest("[data-payment-method]");
    var brandTargets = card ? card.querySelectorAll("[data-card-brand], [data-card-brand-inline]") : [];

    input.addEventListener("input", function () {
      var digits = input.value.replace(/\D/g, "").slice(0, 19);
      input.value = digits.replace(/(.{4})/g, "$1 ").trim();

      var detected = { name: "Bandeira", slug: "", image: "" };
      brandPatterns.some(function (item) {
        if (item.pattern.test(digits)) {
          detected = item;
          return true;
        }
        return false;
      });

      brandTargets.forEach(function (brand) {
        brand.classList.remove("is-detected", "is-visa", "is-mastercard", "is-elo", "is-amex", "is-hipercard");
        brand.setAttribute("aria-label", detected.name);

        if (!detected.slug) {
          brand.innerHTML = '<img src="' + placeholderBrand + '" alt="Bandeira do cartão">';
          return;
        }

        brand.innerHTML = '<img src="' + detected.image + '" alt="' + detected.name + '">';

        brand.classList.add("is-detected", "is-" + detected.slug);
      });
    });
  });

  document.querySelectorAll("#card_expiry_month, #card_expiry_year, #card_ccv, #holder_cpf_cnpj, #holder_phone, #holder_postal_code").forEach(function (input) {
    input.addEventListener("input", function () {
      input.value = input.value.replace(/\D/g, "");
    });
  });

  document.querySelectorAll("[data-copy-payment-link], [data-copy-value]").forEach(function (button) {
    button.addEventListener("click", function () {
      var link = button.dataset.copyValue || button.dataset.paymentLink || "";
      if (!link || !navigator.clipboard) {
        return;
      }

      navigator.clipboard.writeText(link).then(function () {
        var original = button.innerHTML;
        button.textContent = button.dataset.copyLabel || "Copiado";
        button.classList.add("is-copied");

        window.setTimeout(function () {
          button.innerHTML = original;
          button.classList.remove("is-copied");
        }, 1800);
      });
    });
  });
})();
