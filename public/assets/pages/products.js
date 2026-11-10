document.addEventListener('DOMContentLoaded', () => {
  const state = { page: 1, perPage: 10, search: '' };
  const tbody    = document.getElementById('products-table');
  const pager    = document.getElementById('products-pager');
  const searchIn = document.getElementById('products-search');
  const form     = document.getElementById('product-form');
  const formErr  = document.getElementById('product-form-error');

  const canEdit = App.user && (App.user.role === 'admin' || App.user.role === 'manager');

  async function load() {
    try {
      const qs = new URLSearchParams({
        search: state.search, page: state.page, per_page: state.perPage,
      });
      const data = await App.apiGet('api/products.php?' + qs.toString());
      renderRows(data.items);
      renderPager(data.total, data.page, data.per_page);
    } catch (e) { App.error(e); }
  }

  function renderRows(rows) {
    const cols = [
      { key: 'sku', label: 'SKU' },
      { key: 'name', label: 'Название' },
      { key: 'category_name', label: 'Категория' },
      { key: 'unit', label: 'Ед.' },
      {
        label: 'Остаток',
        render: r => `<span class="${r.quantity <= r.min_stock ? 'num-low' : ''}">${r.quantity}</span>`,
      },
      { key: 'min_stock', label: 'Мин.' },
      { label: 'Цена', render: r => App.money(r.price) },
      {
          label: 'Действия',
          render: r => {
              const open = `<div class="pr-btns"><a class="btn btn-ghost btn-sm" href="?page=product&id=${r.id}" title="Открыть карточку" aria-label="Открыть карточку товара">👁</a>`;
              if (!canEdit) return open + `</div>`;
              return open
                  + ` <button class="btn btn-ghost btn-sm" data-edit="${r.id}" title="Редактировать" aria-label="Редактировать товар">✎</button>`
                  + ` <button class="btn btn-danger btn-sm" data-del="${r.id}" title="Удалить" aria-label="Удалить товар">🗑</button>`
                  + `</div>`; 
          },
      }
    ];
    tbody.innerHTML = App.renderTable(rows, cols);
  }

  function renderPager(total, page, perPage) {
    const pages = Math.max(1, Math.ceil(total / perPage));
    let html = '';
    for (let i = 1; i <= pages; i++) {
      html += `<button class="btn ${i === page ? 'active' : 'btn-ghost'}" data-page="${i}">${i}</button>`;
    }
    pager.innerHTML = pages > 1 ? html : '';
  }

  // Поиск (debounce)
  searchIn.addEventListener('input', App.debounce(() => {
    state.search = searchIn.value.trim();
    state.page = 1;
    load();
  }, 300));

  // Пагинация + действия
  pager.addEventListener('click', e => {
    const b = e.target.closest('button[data-page]');
    if (!b) return;
    state.page = Number(b.dataset.page);
    load();
  });

  tbody.addEventListener('click', async e => {
    const editBtn = e.target.closest('button[data-edit]');
    const delBtn  = e.target.closest('button[data-del]');

    if (editBtn) {
      const id = Number(editBtn.dataset.edit);
      try {
        const data = await App.apiGet('api/products.php?search=&page=1&per_page=1000');
        const item = data.items.find(x => Number(x.id) === id);
        if (!item) return;
        openForm(item);
      } catch (err) { App.error(err); }
    }

    if (delBtn) {
      const id = Number(delBtn.dataset.del);
      if (!confirm('Удалить товар #' + id + '?')) return;
      try {
        await App.apiDelete('api/products.php', { id });
        App.success('Товар удалён');
        load();
      } catch (err) { App.error(err); }
    }
  });

  // Кнопка "Добавить"
  const addBtn = document.getElementById('product-add');
  if (addBtn) {
    addBtn.addEventListener('click', () => openForm(null));
    addBtn.style.display = canEdit ? '' : 'none';
  }

  function openForm(item) {
    form.reset();
    formErr.textContent = '';
    document.getElementById('product-id').value = item ? item.id : '';
    document.getElementById('product-dialog-title').textContent =
      item ? 'Редактировать товар' : 'Новый товар';

    if (item) {
      form.sku.value         = item.sku;
      form.name.value        = item.name;
      form.category_id.value = item.category_id || '';
      form.unit.value        = item.unit;
      form.quantity.value    = item.quantity;
      form.min_stock.value   = item.min_stock;
      form.price.value       = item.price;
    }
    App.openDialog('product-dialog');
  }

  // Загрузка категорий (select)
  (async () => {
    try {
      const cats = await App.apiGet('api/products.php?categories=1');
      const sel  = form.category_id;
      sel.innerHTML = '<option value="">— без категории —</option>' +
        (cats.categories || []).map(c => `<option value="${c.id}">${App.esc(c.name)}</option>`).join('');
    } catch (e) { /* не критично */ }
  })();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    formErr.textContent = '';

    const sku  = form.sku.value.trim();
    const name = form.name.value.trim();
    const unit = form.unit.value.trim();

    if (sku === '')  { formErr.textContent = 'Укажите SKU';           return; }
    if (name === '') { formErr.textContent = 'Укажите название';      return; }
    if (unit === '') { formErr.textContent = 'Укажите единицу';       return; }

    const id = document.getElementById('product-id').value;
    const payload = {
      sku:         form.sku.value.trim(),
      name:        form.name.value.trim(),
      category_id: form.category_id.value ? Number(form.category_id.value) : null,
      unit:        form.unit.value.trim(),
      quantity:    Number(form.quantity.value || 0),
      min_stock:   Number(form.min_stock.value || 0),
      price:       Number(form.price.value || 0),
    };

    const qty   = Number(form.quantity.value  || 0);
    const minSt = Number(form.min_stock.value || 0);
    const price = Number(form.price.value     || 0);

    const MAX_QTY   = 999999999.999;
    const MAX_PRICE = 999999999.99;

    if (qty   < 0 || qty   > MAX_QTY)   { formErr.textContent = 'Остаток вне допустимого диапазона';      return; }
    if (minSt < 0 || minSt > MAX_QTY)   { formErr.textContent = 'Мин. остаток вне допустимого диапазона'; return; }
    if (price < 0 || price > MAX_PRICE) { formErr.textContent = 'Цена вне допустимого диапазона';         return; }

    try {
      if (id) {
        payload.id = Number(id);
        await App.apiPost('api/products.php', payload, 'PUT');
        App.success('Товар обновлён');
      } else {
        await App.apiPost('api/products.php', payload);
        App.success('Товар создан');
      }
      App.closeDialog('product-dialog');
      load();
    } catch (err) {
      formErr.textContent = err.message;
    }
  });

  document.getElementById('product-cancel').addEventListener('click', () => App.closeDialog('product-dialog'));

  load();
});