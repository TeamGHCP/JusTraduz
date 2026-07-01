document.addEventListener('DOMContentLoaded', () => {
document.querySelectorAll("[data-current-year]").forEach((node) => {
    node.textContent = new Date().getFullYear();
  });
});
