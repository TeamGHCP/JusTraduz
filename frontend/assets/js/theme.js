(function () {
  const storageKey = 'justraduz-theme';

  function preferredTheme() {
    const stored = localStorage.getItem(storageKey);
    if (stored === 'dark' || stored === 'light') return stored;
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;
    document.querySelectorAll('[data-theme-toggle-button]').forEach((button) => {
      const isDark = theme === 'dark';
      button.classList.toggle('is-dark', isDark);
      button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      button.setAttribute('title', isDark ? 'Trocar para tema claro' : 'Trocar para tema escuro');
    });
  }

  function setTheme(theme, animated) {
    localStorage.setItem(storageKey, theme);

    if (animated && document.startViewTransition && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      document.startViewTransition(() => {
        applyTheme(theme);
      });
      return;
    }

    applyTheme(theme);
  }

  applyTheme(preferredTheme());

  document.addEventListener('DOMContentLoaded', () => {
    applyTheme(preferredTheme());
    requestAnimationFrame(() => {
      document.documentElement.classList.add('theme-ready');
    });

    document.querySelectorAll('[data-theme-toggle-button]').forEach((button) => {
      button.addEventListener('click', () => {
        const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        setTheme(theme, true);
      });
    });
  });
})();
