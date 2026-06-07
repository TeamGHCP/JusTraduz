(function () {
  async function copyText(text) {
    if (!text) return false;

    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return true;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();

    let copied = false;
    try {
      copied = document.execCommand('copy');
    } finally {
      document.body.removeChild(textarea);
    }

    return copied;
  }

  function setCopiedLabel(button, copied) {
    if (!button || !copied) return;

    const original = button.dataset.originalLabel || button.textContent;
    button.dataset.originalLabel = original;
    button.textContent = 'Copiado';

    window.setTimeout(() => {
      button.textContent = original;
    }, 1600);
  }

  document.addEventListener('click', async (event) => {
    const copyButton = event.target.closest('[data-oab-copy]');
    if (copyButton) {
      const copied = await copyText(copyButton.dataset.copyText || '');
      setCopiedLabel(copyButton, copied);
      return;
    }

    const copyOpenLink = event.target.closest('[data-oab-copy-open]');
    if (copyOpenLink) {
      await copyText(copyOpenLink.dataset.copyText || '');
    }
  });
})();
