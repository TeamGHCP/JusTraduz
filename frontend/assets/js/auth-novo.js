const authPage = document.querySelector("#authPage");
const showCadastro = document.querySelector("#showCadastro");
const showLogin = document.querySelector("#showLogin");

document.addEventListener("click", function (event) {
  const link = event.target.closest("a[href]");

  if (!link || link.target || link.hasAttribute("download") || event.defaultPrevented) {
    return;
  }

  if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
    return;
  }

  try {
    const url = new URL(link.getAttribute("href"), window.location.href);
    const isSameOrigin = url.origin === window.location.origin;
    const isHomePage = /\/frontend\/(?:index\.html)?$/.test(url.pathname) || /\/frontend\/$/.test(url.pathname);

    if (isSameOrigin && isHomePage && (!url.hash || url.hash === "#top")) {
      window.sessionStorage.setItem("jtSkipOpeningLoader", "1");
    }
  } catch (e) {
    // ignore invalid hrefs
  }
});

if (authPage) {
  const replayAuthEntrance = function (panelSelector) {
    const sharedElements = Array.from(authPage.querySelectorAll(
      ".auth-line-field, .auth-line, .auth-slider .auth-art-inner"
    ));
    const activePanel = authPage.querySelector(panelSelector);
    const panelElements = activePanel ? Array.from(activePanel.querySelectorAll(
      ".auth-card, .auth-card > h1, .auth-card > .subtitle, .auth-card .auth-form, .auth-card .auth-divider, .auth-card .btn-google, .auth-card .auth-footer, .auth-card .auth-home-link"
    )) : [];
    const animatedElements = sharedElements.concat(panelElements);

    animatedElements.forEach(function (element) {
      element.style.animation = "none";
    });

    void authPage.offsetHeight;

    animatedElements.forEach(function (element) {
      element.style.animation = "";
    });
  };

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
      history.replaceState(null, "", "login.html?cadastro");
      replayAuthEntrance(".auth-cadastro-panel");
      const heading = authPage.querySelector(".auth-cadastro-panel h1");
      if (heading) {
        heading.tabIndex = -1;
        heading.focus();
      }
    });
  }

  if (showLogin) {
    showLogin.addEventListener("click", function (event) {
      event.preventDefault();
      authPage.classList.remove("cadastro-ativo");
      history.replaceState(null, "", "login.html");
      replayAuthEntrance(".auth-login-panel");
      const heading = authPage.querySelector(".auth-login-panel h1");
      if (heading) {
        heading.tabIndex = -1;
        heading.focus();
      }
    });
  }
}

document.querySelectorAll("[data-auth-fluid-particles]").forEach(function (canvas) {
  const context = canvas.getContext("2d", { alpha: true });
  const slider = canvas.closest(".auth-slider");
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

  if (!context || !slider) {
    return;
  }

  const particles = [];
  let width = 0;
  let height = 0;
  let animationFrame = 0;
  let lastTime = 0;

  const palette = {
    teal: "rgba(32, 214, 200, ",
    mint: "rgba(94, 234, 212, ",
    gold: "rgba(255, 241, 188, ",
    navy: "rgba(7, 27, 45, ",
  };

  const noise = function (x, y, t) {
    return (
      Math.sin(x * .006 + t * .0008) +
      Math.cos(y * .007 - t * .0006) +
      Math.sin((x + y) * .003 + t * .00035)
    ) / 3;
  };

  const resetParticle = function (particle) {
    particle.x = Math.random() * width;
    particle.y = Math.random() * height;
    particle.size = .7 + Math.random() * 1.8;
    particle.life = Math.random();
    particle.speed = .38 + Math.random() * 1.05;
    particle.tint = Math.random() > .82 ? palette.gold : (Math.random() > .48 ? palette.mint : palette.teal);
  };

  const resize = function () {
    const rect = slider.getBoundingClientRect();
    const scale = Math.min(window.devicePixelRatio || 1, 2);
    width = Math.max(1, Math.floor(rect.width));
    height = Math.max(1, Math.floor(rect.height));
    canvas.width = Math.floor(width * scale);
    canvas.height = Math.floor(height * scale);
    canvas.style.width = width + "px";
    canvas.style.height = height + "px";
    context.setTransform(scale, 0, 0, scale, 0, 0);

    const targetCount = Math.max(120, Math.min(360, Math.floor((width * height) / 3600)));
    particles.length = 0;
    for (let index = 0; index < targetCount; index += 1) {
      const particle = {};
      resetParticle(particle);
      particles.push(particle);
    }
  };

  const draw = function (time) {
    const delta = Math.min(32, time - lastTime || 16);
    lastTime = time;

    context.fillStyle = palette.navy + ".12)";
    context.fillRect(0, 0, width, height);

    context.lineWidth = 1;
    particles.forEach(function (particle) {
      const previousX = particle.x;
      const previousY = particle.y;
      const flow = noise(particle.x, particle.y, time) * Math.PI * 2.8;

      particle.x += Math.cos(flow) * particle.speed * delta * .06;
      particle.y += Math.sin(flow) * particle.speed * delta * .06;
      particle.life += delta * .0009;

      if (particle.x < -20 || particle.x > width + 20 || particle.y < -20 || particle.y > height + 20 || particle.life > 1) {
        resetParticle(particle);
      }

      const alpha = Math.sin(particle.life * Math.PI) * .34;
      context.strokeStyle = particle.tint + alpha + ")";
      context.beginPath();
      context.moveTo(previousX, previousY);
      context.lineTo(particle.x, particle.y);
      context.stroke();

      context.fillStyle = particle.tint + (alpha * .82) + ")";
      context.beginPath();
      context.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
      context.fill();
    });

    animationFrame = window.requestAnimationFrame(draw);
  };

  const start = function () {
    window.cancelAnimationFrame(animationFrame);
    context.clearRect(0, 0, width, height);
    if (!reduceMotion.matches) {
      animationFrame = window.requestAnimationFrame(draw);
    }
  };

  resize();
  start();
  window.addEventListener("resize", function () {
    resize();
    start();
  });

  if (typeof reduceMotion.addEventListener === "function") {
    reduceMotion.addEventListener("change", start);
  }
});
