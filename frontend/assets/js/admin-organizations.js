document.addEventListener("DOMContentLoaded", () => {
  const fields = document.querySelectorAll("[data-cnpj-mask]");

  const normalizeCnpj = (value) => {
    const chars = value.toUpperCase().replace(/[^0-9A-Z]/g, "");
    const base = chars.slice(0, 12);
    const digits = chars.slice(12).replace(/\D/g, "").slice(0, 2);

    return base + digits;
  };

  const formatCnpj = (value) => {
    const chars = normalizeCnpj(value);
    let formatted = chars.slice(0, 2);

    if (chars.length > 2) {
      formatted += "." + chars.slice(2, 5);
    }

    if (chars.length > 5) {
      formatted += "." + chars.slice(5, 8);
    }

    if (chars.length > 8) {
      formatted += "/" + chars.slice(8, 12);
    }

    if (chars.length > 12) {
      formatted += "-" + chars.slice(12, 14);
    }

    return formatted;
  };

  fields.forEach((field) => {
    const applyMask = () => {
      field.value = formatCnpj(field.value);
    };

    field.addEventListener("input", applyMask);
    field.addEventListener("blur", applyMask);
    applyMask();
  });
});
