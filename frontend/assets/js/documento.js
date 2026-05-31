document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-copy-text]").forEach((button) => {
    button.addEventListener("click", async () => {
      const target = document.querySelector(button.dataset.copyText);
      const text = target ? target.innerText.trim() : "";

      if (!text) return;

      try {
        await navigator.clipboard.writeText(text);
        const originalText = button.textContent;
        button.textContent = "Copiado";
        window.setTimeout(() => {
          button.textContent = originalText;
        }, 1400);
      } catch (error) {
        const textarea = document.createElement("textarea");
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand("copy");
        textarea.remove();
      }
    });
  });

  document.querySelectorAll("[data-confirm-delete]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      const message = form.getAttribute("data-confirm-delete") || "Confirmar exclusão?";
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll(".analysis-form").forEach((form) => {
    form.addEventListener("submit", () => {
      const button = form.querySelector("button[type='submit']");
      if (!button) return;
      button.disabled = true;
      button.textContent = "Gerando análise...";
    });
  });
});
