(function () {
  function money(cents) {
    return "R$ " + Math.round(cents / 100).toLocaleString("pt-BR");
  }

  function yearlySaving(note) {
    var monthly = Number(note.dataset.monthlyCents || 0);
    var yearly = Number(note.dataset.yearlyCents || 0);
    var fullYear = monthly * 12;
    return Math.max(0, fullYear - yearly);
  }

  function setCycle(toggle, cycle) {
    var yearly = cycle === "yearly";
    toggle.classList.toggle("is-yearly", yearly);
    toggle.setAttribute("aria-pressed", yearly ? "true" : "false");

    document.querySelectorAll("[data-billing-cycle-input]").forEach(function (input) {
      input.value = yearly ? "yearly" : "monthly";
    });

    document.querySelectorAll(".pricing-card").forEach(function (card) {
      var price = card.querySelector(".pricing-price");
      var period = card.querySelector("[data-pricing-period]");
      var note = card.querySelector("[data-pricing-note]");

      if (price) {
        price.textContent = yearly ? price.dataset.yearlyPrice : price.dataset.monthlyPrice;
      }

      if (period) {
        period.textContent = yearly ? "/ano" : "/mês";
      }

      if (note && price) {
        var saving = yearlySaving(note);
        note.classList.remove("is-applying-discount");
        void note.offsetWidth;
        note.classList.toggle("is-discount-applied", yearly);
        note.classList.toggle("is-applying-discount", yearly);
        note.textContent = yearly
          ? (saving > 0 ? "Desconto aplicado: economize " + money(saving) + " por ano" : "Cobrança anual aplicada")
          : (price.dataset.yearlyPrice || "") + "/ano · desconto anual";
      }
    });
  }

  document.querySelectorAll("[data-pricing-cycle-toggle]").forEach(function (toggle) {
    toggle.addEventListener("click", function () {
      var alreadyYearly = toggle.classList.contains("is-yearly");
      var nextCycle = alreadyYearly ? "monthly" : "yearly";
      setCycle(toggle, nextCycle);
    });
  });

  document.querySelectorAll("[data-pricing-card]").forEach(function (card) {
    var button = card.querySelector("button[type='submit']");

    function resetPointer() {
      card.classList.remove("is-card-active", "is-card-pressed");
      card.style.removeProperty("--pricing-card-rotate-x");
      card.style.removeProperty("--pricing-card-rotate-y");
      card.style.removeProperty("--pricing-card-glow-x");
      card.style.removeProperty("--pricing-card-glow-y");
    }

    function activateCard() {
      if (button && !button.disabled) {
        button.click();
      }
    }

    card.addEventListener("pointermove", function (event) {
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        return;
      }

      var rect = card.getBoundingClientRect();
      var x = event.clientX - rect.left;
      var y = event.clientY - rect.top;
      var rotateY = ((x / rect.width) - 0.5) * 5;
      var rotateX = ((0.5 - (y / rect.height)) * 5);

      card.classList.add("is-card-active");
      card.style.setProperty("--pricing-card-rotate-x", rotateX.toFixed(2) + "deg");
      card.style.setProperty("--pricing-card-rotate-y", rotateY.toFixed(2) + "deg");
      card.style.setProperty("--pricing-card-glow-x", ((x / rect.width) * 100).toFixed(2) + "%");
      card.style.setProperty("--pricing-card-glow-y", ((y / rect.height) * 100).toFixed(2) + "%");
    });

    card.addEventListener("pointerleave", resetPointer);

    card.addEventListener("pointerdown", function () {
      card.classList.add("is-card-pressed");
    });

    card.addEventListener("pointerup", function () {
      card.classList.remove("is-card-pressed");
    });

    card.addEventListener("click", function (event) {
      if (event.target.closest("button, a, input, select, textarea, label")) {
        return;
      }

      activateCard();
    });

    card.addEventListener("keydown", function (event) {
      if (event.key !== "Enter" && event.key !== " ") {
        return;
      }

      if (event.target.closest("button, a, input, select, textarea")) {
        return;
      }

      event.preventDefault();
      card.classList.add("is-card-pressed");
      window.setTimeout(function () {
        card.classList.remove("is-card-pressed");
      }, 140);
      activateCard();
    });
  });
})();
