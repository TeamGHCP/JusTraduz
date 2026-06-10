document.addEventListener("DOMContentLoaded", () => {
  const chatbot = document.querySelector("[data-ai-chatbot]");
  if (!chatbot) return;

  const toggle = chatbot.querySelector("[data-ai-chatbot-toggle]");
  const closeButton = chatbot.querySelector("[data-ai-chatbot-close]");
  const panel = chatbot.querySelector("[data-ai-chatbot-panel]");
  const messages = chatbot.querySelector("[data-ai-chatbot-messages]");
  const form = chatbot.querySelector("[data-ai-chatbot-form]");
  const input = chatbot.querySelector("[data-ai-chatbot-input]");
  const frontendMarker = "/frontend/";
  const frontendIndex = window.location.pathname.indexOf(frontendMarker);
  const appBasePath = frontendIndex >= 0 ? window.location.pathname.slice(0, frontendIndex) : "";
  const backendBase = `${appBasePath}/backend/public/index.php`;
  const backendRoute = (path) => `${backendBase}?rota=${encodeURIComponent(path)}`;
  const chatHistory = [];
  let csrfToken = "";
  let isSending = false;

  function setOpen(open) {
    chatbot.classList.toggle("is-open", open);
    toggle?.setAttribute("aria-expanded", open ? "true" : "false");
    panel?.setAttribute("aria-hidden", open ? "false" : "true");

    if (open) {
      window.setTimeout(() => input?.focus(), 120);
    }
  }

  function appendMessage(text, type) {
    const item = document.createElement("article");
    item.className = `ai-chatbot-message ai-chatbot-message-${type}`;

    const paragraph = document.createElement("p");
    paragraph.textContent = text;
    item.appendChild(paragraph);
    messages.appendChild(item);
    messages.scrollTop = messages.scrollHeight;
    return item;
  }

  function setSending(sending) {
    isSending = sending;
    input.disabled = sending;
    form.querySelector("button")?.toggleAttribute("disabled", sending);
  }

  function resizeInput() {
    input.style.height = "auto";
    input.style.height = `${Math.min(input.scrollHeight, 96)}px`;
  }

  function delay(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
  }

  function estimateResponseDelay(text) {
    const length = String(text || "").length;

    if (length <= 90) return 550;
    if (length <= 220) return 1100;
    if (length <= 420) return 1900;
    return 2800;
  }

  function setMessageText(messageNode, text) {
    const paragraph = messageNode?.querySelector("p");
    if (paragraph) paragraph.textContent = text;
  }

  async function ensureCsrfToken() {
    if (csrfToken) return csrfToken;

    const response = await fetch(backendRoute("/ai/csrf"), {
      credentials: "include",
      headers: { Accept: "application/json" },
    });

    if (!response.ok) return "";

    const data = await response.json();
    csrfToken = data.csrf || "";
    return csrfToken;
  }

  async function sendMessage(message) {
    const token = await ensureCsrfToken();
    if (!token) {
      throw new Error("Nao foi possivel preparar a seguranca do chat. Recarregue a pagina.");
    }

    const response = await fetch(backendRoute("/ai/chat"), {
      method: "POST",
      credentials: "include",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-Token": token,
      },
      body: JSON.stringify({ mensagem: message, historico: chatHistory.slice(-8) }),
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.erro || "Nao foi possivel falar com a IA agora.");
    }

    const answer = data.resposta || "Nao consegui montar uma resposta agora.";
    chatHistory.push({ papel: "usuario", texto: message });
    chatHistory.push({ papel: "assistente", texto: answer });

    while (chatHistory.length > 10) {
      chatHistory.shift();
    }

    return answer;
  }

  toggle?.addEventListener("click", () => setOpen(true));
  closeButton?.addEventListener("click", () => setOpen(false));

  input?.addEventListener("input", resizeInput);
  input?.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (isSending) return;

    const message = input.value.trim();
    if (!message) {
      input.focus();
      return;
    }

    appendMessage(message, "user");
    input.value = "";
    resizeInput();
    setSending(true);

    const loading = appendMessage("Pensando...", "loading");
    const requestStartedAt = Date.now();

    try {
      const answer = await sendMessage(message);
      const targetDelay = estimateResponseDelay(answer);
      const elapsed = Date.now() - requestStartedAt;
      const remainingDelay = Math.max(0, targetDelay - elapsed);

      setMessageText(loading, "Digitando...");
      if (remainingDelay > 0) {
        await delay(remainingDelay);
      }

      loading.remove();
      appendMessage(answer, "bot");
    } catch (error) {
      loading.remove();
      appendMessage(error.message || "Nao foi possivel responder agora.", "error");
    } finally {
      setSending(false);
      input.focus();
    }
  });
});
