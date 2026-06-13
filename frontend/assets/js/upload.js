document.addEventListener("DOMContentLoaded", () => {
  const drop = document.querySelector("[data-upload-box]");
  const input = document.querySelector("[data-upload-input]");
  const fileName = document.querySelector("[data-file-name]");
  const form = document.querySelector("[data-upload-form]");
  const submit = document.querySelector("[data-upload-submit]");

  if (fileName) {
    fileName.setAttribute("role", "status");
    fileName.setAttribute("aria-live", "polite");
    fileName.setAttribute("aria-atomic", "true");
  }

  if (!drop || !input) return;

  drop.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" && event.key !== " ") return;
    event.preventDefault();
    input.click();
  });

  ["dragenter", "dragover"].forEach((eventName) => {
    drop.addEventListener(eventName, (event) => {
      event.preventDefault();
      drop.classList.add("is-dragging");
    });
  });

  ["dragleave", "drop"].forEach((eventName) => {
    drop.addEventListener(eventName, (event) => {
      event.preventDefault();
      drop.classList.remove("is-dragging");
    });
  });

  drop.addEventListener("drop", (event) => {
    const file = event.dataTransfer.files[0];
    if (!file) return;
    input.files = event.dataTransfer.files;
    if (fileName) fileName.textContent = file.name;
    input.focus();
  });

  input.addEventListener("change", () => {
    if (fileName) fileName.textContent = input.files[0]?.name || "Nenhum arquivo selecionado";
  });

  if (form && submit) {
    form.addEventListener("submit", () => {
      form.classList.add("is-submitting");
      submit.disabled = true;
      submit.textContent = "Enviando e preparando análise...";
      submit.setAttribute("aria-busy", "true");
    });
  }
});
