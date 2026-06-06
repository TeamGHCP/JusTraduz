document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("[data-chat-form]");
  const input = document.querySelector("[data-chat-input]");
  const file = document.querySelector("[data-chat-file]");
  const fileName = document.querySelector("[data-chat-file-name]");
  const messages = document.querySelector("[data-chat-messages]");
  const modal = document.querySelector("[data-attachment-modal]");
  const preview = document.querySelector("[data-attachment-preview]");
  const title = document.querySelector("[data-attachment-title]");
  const download = document.querySelector("[data-attachment-download]");
  let lastFocused = null;

  if (!messages) return;

  messages.scrollTop = messages.scrollHeight;

  function closeAttachmentPreview() {
    if (!modal || !preview) return;

    modal.hidden = true;
    document.body.classList.remove("attachment-modal-open");
    preview.innerHTML = "";

    if (lastFocused) {
      lastFocused.focus();
      lastFocused = null;
    }
  }

  function openAttachmentPreview(trigger) {
    if (!modal || !preview || !title || !download) return;

    const url = trigger.getAttribute("data-attachment-url") || "";
    const name = trigger.getAttribute("data-attachment-name") || "Anexo";
    const type = trigger.getAttribute("data-attachment-type") || "";

    if (!url || !type) return;

    lastFocused = trigger;
    title.textContent = name;
    download.href = `${url}&download=1`;
    preview.innerHTML = "";

    if (type === "image") {
      const image = document.createElement("img");
      image.src = url;
      image.alt = name;
      image.loading = "eager";
      preview.appendChild(image);
    } else if (type === "pdf") {
      const frame = document.createElement("iframe");
      frame.src = url;
      frame.title = name;
      preview.appendChild(frame);
    }

    modal.hidden = false;
    document.body.classList.add("attachment-modal-open");
  }

  messages.addEventListener("click", (event) => {
    if (event.target.closest(".attachment-download")) return;

    const trigger = event.target.closest("[data-attachment-url]");
    if (!trigger) return;
    openAttachmentPreview(trigger);
  });

  messages.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" && event.key !== " ") return;

    const trigger = event.target.closest("[data-attachment-url]");
    if (!trigger) return;

    event.preventDefault();
    openAttachmentPreview(trigger);
  });

  document.querySelectorAll("[data-attachment-close]").forEach((button) => {
    button.addEventListener("click", closeAttachmentPreview);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && modal && !modal.hidden) {
      closeAttachmentPreview();
    }
  });

  if (!form || !input) return;

  if (file && fileName) {
    file.addEventListener("change", () => {
      const selected = file.files && file.files[0] ? file.files[0].name : "";
      fileName.textContent = selected ? `Anexo: ${selected}` : "";
    });
  }

  form.addEventListener("submit", (event) => {
    const hasText = input.value.trim() !== "";
    const hasFile = Boolean(file && file.files && file.files.length > 0);

    if (!hasText && !hasFile) {
      event.preventDefault();
      input.focus();
      return;
    }

    window.setTimeout(() => {
      messages.scrollTop = messages.scrollHeight;
    }, 0);
  });
});
