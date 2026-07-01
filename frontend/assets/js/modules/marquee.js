(function() {
  const init = () => {
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
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
