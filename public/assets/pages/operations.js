document.addEventListener('DOMContentLoaded', () => {
  const role       = window.OPERATIONS_ROLE || 'worker';
  const form       = document.getElementById('operation-form');
  const formErr    = document.getElementById('operation-form-error');
  const select     = form.product_id;
  const typeSel    = form.type;
  const container  = document.getElementById('operations-table');
  const pager      = document.getElementById('operations-pager');

  const fType      = document.getElementById('f-type');
  const fProduct   = document.getElementById('f-product');
  const fUser      = document.getElementById('f-user'); // может отсутствовать
  const fFrom      = document.getElementById('f-from');
  const fTo        = document.getElementById('f-to');
  const fReset     = document.getElementById('f-reset');

  const canDelete  = role === 'admin';
  const state      = { page: 1, perPage: 30 };

  async function loadProducts() {
    try {
      const data = await App.apiGet('api/products.php?search=&page=1&per_page=1000');
      const opts = data.items.map(p =>
        `<option value="${p.id}">${App.esc(p.sku + ' — ' + p.name)} (ост. ${p.quantity})</option>`
      ).join('');
      select.innerHTML    = '<option value="">— выберите товар —</option>' + opts;
      fProduct.innerHTML  = '<option value="">Все товары</option>' + opts;
    } catch (e) { App.error(e); }
  }

  async function loadUsers() {
    if (!fUser) return;
    try {
      const data = await App.apiGet('api/users.php');
      fUser.innerHTML = '<option value="">Все пользователи</option>' +
        (data.users || []).map(u => `<option value="${u.id}">${App.esc(u.full_name)}</option>`).join('');
    } catch (e) { /* manager не имеет доступа к api/users — молча игнорируем */ }
  }

  function buildQuery() {
    const qs = new URLSearchParams();
    qs.set('page', state.page);
    qs.set('per_page', state.perPage);
    if (fType.value)    qs.set('type', fType.value);
    if (fProduct.value) qs.set('product_id', fProduct.value);
    if (fUser && fUser.value) qs.set('user_id', fUser.value);
    if (fFrom.value)    qs.set('from', fFrom.value);
    if (fTo.value)      qs.set('to', fTo.value);
    return qs.toString();
  }

  async function loadOperations() {
    try {
      const data = await App.apiGet('api/operations.php?' + buildQuery());
      renderRows(data.items || []);
      renderPager(data.total, data.page, data.per_page);
    } catch (e) { App.error(e); }
  }

  function renderRows(rows) {
    const typeMap = { in: ['in', 'Приход'], out: ['out', 'Расход'], writeoff: ['writeoff', 'Списание'] };
    const cols = [
      { label: 'Дата', render: r => App.formatDate(r.created_at) },
      { label: 'Тип',  render: r => {
          const [cls, label] = typeMap[r.type] || ['', r.type];
          return `<span class="badge ${cls}">${label}</span>`;
      }},
      { key: 'sku', label: 'SKU' },
      { key: 'product_name', label: 'Товар' },
      { key: 'qty', label: 'Кол-во' },
      { key: 'comment', label: 'Комментарий' },
      { key: 'user_name', label: 'Пользователь' },
    ];
    if (canDelete) {
      cols.push({
        label: 'Действие',
        render: r => `<button class="btn btn-danger btn-sm" data-del="${r.id}" title="Удалить">🗑</button>`,
      });
    }
    container.innerHTML = App.renderTable(rows, cols);
  }

  function renderPager(total, page, perPage) {
    const pages = Math.max(1, Math.ceil(total / perPage));
    if (pages <= 1) { pager.innerHTML = ''; return; }

    // Показываем максимум 7 страниц вокруг текущей
    const start = Math.max(1, page - 3);
    const end   = Math.min(pages, start + 6);
    let html = '';
    if (start > 1) {
      html += `<button class="btn btn-ghost" data-page="1">1</button>`;
      if (start > 2) html += `<span class="muted" style="align-self:center">…</span>`;
    }
    for (let i = start; i <= end; i++) {
      html += `<button class="btn ${i === page ? 'active' : 'btn-ghost'}" data-page="${i}">${i}</button>`;
    }
    if (end < pages) {
      if (end < pages - 1) html += `<span class="muted" style="align-self:center">…</span>`;
      html += `<button class="btn btn-ghost" data-page="${pages}">${pages}</button>`;
    }
    pager.innerHTML = html;
  }

  pager.addEventListener('click', e => {
    const b = e.target.closest('button[data-page]');
    if (!b) return;
    state.page = Number(b.dataset.page);
    loadOperations();
  });

  // Фильтры
  [fType, fProduct, fFrom, fTo].forEach(el => {
    if (!el) return;
    el.addEventListener('change', () => { state.page = 1; loadOperations(); });
  });
  if (fUser) fUser.addEventListener('change', () => { state.page = 1; loadOperations(); });

  fReset.addEventListener('click', () => {
    fType.value = '';
    fProduct.value = '';
    if (fUser) fUser.value = '';
    fFrom.value = '';
    fTo.value = '';
    state.page = 1;
    loadOperations();
  });

  // Удаление
  container.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-del]');
    if (!btn || !canDelete) return;
    const id = Number(btn.dataset.del);
    if (!confirm('Удалить операцию #' + id + '?\n\nОстаток будет пересчитан (откат операции).')) return;
        try {
      const res = await App.apiDelete('api/operations.php', { id });
      App.success(`Операция удалена. Новый остаток: ${res.new_quantity}`);
      await loadProducts();   // обновляем остатки в селектах
      loadOperations();
    } catch (err) {
      App.error(err);
    }
  });

  // Создание операции
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    formErr.textContent = '';

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

      const payload = {
        type:       typeSel.value,
        product_id: Number(select.value || 0),
        qty:        Number(form.qty.value || 0),
        comment:    form.comment.value.trim(),
      };
    if (!payload.product_id) { formErr.textContent = 'Выберите товар'; if (submitBtn) submitBtn.disabled = false; return; }
    if (!(payload.qty > 0))  { formErr.textContent = 'Количество должно быть > 0'; if (submitBtn) submitBtn.disabled = false; return; }

    try {
      await App.apiPost('api/operations.php', payload);
      App.success('Операция проведена');
      form.reset();
      await loadProducts();
      loadOperations();
    } catch (err) {
      formErr.textContent = err.message;
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  });

  // Инициализация
  loadProducts();
  loadUsers();
  loadOperations();
});