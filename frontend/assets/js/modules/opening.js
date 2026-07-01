document.addEventListener('DOMContentLoaded', () => {
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
});
