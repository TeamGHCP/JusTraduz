document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("[data-chat-form]");
  const input = document.querySelector("[data-chat-input]");
  const messages = document.querySelector("[data-chat-messages]");

  if (!form || !input || !messages) return;

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    const bubble = document.createElement("div");
    bubble.className = "message out";
    bubble.textContent = text;
    messages.appendChild(bubble);
    input.value = "";
    messages.scrollTop = messages.scrollHeight;
  });
});
