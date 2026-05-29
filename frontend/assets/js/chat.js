document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("[data-chat-form]");
  const input = document.querySelector("[data-chat-input]");
  const messages = document.querySelector("[data-chat-messages]");

  if (!messages) return;

  messages.scrollTop = messages.scrollHeight;

  if (!form || !input) return;

  form.addEventListener("submit", () => {
    window.setTimeout(() => {
      messages.scrollTop = messages.scrollHeight;
    }, 0);
  });
});
