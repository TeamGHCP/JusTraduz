document.addEventListener("DOMContentLoaded", () => {
  const calendarEl = document.getElementById("calendar");
  if (!calendarEl) return;

  function readAgendaUserContext() {
    const node = document.getElementById("agenda-user-context");
    if (!node) return {};

    try {
      return JSON.parse(node.textContent || "{}");
    } catch (error) {
      return {};
    }
  }

  const agendaUserContext = readAgendaUserContext();
  const currentUserType = String(agendaUserContext.type || window.CURRENT_USER_TYPE || "");
  const currentUserId = Number(agendaUserContext.id || window.CURRENT_USER_ID || 0);
  const isProfessional = currentUserType === "advogado" || currentUserType === "estagiario";
  const frontendIndex = window.location.pathname.indexOf("/frontend/");
  const appBasePath = frontendIndex >= 0 ? window.location.pathname.slice(0, frontendIndex) : "";
  const params = new URLSearchParams(window.location.search);
  const professionalSelect = document.getElementById("professional_id");
  const roleSelect = document.getElementById("perfil");
  const slotModal = document.getElementById("slot-modal");
  const slotForm = document.getElementById("slot-modal-form");
  const slotModalTitle = document.getElementById("slot-modal-title");
  const slotModalCancel = document.getElementById("slot-modal-cancel");
  const daySlotsModal = document.getElementById("day-slots-modal");
  const daySlotsTitle = document.getElementById("day-slots-title");
  const daySlotsContent = document.getElementById("day-slots-content");

  let professionalId = professionalSelect ? Number(professionalSelect.value || 0) : 0;
  let roleFilter = roleSelect ? String(roleSelect.value || "") : "";
  let year = Number(params.get("year") || new Date().getFullYear());
  let month = Number(params.get("month") || new Date().getMonth() + 1);

  function apiRoute(path, extraParams = {}) {
    const url = new URL(`${appBasePath}/backend/public/index.php`, window.location.origin);
    url.searchParams.set("rota", path);
    Object.entries(extraParams).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") {
        url.searchParams.set(key, String(value));
      }
    });
    return url.toString();
  }

  function startOfMonth(y, m) {
    return new Date(y, m - 1, 1);
  }

  function endOfMonth(y, m) {
    return new Date(y, m, 0);
  }

  function formatDateISO(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
  }

  function parseServerDate(value) {
    const raw = String(value || "").trim();
    if (!raw) return new Date("invalid");
    return new Date(raw.includes("T") ? raw : raw.replace(" ", "T"));
  }

  function extractDayKey(value) {
    const raw = String(value || "").trim();
    if (raw.length >= 10) return raw.slice(0, 10);
    const date = parseServerDate(raw);
    return Number.isNaN(date.getTime()) ? "" : formatDateISO(date);
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function monthLabel(y, m) {
    return new Date(y, m - 1, 1).toLocaleDateString("pt-BR", { month: "long", year: "numeric" });
  }

  function formatHour(value) {
    const raw = String(value || "").trim();
    if (raw.length >= 16 && raw.includes(" ")) return raw.slice(11, 16);
    const date = parseServerDate(value);
    if (Number.isNaN(date.getTime())) return "--:--";
    return date.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
  }

  function formatDayLabel(value) {
    return value.toLocaleDateString("pt-BR", { weekday: "long", day: "2-digit", month: "2-digit", year: "numeric" });
  }

  function groupByDay(items) {
    const grouped = {};
    items.forEach((item) => {
      const key = extractDayKey(item.starts_at);
      if (!key) return;
      grouped[key] = grouped[key] || [];
      grouped[key].push(item);
    });
    return grouped;
  }

  async function loadData() {
    const start = formatDateISO(startOfMonth(year, month));
    const end = formatDateISO(endOfMonth(year, month));
    const response = await fetch(apiRoute("/schedule/calendar", {
      start,
      end,
      professional_id: professionalId || "",
      perfil: roleFilter || "",
    }), { credentials: "include" });

    if (!response.ok) {
      return { slots: [], appointments: [] };
    }

    return response.json();
  }

  function scrollToSlot(slotId) {
    const card = document.querySelector(`[data-slot-card="${slotId}"]`);
    if (!card) return;
    card.scrollIntoView({ behavior: "smooth", block: "center" });
    card.classList.add("is-highlighted");
    setTimeout(() => card.classList.remove("is-highlighted"), 1200);
  }

  function showDayModal(dayDate, slots, appointments) {
    if (!daySlotsModal || !daySlotsTitle || !daySlotsContent) return;

    daySlotsTitle.textContent = `Horarios de ${formatDayLabel(dayDate)}`;
    const rows = [];

    slots
      .slice()
      .sort((a, b) => parseServerDate(a.starts_at) - parseServerDate(b.starts_at))
      .forEach((slot) => {
        const canEdit = isProfessional && currentUserId === Number(slot.professional_id);
        const canBook = currentUserType === "cliente" && String(slot.status) === "livre";
        rows.push(`
          <div class="day-slot-row">
            <div>
              <div class="day-slot-time">${formatHour(slot.starts_at)} - ${formatHour(slot.ends_at)}</div>
              <div class="day-slot-title">${escapeHtml(slot.titulo || "Horario de atendimento")}</div>
              <div class="day-slot-meta">${escapeHtml(slot.professional_name || slot.profissional || "")} ${slot.status ? "| " + escapeHtml(slot.status) : ""}</div>
            </div>
            ${canBook ? `<button type="button" class="btn btn-primary btn-sm" data-scroll-slot="${slot.id}">Agendar</button>` : ""}
            ${canEdit ? `<button type="button" class="btn btn-soft btn-sm" data-edit-slot="${slot.id}">Editar</button>` : ""}
          </div>
        `);
      });

    appointments
      .slice()
      .sort((a, b) => parseServerDate(a.starts_at) - parseServerDate(b.starts_at))
      .forEach((appointment) => {
        rows.push(`
          <div class="day-slot-row day-slot-row-appointment">
            <div>
              <div class="day-slot-time">${formatHour(appointment.starts_at)} - ${formatHour(appointment.ends_at)}</div>
              <div class="day-slot-title">${escapeHtml(appointment.assunto || "Atendimento agendado")}</div>
              <div class="day-slot-meta">${escapeHtml(appointment.client_name || appointment.professional_name || "")} | ${escapeHtml(appointment.status || "agendado")}</div>
            </div>
          </div>
        `);
      });

    daySlotsContent.innerHTML = rows.length ? rows.join("") : '<p class="text-muted">Nenhum horário neste dia.</p>';
    daySlotsContent.querySelectorAll("[data-scroll-slot]").forEach((button) => {
      button.addEventListener("click", () => {
        hideDayModal();
        scrollToSlot(button.dataset.scrollSlot);
      });
    });
    daySlotsContent.querySelectorAll("[data-edit-slot]").forEach((button) => {
      button.addEventListener("click", () => {
        hideDayModal();
        openEditModal(button.dataset.editSlot);
      });
    });

    daySlotsModal.style.display = "flex";
  }

  function hideDayModal() {
    if (daySlotsModal) daySlotsModal.style.display = "none";
  }

  function buildCalendar(slotsByDay, appointmentsByDay, total) {
    const first = startOfMonth(year, month);
    const last = endOfMonth(year, month);
    const startWeekDay = first.getDay();
    const daysInMonth = last.getDate();
    const table = document.createElement("table");
    table.className = "calendar-table";
    table.innerHTML = "<thead><tr><th>Dom</th><th>Seg</th><th>Ter</th><th>Qua</th><th>Qui</th><th>Sex</th><th>Sab</th></tr></thead>";

    const tbody = document.createElement("tbody");
    let row = document.createElement("tr");

    for (let i = 0; i < startWeekDay; i += 1) {
      row.appendChild(document.createElement("td"));
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
      if ((startWeekDay + day - 1) % 7 === 0 && day !== 1) {
        tbody.appendChild(row);
        row = document.createElement("tr");
      }

      const cell = document.createElement("td");
      cell.className = "calendar-cell";
      if (isProfessional) cell.classList.add("is-clickable");

      const date = new Date(year, month - 1, day);
      const key = formatDateISO(date);
      const slots = slotsByDay[key] || [];
      const appointments = appointmentsByDay[key] || [];
      const dayTotal = slots.length + appointments.length;

      const header = document.createElement("div");
      header.className = "calendar-day-header";
      header.innerHTML = `<span class="day-num">${day}</span>`;

      if (dayTotal > 0) {
        cell.classList.add("has-schedule");
        header.insertAdjacentHTML("beforeend", `<span class="calendar-day-count">${dayTotal}</span>`);
      }

      cell.appendChild(header);

      if (dayTotal > 0) {
        const dot = document.createElement("button");
        dot.type = "button";
        dot.className = "day-dot";
        dot.innerHTML = `<span>${dayTotal}</span>`;
        dot.title = `${dayTotal} item(ns) de agenda`;
        dot.addEventListener("click", (event) => {
          event.preventDefault();
          event.stopPropagation();
          showDayModal(date, slots, appointments);
        });
        cell.appendChild(dot);
      }

      cell.addEventListener("click", () => {
        if (isProfessional) prefillCreateModal(date);
      });

      row.appendChild(cell);
    }

    while (row.children.length < 7) {
      row.appendChild(document.createElement("td"));
    }

    tbody.appendChild(row);
    table.appendChild(tbody);

    calendarEl.innerHTML = "";
    const controls = document.createElement("div");
    controls.className = "calendar-controls";
    controls.innerHTML = `
      <button id="cal-prev" class="btn btn-outline btn-sm" type="button">&lt;</button>
      <div class="calendar-month-summary">
        <strong>${monthLabel(year, month)}</strong>
        <span>${total} item(ns) de agenda no mes</span>
      </div>
      <button id="cal-next" class="btn btn-outline btn-sm" type="button">&gt;</button>
    `;
    calendarEl.appendChild(controls);
    calendarEl.appendChild(table);

    document.getElementById("cal-prev").addEventListener("click", () => {
      if (month === 1) {
        year -= 1;
        month = 12;
      } else {
        month -= 1;
      }
      render();
    });

    document.getElementById("cal-next").addEventListener("click", () => {
      if (month === 12) {
        year += 1;
        month = 1;
      } else {
        month += 1;
      }
      render();
    });
  }

  async function render() {
    const data = await loadData();
    const slots = data.slots || [];
    const appointments = data.appointments || [];
    buildCalendar(groupByDay(slots), groupByDay(appointments), slots.length + appointments.length);
  }

  async function fetchCsrf() {
    const existing = slotForm?.querySelector('input[name="_csrf"]')?.value;
    if (existing) return existing;

    try {
      const response = await fetch(apiRoute("/auth/csrf"), { credentials: "include" });
      if (!response.ok) return "";
      const data = await response.json();
      return data.csrf || "";
    } catch (error) {
      return "";
    }
  }

  function showSlotModal() {
    if (slotModal) slotModal.style.display = "flex";
  }

  function hideSlotModal() {
    if (slotModal) slotModal.style.display = "none";
  }

  function showModalAlert(message, kind) {
    const box = document.getElementById("slot-modal-alert");
    if (!box) return;
    box.textContent = message;
    box.className = `modal-alert ${kind === "success" ? "alert-success" : "alert-error"}`;
    box.style.display = "block";
  }

  function clearModalAlert() {
    const box = document.getElementById("slot-modal-alert");
    if (box) box.style.display = "none";
  }

  function prefillCreateModal(date) {
    if (!slotForm) return;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const selected = new Date(date);
    selected.setHours(0, 0, 0, 0);
    if (selected < today) return;

    slotForm.reset();
    clearModalAlert();
    document.getElementById("slot-modal-id").value = "";
    document.getElementById("slot-date").value = formatDateISO(date);
    document.getElementById("slot-starts").value = "09:00";
    document.getElementById("slot-ends").value = "10:00";
    document.getElementById("slot-status").value = "livre";
    if (slotModalTitle) slotModalTitle.textContent = "Novo horário";
    showSlotModal();
  }

  async function openEditModal(slotId) {
    if (!slotForm) return;
    const data = await loadData();
    const slot = (data.slots || []).find((item) => String(item.id) === String(slotId));
    if (!slot) return;

    const starts = parseServerDate(slot.starts_at);
    const ends = parseServerDate(slot.ends_at);
    if (Number.isNaN(starts.getTime()) || Number.isNaN(ends.getTime())) return;

    clearModalAlert();
    document.getElementById("slot-modal-id").value = slot.id;
    document.getElementById("slot-date").value = formatDateISO(starts);
    document.getElementById("slot-starts").value = `${String(starts.getHours()).padStart(2, "0")}:${String(starts.getMinutes()).padStart(2, "0")}`;
    document.getElementById("slot-ends").value = `${String(ends.getHours()).padStart(2, "0")}:${String(ends.getMinutes()).padStart(2, "0")}`;
    document.getElementById("slot-title").value = slot.titulo || "";
    document.getElementById("slot-status").value = slot.status === "livre" ? "livre" : "bloqueado";
    if (slotModalTitle) slotModalTitle.textContent = "Editar horário";
    showSlotModal();
  }

  slotModalCancel?.addEventListener("click", hideSlotModal);
  document.querySelectorAll("[data-slot-modal-close]").forEach((el) => el.addEventListener("click", hideSlotModal));
  document.getElementById("day-slots-close")?.addEventListener("click", hideDayModal);
  document.querySelectorAll("[data-day-modal-close]").forEach((el) => el.addEventListener("click", hideDayModal));

  slotForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const raw = new FormData(slotForm);
    const slotId = String(raw.get("slot_id") || "");
    const slotDate = String(raw.get("slot_date") || "");
    const startsTime = String(raw.get("starts_time") || "");
    const endsTime = String(raw.get("ends_time") || "");

    if (!slotDate || !startsTime || !endsTime) {
      showModalAlert("Informe dia, inicio e fim.", "error");
      return;
    }

    const payload = new FormData();
    payload.set("starts_at", `${slotDate}T${startsTime}`);
    payload.set("ends_at", `${slotDate}T${endsTime}`);
    payload.set("titulo", String(raw.get("titulo") || ""));
    payload.set("status", String(raw.get("status") || "livre"));
    if (slotId) payload.set("slot_id", slotId);

    const csrf = await fetchCsrf();
    if (csrf) payload.set("_csrf", csrf);

    try {
      const response = await fetch(apiRoute(slotId ? "/schedule/slots/update" : "/schedule/slots/create"), {
        method: "POST",
        credentials: "include",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: payload,
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        showModalAlert(data.error || "Não foi possível salvar horário.", "error");
        return;
      }
      showModalAlert("Horario salvo.", "success");
      setTimeout(() => {
        hideSlotModal();
        render();
      }, 500);
    } catch (error) {
      showModalAlert("Falha ao salvar horário.", "error");
    }
  });

  professionalSelect?.addEventListener("change", () => {
    professionalId = Number(professionalSelect.value || 0);
    render();
  });

  roleSelect?.addEventListener("change", () => {
    roleFilter = String(roleSelect.value || "");
    render();
  });

  render();
});
