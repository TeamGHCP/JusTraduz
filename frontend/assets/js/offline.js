document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-reload-page]").forEach((button) => {
    button.addEventListener("click", () => {
      window.location.reload();
    });
  });
});
