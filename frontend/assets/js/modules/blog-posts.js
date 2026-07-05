(function() {
  const init = () => {
    document.querySelectorAll("[data-blog-copy]").forEach((button) => {
      button.addEventListener("click", async () => {
        const source = button.closest("[data-copy-source]");
        const clone = source ? source.cloneNode(true) : null;
        clone?.querySelectorAll("button").forEach((item) => item.remove());
        const text = clone ? clone.innerText.trim() : (button.dataset.copyValue || "");
        const original = button.textContent;

        try {
          await navigator.clipboard.writeText(text);
          button.textContent = "Copiado";
          button.classList.add("is-copied");
          window.setTimeout(() => {
            button.textContent = original;
            button.classList.remove("is-copied");
          }, 1600);
        } catch (error) {
          button.textContent = "Selecione e copie";
          window.setTimeout(() => {
            button.textContent = original;
          }, 1600);
        }
      });
    });

    document.querySelectorAll("[data-blog-checklist]").forEach((checklist) => {
      const checks = Array.from(checklist.querySelectorAll("input[type='checkbox']"));
      const count = checklist.querySelector("[data-blog-check-count]");
      const bar = checklist.querySelector("[data-blog-check-progress]");

      const update = () => {
        const done = checks.filter((check) => check.checked).length;
        const total = checks.length || 1;
        checklist.classList.toggle("is-complete", done === total);

        if (count) {
          count.textContent = `${done}/${total}`;
        }

        if (bar) {
          bar.style.width = `${Math.round((done / total) * 100)}%`;
        }
      };

      checks.forEach((check) => check.addEventListener("change", update));
      update();
    });

    document.querySelectorAll("[data-blog-filter]").forEach((input) => {
      const target = document.querySelector(input.dataset.blogFilter || "");
      const cards = target ? Array.from(target.querySelectorAll("[data-term-card]")) : [];

      input.addEventListener("input", () => {
        const query = input.value.trim().toLowerCase();

        cards.forEach((card) => {
          const text = card.innerText.toLowerCase();
          card.hidden = query !== "" && !text.includes(query);
        });
      });
    });
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
