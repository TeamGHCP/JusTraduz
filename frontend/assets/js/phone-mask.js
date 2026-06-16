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

  function formatCpf(value) {
    const digits = String(value || '').replace(/\D+/g, '').slice(0, 11);

    if (digits.length <= 3) {
      return digits;
    }

    if (digits.length <= 6) {
      return `${digits.slice(0, 3)}.${digits.slice(3)}`;
    }

    if (digits.length <= 9) {
      return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
    }

    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
  }

  function isValidCpf(value) {
    const digits = String(value || '').replace(/\D+/g, '');

    if (digits.length !== 11 || /^(\d)\1{10}$/.test(digits)) {
      return false;
    }

    for (let position = 9; position <= 10; position += 1) {
      let sum = 0;

      for (let index = 0; index < position; index += 1) {
        sum += Number(digits[index]) * ((position + 1) - index);
      }

      let checkDigit = (sum * 10) % 11;
      if (checkDigit === 10) checkDigit = 0;

      if (checkDigit !== Number(digits[position])) {
        return false;
      }
    }

    return true;
  }

  function validateCpfInput(input) {
    const digits = String(input.value || '').replace(/\D+/g, '');
    const isRequired = input.required || input.hasAttribute('required');

    if (!digits && !isRequired) {
      input.setCustomValidity('');
      return;
    }

    input.setCustomValidity(isValidCpf(digits) ? '' : 'Informe um CPF válido.');
  }

  function applyCpfMask(input) {
    input.value = formatCpf(input.value);
    validateCpfInput(input);

    input.addEventListener('input', () => {
      input.value = formatCpf(input.value);
      validateCpfInput(input);
    });

    input.addEventListener('blur', () => {
      input.value = formatCpf(input.value);
      validateCpfInput(input);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="telefone"]').forEach(applyPhoneMask);
    document.querySelectorAll('input[name="cpf"]').forEach(applyCpfMask);
  });
})();
