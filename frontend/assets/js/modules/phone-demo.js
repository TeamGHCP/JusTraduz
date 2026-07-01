document.addEventListener('DOMContentLoaded', () => {
document.querySelectorAll("[data-phone-demo]").forEach((phone) => {
    const cards = Array.from(phone.querySelectorAll("[data-phone-open]"));
    const sheets = Array.from(phone.querySelectorAll("[data-phone-sheet]"));
    const backdrop = phone.querySelector("[data-sheet-backdrop]");
    const toast = phone.querySelector("[data-phone-toast]");
    const confidenceNumber = phone.querySelector("[data-confidence-number]");
    const confidenceRing = phone.querySelector(".phone-confidence-ring");
    let lastTrigger = null;
    let confidenceAnimated = false;

    const closeSheets = ({ restoreFocus = false, clearActive = true } = {}) => {
      sheets.forEach((sheet) => {
        sheet.classList.remove("show");
        sheet.setAttribute("aria-hidden", "true");
      });
      backdrop?.classList.remove("show");
      backdrop?.setAttribute("aria-hidden", "true");

      cards.forEach((card) => {
        card.setAttribute("aria-expanded", "false");
        if (clearActive) card.classList.remove("active");
      });

      if (restoreFocus && lastTrigger) lastTrigger.focus({ preventScroll: true });
    };

    const openSheet = (name, trigger) => {
      closeSheets({ clearActive: true });
      const sheet = phone.querySelector(`[data-phone-sheet="${name}"]`);
      if (!sheet) return;

      lastTrigger = trigger;
      sheet.classList.add("show");
      sheet.setAttribute("aria-hidden", "false");
      backdrop?.classList.add("show");
      backdrop?.setAttribute("aria-hidden", "false");

      trigger.setAttribute("aria-expanded", "true");
      if (name !== "request") {
        trigger.classList.add("active");
      }

      window.setTimeout(() => sheet.querySelector("[data-sheet-close]")?.focus({ preventScroll: true }), 420);
    };

    cards.forEach((trigger) => {
      trigger.addEventListener("click", () => {
        const target = trigger.dataset.phoneOpen;
        openSheet(target, trigger);
        if (target === "request") toast?.classList.add("show");
      });
    });

    phone.querySelectorAll("[data-sheet-close]").forEach((button) => {
      button.addEventListener("click", () => closeSheets({ restoreFocus: true }));
    });

    backdrop?.addEventListener("click", () => closeSheets({ restoreFocus: true }));
    phone.querySelector("[data-toast-close]")?.addEventListener("click", () => toast?.classList.remove("show"));
    phone.querySelector("[data-sheet-home]")?.addEventListener("click", () => {
      closeSheets({ clearActive: true });
      toast?.classList.remove("show");
    });

    phone.addEventListener("keydown", (event) => {
      if (event.key === "Escape") closeSheets({ restoreFocus: true });
    });

    const animateConfidence = () => {
      if (!confidenceNumber || confidenceAnimated) return;
      confidenceAnimated = true;
      confidenceNumber.textContent = "0%";
      confidenceRing?.style.setProperty("--confidence-progress", "0%");

      if (prefersReducedMotion) {
        confidenceNumber.textContent = "92%";
        confidenceRing?.style.setProperty("--confidence-progress", "92%");
        return;
      }

      const duration = 3800;
      const start = performance.now();
      const update = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 2);
        const value = 92 * eased;
        confidenceNumber.textContent = `${Math.round(value)}%`;
        confidenceRing?.style.setProperty("--confidence-progress", `${value}%`);
        if (progress < 1) window.requestAnimationFrame(update);
      };
      window.requestAnimationFrame(update);
    };

    const phoneWrap = phone.closest(".hero-phone-wrap") || phone;
    if ("IntersectionObserver" in window) {
      const phoneObserver = new IntersectionObserver((entries, observer) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        const start = () => {
          window.setTimeout(animateConfidence, prefersReducedMotion ? 0 : 2800);
          observer.disconnect();
        };
        if (openingLoader && !document.body.classList.contains("is-opening-complete")) {
          window.addEventListener("justraduz:opening-complete", start, { once: true });
        } else {
          start();
        }
      }, { threshold: .35 });
      phoneObserver.observe(phoneWrap);
    } else {
      animateConfidence();
    }
  });
});
