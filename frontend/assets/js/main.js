document.addEventListener("DOMContentLoaded", () => {
  const openingLoader = document.querySelector("[data-opening-loader]");

  if (openingLoader) {
    const revealHero = () => {
      document.querySelectorAll(".home-hero .reveal-on-scroll").forEach((element) => {
        element.classList.add("is-visible");
      });
    };

    if (document.documentElement.classList.contains("skip-cinematic-opening")) {
      document.body.classList.remove("has-opening-loader");
      document.body.classList.add("is-opening-complete");
      openingLoader.remove();
      revealHero();
      window.dispatchEvent(new CustomEvent("justraduz:opening-complete"));
    } else {
      let loaderRemoved = false;
      let openingRevealStarted = false;

      const beginOpeningReveal = () => {
        if (openingRevealStarted) return;
        openingRevealStarted = true;
        document.body.classList.remove("has-opening-loader");
        document.body.classList.add("is-opening-revealing");
        revealHero();
      };

      const removeOpeningLoader = () => {
        if (loaderRemoved) return;
        loaderRemoved = true;
        beginOpeningReveal();
        openingLoader.remove();
        document.body.classList.remove("is-opening-revealing");
        document.body.classList.add("is-opening-complete");
        window.dispatchEvent(new CustomEvent("justraduz:opening-complete"));
      };

      openingLoader.addEventListener("animationstart", (event) => {
        if (event.target === openingLoader && event.animationName === "jt-cinematic-exit") {
          beginOpeningReveal();
        }
      });
      openingLoader.addEventListener("animationend", (event) => {
        if (event.target === openingLoader) removeOpeningLoader();
      });
      window.setTimeout(beginOpeningReveal, 5550);
      window.setTimeout(removeOpeningLoader, 7800);
    }
  }

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

  const syncFeedbackMarqueeState = () => {
    document.querySelectorAll("[data-feedback-marquee]").forEach((marquee) => {
      marquee.classList.toggle("is-paused", document.hidden);
    });
  };

  document.addEventListener("visibilitychange", syncFeedbackMarqueeState);
  syncFeedbackMarqueeState();

  document.querySelectorAll("[data-home-feature-flow]").forEach((flow) => {
    const panels = Array.from(flow.querySelectorAll("[data-flow-panel]"));
    const timeline = flow.querySelector("[data-flow-progress-timeline]");
    const timelineSteps = Array.from(flow.querySelectorAll("[data-timeline-step]"));
    const timelineChevrons = timeline ? Array.from(timeline.querySelectorAll(".home-flow-timeline-chevron")) : [];
    const desktopQuery = window.matchMedia("(min-width: 981px)");

    if (panels.length === 0) {
      return;
    }

    let activeIndex = 0;
    let currentProgress = 0;
    let targetProgress = 0;
    let frameRequested = false;
    let smoothFrameRequested = false;
    let isFlowVisible = false;
    let geometryDirty = true;
    let exitTimer = 0;

    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

    const getLineProgressForStep = (index) => {
      if (panels.length <= 1) {
        return 1;
      }

      return index / (panels.length - 1);
    };

    const getScrollProgressForStep = (index) => index / panels.length;

    const renderTimelineProgress = (progress) => {
      currentProgress = clamp(progress, 0, 1);
      const percent = `${(currentProgress * 100).toFixed(2)}%`;
      const scale = currentProgress.toFixed(4);

      flow.style.setProperty("--flow-scroll-progress", currentProgress.toFixed(4));
      timeline?.style.setProperty("--timeline-progress", percent);
      timeline?.style.setProperty("--timeline-progress-scale", scale);
    };

    const animateTimelineProgress = () => {
      smoothFrameRequested = false;

      const distance = targetProgress - currentProgress;

      if (Math.abs(distance) < 0.0015) {
        renderTimelineProgress(targetProgress);
        updateTimelineSteps();
        return;
      }

      renderTimelineProgress(currentProgress + (distance * 0.18));
      updateTimelineSteps();

      smoothFrameRequested = true;
      window.requestAnimationFrame(animateTimelineProgress);
    };

    const setTimelineProgress = (progress, options = {}) => {
      targetProgress = clamp(progress, 0, 1);

      if (options.instant || prefersReducedMotion) {
        renderTimelineProgress(targetProgress);
        updateTimelineSteps();
        return;
      }

      if (!smoothFrameRequested) {
        smoothFrameRequested = true;
        window.requestAnimationFrame(animateTimelineProgress);
      }
    };

    const updateTimelineGeometry = () => {
      geometryDirty = false;

      if (!timeline || timelineSteps.length < 2) {
        return;
      }

      const firstMarker = timelineSteps[0].querySelector(".home-flow-step-icon");
      const lastMarker = timelineSteps[timelineSteps.length - 1].querySelector(".home-flow-step-icon");

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
        const stepProgress = getLineProgressForStep(stepIndex);
        const isComplete = stepProgress <= currentProgress + 0.002;
        const isDiscovered = stepProgress <= currentProgress + 0.045;

        step.classList.toggle("is-active", isActive);
        step.classList.toggle("is-complete", isComplete);
        step.classList.toggle("is-discovered", isDiscovered);
        step.setAttribute("aria-current", isActive ? "step" : "false");
      });

      timelineChevrons.forEach((chevron, chevronIndex) => {
        const nextStepProgress = getLineProgressForStep(chevronIndex + 1);
        const isComplete = nextStepProgress <= currentProgress + 0.002;

        chevron.classList.toggle("is-complete", isComplete);
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
      const revealOffset = Math.min(window.innerHeight * 0.76, window.innerHeight - 150);

      if (scrollable <= 0) {
        return 0;
      }

      return clamp((revealOffset - rect.top) / (scrollable + revealOffset), 0, 1);
    };

    const getInlineScrollProgress = () => {
      const rect = flow.getBoundingClientRect();
      const start = window.innerHeight * 0.82;
      const end = Math.max(window.innerHeight * 0.26, rect.height * 0.46);
      const distance = start + rect.height - end;

      if (distance <= 0) {
        return rect.top <= start ? 1 : 0;
      }

      return clamp((start - rect.top) / distance, 0, 1);
    };

    const getActiveIndexFromProgress = (progress) => {
      let nextIndex = 0;

      for (let index = 0; index < panels.length; index += 1) {
        if (progress + 0.002 >= getLineProgressForStep(index)) {
          nextIndex = index;
        }
      }

      return nextIndex;
    };

    const syncFlowToScroll = () => {
      frameRequested = false;

      if (geometryDirty) {
        updateTimelineGeometry();
      }

      const flowTop = flow.getBoundingClientRect().top;
      const startRevealAt = Math.min(window.innerHeight * 0.76, window.innerHeight - 150);
      const hasStarted = desktopQuery.matches ? flowTop <= startRevealAt : flowTop <= window.innerHeight * 0.86;
      flow.classList.toggle("is-flow-started", hasStarted);

      if (!hasStarted) {
        setTimelineProgress(0);
        activateFeature(0, { force: true });
        return;
      }

      const progress = desktopQuery.matches ? getScrollProgress() : getInlineScrollProgress();
      const lineTravelEnd = (panels.length - 1) / panels.length;
      const lineProgress = desktopQuery.matches ? clamp(progress / lineTravelEnd, 0, 1) : progress;
      const index = getActiveIndexFromProgress(lineProgress);

      setTimelineProgress(lineProgress);
      activateFeature(index);
    };

    const requestSync = () => {
      if (frameRequested || !isFlowVisible) {
        return;
      }

      frameRequested = true;
      window.requestAnimationFrame(syncFlowToScroll);
    };

    timelineSteps.forEach((step, index) => {
      step.addEventListener("click", () => {
        if (!desktopQuery.matches) {
          const sectionTop = window.scrollY + flow.getBoundingClientRect().top;
          const targetProgress = getLineProgressForStep(index);
          const start = window.innerHeight * 0.82;
          const end = Math.max(window.innerHeight * 0.26, flow.getBoundingClientRect().height * 0.46);
          const distance = start + flow.offsetHeight - end;

          window.scrollTo({
            top: sectionTop + (distance * targetProgress) - start,
            behavior: prefersReducedMotion ? "auto" : "smooth",
          });
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
          isFlowVisible = entry.isIntersecting;
          flow.classList.toggle("is-in-view", isFlowVisible);

          if (isFlowVisible) {
            requestSync();
          }
        });
      }, {
        rootMargin: "-14% 0px -14% 0px",
        threshold: 0,
      });

      flowObserver.observe(flow);
    } else {
      isFlowVisible = true;
      flow.classList.add("is-in-view");
    }

    window.addEventListener("scroll", requestSync, { passive: true });
    window.addEventListener("resize", () => {
      geometryDirty = true;
      requestSync();
    });

    if (typeof desktopQuery.addEventListener === "function") {
      desktopQuery.addEventListener("change", () => {
        geometryDirty = true;
        requestSync();
      });
    } else if (typeof desktopQuery.addListener === "function") {
      desktopQuery.addListener(() => {
        geometryDirty = true;
        requestSync();
      });
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

    const startTypewriter = () => {
      window.setTimeout(() => {
        isDeleting = true;
        tick();
      }, waitTime + 450);
    };

    if (openingLoader) {
      window.addEventListener("justraduz:opening-complete", startTypewriter, { once: true });
    } else {
      startTypewriter();
    }
  });

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

  document.querySelectorAll("[data-ai-document-insights]").forEach((section) => {
    const cards = Array.from(section.querySelectorAll("[data-insight-card]"));
    const stackedQuery = window.matchMedia("(max-width: 1120px)");
    let lineFrame = 0;
    let resizeTimer = 0;
    let settleTimer = 0;

    const updateLines = () => {
      lineFrame = 0;
      const svg = section.querySelector(".ai-connection-lines");

      if (!svg || stackedQuery.matches) {
        section.classList.remove("is-lines-ready");
        return;
      }

      const svgRect = svg.getBoundingClientRect();

      if (svgRect.width <= 0 || svgRect.height <= 0) {
        section.classList.remove("is-lines-ready");
        return;
      }

      svg.setAttribute("viewBox", `0 0 ${svgRect.width.toFixed(2)} ${svgRect.height.toFixed(2)}`);

      ["risk", "deadline", "renewal"].forEach((target) => {
        const clause = section.querySelector(`[data-clause="${target}"]`);
        const card = section.querySelector(`[data-insight-target="${target}"]`);
        const line = section.querySelector(`[data-line="${target}"]`);

        if (!clause || !card || !line) {
          return;
        }

        const clauseRect = clause.getBoundingClientRect();
        const cardRect = card.getBoundingClientRect();
        const clauseDotClearance = 10;
        const startX = clauseRect.right + clauseDotClearance - svgRect.left;
        const startY = clauseRect.top + (clauseRect.height / 2) - svgRect.top;
        const endX = cardRect.left - svgRect.left - 1;
        const endY = cardRect.top + (cardRect.height / 2) - svgRect.top;
        const curve = Math.max(44, Math.min(130, (endX - startX) * 0.46));

        line.setAttribute("d", `M ${startX.toFixed(1)} ${startY.toFixed(1)} C ${(startX + curve).toFixed(1)} ${startY.toFixed(1)} ${(endX - curve).toFixed(1)} ${endY.toFixed(1)} ${endX.toFixed(1)} ${endY.toFixed(1)}`);
      });

      section.classList.add("is-lines-ready");
    };

    const requestLineUpdate = () => {
      if (lineFrame) {
        return;
      }

      lineFrame = window.requestAnimationFrame(() => {
        lineFrame = window.requestAnimationFrame(updateLines);
      });
    };

    const requestLineUpdateSettled = () => {
      requestLineUpdate();
      window.clearTimeout(settleTimer);
      settleTimer = window.setTimeout(requestLineUpdate, 260);
    };

    const requestLineUpdateDebounced = () => {
      window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(requestLineUpdateSettled, 140);
    };

    const clearActive = () => {
      section.removeAttribute("data-active-insight");
      cards.forEach((card) => card.classList.remove("is-active"));
    };

    const revealSection = () => {
      section.classList.add("is-visible");
      section.querySelectorAll(".reveal-on-scroll").forEach((element) => {
        element.classList.add("is-visible");
      });
      requestLineUpdateSettled();
    };

    cards.forEach((card) => {
      const target = card.dataset.insightTarget;

      if (!target) {
        return;
      }

      card.addEventListener("mouseenter", () => {
        section.dataset.activeInsight = target;
        card.classList.add("is-active");
      });

      card.addEventListener("mouseleave", clearActive);
      card.addEventListener("focusin", () => {
        section.dataset.activeInsight = target;
        card.classList.add("is-active");
      });
      card.addEventListener("focusout", clearActive);
    });

    if (prefersReducedMotion) {
      revealSection();
      return;
    }

    if ("IntersectionObserver" in window) {
      const insightObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) {
            return;
          }

          revealSection();
          observer.unobserve(section);
        });
      }, {
        rootMargin: "0px 0px -120px 0px",
        threshold: 0.18,
      });

      insightObserver.observe(section);
    } else {
      revealSection();
    }

    window.addEventListener("resize", requestLineUpdateDebounced, { passive: true });
    window.addEventListener("load", requestLineUpdateSettled, { once: true });

    if (document.fonts && typeof document.fonts.ready?.then === "function") {
      document.fonts.ready.then(requestLineUpdateSettled).catch(() => {});
    }

    section.querySelectorAll("img").forEach((image) => {
      if (image.complete) {
        return;
      }

      image.addEventListener("load", requestLineUpdateSettled, { once: true });
      image.addEventListener("error", requestLineUpdateSettled, { once: true });
    });

    if (typeof stackedQuery.addEventListener === "function") {
      stackedQuery.addEventListener("change", requestLineUpdateDebounced);
    } else if (typeof stackedQuery.addListener === "function") {
      stackedQuery.addListener(requestLineUpdateDebounced);
    }

    requestLineUpdateDebounced();
  });

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
