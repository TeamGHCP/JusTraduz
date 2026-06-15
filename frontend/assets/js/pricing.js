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
})();
