document.addEventListener('DOMContentLoaded', () => {
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
document.querySelectorAll("[data-security-panel]").forEach((panel) => {
    const tabs = Array.from(panel.querySelectorAll("[data-security-tab]"));
    const preview = panel.querySelector(".home-security-preview");
    const kicker = panel.querySelector("[data-security-preview-kicker]");
    const title = panel.querySelector("[data-security-preview-title]");
    const text = panel.querySelector("[data-security-preview-text]");
    const checkOne = panel.querySelector("[data-security-preview-check-one]");
    const checkTwo = panel.querySelector("[data-security-preview-check-two]");
    const checkThree = panel.querySelector("[data-security-preview-check-three]");

    const content = {
      consentimento: {
        kicker: "Proteção ativa",
        title: "Consentimento antes do envio",
        text: "O usuário entende o uso da plataforma antes de enviar documentos, com regras visíveis e registro do aceite.",
        checks: ["Maioridade confirmada", "Termos aceitos", "Dados tratados com finalidade"],
      },
      auditoria: {
        kicker: "Rastro organizado",
        title: "Histórico pronto para conferência",
        text: "Solicitações, documentos e etapas relevantes ficam conectados para reduzir perda de contexto durante o atendimento.",
        checks: ["Eventos importantes", "Status do atendimento", "Contexto preservado"],
      },
      limites: {
        kicker: "Uso responsável",
        title: "IA como apoio, não substituição",
        text: "As respostas ajudam na compreensão inicial, mas a plataforma mantém claro quando a análise precisa de profissional habilitado.",
        checks: ["Avisos de limite", "Revisão humana", "Sem promessa de resultado"],
      },
    };

    const activate = (name) => {
      const selected = content[name] || content.consentimento;

      tabs.forEach((tab) => {
        const isActive = tab.dataset.securityTab === name;
        tab.classList.toggle("is-active", isActive);
        tab.setAttribute("aria-pressed", isActive ? "true" : "false");
      });

      if (kicker) kicker.textContent = selected.kicker;
      if (title) title.textContent = selected.title;
      if (text) text.textContent = selected.text;
      if (checkOne) checkOne.textContent = selected.checks[0];
      if (checkTwo) checkTwo.textContent = selected.checks[1];
      if (checkThree) checkThree.textContent = selected.checks[2];

      if (preview && !prefersReducedMotion) {
        preview.classList.remove("is-changing");
        void preview.offsetWidth;
        preview.classList.add("is-changing");
      }
    };

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => activate(tab.dataset.securityTab));
    });

    activate(tabs.find((tab) => tab.classList.contains("is-active"))?.dataset.securityTab || "consentimento");
  });
});
