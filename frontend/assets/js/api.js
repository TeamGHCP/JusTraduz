const JusApi = {
  baseUrl: "../backend/public/index.php",

  route(path) {
    return `${this.baseUrl}?rota=${encodeURIComponent(path)}`;
  },

  authAction(path) {
    return this.route(path);
  }
};
