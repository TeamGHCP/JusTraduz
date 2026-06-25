(function () {
  const storageKey = 'justraduz-theme';

  function canUsePreferences() {
    return !!(window.JusTraduzCookieConsent && window.JusTraduzCookieConsent.allowed('preferences'));
  }

  function preferredTheme() {
    if (canUsePreferences()) {
      try {
        const stored = localStorage.getItem(storageKey);
        if (stored === 'dark' || stored === 'light') return stored;
      } catch (error) {
        // Theme still follows the system preference when storage is unavailable.
      }
    }
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

  function setTheme(theme) {
    if (canUsePreferences()) {
      try {
        localStorage.setItem(storageKey, theme);
      } catch (error) {
        // Theme change remains active for the current page.
      }
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
        setTheme(theme);
      });
    });
  });

  window.addEventListener('justraduz:cookie-consent-changed', () => {
    applyTheme(preferredTheme());
  });
})();
