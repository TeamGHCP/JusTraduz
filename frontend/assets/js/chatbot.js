document.addEventListener("DOMContentLoaded", () => {
  const chatbot = document.querySelector("[data-ai-chatbot]");
  if (!chatbot) return;

  const toggle = chatbot.querySelector("[data-ai-chatbot-toggle]");
  const closeButton = chatbot.querySelector("[data-ai-chatbot-close]");
  const panel = chatbot.querySelector("[data-ai-chatbot-panel]");
  const messages = chatbot.querySelector("[data-ai-chatbot-messages]");
  const form = chatbot.querySelector("[data-ai-chatbot-form]");
  const input = chatbot.querySelector("[data-ai-chatbot-input]");
  const consentPanel = chatbot.querySelector("[data-ai-chatbot-consent]");
  const ageConfirmation = chatbot.querySelector("[data-ai-chatbot-age]");
  const termsConfirmation = chatbot.querySelector("[data-ai-chatbot-terms]");
  const consentButton = chatbot.querySelector("[data-ai-chatbot-consent-button]");
  const frontendMarker = "/frontend/";
  const frontendIndex = window.location.pathname.indexOf(frontendMarker);
  const appBasePath = frontendIndex >= 0 ? window.location.pathname.slice(0, frontendIndex) : "";
  const backendBase = `${appBasePath}/backend/public/index.php`;
  const backendRoute = (path) => `${backendBase}?rota=${encodeURIComponent(path)}`;
  const consentVersion = "2026-06-13-v1";
  const consentStorageKey = `justraduz-ai-consent:${consentVersion}`;
  const greetingMessage = "Olá, sou o Jus IA, um assistente informativo. Como eu posso ajudar?";
  const chatHistory = [];
  const guidedTopics = [
    {
      label: "Orçamento",
      children: [
        {
          label: "Quanto custa?",
          answer: "O valor depende do tipo de documento, idioma, quantidade de páginas/laudas, prazo e se precisa de tradução juramentada. Para receber um orçamento correto, envie o arquivo completo e legível pelo JusTraduz.",
        },
        {
          label: "Pagamento",
          answer: "As formas de pagamento e possível parcelamento devem ser confirmados no atendimento. O ideal é enviar o documento primeiro para a equipe calcular o valor e apresentar a proposta.",
        },
        {
          label: "Desconto",
          answer: "Quando há vários documentos, a equipe pode avaliar o conjunto antes de confirmar o valor. Envie todos os arquivos para uma proposta mais precisa.",
        },
      ],
    },
    {
      label: "Tradução",
      children: [
        {
          label: "Juramentada",
          answer: "Tradução juramentada é a tradução oficial feita por tradutor público habilitado. Ela costuma ser exigida para documentos usados em órgãos públicos, universidades, cartórios, consulados, processos e autoridades estrangeiras.",
        },
        {
          label: "Simples",
          answer: "Tradução simples é indicada quando você precisa entender o conteúdo ou usar o texto sem exigência oficial. Se o documento será entregue a consulado, universidade, cartório, órgão público ou processo, confirme antes se pedem tradução juramentada. Para orientar melhor, diga qual é o documento, o idioma atual, o idioma desejado e onde ele será usado.",
        },
        {
          label: "Qual escolher?",
          answer: "Para escolher entre tradução simples e juramentada, precisamos saber qual documento você tem, em qual idioma ele está, para qual idioma precisa traduzir e onde ele será usado.",
        },
        {
          label: "Quero traduzir",
          answer: "Claro. Para orientar a tradução, preciso de quatro informações: qual é o documento, em qual idioma ele está, para qual idioma você precisa traduzir e onde ele será usado. Se for para consulado, universidade, cartório, processo ou órgão público, pode ser necessário avaliar tradução juramentada.",
        },
      ],
    },
    {
      label: "Documentos",
      children: [
        {
          label: "Difícil de entender",
          answer: "Posso ajudar a explicar em linguagem simples. Envie o arquivo ou uma foto legível pelo JusTraduz. Se quiser descrever aqui, não informe CPF, nomes completos, número de processo ou dados sigilosos. Diga só o tipo de documento e qual parte confundiu você: prazo, valor, multa, obrigação, assinatura ou próximos passos.",
        },
        {
          label: "Certidoes",
          answer: "Certidões de nascimento, casamento e outros registros civis geralmente podem ser traduzidas. Para confirmar o formato correto, informe o país, idioma e órgão que vai receber o documento.",
        },
        {
          label: "Diploma ou histórico",
          answer: "Diploma e histórico escolar muitas vezes exigem tradução juramentada para estudo, validação ou uso no exterior. A regra final depende da universidade, consulado ou órgão de destino.",
        },
        {
          label: "Contrato",
          answer: "Contratos podem ser traduzidos, mas a necessidade de tradução juramentada depende do uso. Envie o arquivo completo e informe onde ele será apresentado.",
        },
      ],
    },
    {
      label: "Prazos",
      children: [
        {
          label: "Prazo normal",
          answer: "O prazo depende do tipo de documento, idioma, volume, legibilidade e necessidade de tradução juramentada. Para confirmar a entrega, envie o arquivo e informe quando precisa usar a tradução.",
        },
        {
          label: "Urgente",
          answer: "Para urgência, a equipe precisa analisar o documento antes de confirmar viabilidade. Envie o arquivo completo e diga a data limite em que você precisa da tradução.",
        },
      ],
    },
    {
      label: "Enviar arquivo",
      answer: "Você pode enviar PDF ou imagem pelo celular, desde que esteja completo e legível. Se o documento estiver cortado, borrado ou ilegível, talvez seja necessário reenviar uma versão melhor.",
    },
    {
      label: "Usar o site",
      children: [
        {
          label: "Criar conta",
          answer: "Para criar uma conta, use o botão Criar conta ou Cadastrar no site. Preencha seus dados, escolha seu perfil e confirme o envio. Se você for profissional, a validação pode depender da análise dos dados da OAB antes de liberar recursos completos.",
        },
        {
          label: "Entrar",
          answer: "Para entrar, clique em Entrar e informe e-mail e senha. Se estiver usando cadastro pelo Google, use a opção correspondente na tela de login. Se não lembrar a senha, use Recuperar senha antes de tentar criar outra conta.",
        },
        {
          label: "Enviar documento",
          answer: "Para enviar um documento, entre na sua conta e use a área de envio de documentos. Prefira PDF ou imagem nítida, completa e sem cortes. Antes de enviar, confirme se não há páginas faltando e autorize a análise por IA somente se estiver de acordo com os termos.",
        },
        {
          label: "Acompanhar pedido",
          answer: "Depois de entrar na conta, acompanhe seus pedidos pela área de solicitações ou casos. Lá você pode ver status, mensagens, documentos relacionados e próximas ações. Se houver advogado responsável, use o chat do caso para continuar o atendimento.",
        },
        {
          label: "Perfis",
          answer: "Cliente envia documentos e acompanha solicitações. Advogado atende casos e interage com clientes quando validado. Administrador gerencia usuários, auditoria e validações.",
        },
        {
          label: "Contato",
          answer: "Para falar com o time, use a página Contato do site. Descreva o assunto de forma objetiva e evite enviar CPF, senha, número de processo ou documentos sigilosos em mensagens abertas.",
        },
      ],
    },
    {
      label: "Uso no exterior",
      children: [
        {
          label: "Cidadania",
          answer: "Para cidadania, a exigência depende do país, consulado ou órgão responsável. Certidões e documentos pessoais frequentemente pedem tradução juramentada, mas é importante conferir a regra do destino.",
        },
        {
          label: "Estudo",
          answer: "Para estudo no exterior, diplomas, históricos e documentos acadêmicos podem exigir tradução juramentada. Confirme a exigência da universidade ou instituição antes de finalizar.",
        },
        {
          label: "Imigracao",
          answer: "Para imigração, não dá para garantir aceite ou aprovação. O caminho seguro é conferir a lista oficial de documentos e traduzir no formato solicitado pelo órgão de destino.",
        },
      ],
    },
    {
      label: "Digitar pergunta",
      action: "free-text",
    },
  ];
  let csrfToken = "";
  let isSending = false;
  let hasShownGreeting = false;
  let freeTextEnabled = false;
  let hasConsent = false;

  try {
    hasConsent = window.localStorage.getItem(consentStorageKey) === "accepted";
  } catch (error) {
    hasConsent = false;
  }

  function applyConsentState() {
    consentPanel.hidden = hasConsent;
    messages.hidden = !hasConsent;
    form.hidden = !hasConsent;

    if (hasConsent) {
      showGreetingOnce();
    }
  }

  function updateConsentButton() {
    consentButton.disabled = !(ageConfirmation.checked && termsConfirmation.checked);
  }

  function syncFloatingAccessibilityLayers(open) {
    const vlibrasRoot = document.querySelector("[vw]");
    const vlibrasButton = document.querySelector("[vw-access-button]");
    const vlibrasWrapper = document.querySelector("[vw-plugin-wrapper]");
    const accessibilityButton = document.querySelector(".a11y-launcher");

    if (open) {
      vlibrasRoot?.style.setProperty("z-index", "99990", "important");
      vlibrasButton?.style.setProperty("z-index", "99990", "important");
      vlibrasWrapper?.style.setProperty("z-index", "99989", "important");
      accessibilityButton?.style.setProperty("z-index", "99991", "important");
      return;
    }

    vlibrasRoot?.style.setProperty("z-index", "99999", "important");
    vlibrasButton?.style.setProperty("z-index", "99999", "important");
    vlibrasWrapper?.style.setProperty("z-index", "99998", "important");
    accessibilityButton?.style.setProperty("z-index", "100000", "important");
  }

  function setOpen(open) {
    chatbot.classList.toggle("is-open", open);
    toggle?.setAttribute("aria-expanded", open ? "true" : "false");
    panel?.setAttribute("aria-hidden", open ? "false" : "true");
    if (panel) panel.inert = !open;
    syncFloatingAccessibilityLayers(open);

    if (open) {
      if (freeTextEnabled) {
        window.setTimeout(() => input?.focus(), 120);
      } else {
        window.setTimeout(() => (hasConsent ? panel.querySelector("button") : ageConfirmation)?.focus(), 120);
      }
      if (hasConsent) showGreetingOnce();
    } else {
      toggle?.focus();
    }
  }

  function appendMessage(text, type) {
    const item = document.createElement("article");
    item.className = `ai-chatbot-message ai-chatbot-message-${type}`;

    if (type !== "user") {
      const avatar = document.createElement("span");
      avatar.className = "ai-chatbot-avatar";
      avatar.setAttribute("aria-hidden", "true");

      const image = document.createElement("img");
      image.src = "assets/img/chat-bot-logo-small.png";
      image.width = 48;
      image.height = 48;
      image.loading = "lazy";
      image.decoding = "async";
      image.alt = "";
      avatar.appendChild(image);
      item.appendChild(avatar);
    }

    const paragraph = document.createElement("p");
    paragraph.textContent = text;
    item.appendChild(paragraph);
    messages.appendChild(item);
    messages.scrollTop = messages.scrollHeight;
    return item;
  }

  function appendChoiceGroup(title, choices) {
    const item = document.createElement("article");
    item.className = "ai-chatbot-message ai-chatbot-message-bot ai-chatbot-message-choices";

    const avatar = document.createElement("span");
    avatar.className = "ai-chatbot-avatar";
    avatar.setAttribute("aria-hidden", "true");

    const image = document.createElement("img");
    image.src = "assets/img/chat-bot-logo-small.png";
    image.width = 48;
    image.height = 48;
    image.loading = "lazy";
    image.decoding = "async";
    image.alt = "";
    avatar.appendChild(image);
    item.appendChild(avatar);

    const content = document.createElement("div");
    content.className = "ai-chatbot-choice-content";

    const paragraph = document.createElement("p");
    paragraph.textContent = title;
    content.appendChild(paragraph);

    const list = document.createElement("div");
    list.className = "ai-chatbot-choice-list";

    choices.forEach((choice) => {
      const button = document.createElement("button");
      button.type = "button";
      if (choice.action === "main-menu") {
        const arrow = document.createElement("span");
        arrow.className = "ai-chatbot-choice-arrow";
        arrow.setAttribute("aria-hidden", "true");

        const label = document.createElement("span");
        label.textContent = "Voltar";

        button.appendChild(arrow);
        button.appendChild(label);
      } else {
        button.textContent = choice.label;
      }
      button.addEventListener("click", () => {
        list.querySelectorAll("button").forEach((item) => {
          item.disabled = true;
        });
        handleGuidedChoice(choice);
      });
      list.appendChild(button);
    });

    content.appendChild(list);
    item.appendChild(content);
    messages.appendChild(item);
    messages.scrollTop = messages.scrollHeight;
    return item;
  }

  function setSending(sending) {
    isSending = sending;
    input.disabled = sending || !freeTextEnabled;
    form.querySelector("button")?.toggleAttribute("disabled", sending || !freeTextEnabled);
  }

  function setFreeTextEnabled(enabled) {
    freeTextEnabled = enabled;
    input.disabled = !enabled;
    input.placeholder = enabled ? "Digite sua dúvida..." : "Escolha um tópico acima";
    form.querySelector("button")?.toggleAttribute("disabled", !enabled);
  }

  function resizeInput() {
    input.style.height = "auto";
    input.style.height = `${Math.min(input.scrollHeight, 96)}px`;
  }

  function delay(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
  }

  async function showGreetingOnce() {
    if (hasShownGreeting) return;
    hasShownGreeting = true;

    const loading = appendMessage("Digitando...", "loading");
    await delay(1000 + Math.floor(Math.random() * 1000));
    loading.remove();
    appendMessage(greetingMessage, "bot");
    appendChoiceGroup("Escolha um assunto para eu te orientar melhor:", guidedTopics);
  }

  function estimateResponseDelay(text) {
    const length = String(text || "").length;

    if (length <= 90) return 550;
    if (length <= 220) return 1100;
    if (length <= 420) return 1900;
    return 2800;
  }

  function appendMainTopics() {
    appendChoiceGroup("Escolha outro assunto:", guidedTopics);
  }

  function registerGuidedInteraction(label, answer) {
    chatHistory.push({ papel: "usuario", texto: label });
    chatHistory.push({ papel: "assistente", texto: answer });

    while (chatHistory.length > 10) {
      chatHistory.shift();
    }
  }

  async function answerGuidedChoice(choice) {
    const loading = appendMessage("Digitando...", "loading");
    await delay(estimateResponseDelay(choice.answer || ""));
    loading.remove();
    appendMessage(choice.answer, "bot");
    registerGuidedInteraction(choice.label, choice.answer);
    appendChoiceGroup("Posso ajudar com mais alguma coisa?", [
      { label: "Voltar", action: "main-menu" },
      { label: "Digitar pergunta", action: "free-text" },
    ]);
  }

  function handleGuidedChoice(choice) {
    if (isSending) return;

    if (choice.action === "free-text") {
      appendMessage("Quero digitar uma pergunta", "user");
      appendMessage("Claro. Digite sua dúvida no campo abaixo que eu respondo.", "bot");
      setFreeTextEnabled(true);
      input.focus();
      return;
    }

    if (choice.action === "main-menu") {
      appendMessage("Voltar", "user");
      setFreeTextEnabled(false);
      appendMainTopics();
      return;
    }

    setFreeTextEnabled(false);
    appendMessage(choice.label, "user");

    if (Array.isArray(choice.children) && choice.children.length > 0) {
      appendChoiceGroup("Escolha uma opção:", [
        ...choice.children,
        { label: "Voltar", action: "main-menu" },
      ]);
      return;
    }

    if (choice.answer) {
      answerGuidedChoice(choice);
    }
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
    if (!hasConsent) {
      throw new Error("Confirme sua maioridade e aceite os termos antes de usar o chat.");
    }

    const token = await ensureCsrfToken();
    if (!token) {
      throw new Error("Não foi possível preparar a segurança do chat. Recarregue a página.");
    }

    const response = await fetch(backendRoute("/ai/chat"), {
      method: "POST",
      credentials: "include",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-Token": token,
      },
      body: JSON.stringify({
        mensagem: message,
        historico: chatHistory.slice(-8),
        autorizar_ia: true,
        confirmar_maioridade: true,
        versao_consentimento: consentVersion,
      }),
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.erro || "Não foi possível falar com a IA agora.");
    }

    const answer = data.resposta || "Não consegui montar uma resposta agora.";
    chatHistory.push({ papel: "usuario", texto: message });
    chatHistory.push({ papel: "assistente", texto: answer });

    while (chatHistory.length > 10) {
      chatHistory.shift();
    }

    return answer;
  }

  toggle?.addEventListener("click", () => setOpen(true));
  closeButton?.addEventListener("click", () => setOpen(false));
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && chatbot.classList.contains("is-open")) setOpen(false);
  });
  ageConfirmation?.addEventListener("change", updateConsentButton);
  termsConfirmation?.addEventListener("change", updateConsentButton);
  consentButton?.addEventListener("click", () => {
    if (!ageConfirmation.checked || !termsConfirmation.checked) return;

    hasConsent = true;
    try {
      window.localStorage.setItem(consentStorageKey, "accepted");
    } catch (error) {
      // The backend still validates consent for every request.
    }
    applyConsentState();
  });
  setFreeTextEnabled(false);
  applyConsentState();

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
      appendMessage(error.message || "Não foi possível responder agora.", "error");
    } finally {
      setSending(false);
      input.focus();
    }
  });
});
