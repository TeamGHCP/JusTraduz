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
    "#depoimentos .testimonial-marquee",
    "#depoimentos .testimonial-controls",
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
    const cards = Array.from(flow.querySelectorAll(".home-feature-grid-interactive .feature-card"));
    const stepLabel = flow.querySelector("[data-flow-step-label]");
    const metric = flow.querySelector("[data-flow-metric]");
    const title = flow.querySelector("[data-flow-title]");
    const copy = flow.querySelector("[data-flow-copy]");
    const status = flow.querySelector("[data-flow-status]");
    const percent = flow.querySelector("[data-flow-percent]");
    const progress = flow.querySelector("[data-flow-progress]");
    const timeline = flow.querySelector("[data-flow-progress-timeline]");
    const timelineSteps = Array.from(flow.querySelectorAll("[data-timeline-step]"));

    if (cards.length === 0) {
      return;
    }

    const featureStates = [
      {
        step: "Etapa 01",
        metric: "Documento seguro",
        title: "Documento recebido",
        copy: "Upload protegido, consentimento claro e contexto inicial organizados no mesmo lugar.",
        status: "Arquivo protegido",
        percent: "25%",
        progress: 25,
      },
      {
        step: "Etapa 02",
        metric: "IA em leitura",
        title: "Clareza em segundos",
        copy: "A IA transforma termos difíceis em resumo simples, pontos de atenção e próximos passos.",
        status: "Resumo gerado",
        percent: "50%",
        progress: 50,
      },
      {
        step: "Etapa 03",
        metric: "Solicitação pronta",
        title: "Pedido com contexto",
        copy: "Sua dúvida vira uma solicitação estruturada para o profissional entender sem retrabalho.",
        status: "Atendimento conectado",
        percent: "75%",
        progress: 75,
      },
      {
        step: "Etapa 04",
        metric: "Tudo acompanhado",
        title: "Histórico vivo",
        copy: "Agenda, chat, atualizações e auditoria ficam conectados para você acompanhar cada etapa.",
        status: "Fluxo completo",
        percent: "100%",
        progress: 100,
      },
    ];

    let activeIndex = 0;
    let animationStarted = false;
    const animationTimers = [];

    const timelineProgressFor = (index) => {
      if (cards.length <= 1) {
        return 100;
      }

      return Math.round((index / (cards.length - 1)) * 100);
    };

    const activateFeature = (index) => {
      const card = cards[index];
      const state = featureStates[index] || {};

      if (!card) {
        return;
      }

      activeIndex = index;
      cards.forEach((item, itemIndex) => {
        const isActive = itemIndex === index;
        item.classList.toggle("is-active", isActive);
        item.setAttribute("aria-pressed", String(isActive));
      });

      timelineSteps.forEach((step, stepIndex) => {
        const isActive = stepIndex === index;
        const isComplete = stepIndex <= index;
        step.classList.toggle("is-active", isActive);
        step.classList.toggle("is-complete", isComplete);
        step.setAttribute("aria-current", isActive ? "step" : "false");
      });

      timeline?.style.setProperty("--timeline-progress", `${timelineProgressFor(index)}%`);
      if (stepLabel) stepLabel.textContent = state.step || `Etapa ${String(index + 1).padStart(2, "0")}`;
      if (metric) metric.textContent = state.metric || "";
      if (title) title.textContent = state.title || card.querySelector("h3")?.textContent || "";
      if (copy) copy.textContent = state.copy || card.querySelector("p")?.textContent || "";
      if (status) status.textContent = state.status || "";
      if (percent) percent.textContent = state.percent || "";
      if (progress) progress.style.width = `${state.progress || 0}%`;
    };

    const runFlowAnimation = () => {
      if (animationStarted) {
        return;
      }

      animationStarted = true;
      animationTimers.forEach((timer) => window.clearTimeout(timer));

      if (prefersReducedMotion) {
        activateFeature(cards.length - 1);
        return;
      }

      cards.forEach((_, index) => {
        const timer = window.setTimeout(() => {
          activateFeature(index);
        }, index * 780);

        animationTimers.push(timer);
      });
    };

    cards.forEach((card, index) => {
      card.tabIndex = 0;
      card.setAttribute("role", "button");
      card.setAttribute("aria-pressed", String(index === 0));

      card.addEventListener("mouseenter", () => {
        activateFeature(index);
      });

      card.addEventListener("click", () => {
        activateFeature(index);
      });

      card.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") {
          return;
        }

        event.preventDefault();
        activateFeature(index);
      });
    });

    timelineSteps.forEach((step, index) => {
      step.addEventListener("click", () => {
        activateFeature(index);
      });

      step.addEventListener("mouseenter", () => {
        activateFeature(index);
      });
    });

    activateFeature(0);

    if ("IntersectionObserver" in window) {
      const flowObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) {
            return;
          }

          runFlowAnimation();
          observer.unobserve(entry.target);
        });
      }, {
        rootMargin: "0px 0px -18% 0px",
        threshold: 0.32,
      });

      flowObserver.observe(flow);
    } else {
      runFlowAnimation();
    }
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
