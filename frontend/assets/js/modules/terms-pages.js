(function() {
  const init = () => {
    const page = document.querySelector(".terms-page-enhanced");

    if (!page) {
      return;
    }

    const aside = page.querySelector(".terms-modern-aside");
    const links = aside
      ? Array.from(aside.querySelectorAll("[data-terms-nav]"))
      : [];

    const sections = links
      .map((link) => {
        const targetId = link.getAttribute("href") || "";
        return targetId.startsWith("#")
          ? document.getElementById(targetId.slice(1))
          : null;
      })
      .filter(Boolean);

    if (!aside || links.length === 0 || sections.length === 0) {
      return;
    }

    aside.classList.add("is-enhanced");
    page.classList.add("terms-interactive-ready");

    if (!aside.querySelector(".terms-progress")) {
      const progress = document.createElement("div");
      progress.className = "terms-progress";
      progress.setAttribute("aria-hidden", "true");
      progress.innerHTML = "<span></span>";
      const title = aside.querySelector("h2");
      title.insertAdjacentElement("afterend", progress);
    }

    if (!aside.querySelector(".terms-nav-track")) {
      const track = document.createElement("div");
      track.className = "terms-nav-track";
      links[0].insertAdjacentElement("beforebegin", track);
      links.forEach((link) => track.appendChild(link));
    }

    sections.forEach((section, index) => {
      section.classList.add("terms-observed-section");
      section.style.setProperty("--terms-section-delay", `${Math.min(index * 70, 280)}ms`);
    });

    const progressFill = aside.querySelector(".terms-progress span");
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    let activeId = "";
    let ticking = false;

    const setActiveSection = (id) => {
      if (!id || id === activeId) {
        return;
      }

      activeId = id;

      links.forEach((link) => {
        const isActive = link.getAttribute("href") === `#${id}`;
        link.classList.toggle("is-active", isActive);

        if (isActive) {
          link.setAttribute("aria-current", "true");
        } else {
          link.removeAttribute("aria-current");
        }

        if (isActive) {
          link.scrollIntoView({
            behavior: prefersReducedMotion ? "auto" : "smooth",
            block: "nearest",
            inline: "center",
          });
        }
      });

      sections.forEach((section) => {
        section.classList.toggle("is-current", section.id === id);
      });
    };

    const findCurrentSection = () => {
      const offset = Math.max(96, Math.round(window.innerHeight * 0.28));
      let current = sections[0];

      sections.forEach((section) => {
        if (section.getBoundingClientRect().top <= offset) {
          current = section;
        }
      });

      return current;
    };

    const updateProgress = () => {
      ticking = false;

      const first = sections[0];
      const last = sections[sections.length - 1];
      const start = first.offsetTop;
      const end = Math.max(last.offsetTop + last.offsetHeight - window.innerHeight, start + 1);
      const progress = Math.min(1, Math.max(0, (window.scrollY - start) / (end - start)));

      if (progressFill) {
        progressFill.style.width = `${Math.round(progress * 100)}%`;
      }

      setActiveSection(findCurrentSection().id);
    };

    const requestUpdate = () => {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(updateProgress);
      }
    };

    if ("IntersectionObserver" in window) {
      const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) {
            return;
          }

          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        });
      }, {
        rootMargin: "0px 0px -90px 0px",
        threshold: 0.08,
      });

      sections.forEach((section) => revealObserver.observe(section));
    } else {
      sections.forEach((section) => section.classList.add("is-visible"));
    }

    links.forEach((link) => {
      link.addEventListener("click", () => {
        const href = link.getAttribute("href") || "";
        if (href.startsWith("#")) {
          setActiveSection(href.slice(1));
        }
      });
    });

    window.addEventListener("scroll", requestUpdate, { passive: true });
    window.addEventListener("resize", requestUpdate);
    updateProgress();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
