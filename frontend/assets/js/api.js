const JusApi = {
  baseUrl: (() => {
    const marker = "/frontend/";
    const index = window.location.pathname.indexOf(marker);
    const basePath = index >= 0 ? window.location.pathname.slice(0, index) : "";
    return `${basePath}/backend/public/index.php`;
  })(),

  route(path) {
    return `${this.baseUrl}?rota=${encodeURIComponent(path)}`;
  },

  authAction(path) {
    return this.route(path);
  }
};
