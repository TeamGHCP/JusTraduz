document.addEventListener("DOMContentLoaded", function () {
  var cancelForm = document.querySelector("[data-billing-cancel-form]");
  var cancelModal = document.querySelector("[data-billing-cancel-modal]");
  var cancelConfirm = document.querySelector("[data-billing-cancel-confirm]");
  var cancelCloseButtons = document.querySelectorAll("[data-billing-cancel-close]");
  var detailModal = document.querySelector("[data-billing-detail-modal]");
  var detailOpenButtons = document.querySelectorAll("[data-billing-detail-open]");
  var detailCloseButtons = document.querySelectorAll("[data-billing-detail-close]");
  var previousFocus = null;
  var cancelConfirmed = false;

  function visibleModalCount() {
    return [cancelModal, detailModal].filter(function (modal) {
      return modal && !modal.hidden;
    }).length;
  }

  function syncBodyModalState() {
    document.body.classList.toggle("has-modal-open", visibleModalCount() > 0);
  }

  function setModalOpen(modal, open, focusTarget) {
    if (!modal) {
      return;
    }

    modal.hidden = !open;
    syncBodyModalState();

    if (open) {
      previousFocus = document.activeElement;
      window.setTimeout(function () {
        if (focusTarget && typeof focusTarget.focus === "function") {
          focusTarget.focus();
        }
      }, 40);
      return;
    }

    if (previousFocus && typeof previousFocus.focus === "function") {
      previousFocus.focus();
    }
  }

  if (cancelForm && cancelModal && cancelConfirm) {
    cancelForm.addEventListener("submit", function (event) {
      if (cancelConfirmed) {
        return;
      }

      event.preventDefault();
      setModalOpen(cancelModal, true, cancelConfirm);
    });

    cancelConfirm.addEventListener("click", function () {
      cancelConfirmed = true;
      cancelConfirm.disabled = true;
      cancelConfirm.classList.add("is-loading");
      cancelForm.submit();
    });

    cancelCloseButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        setModalOpen(cancelModal, false);
      });
    });

    cancelModal.addEventListener("click", function (event) {
      if (event.target === cancelModal) {
        setModalOpen(cancelModal, false);
      }
    });
  }

  if (detailModal && detailOpenButtons.length) {
    var title = detailModal.querySelector("[data-billing-detail-title]");
    var amount = detailModal.querySelector("[data-billing-detail-amount]");
    var status = detailModal.querySelector("[data-billing-detail-status]");
    var date = detailModal.querySelector("[data-billing-detail-date]");
    var method = detailModal.querySelector("[data-billing-detail-method]");
    var cycle = detailModal.querySelector("[data-billing-detail-cycle]");
    var provider = detailModal.querySelector("[data-billing-detail-provider]");
    var paymentId = detailModal.querySelector("[data-billing-detail-payment-id]");
    var invoiceLink = detailModal.querySelector("[data-billing-invoice-link]");
    var receiptLink = detailModal.querySelector("[data-billing-receipt-link]");

    detailOpenButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        var statusClass = button.dataset.eventStatusClass || "badge-info";

        if (title) title.textContent = button.dataset.eventTitle || "Fatura";
        if (amount) amount.textContent = button.dataset.eventAmount || "R$ 0,00";
        if (date) date.textContent = button.dataset.eventDate || "-";
        if (method) method.textContent = button.dataset.eventMethod || "-";
        if (cycle) cycle.textContent = button.dataset.eventCycle || "-";
        if (provider) provider.textContent = button.dataset.eventProvider || "-";
        if (paymentId) paymentId.textContent = button.dataset.eventPaymentId || "Ainda não informado";
        if (invoiceLink) invoiceLink.href = button.dataset.invoiceUrl || "#";
        if (receiptLink) receiptLink.href = button.dataset.receiptUrl || "#";

        if (status) {
          status.textContent = button.dataset.eventStatus || "Pendente";
          status.className = "badge " + statusClass;
        }

        setModalOpen(detailModal, true, detailCloseButtons[0]);
      });
    });

    detailCloseButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        setModalOpen(detailModal, false);
      });
    });

    detailModal.addEventListener("click", function (event) {
      if (event.target === detailModal) {
        setModalOpen(detailModal, false);
      }
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key !== "Escape") {
      return;
    }

    if (detailModal && !detailModal.hidden) {
      setModalOpen(detailModal, false);
      return;
    }

    if (cancelModal && !cancelModal.hidden) {
      setModalOpen(cancelModal, false);
    }
  });
});
