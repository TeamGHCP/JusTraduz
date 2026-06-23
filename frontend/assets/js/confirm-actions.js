document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-confirm]").forEach((element) => {
    const eventName = element.tagName === "FORM" ? "submit" : "click";

    element.addEventListener(eventName, (event) => {
      const message = element.getAttribute("data-confirm") || "Confirmar esta ação?";
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
});
