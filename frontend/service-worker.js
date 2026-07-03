const CACHE_VERSION = "justraduz-pwa-2026-07-03-cleanup-v1";
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const OFFLINE_URL = "offline.html";
const PUBLIC_PAGE_FALLBACKS = new Set([
  "/index.php",
  "/login.html",
  "/cadastro.html",
  "/recuperar-senha.html",
  "/contato.php",
  "/termos.php",
  "/privacidade.php",
  "/403.php",
  "/404.php",
  "/500.php"
]);

const STATIC_ASSETS = [
  "offline.html",
  "index.php",
  "login.html",
  "cadastro.html",
  "recuperar-senha.html",
  "contato.php",
  "termos.php",
  "privacidade.php",
  "403.php",
  "404.php",
  "500.php",
  "assets/css/style.css",
  "assets/css/style-home-bundle.min.css",
  "assets/css/base.css",
  "assets/css/layout.css",
  "assets/css/components.css",
  "assets/css/home.css",
  "assets/css/contato.css",
  "assets/css/termos.css",
  "assets/css/planos.css",
  "assets/css/pagamento-plano.css",
  "assets/css/pagamento-confirmado.css",
  "assets/css/errors.css",
  "assets/css/dashboard.css",
  "assets/css/auth.css",
  "assets/css/auth-novo.css",
  "assets/css/responsive.css",
  "assets/css/chatbot.css",
  "assets/css/chatbot.min.css",
  "assets/css/cookie-consent.css",
  "assets/css/cookie-consent.min.css",
  "assets/css/accessibility.min.css",
  "assets/js/main.js",
  "assets/js/main-home-bundle.min.js",
  "assets/js/modules/opening.js",
  "assets/js/modules/navigation.js",
  "assets/js/modules/helpers.js",
  "assets/js/modules/scroll-reveal.js",
  "assets/js/modules/marquee.js",
  "assets/js/modules/feature-flow.js",
  "assets/js/modules/tabs.js",
  "assets/js/modules/phone-demo.js",
  "assets/js/modules/security.js",
  "assets/js/auth.js",
  "assets/js/auth-novo.js",
  "assets/js/chatbot.js",
  "assets/js/chatbot.min.js",
  "assets/js/cookie-consent.js",
  "assets/js/cookie-consent.min.js",
  "assets/js/vlibras-init.js",
  "assets/js/accessibility.min.js",
  "assets/js/pwa.js",
  "assets/js/pwa.min.js",
  "assets/img/app-mark.png",
  "assets/img/apple-touch-icon.png",
  "assets/img/app-icon-192.png",
  "assets/img/app-icon-512.png",
  "assets/img/app-icon-maskable-192.png",
  "assets/img/app-icon-maskable-512.png",
  "assets/img/pwa-screenshot-mobile.png",
  "assets/img/pwa-screenshot-wide.png",
  "assets/img/logo.png",
  "assets/img/logo-header.png",
  "assets/img/chat-bot-logo-small.png",
  "assets/img/depoimentos/thumbs/ana-ribeiro.jpg",
  "assets/img/depoimentos/thumbs/bruno-martins.jpg",
  "assets/img/depoimentos/thumbs/carolina-lima.jpg",
  "assets/img/depoimentos/thumbs/diego-souza.jpg",
  "assets/img/depoimentos/thumbs/elisa-nogueira.jpg",
  "assets/img/depoimentos/thumbs/felipe-azevedo.jpg",
  "assets/img/depoimentos/thumbs/gabriela-rocha.jpg",
  "assets/img/depoimentos/thumbs/helena-duarte.jpg",
  "assets/img/depoimentos/thumbs/igor-almeida.jpg",
  "assets/img/phone-boot-leaf.png",
  "site.webmanifest"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll(STATIC_ASSETS.map((asset) => new Request(asset, { cache: "reload" }))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    Promise.all([
      self.registration.navigationPreload ? self.registration.navigationPreload.enable() : Promise.resolve(),
      caches.keys().then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith("justraduz-pwa-") && !key.startsWith(CACHE_VERSION))
          .map((key) => caches.delete(key))
      ))
    ]).then(() => self.clients.claim())
  );
});

self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

self.addEventListener("fetch", (event) => {
  const request = event.request;

  if (request.method !== "GET") {
    return;
  }

  const url = new URL(request.url);

  if (url.origin !== self.location.origin) {
    return;
  }

  if (url.pathname.includes("/backend/")) {
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(networkFirstPage(event));
    return;
  }

  if (isStaticAsset(url)) {
    event.respondWith(staleWhileRevalidate(request));
  }
});

function isStaticAsset(url) {
  return /\.(?:css|js|png|jpg|jpeg|webp|ico|svg|woff2?)$/i.test(url.pathname)
    || url.pathname.endsWith("/site.webmanifest");
}

async function networkFirstPage(event) {
  try {
    const preload = await event.preloadResponse;
    const response = preload || await fetchWithTimeout(event.request, 5500);
    return response;
  } catch (error) {
    const cache = await caches.open(STATIC_CACHE);
    const fallback = await publicPageFallback(event.request, cache);
    return fallback || await cache.match(OFFLINE_URL);
  }
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(STATIC_CACHE);
  const cacheKey = normalizeStaticRequest(request);
  const cached = await cache.match(cacheKey);
  const network = fetch(request)
    .then((response) => {
      if (response && response.ok) {
        cache.put(cacheKey, response.clone());
      }
      return response;
    })
    .catch(() => cached);

  return cached || network;
}

function normalizeStaticRequest(request) {
  const url = new URL(request.url);
  // Keep cache-busting query strings. A file requested as style.css?v=new
  // must not receive the cached response from style.css?v=old.
  return new Request(url.href, { credentials: "same-origin" });
}

function fetchWithTimeout(request, timeout) {
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error("network-timeout")), timeout);

    fetch(request).then((response) => {
      clearTimeout(timer);
      resolve(response);
    }).catch((error) => {
      clearTimeout(timer);
      reject(error);
    });
  });
}

async function publicPageFallback(request, cache) {
  const url = new URL(request.url);
  let path = url.pathname.replace(self.registration.scope ? new URL(self.registration.scope).pathname : "", "/");

  if (path === "/" || path === "") {
    path = "/index.php";
  }

  // Normalize extension-less routing (e.g. /contato -> /contato.php)
  if (!path.includes(".") && path !== "/") {
    path += ".php";
  }

  if (!PUBLIC_PAGE_FALLBACKS.has(path)) {
    return null;
  }

  return await cache.match(path.slice(1));
}
