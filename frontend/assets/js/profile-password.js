document.addEventListener('DOMContentLoaded', () => {
  const modal = document.querySelector('[data-profile-password-modal]');
  const openButton = document.querySelector('[data-password-modal-open]');
  const closeButtons = document.querySelectorAll('[data-password-modal-close]');
  const alertBox = document.querySelector('[data-password-modal-alert]');
  const codeForm = document.querySelector('[data-password-code-form]');
  const resetForm = document.querySelector('[data-password-reset-form]');
  const codeInput = document.querySelector('#profile_password_code');
  const codeHint = document.querySelector('[data-password-code-hint]');
  const resetSubmit = document.querySelector('[data-password-reset-submit]');

  if (!modal || !openButton || !codeForm || !resetForm) return;

  function showMessage(message, kind = 'info') {
    if (!alertBox || !message) return;
    alertBox.textContent = message;
    alertBox.className = `alert is-visible alert-${kind}`;
  }

  function clearMessage() {
    if (!alertBox) return;
    alertBox.textContent = '';
    alertBox.className = 'alert';
  }

  function setOpen(open) {
    modal.hidden = !open;
    document.body.classList.toggle('has-modal-open', open);

    if (open) {
      clearMessage();
      setTimeout(() => codeForm.querySelector('button')?.focus(), 40);
      return;
    }

    resetForm.reset();
  }

  function setResetReady(ready) {
    if (resetSubmit) resetSubmit.disabled = !ready;
    if (codeHint) {
      codeHint.textContent = ready
        ? 'Código enviado. Informe os 6 dígitos recebidos e escolha a nova senha.'
        : 'Envie o código para habilitar a atualização da senha.';
      codeHint.classList.toggle('is-ready', ready);
    }
  }

  function syncCsrfToken(token) {
    if (!token) return;
    document.querySelectorAll('input[name="_csrf"]').forEach((input) => {
      input.value = token;
    });
  }

  async function postForm(form) {
    const response = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });

    let data = {};
    try {
      data = await response.json();
    } catch (error) {
      data = { message: 'Não foi possível concluir a solicitação agora.' };
    }

    if (!response.ok || data.success !== true) {
      throw new Error(data.message || 'Não foi possível concluir a solicitação agora.');
    }

    return data;
  }

  async function handleSubmit(form, pendingMessage, onSuccess) {
    const submitButton = form.querySelector('[type="submit"]');
    const originalText = submitButton?.innerHTML || '';

    if (submitButton) submitButton.disabled = true;
    showMessage(pendingMessage, 'info');

    try {
      const data = await postForm(form);
      showMessage(data.message, 'success');
      syncCsrfToken(data.csrf);
      onSuccess?.();
    } catch (error) {
      showMessage(error.message, 'error');
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
      }
    }
  }

  openButton.addEventListener('click', () => setOpen(true));
  setResetReady(false);

  closeButtons.forEach((button) => {
    button.addEventListener('click', () => setOpen(false));
  });

  modal.addEventListener('click', (event) => {
    if (event.target === modal) setOpen(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) setOpen(false);
  });

  codeInput?.addEventListener('input', () => {
    codeInput.value = codeInput.value.replace(/\D+/g, '').slice(0, 6);
  });

  codeForm.addEventListener('submit', (event) => {
    event.preventDefault();
    handleSubmit(codeForm, 'Enviando código...', () => {
      setResetReady(true);
      codeInput?.focus();
    });
  });

  resetForm.addEventListener('submit', (event) => {
    event.preventDefault();
    handleSubmit(resetForm, 'Validando código...', () => {
      resetForm.reset();
      setResetReady(false);
      setTimeout(() => setOpen(false), 1100);
    });
  });
});
