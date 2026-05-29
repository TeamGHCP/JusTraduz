document.addEventListener("DOMContentLoaded", () => {
  const typeSelect = document.querySelector("[data-account-type]");
  const oabFields = document.querySelector("[data-oab-fields]");
  const oabInput = document.querySelector("[name='inscricao']");
  const ufInput = document.querySelector("[name='oab_uf']");
  const alertBox = document.querySelector("[data-auth-alert]");

  function syncOabFields() {
    if (!typeSelect || !oabFields) return;

    const needsOab = ["advogado", "estagiario"].includes(typeSelect.value);
    oabFields.classList.toggle("is-visible", needsOab);

    [oabInput, ufInput].forEach((field) => {
      if (!field) return;
      field.toggleAttribute("required", needsOab);
      if (!needsOab) field.value = "";
    });
  }

  function showMessage(message, kind) {
    if (!alertBox || !message) return;
    alertBox.textContent = message;
    alertBox.className = `alert is-visible ${kind === "success" ? "alert-success" : "alert-error"}`;
  }

  if (typeSelect) {
    typeSelect.addEventListener("change", syncOabFields);
    syncOabFields();
  }

  const params = new URLSearchParams(window.location.search);
  if (params.has("erro")) showMessage(params.get("erro"), "error");
  if (params.get("sucesso") === "conta_criada") {
    showMessage("Conta criada com sucesso. Entre para continuar.", "success");
  }
});
