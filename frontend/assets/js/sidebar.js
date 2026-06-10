(function () {
  const storageKey = 'justraduz_sidebar_collapsed';
  const modulesStorageKey = 'justraduz_sidebar_modules';
  const mobileQuery = window.matchMedia('(max-width: 980px)');

  function readCollapsedState() {
    try {
      return localStorage.getItem(storageKey) === 'true';
    } catch (error) {
      return false;
    }
  }

  function saveCollapsedState(collapsed) {
    try {
      localStorage.setItem(storageKey, collapsed ? 'true' : 'false');
    } catch (error) {
      // The sidebar still works when browser storage is unavailable.
    }
  }

  function readModuleStates() {
    try {
      const stored = JSON.parse(localStorage.getItem(modulesStorageKey) || '{}');
      return stored && typeof stored === 'object' ? stored : {};
    } catch (error) {
      return {};
    }
  }

  function saveModuleStates(states) {
    try {
      localStorage.setItem(modulesStorageKey, JSON.stringify(states));
    } catch (error) {
      // Module toggles still work when browser storage is unavailable.
    }
  }

  document.querySelectorAll('[data-sidebar]').forEach((sidebar) => {
    const shell = sidebar.closest('.app-shell');
    const brandToggle = sidebar.querySelector('[data-sidebar-brand-toggle]');
    const collapseButton = sidebar.querySelector('[data-sidebar-toggle]');
    const mobileButton = shell && shell.querySelector('[data-sidebar-mobile-toggle]');
    const backdrop = shell && shell.querySelector('[data-sidebar-backdrop]');

    if (!shell || !brandToggle || !collapseButton || !mobileButton || !backdrop) return;

    let collapsed = readCollapsedState();
    const moduleStates = readModuleStates();
    const modules = Array.from(sidebar.querySelectorAll('[data-sidebar-module]'));

    function updateDesktopState(shouldCollapse, persist) {
      collapsed = shouldCollapse;
      shell.classList.toggle('is-sidebar-collapsed', collapsed);
      sidebar.classList.toggle('is-collapsed', collapsed);

      if (!mobileQuery.matches) {
        brandToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        brandToggle.setAttribute('aria-label', collapsed ? 'Expandir menu lateral' : 'Logo JusTraduz');
        brandToggle.setAttribute('title', collapsed ? 'Expandir menu lateral' : '');
        collapseButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      }

      if (persist) saveCollapsedState(collapsed);
    }

    function updateModuleState(module, isOpen, persist) {
      const button = module.querySelector('[data-sidebar-module-toggle]');
      const moduleKey = module.dataset.moduleKey;

      module.classList.toggle('is-open', isOpen);
      if (button) button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

      if (persist && moduleKey) {
        moduleStates[moduleKey] = isOpen;
        saveModuleStates(moduleStates);
      }
    }

    function updateMobileState(isOpen) {
      shell.classList.toggle('is-sidebar-mobile-open', isOpen);
      document.body.classList.toggle('sidebar-mobile-open', isOpen);
      mobileButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      brandToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      collapseButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

      if (isOpen) collapseButton.focus();
    }

    modules.forEach((module) => {
      const button = module.querySelector('[data-sidebar-module-toggle]');
      const moduleKey = module.dataset.moduleKey;
      const hasActiveItem = module.classList.contains('has-active-item');
      const storedState = moduleKey ? moduleStates[moduleKey] : undefined;
      const shouldOpen = hasActiveItem || storedState === true;

      updateModuleState(module, shouldOpen, false);

      if (!button) return;

      button.addEventListener('click', () => {
        if (collapsed && !mobileQuery.matches) {
          updateDesktopState(false, true);
          updateModuleState(module, true, true);
          return;
        }

        updateModuleState(module, !module.classList.contains('is-open'), true);
      });
    });

    brandToggle.addEventListener('click', () => {
      if (!collapsed || mobileQuery.matches) return;
      updateDesktopState(false, true);
    });

    collapseButton.addEventListener('click', () => {
      if (mobileQuery.matches) {
        updateMobileState(false);
        mobileButton.focus();
        return;
      }

      updateDesktopState(true, true);
    });

    mobileButton.addEventListener('click', () => updateMobileState(true));
    backdrop.addEventListener('click', () => updateMobileState(false));
    sidebar.querySelectorAll('.sidebar-submenu-link').forEach((link) => {
      link.addEventListener('click', () => {
        if (mobileQuery.matches) updateMobileState(false);
      });
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && shell.classList.contains('is-sidebar-mobile-open')) {
        updateMobileState(false);
        mobileButton.focus();
      }
    });

    mobileQuery.addEventListener('change', (event) => {
      updateMobileState(false);
      updateDesktopState(collapsed, false);

      if (!event.matches) mobileButton.setAttribute('aria-expanded', 'false');
    });

    updateDesktopState(collapsed, false);
    if (mobileQuery.matches) updateMobileState(false);

    requestAnimationFrame(() => shell.classList.add('sidebar-ready'));
  });
})();
