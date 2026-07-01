document.addEventListener('DOMContentLoaded', () => {
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
});
