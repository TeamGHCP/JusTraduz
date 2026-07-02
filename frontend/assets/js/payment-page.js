(function () {
  var placeholderBrand = "assets/img/payment-flags/card-placeholder.png";
  var brandPatterns = [
    { name: "Visa", slug: "visa", image: "assets/img/payment-flags/visa.svg", pattern: /^4/ },
    { name: "Mastercard", slug: "mastercard", image: "assets/img/payment-flags/mastercard.svg", pattern: /^(5[1-5]|2[2-7])/ },
    { name: "Elo", slug: "elo", image: "assets/img/payment-flags/elo.svg", pattern: /^(4011|4312|4389|4514|4576|5041|5067|5090|6277|6362|6363|650|6516|6550)/ },
    { name: "Amex", slug: "amex", image: "assets/img/payment-flags/amex.svg", pattern: /^3[47]/ },
    { name: "Hipercard", slug: "hipercard", image: "assets/img/payment-flags/hipercard.svg", pattern: /^(38|60)/ },
  ];

  function renderBrandImage(target, image, alt) {
    var img = document.createElement("img");
    img.src = image;
    img.alt = alt;
    target.replaceChildren(img);
  }

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
          renderBrandImage(brand, placeholderBrand, "Bandeira do cartao");
          return;
        }

        renderBrandImage(brand, detected.image, detected.name);

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
        var original = button.textContent;
        button.textContent = button.dataset.copyLabel || "Copiado";
        button.classList.add("is-copied");

        window.setTimeout(function () {
          button.textContent = original;
          button.classList.remove("is-copied");
        }, 1800);
      });
    });
  });

  var invitePanel = document.querySelector("[data-office-invite-panel]");
  if (invitePanel) {
    var inviteMin = parseInt(invitePanel.dataset.officeInviteMin || "0", 10);
    var inviteLimit = parseInt(invitePanel.dataset.officeInviteLimit || "5", 10);
    var inviteCountTarget = invitePanel.querySelector("[data-office-invite-count]");
    var inviteCountInput = invitePanel.querySelector("[data-office-invite-count-input]");
    var decreaseInvite = invitePanel.querySelector("[data-office-invite-decrease]");
    var increaseInvite = invitePanel.querySelector("[data-office-invite-increase]");
    var inviteFields = Array.prototype.slice.call(invitePanel.querySelectorAll("[data-office-invite-field]"));
    var inviteInputs = Array.prototype.slice.call(invitePanel.querySelectorAll("[data-office-invite-input]"));
    var inviteCount = parseInt((inviteCountInput && inviteCountInput.value) || "0", 10);

    inviteCount = Math.max(inviteMin, Math.min(inviteLimit, isNaN(inviteCount) ? inviteMin : inviteCount));

    var activeInviteInputs = function () {
      return inviteInputs.slice(0, inviteCount);
    };

    var renderInviteFields = function () {
      inviteFields.forEach(function (field, index) {
        var input = field.querySelector("[data-office-invite-input]");
        var active = index < inviteCount;
        field.hidden = !active;
        if (input) {
          input.disabled = !active;
          input.required = active;
        }
      });

      if (inviteCountTarget) {
        inviteCountTarget.textContent = String(inviteCount);
      }
      if (inviteCountInput) {
        inviteCountInput.value = String(inviteCount);
      }
      if (decreaseInvite) {
        decreaseInvite.disabled = inviteCount <= inviteMin;
      }
      if (increaseInvite) {
        increaseInvite.disabled = inviteCount >= inviteLimit;
      }
    };

    var syncInviteFields = function () {
      var emails = [];
      activeInviteInputs().forEach(function (input) {
        var email = input.value.trim().toLowerCase();
        if (email && emails.indexOf(email) === -1) {
          emails.push(email);
        }
      });

      document.querySelectorAll("[data-office-invite-hidden]").forEach(function (target) {
        target.replaceChildren();
        emails.forEach(function (email) {
          var hidden = document.createElement("input");
          hidden.type = "hidden";
          hidden.name = "team_invites[]";
          hidden.value = email;
          target.appendChild(hidden);
        });
      });
    };

    var setInviteCount = function (nextCount) {
      inviteCount = Math.max(inviteMin, Math.min(inviteLimit, nextCount));
      renderInviteFields();
      syncInviteFields();
      if (inviteCount > 0 && activeInviteInputs()[inviteCount - 1]) {
        activeInviteInputs()[inviteCount - 1].focus();
      }
    };

    var validateInviteFields = function () {
      var seen = [];
      return activeInviteInputs().every(function (input) {
        input.value = input.value.trim().toLowerCase();
        if (!input.value || !input.checkValidity() || seen.indexOf(input.value) !== -1) {
          input.setCustomValidity(seen.indexOf(input.value) !== -1 ? "Este e-mail ja foi informado." : "");
          input.reportValidity();
          return false;
        }
        input.setCustomValidity("");
        seen.push(input.value);
        return true;
      });
    };

    if (decreaseInvite) {
      decreaseInvite.addEventListener("click", function () {
        setInviteCount(inviteCount - 1);
      });
    }
    if (increaseInvite) {
      increaseInvite.addEventListener("click", function () {
        setInviteCount(inviteCount + 1);
      });
    }
    inviteInputs.forEach(function (input) {
      input.addEventListener("input", function () {
        input.setCustomValidity("");
        syncInviteFields();
      });
      input.addEventListener("blur", function () {
        input.value = input.value.trim().toLowerCase();
        syncInviteFields();
      });
    });
    document.querySelectorAll("form").forEach(function (form) {
      form.addEventListener("submit", function (event) {
        if (!validateInviteFields()) {
          event.preventDefault();
          return;
        }
        syncInviteFields();
      });
    });
    renderInviteFields();
    syncInviteFields();
  }
})();
