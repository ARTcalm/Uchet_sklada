document.addEventListener('DOMContentLoaded', async () => {
  const id = Number(window.PRODUCT_ID || 0);
  if (!id) return;

  let data;
  try {
    data = await App.apiGet('api/products.php?id=' + id + '&detail=1');
  } catch (e) {
    App.error(e);
    return;
  }

  const p = data.product;
  document.getElementById('product-name').textContent = p.name;
  document.getElementById('product-sku').textContent  = `SKU: ${p.sku} · категория: ${p.category_name || '—'} · ед.: ${p.unit}`;

  document.getElementById('p-qty').textContent = p.quantity;
  const low = p.quantity <= p.min_stock;
  document.getElementById('p-qty-sub').textContent = low ? 'ниже минимума!' : 'в норме';
  document.getElementById('p-qty').classList.toggle('num-low', low);

  document.getElementById('p-min').textContent = p.min_stock;
  document.getElementById('p-min-sub').textContent = `ед.: ${p.unit}`;

  document.getElementById('p-price').textContent = App.money(p.price);
  document.getElementById('p-price-sub').textContent = 'за единицу';

  document.getElementById('p-value').textContent = App.money(p.quantity * p.price);

  renderChart(data.activity_30d || []);
  renderOperations(data.operations || []);
  renderAudit(data.audit || []);

  // ---------- График ----------
  function renderChart(days) {
    const root = document.getElementById('product-chart');
    if (!days.length) { root.innerHTML = '<div class="empty">Нет операций за 30 дней</div>'; return; }

    const max = Math.max(1, ...days.map(d => Math.max(d.in_qty, d.out_qty, d.wo_qty)));
    root.innerHTML = days.map(d => {
      const dt    = new Date(d.date + 'T00:00:00');
      const label = String(dt.getDate()).padStart(2, '0') + '.' + String(dt.getMonth() + 1).padStart(2, '0');
      const hIn   = Math.round((d.in_qty  / max) * 100);
      const hOut  = Math.round((d.out_qty / max) * 100);
      const hWo   = Math.round((d.wo_qty  / max) * 100);
      const total = d.in_qty + d.out_qty + d.wo_qty;
      return `
        <div class="activity-col" title="${total ? 'Всего: ' + total : 'Нет операций'} (${label})">
          <div class="bars">
            <div class="bar in"       style="height:${hIn}%"  title="Приход: ${d.in_qty}"></div>
            <div class="bar out"      style="height:${hOut}%" title="Расход: ${d.out_qty}"></div>
            <div class="bar writeoff" style="height:${hWo}%"  title="Списание: ${d.wo_qty}"></div>
          </div>
          <div class="activity-label">${label}</div>
        </div>
      `;
    }).join('');
  }

  // ---------- Операции ----------
  function renderOperations(rows) {
    const el = document.getElementById('product-operations');
    if (!rows.length) { el.innerHTML = '<div class="empty">Операций нет</div>'; return; }
    const typeMap = { in: ['in', 'Приход'], out: ['out', 'Расход'], writeoff: ['writeoff', 'Списание'] };
    el.innerHTML = App.renderTable(rows, [
      { label: 'Дата', render: r => App.formatDate(r.created_at) },
      { label: 'Тип',  render: r => {
          const [cls, label] = typeMap[r.type] || ['', r.type];
          return `<span class="badge ${cls}">${label}</span>`;
      }},
      { key: 'qty', label: 'Кол-во' },
      { key: 'user_name', label: 'Кто' },
      { key: 'comment', label: 'Комментарий' },
    ]);
  }

  // ---------- Audit ----------
  function renderAudit(rows) {
    const el = document.getElementById('product-audit');
    if (!rows.length) { el.innerHTML = '<div class="empty">Записей нет</div>'; return; }
    const actionLabels = {
      create: 'Создание', update: 'Изменение', delete: 'Удаление',
      login: 'Вход', logout: 'Выход', change_password: 'Смена пароля',
    };
    el.innerHTML = App.renderTable(rows, [
      { label: 'Дата', render: r => App.formatDate(r.created_at) },
      { label: 'Действие', render: r => `<span class="badge">${App.esc(actionLabels[r.action] || r.action)}</span>` },
      { key: 'user_name', label: 'Кто' },
    ]);
  }
});