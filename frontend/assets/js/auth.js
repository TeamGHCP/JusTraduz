document.addEventListener("DOMContentLoaded", () => {
  const frontendMarker = "/frontend/";
  const frontendIndex = window.location.pathname.indexOf(frontendMarker);
  const appBasePath = frontendIndex >= 0 ? window.location.pathname.slice(0, frontendIndex) : "";
  const backendBase = `${appBasePath}/backend/public/index.php`;
  const backendRoute = (path) => `${backendBase}?rota=${encodeURIComponent(path)}`;
  let csrfToken = "";

  function clearCookie(name) {
    try {
      document.cookie = `${name}=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;`;
      document.cookie = `${name}=; Path=/; Domain=${window.location.hostname}; Expires=Thu, 01 Jan 1970 00:00:01 GMT;`;
    } catch (e) {
      // ignore
    }
  }

  clearCookie("session");

  const typeSelect = document.querySelector("[data-account-type]");
  const cpfFields = document.querySelector("[data-cpf-fields]");
  const cpfInput = document.querySelector("[name='cpf']");
  const oabFields = document.querySelector("[data-oab-fields]");
  const oabInput = document.querySelector("[name='inscricao']");
  const ufInput = document.querySelector("[name='oab_uf']");
  const professionalNote = document.querySelector("[data-professional-note]");
  const alertBoxes = document.querySelectorAll("[data-auth-alert]");

  function enhanceAnimatedLabels() {
    document.querySelectorAll(".auth-switch-page .jt-label").forEach((label) => {
      if (label.dataset.animatedLabel === "true") return;

      const text = label.textContent || "";
      label.dataset.animatedLabel = "true";
      label.textContent = "";

      Array.from(text).forEach((char, index) => {
        const span = document.createElement("span");
        span.className = "jt-label-char";
        span.style.setProperty("--char-index", index);
        span.textContent = char === " " ? "\u00A0" : char;
        label.appendChild(span);
      });
    });
  }

  function enhanceCustomSelect(select) {
    if (!select || select.dataset.customSelect === "true") return;

    const field = select.closest(".jt-field");
    if (!field) return;

    select.dataset.customSelect = "true";
    select.classList.add("jt-native-select-hidden");
    select.tabIndex = -1;
    select.setAttribute("aria-hidden", "true");
    field.classList.add("has-custom-select");

    const options = Array.from(select.options);
    const button = document.createElement("button");
    const list = document.createElement("div");
    const valueLabel = document.createElement("span");
    const label = field.querySelector(".jt-label");
    const buttonId = `${select.id || select.name || "select"}-custom-button`;
    const listId = `${select.id || select.name || "select"}-custom-list`;
    const labelText = label?.textContent?.trim() || select.getAttribute("aria-label") || "Selecionar";

    button.type = "button";
    button.id = buttonId;
    button.className = "jt-select-button";
    button.setAttribute("aria-haspopup", "listbox");
    button.setAttribute("aria-expanded", "false");
    button.setAttribute("aria-controls", listId);
    button.setAttribute("aria-label", labelText);

    valueLabel.className = "jt-select-value";
    button.appendChild(valueLabel);
    button.insertAdjacentHTML("beforeend", '<svg class="jt-select-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>');

    list.id = listId;
    list.className = "jt-select-list";
    list.setAttribute("role", "listbox");
    list.setAttribute("aria-labelledby", buttonId);
    list.hidden = true;

    const visibleOptions = options.filter((option) => option.value !== "");

    const optionNodes = visibleOptions.map((option) => {
      const item = document.createElement("button");
      item.type = "button";
      item.className = "jt-select-option";
      item.setAttribute("role", "option");
      item.dataset.value = option.value;
      item.textContent = option.textContent || labelText;
      list.appendChild(item);
      return item;
    });

    function syncSelected() {
      const selectedOption = options.find((option) => option.value === select.value) || options[0];
      valueLabel.textContent = selectedOption?.textContent || "";
      field.classList.toggle("has-value", select.value !== "");
      button.toggleAttribute("aria-invalid", select.getAttribute("aria-invalid") === "true");

      optionNodes.forEach((item) => {
        const isSelected = item.dataset.value === select.value;
        item.classList.toggle("is-selected", isSelected);
        item.setAttribute("aria-selected", String(isSelected));
      });
    }

    function closeSelect() {
      list.hidden = true;
      field.style.marginBottom = "";
      field.classList.remove("is-custom-select-open", "is-focused");
      button.setAttribute("aria-expanded", "false");
    }

    function openSelect() {
      list.hidden = false;
      field.classList.add("is-custom-select-open", "is-focused");
      button.setAttribute("aria-expanded", "true");
      requestAnimationFrame(() => {
        field.style.marginBottom = `${list.offsetHeight + 8}px`;
      });
    }

    function chooseValue(value) {
      select.value = value;
      select.dispatchEvent(new Event("change", { bubbles: true }));
      syncSelected();
      closeSelect();
      button.focus();
    }

    button.addEventListener("click", () => {
      if (list.hidden) {
        openSelect();
      } else {
        closeSelect();
      }
    });

    button.addEventListener("keydown", (event) => {
      if (["ArrowDown", "ArrowUp", "Enter", " "].includes(event.key)) {
        event.preventDefault();
        openSelect();
        const selected = optionNodes.find((item) => item.classList.contains("is-selected")) || optionNodes[0];
        selected?.focus();
      }
    });

    optionNodes.forEach((item, index) => {
      item.addEventListener("click", () => chooseValue(item.dataset.value));
      item.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
          event.preventDefault();
          closeSelect();
          button.focus();
          return;
        }

        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          chooseValue(item.dataset.value);
          return;
        }

        if (event.key === "ArrowDown" || event.key === "ArrowUp") {
          event.preventDefault();
          const direction = event.key === "ArrowDown" ? 1 : -1;
          const nextIndex = (index + direction + optionNodes.length) % optionNodes.length;
          optionNodes[nextIndex]?.focus();
        }
      });
    });

    document.addEventListener("pointerdown", (event) => {
      if (!field.contains(event.target)) {
        closeSelect();
      }
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeSelect();
      }
    });

    select.addEventListener("change", syncSelected);
    select.insertAdjacentElement("afterend", button);
    button.insertAdjacentElement("afterend", list);
    syncSelected();
  }

  function enhanceAuthSelects() {
    document.querySelectorAll(".auth-switch-page select.jt-input").forEach(enhanceCustomSelect);
  }

  function isBrowserAutofilled(input) {
    try {
      return input.matches(":-webkit-autofill");
    } catch (e) {
      return false;
    }
  }

  function syncAnimatedField(field) {
    const input = field.querySelector(".jt-input");
    if (!input) return;
    const hasValue = input.value !== "" || isBrowserAutofilled(input);
    field.classList.toggle("has-value", hasValue);
    if (input.validity.valid || !input.dataset.touched) {
      input.removeAttribute("aria-invalid");
      field.classList.remove("has-error");
      field.querySelector(".jt-select-button")?.removeAttribute("aria-invalid");
      const error = field.querySelector(".jt-error");
      if (error) error.textContent = "";
    }
  }

  enhanceAnimatedLabels();
  enhanceAuthSelects();

  document.querySelectorAll(".jt-field").forEach((field) => {
    const input = field.querySelector(".jt-input");
    if (!input) return;

    input.addEventListener("focus", () => field.classList.add("is-focused"));
    input.addEventListener("blur", () => {
      field.classList.remove("is-focused");
      input.dataset.touched = "1";
      syncAnimatedField(field);
      if (!input.validity.valid) {
        field.classList.add("has-error");
        const error = field.querySelector(".jt-error");
        if (error) error.textContent = input.validationMessage;
      }
    });
    input.addEventListener("input", () => syncAnimatedField(field));
    input.addEventListener("change", () => syncAnimatedField(field));
    input.addEventListener("animationstart", (event) => {
      if (event.animationName === "jtAutofillStart") {
        syncAnimatedField(field);
      }
    });
    syncAnimatedField(field);
  });

  function syncAllAnimatedFields() {
    document.querySelectorAll(".jt-field").forEach(syncAnimatedField);
  }

  requestAnimationFrame(syncAllAnimatedFields);
  setTimeout(syncAllAnimatedFields, 120);
  setTimeout(syncAllAnimatedFields, 500);
  window.addEventListener("pageshow", syncAllAnimatedFields);

  function formatOab(value) {
    return String(value || "").replace(/\D+/g, "").slice(0, 7);
  }

  function formatCpf(value) {
    const digits = String(value || "").replace(/\D+/g, "").slice(0, 11);
    return digits
      .replace(/^(\d{3})(\d)/, "$1.$2")
      .replace(/^(\d{3})\.(\d{3})(\d)/, "$1.$2.$3")
      .replace(/\.(\d{3})(\d)/, ".$1-$2");
  }

  function syncOabFields() {
    if (!typeSelect || !oabFields) return;

    const needsOab = typeSelect.value === "advogado";
    const needsCpf = typeSelect.value === "cliente";
    oabFields.classList.toggle("is-visible", needsOab);

    if (cpfFields) cpfFields.hidden = !needsCpf;
    if (professionalNote) professionalNote.hidden = !needsOab;
    if (cpfInput) {
      cpfInput.toggleAttribute("required", needsCpf);
      cpfInput.value = needsCpf ? formatCpf(cpfInput.value) : "";
      cpfInput.closest(".jt-field")?.classList.toggle("has-value", cpfInput.value !== "");
    }

    if (oabInput) {
      oabInput.toggleAttribute("required", needsOab);
      oabInput.value = needsOab ? formatOab(oabInput.value) : "";
      oabInput.closest(".jt-field")?.classList.toggle("has-value", oabInput.value !== "");
    }

    if (ufInput) {
      ufInput.toggleAttribute("required", needsOab);
      if (!needsOab) ufInput.value = "";
      ufInput.closest(".jt-field")?.classList.toggle("has-value", ufInput.value !== "");
    }
  }

  function showMessage(message, kind) {
    if (!alertBoxes.length || !message) return;
    alertBoxes.forEach((alertBox) => {
      alertBox.textContent = message;
      alertBox.className = `alert is-visible ${kind === "success" ? "alert-success" : "alert-error"}`;
      alertBox.setAttribute("role", kind === "success" ? "status" : "alert");
      alertBox.setAttribute("aria-live", kind === "success" ? "polite" : "assertive");
    });
  }

  function showFormMessage(form, message, kind = "error") {
    const alertBox = form.querySelector("[data-auth-alert]") || alertBoxes[0];
    if (!alertBox || !message) return;
    alertBox.textContent = message;
    alertBox.className = `alert is-visible ${kind === "success" ? "alert-success" : "alert-error"}`;
    alertBox.setAttribute("role", kind === "success" ? "status" : "alert");
    alertBox.setAttribute("aria-live", kind === "success" ? "polite" : "assertive");
  }

  function countDigits(value) {
    return String(value || "").replace(/\D+/g, "").length;
  }

  function isValidCpf(value) {
    const digits = String(value || "").replace(/\D+/g, "");

    if (digits.length !== 11 || /^(\d)\1{10}$/.test(digits)) {
      return false;
    }

    for (let position = 9; position <= 10; position += 1) {
      let sum = 0;

      for (let index = 0; index < position; index += 1) {
        sum += Number(digits[index]) * ((position + 1) - index);
      }

      let checkDigit = (sum * 10) % 11;
      if (checkDigit === 10) checkDigit = 0;

      if (checkDigit !== Number(digits[position])) {
        return false;
      }
    }

    return true;
  }

  function getAgeValidationMessage(value) {
    if (!value) {
      return "Informe sua data de nascimento.";
    }

    const birthDate = new Date(`${value}T00:00:00`);
    if (Number.isNaN(birthDate.getTime())) {
      return "Informe uma data de nascimento valida.";
    }

    const today = new Date();
    const eighteenthBirthday = new Date(
      birthDate.getFullYear() + 18,
      birthDate.getMonth(),
      birthDate.getDate()
    );

    if (birthDate > today || eighteenthBirthday > today) {
      return "É necessário ter 18 anos ou mais para criar uma conta no JusTraduz.";
    }

    return "";
  }

  function setButtonLoading(button, loading) {
    if (!button) return;
    if (loading) {
      button.dataset.originalText = button.textContent.trim();
      button.textContent = button.dataset.loadingText || "Processando...";
      button.classList.add("is-loading");
      button.disabled = true;
      return;
    }

    button.textContent = button.dataset.originalText || button.textContent;
    button.classList.remove("is-loading");
    button.disabled = false;
  }

  function focusFieldControl(field) {
    if (!field) return;

    const wrapper = field.closest(".jt-field");
    const visibleSelectButton = wrapper?.querySelector(".jt-select-button");
    const focusTarget = field.matches("select.jt-native-select-hidden") && visibleSelectButton
      ? visibleSelectButton
      : field;

    focusTarget.focus({ preventScroll: true });
    focusTarget.scrollIntoView({ block: "center", behavior: "smooth" });
  }

  function markInvalidField(field) {
    if (!field) return;

    field.setAttribute("aria-invalid", "true");
    field.dataset.touched = "1";

    const wrapper = field.closest(".jt-field");
    const error = wrapper?.querySelector(".jt-error");

    wrapper?.classList.add("has-error");
    wrapper?.querySelector(".jt-select-button")?.setAttribute("aria-invalid", "true");
    if (error) {
      error.textContent = field.validationMessage || "Preencha este campo.";
    }
  }

  function validateFormFields(form) {
    const fields = Array.from(form.querySelectorAll("input, select, textarea"))
      .filter((field) => field.type !== "hidden" && !field.disabled);

    let firstInvalid = null;

    fields.forEach((field) => {
      const wrapper = field.closest(".jt-field");
      const error = wrapper?.querySelector(".jt-error");

      if (field.checkValidity()) {
        field.removeAttribute("aria-invalid");
        wrapper?.querySelector(".jt-select-button")?.removeAttribute("aria-invalid");
        wrapper?.classList.remove("has-error");
        if (error) error.textContent = "";
        return;
      }

      if (!firstInvalid) {
        firstInvalid = field;
      }

      markInvalidField(field);
    });

    if (firstInvalid) {
      focusFieldControl(firstInvalid);
      return false;
    }

    return true;
  }

  async function ensureCsrfToken() {
    if (csrfToken) return csrfToken;

    const existing = document.querySelector('input[name="_csrf"]');
    if (existing?.value) {
      csrfToken = existing.value;
      return csrfToken;
    }

    const res = await fetch(backendRoute("/auth/csrf"), { credentials: "include" });
    if (!res.ok) return "";

    const data = await res.json();
    csrfToken = data.csrf || "";
    return csrfToken;
  }

  if (typeSelect) {
    typeSelect.addEventListener("change", syncOabFields);
    syncOabFields();
  }

  if (oabInput) {
    oabInput.addEventListener("input", () => {
      const formatted = formatOab(oabInput.value);
      if (oabInput.value !== formatted) oabInput.value = formatted;
    });
  }

  if (cpfInput) {
    cpfInput.addEventListener("input", () => {
      const formatted = formatCpf(cpfInput.value);
      if (cpfInput.value !== formatted) cpfInput.value = formatted;
    });
  }

  const params = new URLSearchParams(window.location.search);
  if (params.has("erro")) showMessage(params.get("erro"), "error");
  if (params.get("sucesso") === "conta_criada") {
    showMessage("Conta criada com sucesso. Entre para continuar.", "success");
  } else if (params.has("sucesso")) {
    showMessage(params.get("sucesso"), "success");
  }

  async function injectCsrf() {
    try {
      const token = await ensureCsrfToken();
      if (!token) return;
      document.querySelectorAll('form[method="post"]').forEach((form) => {
        if (!form.querySelector('input[name="_csrf"]')) {
          const input = document.createElement("input");
          input.type = "hidden";
          input.name = "_csrf";
          input.value = token;
          form.appendChild(input);
        }
      });
    } catch (e) {
      // ignore
    }
  }

  injectCsrf();

  document.querySelectorAll(".auth-form").forEach((form) => {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      const submitButton = form.querySelector('button[type="submit"]');
      const senha = form.querySelector('input[name="senha"]');
      const senha2 = form.querySelector('input[name="senha2"]');
      const formType = form.querySelector("[data-account-type]")?.value || "";
      const formCpf = form.querySelector('input[name="cpf"]');
      const formOab = form.querySelector('input[name="inscricao"]');
      const formUf = form.querySelector('select[name="oab_uf"]');
      const birthDate = form.querySelector('input[name="data_nascimento"]');

      if (!validateFormFields(form)) {
        return;
      }

      const ageMessage = birthDate ? getAgeValidationMessage(birthDate.value) : "";
      if (birthDate && ageMessage) {
        showFormMessage(form, ageMessage);
        if (birthDate) {
          birthDate.setCustomValidity(ageMessage);
          markInvalidField(birthDate);
          focusFieldControl(birthDate);
          birthDate.addEventListener("input", () => birthDate.setCustomValidity(""), { once: true });
        }
        return;
      }

      if (senha && senha2 && senha.value !== senha2.value) {
        showFormMessage(form, "As senhas precisam ser iguais.");
        senha2.setAttribute("aria-invalid", "true");
        senha2.focus();
        return;
      }

      if (formCpf?.required && !isValidCpf(formCpf.value)) {
        showFormMessage(form, "Informe um CPF valido para continuar.");
        formCpf.setAttribute("aria-invalid", "true");
        formCpf.focus();
        return;
      }

      if (formType === "advogado") {
        if (!formOab?.value || countDigits(formOab.value) < 4 || !formUf?.value) {
          showFormMessage(form, "Informe numero da OAB e UF para o admin validar seu acesso.");
          (formOab?.value ? formUf : formOab)?.setAttribute("aria-invalid", "true");
          focusFieldControl(formOab?.value ? formUf : formOab);
          return;
        }
      }

      setButtonLoading(submitButton, true);

      try {
        const token = await ensureCsrfToken();
        if (!token) {
          showFormMessage(form, "Não foi possível preparar a segurança do formulário. Recarregue a página.");
          setButtonLoading(submitButton, false);
          return;
        }

        if (!form.querySelector('input[name="_csrf"]')) {
          const input = document.createElement("input");
          input.type = "hidden";
          input.name = "_csrf";
          input.value = token;
          form.appendChild(input);
        }

        HTMLFormElement.prototype.submit.call(form);
      } catch (e) {
        showFormMessage(form, "Não foi possível enviar agora. Tente novamente.");
        setButtonLoading(submitButton, false);
      }
    });
  });
});
