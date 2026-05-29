document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-demo-filter]").forEach((button) => {
    button.addEventListener("click", () => {
      document.querySelectorAll("[data-demo-filter]").forEach((item) => item.classList.remove("active"));
      button.classList.add("active");
    });
  });
});
