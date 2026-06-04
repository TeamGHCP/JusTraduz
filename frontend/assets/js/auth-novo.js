const authPage = document.querySelector("#authPage");
const showCadastro = document.querySelector("#showCadastro");
const showLogin = document.querySelector("#showLogin");

if (authPage) {
  if (window.location.search.includes("cadastro")) {
    authPage.classList.add("cadastro-ativo");
  }

  setTimeout(function () {
    authPage.classList.remove("preload");
  }, 100);

  if (showCadastro) {
    showCadastro.addEventListener("click", function (event) {
      event.preventDefault();
      authPage.classList.add("cadastro-ativo");
      history.replaceState(null, "", "login-novo.html?cadastro");
    });
  }

  if (showLogin) {
    showLogin.addEventListener("click", function (event) {
      event.preventDefault();
      authPage.classList.remove("cadastro-ativo");
      history.replaceState(null, "", "login-novo.html");
    });
  }
}