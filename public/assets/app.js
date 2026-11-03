/* ============================================================
 * app.js — общий модуль
 * - apiGet / apiPost / apiDelete
 * - renderTable(rows, columns) -> HTML
 * - работа с <dialog> и тостами
 * - CSRF-токен и текущий пользователь
 * ============================================================ */

const App = {
  csrf: null,
  user: null,

  init(user, csrf) {
    this.user = user;
    this.csrf = csrf;
  },

  // ---------- API ----------
  async request(url, options = {}) {
    const opts = Object.assign({ credentials: 'same-origin' }, options);
    const res  = await fetch(url, opts);
    let data;
    try {
      data = await res.json();
    } catch (e) {
      throw new Error(`Некорректный ответ сервера (${res.status})`);
    }
    if (!data || data.ok !== true) {
      const msg = (data && data.error) || `Ошибка ${res.status}`;
      throw new Error(msg);
    }
    return data.data;
  },

  apiGet(url) {
    return this.request(url);
  },

  async apiPost(url, body = {}, method = 'POST') {
    return this.request(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ csrf: this.csrf }, body)),
    });
  },

  apiDelete(url, body = {}) {
    return this.apiPost(url, body, 'DELETE');
  },

  apiPostForm(url, formData) {
    return this.request(url, { method: 'POST', body: formData });
  },

  // ---------- Рендер таблиц ----------
  /**
   * rows — массив объектов
   * columns — [{ key, label, render?(row), className? }]
   */
  renderTable(rows, columns) {
    if (!rows || rows.length === 0) {
      return `<div class="empty">Нет данных</div>`;
    }
    const head = columns.map(c => `<th>${this.esc(c.label)}</th>`).join('');
    const body = rows.map(row => {
      const tds = columns.map(c => {
        const raw = c.render ? c.render(row) : row[c.key];
        const cls = typeof c.className === 'function'
          ? c.className(row)
          : (c.className || '');
        return `<td class="${this.esc(cls)}">${raw === null || raw === undefined ? '' : raw}</td>`;
      }).join('');
      return `<tr>${tds}</tr>`;
    }).join('');
    return `<table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
  },

  esc(s) {
    if (s === null || s === undefined) return '';
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  },

  money(v) {
    return Number(v).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  },

  // ---------- Тосты ----------
  toast(message, type = 'info', timeout = 3200) {
    let stack = document.querySelector('.toast-stack');
    if (!stack) {
        stack = document.createElement('div');
        stack.className = 'toast-stack';
        stack.setAttribute('role', 'status');
        stack.setAttribute('aria-live', 'polite');
        document.body.appendChild(stack);
    }
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = message;
    stack.appendChild(el);
    setTimeout(() => el.remove(), timeout);
  },

  error(e) { this.toast(e.message || String(e), 'error'); },
  success(m) { this.toast(m, 'success'); },

  // ---------- Диалоги ----------
  openDialog(id) {
    const d = document.getElementById(id);
    if (d && typeof d.showModal === 'function') d.showModal();
  },
  closeDialog(id) {
    const d = document.getElementById(id);
    if (d) d.close();
  },

  // ---------- Дебаунс ----------
  debounce(fn, ms = 300) {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), ms);
    };
  },

  // ---------- Выход ----------
  async logout() {
    try {
      const data = await this.apiPost('api/auth.php?action=logout');
      window.location.href = (data && data.redirect) || '?page=login';
    } catch (e) {
      this.error(e);
    }
  },
};

/* ---------- Хелпер: экранирование даты --------- */
App.formatDate = function (s) {
  if (!s) return '';
  const d = new Date(s.replace(' ', 'T'));
  if (isNaN(d)) return s;
  const pad = n => String(n).padStart(2, '0');
  return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

window.App = App;

// Анимация появления строк таблиц
const _origRenderTable = App.renderTable.bind(App);
App.renderTable = function (rows, columns) {
  const html = _origRenderTable(rows, columns);
  if (html.indexOf('<table') === 0) {
    // Добавляем класс к обёртке и запускаем анимацию после вставки
    setTimeout(() => {
      document.querySelectorAll('table tbody tr').forEach((tr, i) => {
        tr.style.animation = `fadeIn .25s ease-out ${i * 0.02}s both`;
      });
    }, 0);
  }
  return html;
};