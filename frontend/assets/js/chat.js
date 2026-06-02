document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("[data-chat-form]");
  const input = document.querySelector("[data-chat-input]");
  const file = document.querySelector("[data-chat-file]");
  const fileName = document.querySelector("[data-chat-file-name]");
  const messages = document.querySelector("[data-chat-messages]");

  if (!messages) return;

  messages.scrollTop = messages.scrollHeight;

  if (!form || !input) return;

  if (file && fileName) {
    file.addEventListener("change", () => {
      const selected = file.files && file.files[0] ? file.files[0].name : "";
      fileName.textContent = selected ? `Anexo: ${selected}` : "";
    });
  }

  form.addEventListener("submit", (event) => {
    const hasText = input.value.trim() !== "";
    const hasFile = Boolean(file && file.files && file.files.length > 0);

    if (!hasText && !hasFile) {
      event.preventDefault();
      input.focus();
      return;
    }

    window.setTimeout(() => {
      messages.scrollTop = messages.scrollHeight;
    }, 0);
  });
});
