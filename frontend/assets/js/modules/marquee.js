(function() {
  const init = () => {
    document.querySelectorAll("[data-feedback-marquee] .feedback-track").forEach((track) => {
      const sourceGroup = track.querySelector(".feedback-group");

      if (!sourceGroup || track.dataset.feedbackReady === "true") {
        return;
      }

      const cards = Array.from(sourceGroup.querySelectorAll(".feedback-card"));
      const column = track.closest(".feedback-column");

      if (cards.length === 0 || !column) {
        return;
      }

      const prepareImages = (root) => {
        root.querySelectorAll("img").forEach((image) => {
          image.loading = "lazy";
          image.decoding = "async";
        });
      };

      const clearRevealState = (root) => {
        root.querySelectorAll(".feedback-card").forEach((card) => {
          card.classList.remove("reveal-on-scroll");
          card.classList.add("is-visible");
          card.removeAttribute("data-reveal");
          card.style.removeProperty("--reveal-delay");
        });
      };

      const buildGroup = () => {
        const group = document.createElement("div");
        group.className = "feedback-group";

        cards.forEach((card) => {
          const clone = card.cloneNode(true);
          group.appendChild(clone);
        });

        return group;
      };

      const fillGroup = (group) => {
        const columnHeight = column.getBoundingClientRect().height;

        while (group.getBoundingClientRect().height < columnHeight + 120 && group.children.length < 12) {
          cards.forEach((card) => {
            if (group.getBoundingClientRect().height >= columnHeight + 120 || group.children.length >= 12) {
              return;
            }

            group.appendChild(card.cloneNode(true));
          });
        }
      };

      const rebuild = () => {
        track.innerHTML = "";

        const primaryGroup = buildGroup();
        fillGroup(primaryGroup);
        prepareImages(primaryGroup);
        clearRevealState(primaryGroup);

        const duplicateGroup = primaryGroup.cloneNode(true);
        duplicateGroup.setAttribute("aria-hidden", "true");
        prepareImages(duplicateGroup);
        clearRevealState(duplicateGroup);

        track.append(primaryGroup, duplicateGroup);

        const styles = window.getComputedStyle(track);
        const gap = parseFloat(styles.rowGap || styles.gap || "0") || 0;
        track.style.setProperty("--feedback-shift", `-${primaryGroup.getBoundingClientRect().height + gap}px`);
      };

      rebuild();
      window.addEventListener("resize", rebuild, { passive: true });
      track.dataset.feedbackReady = "true";
    });

    const syncFeedbackMarqueeState = () => {
      document.querySelectorAll("[data-feedback-marquee]").forEach((marquee) => {
        marquee.classList.toggle("is-paused", document.hidden);
      });
    };

    document.addEventListener("visibilitychange", syncFeedbackMarqueeState);
    syncFeedbackMarqueeState();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
