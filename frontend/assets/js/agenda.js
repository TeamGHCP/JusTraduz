document.addEventListener('DOMContentLoaded', () => {
  const calendarEl = document.getElementById('calendar');
  if (!calendarEl) return;
  const currentUserType = String(window.CURRENT_USER_TYPE || '');
  const isProfessional = currentUserType === 'advogado' || currentUserType === 'estagiario';

  const params = new URLSearchParams(window.location.search);
  const professionalSelect = document.getElementById('professional_id');
  const professionalId = professionalSelect ? Number(professionalSelect.value || 0) : 0;

  const now = new Date();
  let year = Number(params.get('year') || now.getFullYear());
  let month = Number(params.get('month') || now.getMonth() + 1); // 1-12

  function startOfMonth(y, m) {
    return new Date(y, m - 1, 1);
  }
  function endOfMonth(y, m) {
    return new Date(y, m, 0);
  }

  function formatDateISO(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function parseServerDate(value) {
    // Normalize MySQL DATETIME (YYYY-MM-DD HH:MM:SS) for robust browser parsing.
    const raw = String(value || '').trim();
    if (!raw) return new Date('invalid');
    const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
    return new Date(normalized);
  }

  function extractDayKey(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    // Prefer direct DB date extraction to avoid browser parse inconsistencies.
    if (raw.length >= 10) {
      return raw.slice(0, 10);
    }
    const d = parseServerDate(raw);
    if (isNaN(d.getTime())) return '';
    return formatDateISO(d);
  }

  async function loadData() {
    const start = formatDateISO(startOfMonth(year, month));
    const end = formatDateISO(endOfMonth(year, month));
    const url = `/backend/public/index.php?rota=/schedule/calendar&start=${start}&end=${end}` + (professionalId ? `&professional_id=${professionalId}` : '');
    const res = await fetch(url, { credentials: 'include' });
    if (!res.ok) return { slots: [], appointments: [] };
    return await res.json();
  }

  function buildCalendarGrid(slotsByDay, apptsByDay) {
    const first = startOfMonth(year, month);
    const last = endOfMonth(year, month);
    const startWeekDay = first.getDay();
    const daysInMonth = last.getDate();

    const table = document.createElement('table');
    table.className = 'calendar-table';

    const thead = document.createElement('thead');
    thead.innerHTML = '<tr><th>Dom</th><th>Seg</th><th>Ter</th><th>Qua</th><th>Qui</th><th>Sex</th><th>Sáb</th></tr>';
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    let row = document.createElement('tr');
    // fill empty cells
    for (let i = 0; i < startWeekDay; i++) {
      row.appendChild(document.createElement('td'));
    }

    for (let day = 1; day <= daysInMonth; day++) {
      if ((startWeekDay + day - 1) % 7 === 0 && day !== 1) {
        tbody.appendChild(row);
        row = document.createElement('tr');
      }

      const cell = document.createElement('td');
      cell.className = 'calendar-cell';
      const d = new Date(year, month - 1, day);
      const key = formatDateISO(d);

      const dayHeader = document.createElement('div');
      dayHeader.className = 'calendar-day-header';
      dayHeader.innerHTML = `<span class="day-num">${day}</span>`;
      cell.appendChild(dayHeader);

      const slots = slotsByDay[key] || [];
      const appts = apptsByDay[key] || [];

      const totalItems = slots.length + appts.length;
      if (totalItems > 0) {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'day-dot';
        dot.title = `${totalItems} horário(s) neste dia`;
        dot.innerHTML = `<span class="day-dot-count">${totalItems}</span>`;
        dot.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          openDaySlotsModal(d, slots, appts);
        });
        cell.appendChild(dot);
      }

      // click day to prefill new slot form (for professionals)
      cell.addEventListener('click', (ev) => {
        // avoid firing when clicking inside slot/appointment elements
        if (ev.target && ev.target.closest && ev.target.closest('.calendar-slot, .calendar-appointment')) return;
        if (isProfessional) prefillNewSlot(day);
      });

      row.appendChild(cell);
    }

    // fill remaining cells
    while (row.children.length < 7) {
      row.appendChild(document.createElement('td'));
    }
    tbody.appendChild(row);
    table.appendChild(tbody);

    calendarEl.innerHTML = '';
    const header = document.createElement('div');
    header.className = 'calendar-controls';
    header.innerHTML = `<button id="cal-prev" class="btn btn-sm">&lt;</button> <strong>${year}-${String(month).padStart(2, '0')}</strong> <button id="cal-next" class="btn btn-sm">&gt;</button>`;
    calendarEl.appendChild(header);
    calendarEl.appendChild(table);

    document.getElementById('cal-prev').addEventListener('click', () => { if (month === 1) { year--; month = 12; } else { month--; } render(); });
    document.getElementById('cal-next').addEventListener('click', () => { if (month === 12) { year++; month = 1; } else { month++; } render(); });
  }

  // simple text escape for insertion into innerHTML
  function escapeHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function groupByDay(items, dateKey) {
    const map = {};
    items.forEach((it) => {
      const key = extractDayKey(it[dateKey]);
      if (!key) return;
      if (!map[key]) map[key] = [];
      map[key].push(it);
    });
    return map;
  }

  function scrollToBookingForm(slotId) {
    const el = document.querySelector(`form[action*="/schedule/book"] input[name="slot_id"][value="${slotId}"]`);
    if (el) {
      // find the form and scroll to it
      const form = el.closest('form');
      form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
      alert('Formulário de agendamento não encontrado nesta página. Role para baixo para ver horários livres.');
    }
  }

  function formatHour(dateValue) {
    const raw = String(dateValue || '').trim();
    // Fast path for MySQL DATETIME values.
    if (raw.length >= 16 && raw.includes(' ')) {
      return raw.slice(11, 16);
    }
    const d = parseServerDate(dateValue);
    if (isNaN(d.getTime())) return '--:--';
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function formatDayLabel(dateObj) {
    return dateObj.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function openDaySlotsModal(dayDate, slots, appts) {
    const modal = document.getElementById('day-slots-modal');
    const title = document.getElementById('day-slots-title');
    const content = document.getElementById('day-slots-content');
    if (!modal || !title || !content) return;

    title.textContent = 'Horários de ' + formatDayLabel(dayDate);

    const orderedSlots = [...slots].sort((a, b) => parseServerDate(a.starts_at) - parseServerDate(b.starts_at));
    const orderedAppts = [...appts].sort((a, b) => parseServerDate(a.starts_at) - parseServerDate(b.starts_at));

    if (orderedSlots.length === 0 && orderedAppts.length === 0) {
      content.innerHTML = '<p class="text-muted">Nenhum horário neste dia.</p>';
    } else {
      const rows = [];
      orderedSlots.forEach((s) => {
        const canEdit = isProfessional && Number(window.CURRENT_USER_ID) === Number(s.professional_id);
        rows.push(
          `<div class="day-slot-row">
            <div>
              <div class="day-slot-time">${formatHour(s.starts_at)} - ${formatHour(s.ends_at)}</div>
              <div class="day-slot-title">${s.titulo ? escapeHtml(s.titulo) : 'Horário'}</div>
              <div class="day-slot-meta">${s.status === 'livre' ? 'Livre' : 'Ocupado interno'}</div>
            </div>
            ${canEdit ? `<button type="button" class="btn btn-soft btn-sm day-slot-edit" data-slot-id="${s.id}">Editar</button>` : ''}
          </div>`
        );
      });

      orderedAppts.forEach((a) => {
        rows.push(
          `<div class="day-slot-row day-slot-row-appointment">
            <div>
              <div class="day-slot-time">${formatHour(a.starts_at)} - ${formatHour(a.ends_at)}</div>
              <div class="day-slot-title">${a.assunto ? escapeHtml(a.assunto) : 'Agendamento'}</div>
              <div class="day-slot-meta">Agendado</div>
            </div>
          </div>`
        );
      });

      content.innerHTML = rows.join('');
    }

    content.querySelectorAll('.day-slot-edit').forEach((btn) => {
      btn.addEventListener('click', (ev) => {
        ev.preventDefault();
        const slotId = btn.dataset.slotId;
        hideDaySlotsModal();
        openEditModal(slotId);
      });
    });

    modal.style.display = 'flex';
  }

  function hideDaySlotsModal() {
    const modal = document.getElementById('day-slots-modal');
    if (modal) modal.style.display = 'none';
  }

  function prefillNewSlot(day) {
    // open the modal prefilled with the selected day time range
    const y = year; const m = month; const d = day;
    const starts = new Date(y, m - 1, d, 9, 0); // default 09:00
    const ends = new Date(y, m - 1, d, 10, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const clickedDay = new Date(y, m - 1, d, 0, 0, 0);
    if (clickedDay < today) {
      showModalAlert('Não é possível criar horário em dia passado.', 'error');
      return;
    }
    const toTimeValue = (dt) => String(dt.getHours()).padStart(2,'0') + ':' + String(dt.getMinutes()).padStart(2,'0');
    const toDateValue = (dt) => dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2,'0') + '-' + String(dt.getDate()).padStart(2,'0');
    const startsInput = document.getElementById('slot-starts');
    const endsInput = document.getElementById('slot-ends');
    const dateInput = document.getElementById('slot-date');
    if (startsInput) startsInput.value = toTimeValue(starts);
    if (endsInput) endsInput.value = toTimeValue(ends);
    if (dateInput) dateInput.value = toDateValue(starts);
    openCreateModal();
  }

  async function render() {
    const data = await loadData();
    const slotsByDay = groupByDay(data.slots || [], 'starts_at');
    const apptsByDay = groupByDay(data.appointments || [], 'starts_at');
    const monthTotal = (data.slots || []).length + (data.appointments || []).length;
    buildCalendarGrid(slotsByDay, apptsByDay);
    const headerTitle = calendarEl.querySelector('.calendar-controls strong');
    if (headerTitle) {
      headerTitle.textContent = `${year}-${String(month).padStart(2, '0')} (${monthTotal} horário(s))`;
    }
    // initialize modal hooks after render
    initSlotModal();
  }

  // -----------------------
  // Modal: create/edit slot via AJAX
  // -----------------------
  const slotModal = document.getElementById('slot-modal');
  const slotForm = document.getElementById('slot-modal-form');
  const slotModalTitle = document.getElementById('slot-modal-title');
  const slotModalCancel = document.getElementById('slot-modal-cancel');

  function showModal() {
    if (!slotModal) return;
    // use flex so the modal centers via CSS (.modal uses display:flex)
    slotModal.style.display = 'flex';
  }
  function hideModal() {
    if (!slotModal) return;
    slotModal.style.display = 'none';
  }

  async function fetchCsrf() {
    try {
      const r = await fetch('/backend/public/index.php?rota=/auth/csrf', { credentials: 'include' });
      if (!r.ok) return null;
      const j = await r.json();
      return j.csrf || null;
    } catch (e) {
      return null;
    }
  }

  let modalInitialized = false;
  function initSlotModal() {
    if (!slotForm) return;
    if (modalInitialized) return;
    modalInitialized = true;

    // Cancel button
    slotModalCancel?.addEventListener('click', (e) => { e.preventDefault(); hideModal(); });

    const dayClose = document.getElementById('day-slots-close');
    dayClose?.addEventListener('click', (e) => { e.preventDefault(); hideDaySlotsModal(); });

    slotForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const raw = new FormData(slotForm);
      const formData = new FormData();

      const slotId = raw.get('slot_id');
      const slotDate = String(raw.get('slot_date') || '');
      const startsTime = String(raw.get('starts_time') || '');
      const endsTime = String(raw.get('ends_time') || '');
      const titulo = String(raw.get('titulo') || '');
      const status = String(raw.get('status') || 'livre');

      if (!slotDate || !startsTime || !endsTime) {
        showModalAlert('Selecione dia, hora inicial e hora final.', 'error');
        return;
      }

      formData.set('slot_id', String(slotId || ''));
      formData.set('starts_at', `${slotDate}T${startsTime}`);
      formData.set('ends_at', `${slotDate}T${endsTime}`);
      formData.set('titulo', titulo);
      formData.set('status', status);

      const csrf = raw.get('_csrf');
      if (csrf) formData.set('_csrf', String(csrf));

      const currentSlotId = formData.get('slot_id');
      const url = currentSlotId ? '/backend/public/index.php?rota=/schedule/slots/update' : '/backend/public/index.php?rota=/schedule/slots/create';
      const token = await fetchCsrf();
      try {
        const res = await fetch(url, {
          method: 'POST',
          credentials: 'include',
          headers: Object.assign({'X-Requested-With': 'XMLHttpRequest'}, token ? { 'X-CSRF-Token': token } : {}),
          body: formData,
        });
        const contentType = res.headers.get('Content-Type') || '';
        const isJson = contentType.indexOf('application/json') !== -1;

        if (isJson) {
          const payload = await res.json();
          if (res.ok && payload && payload.success === true) {
            showModalAlert('Horário salvo com sucesso.', 'success');
            setTimeout(() => { hideModal(); render(); }, 600);
            return;
          }

          showModalAlert((payload && payload.error) ? payload.error : 'Erro ao salvar horário.', 'error');
          return;
        }

        // Non-JSON means backend redirected/rendered HTML (commonly validation/auth failure).
        if (res.redirected && res.url) {
          try {
            const u = new URL(res.url, window.location.origin);
            const erro = u.searchParams.get('erro');
            if (erro) {
              showModalAlert(erro, 'error');
              return;
            }
          } catch (e) {
            // ignore parse issues and fallback below
          }
        }

        if (res.ok) {
          showModalAlert('Servidor não retornou confirmação de criação. Verifique login/OAB/permissão e tente novamente.', 'error');
          return;
        }

        const text = await res.text();
        const fallback = text ? String(text).slice(0, 220) : 'Erro ao salvar horário.';
        showModalAlert(fallback, 'error');
        return;
      } catch (e) {
        showModalAlert('Falha ao salvar horário. Tente novamente.', 'error');
      }
    });
  }

  function showModalAlert(message, type) {
    const container = document.getElementById('slot-modal-alert');
    if (!container) return;
    container.textContent = message;
    container.className = 'modal-alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
    container.style.display = 'block';
  }

  async function openEditModal(slotId) {
    // Fetch details from calendar endpoint for current month and find slot by id.
    const data = await loadData();
    const allSlots = (data.slots || []);
    const slot = allSlots.find(s => String(s.id) === String(slotId));
    if (!slot) return;
    document.getElementById('slot-modal-id').value = slot.id;
    const startsDt = parseServerDate(slot.starts_at);
    const endsDt = parseServerDate(slot.ends_at);
    const pad = (n) => String(n).padStart(2, '0');
    document.getElementById('slot-date').value = `${startsDt.getFullYear()}-${pad(startsDt.getMonth()+1)}-${pad(startsDt.getDate())}`;
    document.getElementById('slot-starts').value = `${pad(startsDt.getHours())}:${pad(startsDt.getMinutes())}`;
    document.getElementById('slot-ends').value = `${pad(endsDt.getHours())}:${pad(endsDt.getMinutes())}`;
    document.getElementById('slot-title').value = slot.titulo || '';
    document.getElementById('slot-status').value = slot.status === 'livre' ? 'livre' : 'bloqueado';
    slotModalTitle.textContent = 'Editar horário';
    showModal();
  }

  function openCreateModal() {
    // clear fields
    document.getElementById('slot-modal-id').value = '';
    document.getElementById('slot-status').value = 'livre';
    document.getElementById('slot-title').value = '';
    const alertBox = document.getElementById('slot-modal-alert');
    if (alertBox) alertBox.style.display = 'none';
    slotModalTitle.textContent = 'Novo horário';
    showModal();
  }

  render();
});
