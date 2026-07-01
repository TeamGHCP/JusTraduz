(function() {
  const init = () => {
const revealSelectors = [
    ".home-hero h1",
    ".home-hero-copy > p",
    ".home-hero .hero-actions",
    ".home-trust-row",
    ".hero-phone-wrap",
    ".page-section .section-head",
    ".home-flow-panel-copy",
    ".home-flow-system-preview",
    ".home-flow-feature-list li",
    ".home-flow-timeline",
    "#recursos .feature-card",
    ".home-flow-summary",
    ".ai-document-head",
    ".ai-document-mockup",
    ".ai-finding-card",
    ".home-feedback-copy",
    ".feedback-card",
    ".feedback-columns",
    "#depoimentos .testimonial-marquee",
    "#depoimentos .testimonial-controls",
    ".flow-preview",
    "#fluxo .flow-step",
    ".home-security-panel",
    ".home-security-tab",
    ".home-security-preview",
    "#seguranca .form-actions",
  ];
  const revealElements = Array.from(new Set(
    revealSelectors.flatMap((selector) => Array.from(document.querySelectorAll(selector)))
  ));
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (revealElements.length > 0) {
    revealElements.forEach((element, index) => {
      element.classList.add("reveal-on-scroll");
      const isHero = element.closest(".home-hero");
      const isFlowPanelCopy = element.matches(".home-flow-panel-copy");
      const isFlowPreview = element.matches(".home-flow-system-preview");
      const isFlowListItem = element.matches(".home-flow-feature-list li");
      const isAiDocument = element.matches(".ai-document-mockup");
      const isAiFinding = element.matches(".ai-finding-card");
      const isFeedback = element.matches(".feedback-card");
      const directions = ["up", "left", "right", "down", "zoom"];
      const direction = isFlowPanelCopy
        ? "left"
        : isFlowPreview
          ? "right"
          : isAiDocument
            ? "up"
            : isAiFinding
              ? "right"
              : isFlowListItem
                ? "left"
                : isFeedback
                  ? directions[index % directions.length]
                  : directions[index % 3];
      const aiFindingIndex = isAiFinding
        ? Array.from(element.parentElement.querySelectorAll(".ai-finding-card")).indexOf(element)
        : 0;
      const delay = isHero
        ? index * 160
        : isFlowListItem
          ? (index % 3) * 140
          : isAiFinding
            ? 180 + (aiFindingIndex * 150)
          : isFeedback
            ? (index % 6) * 95
            : (index % 5) * 120;

      element.dataset.reveal = direction;
      element.style.setProperty("--reveal-delay", `${delay}ms`);
    });

    if (prefersReducedMotion) {
      revealElements.forEach((element) => element.classList.add("is-visible"));
    } else if ("IntersectionObserver" in window) {
      const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) {
            return;
          }

          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        });
      }, {
        rootMargin: "0px 0px -70px 0px",
        threshold: 0.12,
      });

      revealElements.forEach((element) => revealObserver.observe(element));
    } else {
      revealElements.forEach((element) => element.classList.add("is-visible"));
    }
  }
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
