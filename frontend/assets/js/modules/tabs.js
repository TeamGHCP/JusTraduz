(function() {
  const init = () => {
document.querySelectorAll("[data-flow-steps]").forEach((flow) => {
    if (flow.closest("[hidden]")) {
      return;
    }

    const steps = Array.from(flow.querySelectorAll("[data-flow-step]"));
    const panels = Array.from(flow.querySelectorAll("[data-flow-panel]"));

    if (steps.length === 0 || panels.length === 0) {
      return;
    }

    const activateStep = (target) => {
      steps.forEach((step) => {
        const isActive = step.dataset.flowStep === target;
        step.classList.toggle("is-active", isActive);
        step.setAttribute("aria-pressed", String(isActive));
      });

      panels.forEach((panel) => {
        const isActive = panel.dataset.flowPanel === target;
        panel.classList.toggle("is-active", isActive);
        panel.hidden = !isActive;
      });
    };

    steps.forEach((step) => {
      step.addEventListener("click", () => {
        activateStep(step.dataset.flowStep);
      });
    });

    activateStep(steps.find((step) => step.classList.contains("is-active"))?.dataset.flowStep || steps[0].dataset.flowStep);
  });
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
