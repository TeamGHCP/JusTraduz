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
    ".home-hero-copy",
    ".hero-product-stage",
    ".page-section .section-head",
    "#recursos .feature-card",
    ".home-detail-grid > *",
    "#fluxo .step-card",
    "#depoimentos .testimonial-marquee",
    "#depoimentos .testimonial-controls",
    "#seguranca .form-actions",
  ];
  const revealElements = Array.from(new Set(
    revealSelectors.flatMap((selector) => Array.from(document.querySelectorAll(selector)))
  ));
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (revealElements.length > 0) {
    revealElements.forEach((element, index) => {
      element.classList.add("reveal-on-scroll");
      element.style.setProperty("--reveal-delay", `${(index % 3) * 45}ms`);
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

  document.querySelectorAll("[data-mockup-tilt]").forEach((stage) => {
    const mockup = stage.querySelector(".hero-product-mockup");

    if (!mockup || prefersReducedMotion) {
      return;
    }

    let frame = null;

    const resetTilt = () => {
      mockup.style.setProperty("--tilt-x", "0deg");
      mockup.style.setProperty("--tilt-y", "0deg");
      mockup.style.setProperty("--shift-x", "0px");
      mockup.style.setProperty("--shift-y", "0px");
    };

    stage.addEventListener("pointermove", (event) => {
      window.cancelAnimationFrame(frame);

      frame = window.requestAnimationFrame(() => {
        const rect = stage.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;

        mockup.style.setProperty("--tilt-x", `${x * -7}deg`);
        mockup.style.setProperty("--tilt-y", `${y * 5}deg`);
        mockup.style.setProperty("--shift-x", `${x * 10}px`);
        mockup.style.setProperty("--shift-y", `${y * 8}px`);
      });
    });

    stage.addEventListener("pointerleave", resetTilt);
    stage.addEventListener("pointercancel", resetTilt);
  });

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

  document.querySelectorAll("[data-testimonial-carousel]").forEach((carousel) => {
    const track = carousel.querySelector(".testimonial-track");
    const section = carousel.closest(".testimonials-section");
    const prevButton = section?.querySelector("[data-testimonial-prev]");
    const nextButton = section?.querySelector("[data-testimonial-next]");
    const originalCards = Array.from(carousel.querySelectorAll(".testimonial-card"))
      .filter((card) => card.getAttribute("aria-hidden") !== "true");

    if (!track || originalCards.length === 0) {
      return;
    }

    const originalCount = originalCards.length;
    carousel.setAttribute("role", "region");
    carousel.setAttribute("aria-label", "Depoimentos de usuários");
    const prepareImages = (card) => {
      card.querySelectorAll("img").forEach((image) => {
        image.loading = "lazy";
        image.decoding = "async";
      });
    };
    const buildCardSet = (hidden = false) => originalCards.map((card) => {
      const clone = card.cloneNode(true);

      clone.classList.remove("is-active");
      prepareImages(clone);

      if (hidden) {
        clone.setAttribute("aria-hidden", "true");
      } else {
        clone.removeAttribute("aria-hidden");
      }

      return clone;
    });

    originalCards.forEach(prepareImages);

    track.replaceChildren(
      ...buildCardSet(true),
      ...buildCardSet(false),
      ...buildCardSet(true)
    );

    const cards = Array.from(track.querySelectorAll(".testimonial-card"));

    let activeIndex = originalCount + Math.floor(originalCount / 2);
    let timer = null;
    let isPaused = false;
    let isDragging = false;
    let isCarouselVisible = false;
    let scrollResumeTimer = null;
    let dragStartX = 0;
    let dragStartOffset = 0;
    let dragOffset = 0;
    let pendingResetIndex = null;

    const getGap = () => {
      const styles = window.getComputedStyle(track);
      return Number.parseFloat(styles.columnGap || styles.gap) || 0;
    };

    const setActiveCard = (index) => {
      cards.forEach((card) => card.classList.remove("is-active"));
      cards[index]?.classList.add("is-active");
    };

    const getCardStep = () => cards[0].offsetWidth + getGap();

    const getOffsetForIndex = (index) => {
      const cardWidth = cards[0].offsetWidth;
      return (carousel.clientWidth - cardWidth) / 2 - index * getCardStep();
    };

    const normalizeIndex = (index) => {
      if (index >= originalCount * 2) {
        pendingResetIndex = index - originalCount;
      } else if (index < originalCount) {
        pendingResetIndex = index + originalCount;
      } else {
        pendingResetIndex = null;
      }

      return Math.max(0, Math.min(cards.length - 1, index));
    };

    const getNearestIndex = (offset) => {
      const cardWidth = cards[0].offsetWidth;
      const centeredOffset = (carousel.clientWidth - cardWidth) / 2;
      const index = Math.round((centeredOffset - offset) / getCardStep());

      return normalizeIndex(index);
    };

    const moveTo = (index, animate = true) => {
      track.style.transition = animate ? "transform .5s cubic-bezier(.16, 1, .3, 1)" : "none";
      track.style.transform = `translateX(${getOffsetForIndex(index)}px)`;
      setActiveCard(index);
    };

    const goTo = (direction) => {
      window.clearTimeout(timer);
      activeIndex = normalizeIndex(activeIndex + direction);

      moveTo(activeIndex);
    };

    const scheduleNext = () => {
      window.clearTimeout(timer);

      if (isPaused || !isCarouselVisible) {
        return;
      }

      timer = window.setTimeout(() => {
        goTo(1);
      }, 3000);
    };

    const pauseWhileScrolling = () => {
      if (!isCarouselVisible) {
        return;
      }

      window.clearTimeout(timer);
      window.clearTimeout(scrollResumeTimer);
      scrollResumeTimer = window.setTimeout(scheduleNext, 220);
    };

    track.addEventListener("transitionend", (event) => {
      if (event.propertyName !== "transform") {
        return;
      }

      if (pendingResetIndex !== null) {
        activeIndex = pendingResetIndex;
        pendingResetIndex = null;
        moveTo(activeIndex, false);
      }

      scheduleNext();
    });

    prevButton?.addEventListener("click", () => {
      goTo(-1);
    });

    nextButton?.addEventListener("click", () => {
      goTo(1);
    });

    carousel.addEventListener("pointerdown", (event) => {
      if (event.button !== undefined && event.button !== 0) {
        return;
      }

      isDragging = true;
      isPaused = true;
      dragStartX = event.clientX;
      dragStartOffset = getOffsetForIndex(activeIndex);
      dragOffset = dragStartOffset;
      pendingResetIndex = null;
      window.clearTimeout(timer);
      track.style.transition = "none";
      carousel.classList.add("is-dragging");
      carousel.setPointerCapture?.(event.pointerId);
    });

    carousel.addEventListener("pointermove", (event) => {
      if (!isDragging) {
        return;
      }

      dragOffset = dragStartOffset + event.clientX - dragStartX;
      if (Math.abs(event.clientX - dragStartX) > 4) {
        event.preventDefault();
      }

      track.style.transform = `translateX(${dragOffset}px)`;
    });

    const finishDrag = (event) => {
      if (!isDragging) {
        return;
      }

      isDragging = false;
      isPaused = false;
      carousel.classList.remove("is-dragging");
      carousel.releasePointerCapture?.(event.pointerId);
      activeIndex = getNearestIndex(dragOffset);
      moveTo(activeIndex);
      scheduleNext();
    };

    carousel.addEventListener("pointerup", finishDrag);
    carousel.addEventListener("pointercancel", finishDrag);
    carousel.addEventListener("lostpointercapture", finishDrag);

    carousel.addEventListener("mouseenter", () => {
      if (isDragging) {
        return;
      }

      isPaused = true;
      window.clearTimeout(timer);
    });

    carousel.addEventListener("mouseleave", () => {
      if (isDragging) {
        return;
      }

      isPaused = false;
      scheduleNext();
    });

    carousel.addEventListener("focusin", () => {
      isPaused = true;
      window.clearTimeout(timer);
    });

    carousel.addEventListener("focusout", (event) => {
      if (carousel.contains(event.relatedTarget)) return;
      isPaused = false;
      scheduleNext();
    });

    window.addEventListener("resize", () => {
      moveTo(activeIndex, false);
    });

    moveTo(activeIndex, false);

    if ("IntersectionObserver" in window) {
      const carouselObserver = new IntersectionObserver((entries) => {
        isCarouselVisible = entries.some((entry) => entry.isIntersecting);

        if (isCarouselVisible) {
          carousel.classList.add("is-ready");
          scheduleNext();
        } else {
          carousel.classList.remove("is-ready");
          window.clearTimeout(timer);
          window.clearTimeout(scrollResumeTimer);
        }
      }, {
        threshold: 0.18,
      });

      carouselObserver.observe(carousel);
    } else {
      isCarouselVisible = true;
      carousel.classList.add("is-ready");
      scheduleNext();
    }

    window.addEventListener("scroll", pauseWhileScrolling, { passive: true });
  });
});
