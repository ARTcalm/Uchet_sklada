document.addEventListener('DOMContentLoaded', () => {
  const state = { page: 1, perPage: 30 };

  const table   = document.getElementById('audit-table');
  const pager   = document.getElementById('audit-pager');
  const selAct  = document.getElementById('audit-action');
  const selEnt  = document.getElementById('audit-entity');
  const fromIn  = document.getElementById('audit-from');
  const toIn    = document.getElementById('audit-to');

  const actionLabels = {
    login:           'Вход',
    logout:          'Выход',
    create:          'Создание',
    update:          'Изменение',
    delete:          'Удаление',
    change_password: 'Смена пароля',
  };
  const entityLabels = {
    users:      'Пользователи',
    products:   'Товары',
    operations: 'Операции',
  };

  async function loadDistinct() {
    try {
      const data = await App.apiGet('api/audit.php?distinct=1');
      (data.actions || []).forEach(a => {
        const opt = document.createElement('option');
        opt.value = a;
        opt.textContent = actionLabels[a] || a;
        selAct.appendChild(opt);
      });
      (data.entities || []).forEach(e => {
        const opt = document.createElement('option');
        opt.value = e;
        opt.textContent = entityLabels[e] || e;
        selEnt.appendChild(opt);
      });
    } catch (e) { /* ignore */ }
  }

  async function load() {
    try {
      const qs = new URLSearchParams();
      qs.set('page', state.page);
      qs.set('per_page', state.perPage);
      if (selAct.value)  qs.set('action', selAct.value);
      if (selEnt.value)  qs.set('entity', selEnt.value);
      if (fromIn.value)  qs.set('from', fromIn.value);
      if (toIn.value)    qs.set('to', toIn.value);

      const data = await App.apiGet('api/audit.php?' + qs.toString());
      render(data.items || []);
      renderPager(data.total, data.page, data.per_page);
    } catch (e) { App.error(e); }
  }

  function render(rows) {
    const cols = [
      { label: 'Дата', render: r => App.formatDate(r.created_at) },
      { label: 'Пользователь', render: r =>
          r.user_name !== '—'
            ? `${App.esc(r.user_name)} <span class="muted">(${App.esc(r.user_login || '')})</span>`
            : '<span class="muted">—</span>' },
      { label: 'Действие', render: r =>
          `<span class="badge">${App.esc(actionLabels[r.action] || r.action)}</span>` },
      { label: 'Сущность', render: r =>
          App.esc(entityLabels[r.entity] || r.entity) },
      { label: 'ID', render: r => r.entity_id ?? '—' },
    ];
    table.innerHTML = App.renderTable(rows, cols);
  }

  function renderPager(total, page, perPage) {
    const pages = Math.max(1, Math.ceil(total / perPage));
    let html = '';
    for (let i = 1; i <= pages; i++) {
      html += `<button class="btn ${i === page ? 'active' : 'btn-ghost'}" data-page="${i}">${i}</button>`;
    }
    pager.innerHTML = pages > 1 ? html : '';
  }

  pager.addEventListener('click', e => {
    const b = e.target.closest('button[data-page]');
    if (!b) return;
    state.page = Number(b.dataset.page);
    load();
  });

  [selAct, selEnt, fromIn, toIn].forEach(el => {
    el.addEventListener('change', () => { state.page = 1; load(); });
  });
  document.getElementById('audit-refresh').addEventListener('click', () => {
    state.page = 1;
    load();
  });

  loadDistinct();
  load();
});