document.addEventListener('DOMContentLoaded', () => {
  const calendarEl = document.getElementById('calendar');
  if (!calendarEl) return;

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
      dayHeader.textContent = day;
      cell.appendChild(dayHeader);

      const slotList = document.createElement('div');
      slotList.className = 'calendar-slot-list';

      const slots = slotsByDay[key] || [];
      const appts = apptsByDay[key] || [];

      slots.forEach((s) => {
        const el = document.createElement('div');
        el.className = 'calendar-slot ' + (s.status === 'livre' ? 'slot-free' : 'slot-busy');
        el.textContent = (s.titulo ? s.titulo + ' - ' : '') + new Date(s.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        el.dataset.slotId = s.id;
        // clients can click free slots to book
        if (s.status === 'livre') {
          el.addEventListener('click', () => scrollToBookingForm(s.id));
        }
        slotList.appendChild(el);
      });

      appts.forEach((a) => {
        const el = document.createElement('div');
        el.className = 'calendar-appointment';
        el.textContent = (a.assunto ? a.assunto + ' - ' : '') + new Date(a.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        el.dataset.appointmentId = a.id;
        slotList.appendChild(el);
      });

      if (slotList.children.length > 0) cell.appendChild(slotList);

      // click day to prefill new slot form (for professionals)
      cell.addEventListener('click', (ev) => {
        // avoid firing when clicking a slot element
        if (ev.target && (ev.target.classList.contains('calendar-slot') || ev.target.classList.contains('calendar-appointment'))) return;
        prefillNewSlot(day);
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

  function groupByDay(items, dateKey) {
    const map = {};
    items.forEach((it) => {
      const d = new Date(it[dateKey]);
      const key = formatDateISO(d);
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

  function prefillNewSlot(day) {
    const form = document.querySelector('form[action*="/schedule/slots/create"]');
    if (!form) return;
    const y = year; const m = month; const d = day;
    const starts = new Date(y, m - 1, d, 9, 0); // default 09:00
    const ends = new Date(y, m - 1, d, 10, 0);
    const toInputValue = (dt) => dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2,'0') + '-' + String(dt.getDate()).padStart(2,'0') + 'T' + String(dt.getHours()).padStart(2,'0') + ':' + String(dt.getMinutes()).padStart(2,'0');
    const startsInput = form.querySelector('input[name="starts_at"]');
    const endsInput = form.querySelector('input[name="ends_at"]');
    if (startsInput) startsInput.value = toInputValue(starts);
    if (endsInput) endsInput.value = toInputValue(ends);
    form.scrollIntoView({behavior: 'smooth', block: 'center'});
  }

  async function render() {
    const data = await loadData();
    const slotsByDay = groupByDay(data.slots || [], 'starts_at');
    const apptsByDay = groupByDay(data.appointments || [], 'starts_at');
    buildCalendarGrid(slotsByDay, apptsByDay);
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
    slotModal.style.display = 'block';
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

  function toDateTimeLocalInput(value) {
    const d = new Date(value);
    if (isNaN(d)) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function initSlotModal() {
    if (!slotForm) return;

    // Cancel button
    slotModalCancel?.addEventListener('click', (e) => { e.preventDefault(); hideModal(); });

    // Clicking a slot for professionals opens modal to edit; attach handlers to existing slot elements
    document.querySelectorAll('.calendar-slot').forEach((el) => {
      el.addEventListener('click', (ev) => {
        ev.stopPropagation();
        const slotId = el.dataset.slotId;
        const professionalId = el.dataset.professionalId ? Number(el.dataset.professionalId) : null;
        if (professionalId && window.CURRENT_USER_ID && Number(window.CURRENT_USER_ID) === professionalId) {
          // open edit modal
          openEditModal(slotId);
        } else {
          // otherwise, proceed to booking scroll
          scrollToBookingForm(slotId);
        }
      });
    });

    slotForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const formData = new FormData(slotForm);
      const slotId = formData.get('slot_id');
      const url = slotId ? '/backend/public/index.php?rota=/schedule/slots/update' : '/backend/public/index.php?rota=/schedule/slots/create';
      const token = await fetchCsrf();
      try {
        const res = await fetch(url, {
          method: 'POST',
          credentials: 'include',
          headers: token ? { 'X-CSRF-Token': token } : {},
          body: formData,
        });
        // ignore response body; refresh calendar
        hideModal();
        setTimeout(() => render(), 300);
      } catch (e) {
        alert('Falha ao salvar horário. Tente novamente.');
      }
    });
  }

  async function openEditModal(slotId) {
    // fetch slot data from calendar API (we already have it in the last loaded data via DOM); try to find element
    const el = document.querySelector(`.calendar-slot[data-slot-id="${slotId}"]`);
    if (!el) return;
    // read times from title text isn't reliable; instead call calendar API single day range and find slot by id
    const token = await fetchCsrf();
    // For simplicity, attempt to fetch slot details via calendar endpoint for current month and find slot
    const data = await loadData();
    const allSlots = (data.slots || []);
    const slot = allSlots.find(s => String(s.id) === String(slotId));
    if (!slot) return;
    document.getElementById('slot-modal-id').value = slot.id;
    document.getElementById('slot-starts').value = toDateTimeLocalInput(slot.starts_at);
    document.getElementById('slot-ends').value = toDateTimeLocalInput(slot.ends_at);
    document.getElementById('slot-title').value = slot.titulo || '';
    slotModalTitle.textContent = 'Editar horário';
    showModal();
  }

  // make create modal available via header button if professional
  (function addCreateButtonIfProfessional(){
    const createForm = document.querySelector('form[action*="/schedule/slots/create"]');
    if (!createForm) return;
    const header = calendarEl.querySelector('.calendar-controls');
    if (!header) return;
    const btn = document.createElement('button');
    btn.className = 'btn btn-primary btn-sm';
    btn.textContent = 'Novo horário';
    btn.style.marginLeft = '12px';
    btn.addEventListener('click', (e) => { e.preventDefault(); openCreateModal(); });
    header.appendChild(btn);
  })();

  function openCreateModal() {
    // clear fields
    document.getElementById('slot-modal-id').value = '';
    document.getElementById('slot-starts').value = '';
    document.getElementById('slot-ends').value = '';
    document.getElementById('slot-title').value = '';
    slotModalTitle.textContent = 'Novo horário';
    showModal();
  }

  render();
});
