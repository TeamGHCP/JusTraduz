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

    const needsOab = ["advogado", "estagiario"].includes(typeSelect.value);
    const needsCpf = typeSelect.value === "cliente";
    oabFields.classList.toggle("is-visible", needsOab);

    if (cpfFields) cpfFields.hidden = !needsCpf;
    if (professionalNote) professionalNote.hidden = !needsOab;
    if (cpfInput) {
      cpfInput.toggleAttribute("required", needsCpf);
      cpfInput.value = needsCpf ? formatCpf(cpfInput.value) : "";
    }

    if (oabInput) {
      oabInput.toggleAttribute("required", needsOab);
      oabInput.value = needsOab ? formatOab(oabInput.value) : "";
    }

    if (ufInput) {
      ufInput.toggleAttribute("required", needsOab);
      if (!needsOab) ufInput.value = "";
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

      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      if (senha && senha2 && senha.value !== senha2.value) {
        showFormMessage(form, "As senhas precisam ser iguais.");
        senha2.setAttribute("aria-invalid", "true");
        senha2.focus();
        return;
      }

      if (formCpf?.required && countDigits(formCpf.value) !== 11) {
        showFormMessage(form, "Informe um CPF completo para continuar.");
        formCpf.setAttribute("aria-invalid", "true");
        formCpf.focus();
        return;
      }

      if (["advogado", "estagiario"].includes(formType)) {
        if (!formOab?.value || countDigits(formOab.value) < 4 || !formUf?.value) {
          showFormMessage(form, "Informe numero da OAB e UF para o admin validar seu acesso.");
          (formOab?.value ? formUf : formOab)?.setAttribute("aria-invalid", "true");
          (formOab?.value ? formUf : formOab)?.focus();
          return;
        }
      }

      setButtonLoading(submitButton, true);

      try {
        const token = await ensureCsrfToken();
        if (!token) {
          showFormMessage(form, "Nao foi possivel preparar a seguranca do formulario. Recarregue a pagina.");
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
        showFormMessage(form, "Nao foi possivel enviar agora. Tente novamente.");
        setButtonLoading(submitButton, false);
      }
    });
  });
});
