document.addEventListener("DOMContentLoaded", () => {
  // Defensive: remove any stray `session` cookie left by previous experiments or other apps
  // This helps avoid ambiguous cookies that may cause unexpected auth behavior in local dev.
  function clearCookie(name) {
    try {
      // expire for current host
      document.cookie = name + '=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
      // expire for root domain (if present)
      const host = window.location.hostname;
      document.cookie = name + '=; Path=/; Domain=' + host + '; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
    } catch (e) {
      // ignore
    }
  }

  clearCookie('session');

  const typeSelect = document.querySelector("[data-account-type]");
  const oabFields = document.querySelector("[data-oab-fields]");
  const oabInput = document.querySelector("[name='inscricao']");
  const ufInput = document.querySelector("[name='oab_uf']");
  const nameInput = document.querySelector("[name='nome']");
  const lookupButton = document.querySelector("[data-oab-lookup]");
  const lookupStatus = document.querySelector("[data-oab-status]");
  const alertBox = document.querySelector("[data-auth-alert]");

  function syncOabFields() {
    if (!typeSelect || !oabFields) return;

    const needsOab = ["advogado", "estagiario"].includes(typeSelect.value);
    oabFields.classList.toggle("is-visible", needsOab);

    if (oabInput) oabInput.toggleAttribute("required", needsOab);
    if (!needsOab) {
      if (oabInput) oabInput.value = "";
      if (ufInput) ufInput.value = "";
      setOabStatus("");
    }
  }

  function showMessage(message, kind) {
    if (!alertBox || !message) return;
    alertBox.textContent = message;
    alertBox.className = `alert is-visible ${kind === "success" ? "alert-success" : "alert-error"}`;
  }

  function setOabStatus(message, kind = "") {
    if (!lookupStatus) return;
    lookupStatus.textContent = message;
    lookupStatus.className = `oab-lookup-status ${kind ? `is-${kind}` : ""}`;
  }

  async function lookupOab() {
    if (!typeSelect || !oabInput || !lookupButton) return;
    const needsOab = ["advogado", "estagiario"].includes(typeSelect.value);
    const inscricao = oabInput.value.replace(/\D+/g, "");

    if (!needsOab || !inscricao) {
      setOabStatus("Informe o número da OAB para consultar.", "error");
      return;
    }

    lookupButton.disabled = true;
    setOabStatus("Consultando CNA...", "loading");

    try {
      const body = new URLSearchParams({
        tipo: typeSelect.value,
        inscricao,
        oab_uf: ufInput?.value || "",
        nome: nameInput?.value || "",
      });

      const response = await fetch("../backend/public/index.php?rota=/oab/lookup", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body,
      });
      const data = await response.json();

      if (data.verified && data.data) {
        if (ufInput && data.data.uf) ufInput.value = data.data.uf;
        const tipo = data.data.tipo ? ` (${data.data.tipo})` : "";
        setOabStatus(`Validado no CNA: OAB/${data.data.uf} ${data.data.inscricao}${tipo}.`, "success");
        return;
      }

      setOabStatus(data.message || "Não foi possível validar a inscrição agora.", data.source_available === false ? "warning" : "error");
    } catch (error) {
      setOabStatus("Falha ao consultar o CNA agora.", "warning");
    } finally {
      lookupButton.disabled = false;
    }
  }

  if (typeSelect) {
    typeSelect.addEventListener("change", syncOabFields);
    syncOabFields();
  }

  if (lookupButton) {
    lookupButton.addEventListener("click", lookupOab);
  }

  const params = new URLSearchParams(window.location.search);
  if (params.has("erro")) showMessage(params.get("erro"), "error");
  if (params.get("sucesso") === "conta_criada") {
    showMessage("Conta criada com sucesso. Entre para continuar.", "success");
  } else if (params.has("sucesso")) {
    showMessage(params.get("sucesso"), "success");
  }

  // Ensure server session is cleared (force logout) then fetch CSRF token and inject into all POST forms
  async function ensureCleanSessionAndInjectCsrf() {
    try {
      // Call force-logout to remove any server-side session; request JSON to avoid redirects
      await fetch('/backend/public/index.php?rota=/auth/force-logout', { method: 'GET', headers: { 'Accept': 'application/json' }, credentials: 'include' });
    } catch (e) {
      // ignore failures — proceed to fetch CSRF anyway
    }

    try {
      const res = await fetch('/backend/public/index.php?rota=/auth/csrf', { credentials: 'include' });
      if (!res.ok) return;
      const data = await res.json();
      const token = data.csrf;
      if (!token) return;
      document.querySelectorAll('form[method="post"]').forEach((form) => {
        if (!form.querySelector('input[name="_csrf"]')) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = '_csrf';
          input.value = token;
          form.appendChild(input);
        }
      });
    } catch (e) {
      // ignore CSRF injection failures — forms will fail server-side with token error
    }
  }

  ensureCleanSessionAndInjectCsrf();
});
