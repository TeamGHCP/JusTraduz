const CACHE_VERSION = "justraduz-pwa-2026-06-21-03";
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const OFFLINE_URL = "offline.html";
const PUBLIC_PAGE_FALLBACKS = new Set([
  "/index.html",
  "/login.html",
  "/cadastro.html",
  "/recuperar-senha.html",
  "/contato.html",
  "/termos.html",
  "/privacidade.html",
  "/404.html",
  "/500.html"
]);

const STATIC_ASSETS = [
  "offline.html",
  "index.html",
  "login.html",
  "cadastro.html",
  "recuperar-senha.html",
  "contato.html",
  "termos.html",
  "privacidade.html",
  "404.html",
  "500.html",
  "assets/css/style.css",
  "assets/css/base.css",
  "assets/css/layout.css",
  "assets/css/components.css",
  "assets/css/pages.css",
  "assets/css/dashboard.css",
  "assets/css/auth.css",
  "assets/css/auth-novo.css",
  "assets/css/responsive.css",
  "assets/css/chatbot.css",
  "assets/js/main.js",
  "assets/js/auth.js",
  "assets/js/auth-novo.js",
  "assets/js/chatbot.js",
  "assets/js/vlibras-init.js",
  "assets/js/pwa.js",
  "assets/img/app-mark.png",
  "assets/img/apple-touch-icon.png",
  "assets/img/app-icon-192.png",
  "assets/img/app-icon-512.png",
  "assets/img/app-icon-maskable-192.png",
  "assets/img/app-icon-maskable-512.png",
  "assets/img/pwa-screenshot-mobile.png",
  "assets/img/pwa-screenshot-wide.png",
  "assets/img/logo.png",
  "assets/img/chat-bot-logo.png",
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
  // Keep cache-busting query strings. A file requested as style.css?v=7
  // must not receive the cached response from style.css?v=6.
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
    path = "/index.html";
  }

  if (!PUBLIC_PAGE_FALLBACKS.has(path)) {
    return null;
  }

  return await cache.match(path.slice(1));
}
