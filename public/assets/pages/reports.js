document.addEventListener('DOMContentLoaded', () => {
  const tabButtons = document.querySelectorAll('.tab-btn');
  const tabs       = document.querySelectorAll('.report-tab');

  function showTab(name) {
    tabButtons.forEach(b => {
      const isActive = b.dataset.tab === name;
      b.classList.toggle('active', isActive);
      b.classList.toggle('btn-primary', isActive); // на случай, если где-то остался btn-primary
    });
    tabs.forEach(t => {
      t.style.display = t.dataset.tab === name ? '' : 'none';
    });
  }

  tabButtons.forEach(b => {
    b.addEventListener('click', () => showTab(b.dataset.tab));
  });

  showTab('stock');

   // ---------- Отчёт «Остатки» ----------
  const stockTable   = document.getElementById('stock-table');
  const stockCat     = document.getElementById('stock-category');
  const stockProduct = document.getElementById('stock-product');
  const stockSearch  = document.getElementById('stock-search');
  const stockExport  = document.getElementById('stock-export');
  const stockReset   = document.getElementById('stock-reset');

  async function loadCategories() {
    try {
      const data = await App.apiGet('api/products.php?categories=1');
      stockCat.innerHTML = '<option value="">Все категории</option>' +
        (data.categories || []).map(c => `<option value="${c.id}">${App.esc(c.name)}</option>`).join('');
    } catch (e) { /* ignore */ }
  }

  async function loadProductsForFilter() {
    try {
      const data = await App.apiGet('api/products.php?search=&page=1&per_page=1000');
      stockProduct.innerHTML = '<option value="">Все товары</option>' +
        data.items.map(p => `<option value="${p.id}">${App.esc(p.sku + ' — ' + p.name)}</option>`).join('');
    } catch (e) { /* ignore */ }
  }

  function stockQuery() {
    const qs = new URLSearchParams();
    if (stockCat.value)     qs.set('category_id', stockCat.value);
    if (stockProduct.value) qs.set('product_id',  stockProduct.value);
    if (stockSearch.value.trim()) qs.set('search', stockSearch.value.trim());
    return qs.toString();
  }

  async function loadStock() {
    try {
      const qs = stockQuery();
      const data = await App.apiGet('api/reports.php?type=stock' + (qs ? '&' + qs : ''));
      renderStock(data.rows || []);
    } catch (e) { App.error(e); }
  }

  function renderStock(rows) {
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
      { label: 'Стоимость', render: r => App.money(Number(r.quantity) * Number(r.price)) },
    ];
    stockTable.innerHTML = App.renderTable(rows, cols);
  }

  // Дебаунс для поиска, чтобы не дёргать сервер на каждое нажатие
  const debouncedStockSearch = App.debounce(loadStock, 300);

  stockSearch.addEventListener('input', debouncedStockSearch);
  stockCat.addEventListener('change', loadStock);
  stockProduct.addEventListener('change', loadStock);

  stockReset.addEventListener('click', () => {
    stockSearch.value = '';
    stockCat.value = '';
    stockProduct.value = '';
    loadStock();
  });

  stockExport.addEventListener('click', () => {
    const qs = stockQuery();
    window.location.href = 'api/reports.php?type=export_stock' + (qs ? '&' + qs : '');
  });

  // ---------- Отчёт «Движение» ----------
  const movementTable = document.getElementById('movement-table');
  const fromIn        = document.getElementById('move-from');
  const toIn          = document.getElementById('move-to');
  const productSel    = document.getElementById('move-product');
  const runBtn        = document.getElementById('move-run');
  const exportBtn     = document.getElementById('move-export');

  async function loadProducts() {
    try {
      const data = await App.apiGet('api/products.php?search=&page=1&per_page=1000');
      productSel.innerHTML = '<option value="">Все товары</option>' +
        data.items.map(p => `<option value="${p.id}">${App.esc(p.sku + ' — ' + p.name)}</option>`).join('');
    } catch (e) { /* ignore */ }
  }

  function movementQs() {
    const qs = new URLSearchParams();
    qs.set('from', fromIn.value);
    qs.set('to', toIn.value);
    if (productSel.value) qs.set('product_id', productSel.value);
    return qs.toString();
  }

  async function loadMovement() {
    if (!fromIn.value || !toIn.value) { App.toast('Укажите период', 'info'); return; }
    try {
      const data = await App.apiGet('api/reports.php?type=movement&' + movementQs());
      renderMovement(data.rows || []);
    } catch (e) { App.error(e); }
  }

  function renderMovement(rows) {
    const typeMap = { in: 'Приход', out: 'Расход', writeoff: 'Списание' };
    const cols = [
      { label: 'Дата', render: r => App.formatDate(r.created_at) },
      { label: 'Тип', render: r => `<span class="badge ${r.type}">${typeMap[r.type] || r.type}</span>` },
      { key: 'sku', label: 'SKU' },
      { key: 'product_name', label: 'Товар' },
      { key: 'qty', label: 'Кол-во' },
      { key: 'comment', label: 'Комментарий' },
      { key: 'user_name', label: 'Пользователь' },
    ];
    movementTable.innerHTML = App.renderTable(rows, cols);
  }

  runBtn.addEventListener('click', loadMovement);
  exportBtn.addEventListener('click', () => {
    if (!fromIn.value || !toIn.value) { App.toast('Укажите период', 'info'); return; }
    window.location.href = 'api/reports.php?type=export_movement&' + movementQs();
  });


  loadCategories();
  loadProducts();
  loadProductsForFilter();
  loadStock();
});