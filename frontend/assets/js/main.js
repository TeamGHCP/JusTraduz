document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector("[data-site-header]");
  const toggle = document.querySelector("[data-nav-toggle]");

  if (header && toggle) {
    toggle.addEventListener("click", () => {
      header.classList.toggle("is-open");
    });
  }

  document.querySelectorAll("[data-current-year]").forEach((node) => {
    node.textContent = new Date().getFullYear();
  });

  const revealSelectors = [
    ".home-hero h1",
    ".home-hero-copy > p",
    ".home-hero .hero-actions",
    ".home-trust-row",
    ".hero-phone-wrap",
    ".page-section .section-head",
    ".home-flow-timeline",
    "#recursos .feature-card",
    ".home-flow-summary",
    ".home-detail-grid > *",
    ".home-feedback-copy",
    ".feedback-columns",
    ".flow-preview",
    "#fluxo .flow-step",
    "#seguranca .form-actions",
  ];
  const revealElements = Array.from(new Set(
    revealSelectors.flatMap((selector) => Array.from(document.querySelectorAll(selector)))
  ));
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  document.querySelectorAll("[data-feedback-marquee] .feedback-track").forEach((track) => {
    const group = track.querySelector(".feedback-group");

    if (!group || track.dataset.feedbackReady === "true") {
      return;
    }

    const clone = group.cloneNode(true);
    clone.setAttribute("aria-hidden", "true");
    clone.querySelectorAll("img").forEach((image) => {
      image.loading = "lazy";
      image.decoding = "async";
    });

    group.querySelectorAll("img").forEach((image) => {
      image.loading = "lazy";
      image.decoding = "async";
    });

    track.appendChild(clone);
    track.dataset.feedbackReady = "true";
  });

  document.querySelectorAll("[data-home-feature-flow]").forEach((flow) => {
    const panels = Array.from(flow.querySelectorAll("[data-flow-panel]"));
    const timeline = flow.querySelector("[data-flow-progress-timeline]");
    const timelineSteps = Array.from(flow.querySelectorAll("[data-timeline-step]"));
    const desktopQuery = window.matchMedia("(min-width: 861px)");

    if (panels.length === 0) {
      return;
    }

    let activeIndex = 0;
    let currentProgress = 0;
    let frameRequested = false;
    let exitTimer = 0;

    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

    const getLineProgressForStep = (index) => {
      if (panels.length <= 1) {
        return 1;
      }

      return index / (panels.length - 1);
    };

    const getScrollProgressForStep = (index) => index / panels.length;

    const setTimelineProgress = (progress) => {
      currentProgress = clamp(progress, 0, 1);
      const percent = `${(currentProgress * 100).toFixed(2)}%`;

      flow.style.setProperty("--flow-scroll-progress", currentProgress.toFixed(4));
      timeline?.style.setProperty("--timeline-progress", percent);
    };

    const updateTimelineGeometry = () => {
      if (!timeline || timelineSteps.length < 2) {
        return;
      }

      const firstMarker = timelineSteps[0].querySelector("strong");
      const lastMarker = timelineSteps[timelineSteps.length - 1].querySelector("strong");

      if (!firstMarker || !lastMarker) {
        return;
      }

      const timelineRect = timeline.getBoundingClientRect();
      const firstRect = firstMarker.getBoundingClientRect();
      const lastRect = lastMarker.getBoundingClientRect();

      if (timelineRect.width <= 0) {
        return;
      }

      const left = firstRect.left + (firstRect.width / 2) - timelineRect.left;
      const right = lastRect.left + (lastRect.width / 2) - timelineRect.left;
      const top = firstRect.top + (firstRect.height / 2) - timelineRect.top;

      timeline.style.setProperty("--timeline-line-left", `${Math.max(0, left)}px`);
      timeline.style.setProperty("--timeline-line-right", `${Math.min(timelineRect.width, right)}px`);
      timeline.style.setProperty("--timeline-line-top", `${Math.max(0, top)}px`);
    };

    const setPanelAccessibility = () => {
      const isDesktop = desktopQuery.matches;

      panels.forEach((panel, index) => {
        panel.setAttribute("aria-hidden", isDesktop && index !== activeIndex ? "true" : "false");
      });
    };

    const updateTimelineSteps = () => {
      timelineSteps.forEach((step, stepIndex) => {
        const isActive = stepIndex === activeIndex;
        const isComplete = getLineProgressForStep(stepIndex) <= currentProgress + 0.002;

        step.classList.toggle("is-active", isActive);
        step.classList.toggle("is-complete", isComplete);
        step.setAttribute("aria-current", isActive ? "step" : "false");
      });
    };

    const activateFeature = (index, options = {}) => {
      const nextIndex = clamp(index, 0, panels.length - 1);
      const force = Boolean(options.force);

      if (!force && nextIndex === activeIndex) {
        updateTimelineSteps();
        setPanelAccessibility();
        return;
      }

      const previousIndex = activeIndex;
      activeIndex = nextIndex;
      flow.dataset.flowDirection = activeIndex > previousIndex ? "forward" : "backward";

      window.clearTimeout(exitTimer);

      panels.forEach((panel, panelIndex) => {
        const isActive = panelIndex === activeIndex;
        const wasActive = panelIndex === previousIndex;

        panel.classList.toggle("is-active", isActive);

        if (!isActive && wasActive && !force && desktopQuery.matches && !prefersReducedMotion) {
          panel.classList.add("is-exiting");
        } else {
          panel.classList.remove("is-exiting");
        }
      });

      exitTimer = window.setTimeout(() => {
        panels.forEach((panel) => panel.classList.remove("is-exiting"));
      }, 520);

      updateTimelineSteps();
      setPanelAccessibility();
    };

    const getScrollProgress = () => {
      const rect = flow.getBoundingClientRect();
      const scrollable = flow.offsetHeight - window.innerHeight;

      if (scrollable <= 0) {
        return 0;
      }

      return clamp((rect.top * -1) / scrollable, 0, 1);
    };

    const syncFlowToScroll = () => {
      frameRequested = false;
      updateTimelineGeometry();

      if (!desktopQuery.matches) {
        flow.classList.add("is-flow-started");
        setTimelineProgress(0);
        activateFeature(0, { force: true });
        return;
      }

      const hasStarted = flow.getBoundingClientRect().top <= 84;
      flow.classList.toggle("is-flow-started", hasStarted);

      if (!hasStarted) {
        setTimelineProgress(0);
        activateFeature(0, { force: true });
        return;
      }

      const progress = getScrollProgress();
      const lineTravelEnd = (panels.length - 1) / panels.length;
      const lineProgress = clamp(progress / lineTravelEnd, 0, 1);
      const index = Math.min(panels.length - 1, Math.floor(progress * panels.length));

      setTimelineProgress(lineProgress);
      activateFeature(index);
    };

    const requestSync = () => {
      if (frameRequested) {
        return;
      }

      frameRequested = true;
      window.requestAnimationFrame(syncFlowToScroll);
    };

    timelineSteps.forEach((step, index) => {
      step.addEventListener("click", () => {
        if (!desktopQuery.matches) {
          activateFeature(index);
          return;
        }

        const maxScroll = flow.offsetHeight - window.innerHeight;
        const flowTop = window.scrollY + flow.getBoundingClientRect().top;

        window.scrollTo({
          top: flowTop + (maxScroll * getScrollProgressForStep(index)),
          behavior: prefersReducedMotion ? "auto" : "smooth",
        });
      });
    });

    if ("IntersectionObserver" in window) {
      const flowObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          flow.classList.toggle("is-in-view", entry.isIntersecting);

          if (entry.isIntersecting) {
            requestSync();
          }
        });
      }, {
        rootMargin: "-14% 0px -14% 0px",
        threshold: 0,
      });

      flowObserver.observe(flow);
    } else {
      flow.classList.add("is-in-view");
    }

    window.addEventListener("scroll", requestSync, { passive: true });
    window.addEventListener("resize", requestSync);

    if (typeof desktopQuery.addEventListener === "function") {
      desktopQuery.addEventListener("change", requestSync);
    } else if (typeof desktopQuery.addListener === "function") {
      desktopQuery.addListener(requestSync);
    }

    setTimelineProgress(0);
    activateFeature(0, { force: true });
    updateTimelineGeometry();
    requestSync();
  });

  document.querySelectorAll("[data-hero-typewriter]").forEach((typewriter) => {
    const textElement = typewriter.querySelector("[data-hero-typewriter-text]");
    const words = (typewriter.dataset.words || "")
      .split("|")
      .map((word) => word.trim())
      .filter(Boolean);

    if (!textElement || words.length === 0) {
      return;
    }

    textElement.textContent = words[0];

    if (prefersReducedMotion || words.length === 1) {
      return;
    }

    typewriter.classList.add("is-ready");

    const typeSpeed = 68;
    const deleteSpeed = 36;
    const waitTime = 1900;
    const fadeTime = 180;
    let wordIndex = 0;
    let charIndex = words[0].length;
    let isDeleting = false;

    const render = () => {
      textElement.textContent = words[wordIndex].slice(0, charIndex);
    };

    const tick = () => {
      if (isDeleting) {
        typewriter.classList.add("is-fading");

        if (charIndex > 0) {
          charIndex -= 1;
          render();
          window.setTimeout(tick, deleteSpeed);
          return;
        }

        wordIndex = (wordIndex + 1) % words.length;
        isDeleting = false;
        typewriter.classList.remove("is-fading");
        window.setTimeout(tick, 240);
        return;
      }

      typewriter.classList.remove("is-fading");

      if (charIndex < words[wordIndex].length) {
        charIndex += 1;
        render();
        window.setTimeout(tick, typeSpeed);
        return;
      }

      window.setTimeout(() => {
        typewriter.classList.add("is-fading");
        window.setTimeout(() => {
          isDeleting = true;
          tick();
        }, fadeTime);
      }, waitTime);
    };

    window.setTimeout(() => {
      isDeleting = true;
      tick();
    }, waitTime + 450);
  });

  if (revealElements.length > 0) {
    revealElements.forEach((element, index) => {
      element.classList.add("reveal-on-scroll");
      const isHero = element.closest(".home-hero");
      const delay = isHero ? index * 110 : (index % 4) * 55;
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

  document.querySelectorAll("[data-flow-steps]").forEach((flow) => {
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


});
