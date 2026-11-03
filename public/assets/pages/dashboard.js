document.addEventListener('DOMContentLoaded', async () => {
  let data;
  try {
    data = await App.apiGet('api/reports.php?type=dashboard');
  } catch (e) {
    App.error(e);
    return;
  }

  const t = data.totals || {};

  // ---------- Верхний ряд ----------
  document.getElementById('c-total').textContent = t.products ?? 0;
  document.getElementById('c-total-sub').textContent =
    t.new_products > 0 ? `+${t.new_products} за 7 дней` : 'без новых';

  document.getElementById('c-value').textContent = App.money(t.value ?? 0);

  const lowCard = document.getElementById('c-low-card');
  document.getElementById('c-low').textContent = t.low_stock ?? 0;
  document.getElementById('c-low-sub').textContent =
    t.low_stock > 0 ? 'требуют пополнения' : 'всё в норме';
  lowCard.classList.toggle('warn', (t.low_stock || 0) > 0);

  document.getElementById('c-ops').textContent = t.ops_today ?? 0;
  document.getElementById('c-ops-sub').textContent =
    t.stale_products > 0
      ? `${t.stale_products} товаров без движения 30д`
      : 'все товары в обороте';

  // ---------- График активности ----------
  renderActivity(data.activity || [], data.summary_7d || {});

  // ---------- Ниже минимума ----------
  renderLowStock(data.low_stock_items || []);

  // ---------- Топ по стоимости ----------
  renderTopValue(data.top_value_items || []);

  // ---------- Последние операции ----------
  renderRecent(data.recent_operations || []);

  // ============================================================
  function renderActivity(days, summary) {
    const root = document.getElementById('activity-chart');
    if (!days.length) { root.innerHTML = '<div class="empty">Нет данных</div>'; return; }

    // Максимум для масштабирования
    const max = Math.max(
      1,
      ...days.map(d => Math.max(d.in_qty, d.out_qty, d.wo_qty))
    );

    const dayNames = ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'];

    root.innerHTML = days.map(d => {
      const dt     = new Date(d.date + 'T00:00:00');
      const label  = dayNames[dt.getDay()] + ' ' + String(dt.getDate()).padStart(2, '0');
      const total  = d.in_qty + d.out_qty + d.wo_qty;
      const hIn    = Math.round((d.in_qty  / max) * 100);
      const hOut   = Math.round((d.out_qty / max) * 100);
      const hWo    = Math.round((d.wo_qty  / max) * 100);

      return `
        <div class="activity-col" title="${total ? 'Всего: ' + total : 'Нет операций'}">
          <div class="bars">
            <div class="bar in"       style="height:${hIn}%"  title="Приход: ${d.in_qty}"></div>
            <div class="bar out"      style="height:${hOut}%" title="Расход: ${d.out_qty}"></div>
            <div class="bar writeoff" style="height:${hWo}%"  title="Списание: ${d.wo_qty}"></div>
          </div>
          <div class="activity-label">${label}</div>
        </div>
      `;
    }).join('');

    // Итоги
    const sum = (k, f) => (summary[k] ? summary[k][f] : 0);
    document.getElementById('activity-summary').innerHTML = `
      <div class="summary-item">
        <span class="badge in">Приход</span>
        <strong>${sum('in','qty')}</strong> ед. / ${sum('in','cnt')} оп.
      </div>
      <div class="summary-item">
        <span class="badge out">Расход</span>
        <strong>${sum('out','qty')}</strong> ед. / ${sum('out','cnt')} оп.
      </div>
      <div class="summary-item">
        <span class="badge writeoff">Списание</span>
        <strong>${sum('writeoff','qty')}</strong> ед. / ${sum('writeoff','cnt')} оп.
      </div>
    `;
  }

  function renderLowStock(rows) {
    const el = document.getElementById('low-stock-table');
    if (!rows.length) {
      el.innerHTML = '<div class="empty">Все товары выше минимального остатка</div>';
      return;
    }
    el.innerHTML = App.renderTable(rows, [
      { key: 'sku', label: 'SKU' },
      { key: 'name', label: 'Товар' },
      { label: 'Остаток / мин.', render: r =>
          `<span class="num-low">${r.quantity}</span> / ${r.min_stock} ${App.esc(r.unit)}` },
      { label: 'Дефицит', render: r =>
          `<span class="num-low">−${r.deficit}</span>` },
    ]);
  }

  function renderTopValue(rows) {
    const el = document.getElementById('top-value-table');
    if (!rows.length) {
      el.innerHTML = '<div class="empty">На складе нет товаров с остатком</div>';
      return;
    }
    el.innerHTML = App.renderTable(rows, [
      { key: 'sku', label: 'SKU' },
      { key: 'name', label: 'Товар' },
      { label: 'Кол-во', render: r => `${r.quantity} ${App.esc(r.unit)}` },
      { label: 'Стоимость', render: r => App.money(r.total_value) },
    ]);
  }

  function renderRecent(rows) {
    const el = document.getElementById('recent-table');
    if (!rows.length) {
      el.innerHTML = '<div class="empty">Операций пока нет</div>';
      return;
    }
    const typeMap = { in: ['in','Приход'], out: ['out','Расход'], writeoff: ['writeoff','Списание'] };
    el.innerHTML = App.renderTable(rows, [
      { label: 'Дата', render: r => App.formatDate(r.created_at) },
      { label: 'Тип', render: r => {
          const [cls, label] = typeMap[r.type] || ['', r.type];
          return `<span class="badge ${cls}">${label}</span>`;
      }},
      { key: 'sku', label: 'SKU' },
      { key: 'product_name', label: 'Товар' },
      { key: 'qty', label: 'Кол-во' },
      { key: 'user_name', label: 'Кто' },
    ]);
  }
});