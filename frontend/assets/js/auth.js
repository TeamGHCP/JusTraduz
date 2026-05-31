document.addEventListener("DOMContentLoaded", () => {
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
});
