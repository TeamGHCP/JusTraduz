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
    const buildCardSet = (hidden = false) => originalCards.map((card) => {
      const clone = card.cloneNode(true);

      if (hidden) {
        clone.setAttribute("aria-hidden", "true");
      } else {
        clone.removeAttribute("aria-hidden");
      }

      clone.classList.remove("is-active");
      return clone;
    });

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
      track.style.transition = animate ? "transform .9s cubic-bezier(.16, 1, .3, 1)" : "none";
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

      if (isPaused) {
        return;
      }

      timer = window.setTimeout(() => {
        goTo(1);
      }, 3000);
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

    window.addEventListener("resize", () => {
      moveTo(activeIndex, false);
    });

    moveTo(activeIndex, false);
    scheduleNext();
  });
});
