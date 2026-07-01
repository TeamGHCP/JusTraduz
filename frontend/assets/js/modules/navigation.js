(function() {
  const init = () => {
const header = document.querySelector("[data-site-header]");
  const toggle = document.querySelector("[data-nav-toggle]");

  if (header) {
    let headerScrollFrame = 0;
    const syncHeaderDivider = () => {
      headerScrollFrame = 0;
      header.classList.toggle("is-scrolled", window.scrollY > 8);
    };

    syncHeaderDivider();
    window.addEventListener("scroll", () => {
      if (!headerScrollFrame) {
        headerScrollFrame = window.requestAnimationFrame(syncHeaderDivider);
      }
    }, { passive: true });
  }

  if (header && toggle) {
    const menuQuery = window.matchMedia("(max-width: 980px)");
    const setMenuOpen = (isOpen) => {
      header.classList.toggle("is-open", isOpen);
      toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      toggle.setAttribute("aria-label", isOpen ? "Fechar menu" : "Abrir menu");
    };

    toggle.setAttribute("aria-expanded", "false");
    toggle.addEventListener("click", () => {
      setMenuOpen(!header.classList.contains("is-open"));
    });

    header.querySelectorAll(".nav-links a, .nav-actions a").forEach((link) => {
      link.addEventListener("click", () => setMenuOpen(false));
    });

    document.addEventListener("click", (event) => {
      if (!header.classList.contains("is-open") || header.contains(event.target)) return;
      setMenuOpen(false);
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && header.classList.contains("is-open")) {
        setMenuOpen(false);
        toggle.focus();
      }
    });

    const syncMenuMode = (event) => {
      if (!event.matches) setMenuOpen(false);
    };

    if (typeof menuQuery.addEventListener === "function") {
      menuQuery.addEventListener("change", syncMenuMode);
    } else if (typeof menuQuery.addListener === "function") {
      menuQuery.addListener(syncMenuMode);
    }
  }
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
