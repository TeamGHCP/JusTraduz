document.addEventListener("DOMContentLoaded", () => {
  const drop = document.querySelector("[data-upload-box]");
  const input = document.querySelector("[data-upload-input]");
  const fileName = document.querySelector("[data-file-name]");

  if (!drop || !input) return;

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
  });

  input.addEventListener("change", () => {
    if (fileName) fileName.textContent = input.files[0]?.name || "Nenhum arquivo selecionado";
  });
});
