(function () {
  function formatPhone(value) {
    const digits = String(value || '').replace(/\D+/g, '').slice(0, 11);

    if (digits.length <= 2) {
      return digits ? `(${digits}` : '';
    }

    if (digits.length <= 6) {
      return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    }

    if (digits.length <= 10) {
      return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    }

    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
  }

  function applyPhoneMask(input) {
    input.value = formatPhone(input.value);

    input.addEventListener('input', () => {
      input.value = formatPhone(input.value);
    });

    input.addEventListener('blur', () => {
      input.value = formatPhone(input.value);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="telefone"]').forEach(applyPhoneMask);
  });
})();
