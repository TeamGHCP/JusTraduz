(function () {
  const init = () => {
    const shell = document.querySelector(".app-shell");
    const main = document.querySelector(".app-main");

    if (!shell || !main) {
      return;
    }

    document.body.classList.add("app-interactive-ready");

    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const interactiveSelector = "a, button, input, select, textarea, summary, label, [role='button']";

    const normalize = (value) => String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();

    const enhanceReveal = () => {
      const elements = Array.from(main.querySelectorAll([
        ".command-card",
        ".client-next-step",
        ".stat-card",
        ".lawyer-summary-card",
        ".task-summary-card",
        ".dash-section",
        ".case-card-rich",
        ".professional-case-card",
        ".quick-action-card",
        ".admin-alert-tile",
        ".admin-risk-card",
        ".admin-chart-card",
        ".review-item",
        ".audit-feed-item",
        ".health-item",
      ].join(",")));

      elements.forEach((element, index) => {
        element.classList.add("app-reveal");
        element.style.setProperty("--app-reveal-delay", `${Math.min(index * 26, 180)}ms`);
      });

      if (reduceMotion || !("IntersectionObserver" in window)) {
        elements.forEach((element) => element.classList.add("is-visible"));
        return;
      }

      const observer = new IntersectionObserver((entries, revealObserver) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) {
            return;
          }

          entry.target.classList.add("is-visible");
          revealObserver.unobserve(entry.target);
        });
      }, {
        rootMargin: "0px 0px -70px 0px",
        threshold: 0.06,
      });

      elements.forEach((element) => observer.observe(element));
    };

    const enhanceCardClicks = () => {
      main.querySelectorAll(".quick-action-card, .command-card-secondary").forEach((card) => {
        if (card.dataset.appClickReady === "true") {
          return;
        }

        const primaryLink = card.querySelector("a[href]");
        if (!primaryLink) {
          return;
        }

        if (card.querySelectorAll(interactiveSelector).length > 2) {
          return;
        }

        card.dataset.appClickReady = "true";
        card.classList.add("app-click-card");
        card.tabIndex = card.tabIndex < 0 ? 0 : card.tabIndex;
        card.setAttribute("role", "link");
        card.setAttribute("aria-label", primaryLink.textContent.trim() || "Abrir");

        card.addEventListener("click", (event) => {
          if (event.target.closest(interactiveSelector)) {
            return;
          }

          primaryLink.click();
        });

        card.addEventListener("keydown", (event) => {
          if (event.key !== "Enter" && event.key !== " ") {
            return;
          }

          if (event.target.closest(interactiveSelector) && event.target !== card) {
            return;
          }

          event.preventDefault();
          primaryLink.click();
        });
      });
    };

    const getSectionTitle = (section) => {
      const heading = section.querySelector(".dash-section-title h2, h2, h3");
      return heading ? heading.textContent.trim().replace(/\s+/g, " ") : "itens";
    };

    const listConfigs = [
      [".quick-actions-grid", ".quick-action-card", "Filtrar atalhos"],
      [".case-board", ".case-card-rich", "Filtrar casos"],
      [".professional-card-grid", ".professional-case-card", "Filtrar casos"],
      [".lawyer-directory-grid", ".lawyer-directory-card", "Filtrar advogados"],
      [".admin-review-list", ".review-item", "Filtrar lista"],
      [".admin-audit-feed", ".audit-feed-item", "Filtrar auditoria"],
      [".professional-list", ".professional-list-item", "Filtrar itens"],
      [".task-list", ".task-row", "Filtrar tarefas"],
      [".health-grid", ".health-item", "Filtrar integrações"],
    ];

    const enhanceLocalFilters = () => {
      listConfigs.forEach(([containerSelector, itemSelector, placeholder]) => {
        main.querySelectorAll(containerSelector).forEach((container) => {
          if (container.dataset.appFilterReady === "true") {
            return;
          }

          const items = Array.from(container.querySelectorAll(`:scope > ${itemSelector}`));
          if (items.length < 4) {
            return;
          }

          container.dataset.appFilterReady = "true";

          const section = container.closest(".dash-section, .card, main") || main;
          const tools = document.createElement("div");
          const input = document.createElement("input");
          const count = document.createElement("span");
          const title = getSectionTitle(section);

          tools.className = "app-list-tools";
          input.className = "input app-list-search";
          input.type = "search";
          input.placeholder = placeholder;
          input.setAttribute("aria-label", `${placeholder} em ${title}`);
          input.setAttribute("autocomplete", "off");
          count.className = "app-list-count";
          count.setAttribute("aria-live", "polite");

          tools.appendChild(input);
          tools.appendChild(count);
          container.insertAdjacentElement("beforebegin", tools);

          const empty = document.createElement("div");
          empty.className = "card empty-state app-filter-empty";
          empty.hidden = true;
          empty.innerHTML = "<p>Nenhum item visível para esta busca.</p>";
          container.insertAdjacentElement("afterend", empty);

          const update = () => {
            const query = normalize(input.value);
            let visible = 0;

            items.forEach((item) => {
              const matches = !query || normalize(item.textContent).includes(query);
              item.classList.toggle("is-app-filter-hidden", !matches);
              if (matches) {
                visible += 1;
              }
            });

            count.textContent = `${visible}/${items.length}`;
            empty.hidden = visible > 0;
          };

          input.addEventListener("input", update);
          update();
        });
      });
    };

    const enhanceTables = () => {
      main.querySelectorAll(".table-wrap").forEach((wrap) => {
        if (wrap.dataset.appTableReady === "true") {
          return;
        }

        wrap.dataset.appTableReady = "true";
        wrap.tabIndex = wrap.tabIndex < 0 ? 0 : wrap.tabIndex;
        wrap.setAttribute("role", "region");
        wrap.setAttribute("aria-label", wrap.getAttribute("aria-label") || "Tabela com rolagem horizontal");

        const hint = document.createElement("span");
        hint.className = "app-table-hint";
        hint.textContent = "Arraste para ver mais colunas";
        wrap.insertAdjacentElement("beforebegin", hint);

        const table = wrap.querySelector("table");
        const rows = table ? Array.from(table.querySelectorAll("tbody tr")) : [];

        if (rows.length >= 6 && !wrap.previousElementSibling?.classList.contains("app-list-tools")) {
          const tools = document.createElement("div");
          const input = document.createElement("input");
          const count = document.createElement("span");
          const empty = document.createElement("div");

          tools.className = "app-list-tools app-table-tools";
          input.className = "input app-list-search";
          input.type = "search";
          input.placeholder = "Filtrar tabela";
          input.setAttribute("aria-label", "Filtrar tabela");
          input.setAttribute("autocomplete", "off");
          count.className = "app-list-count";
          count.setAttribute("aria-live", "polite");
          empty.className = "card empty-state app-filter-empty";
          empty.hidden = true;
          empty.innerHTML = "<p>Nenhuma linha visível para esta busca.</p>";

          tools.appendChild(input);
          tools.appendChild(count);
          hint.insertAdjacentElement("beforebegin", tools);
          wrap.insertAdjacentElement("afterend", empty);

          const update = () => {
            const query = normalize(input.value);
            let visible = 0;

            rows.forEach((row) => {
              const matches = !query || normalize(row.textContent).includes(query);
              row.classList.toggle("is-app-filter-hidden", !matches);
              if (matches) {
                visible += 1;
              }
            });

            count.textContent = `${visible}/${rows.length}`;
            empty.hidden = visible > 0;
          };

          input.addEventListener("input", update);
          update();
        }
      });
    };

    const enhanceShortcuts = () => {
      document.addEventListener("keydown", (event) => {
        if (event.key !== "/" || event.ctrlKey || event.metaKey || event.altKey) {
          return;
        }

        if (event.target.closest("input, textarea, select, [contenteditable='true']")) {
          return;
        }

        const target = main.querySelector(".app-list-search, #q, input[type='search']");
        if (!target) {
          return;
        }

        event.preventDefault();
        target.focus();
        target.select?.();
      });
    };

    enhanceReveal();
    enhanceCardClicks();
    enhanceLocalFilters();
    enhanceTables();
    enhanceShortcuts();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
