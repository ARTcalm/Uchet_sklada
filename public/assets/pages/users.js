document.addEventListener('DOMContentLoaded', () => {
  const table       = document.getElementById('users-table');

  // Создание
  const formCreate  = document.getElementById('user-form');
  const errCreate   = document.getElementById('user-form-error');

  // Редактирование
  const formEdit    = document.getElementById('user-edit-form');
  const errEdit     = document.getElementById('user-edit-form-error');

  async function load() {
    try {
      const data = await App.apiGet('api/users.php');
      render(data.users || []);
    } catch (e) { App.error(e); }
  }

  function render(rows) {
    const roleMap = { admin: 'admin', manager: 'manager', worker: 'worker' };
    const cols = [
      { key: 'id', label: '#' },
      { key: 'email', label: 'Email' },,
      { key: 'full_name', label: 'Имя' },
      {
        label: 'Роль',
        render: r => `<span class="badge ${roleMap[r.role] || ''}">${r.role}</span>`,
      },
      {
        label: 'Статус',
        render: r => r.is_active
          ? '<span class="badge ok">Активен</span>'
          : '<span class="badge danger">Заблокирован</span>',
      },
      { label: 'Создан', render: r => App.formatDate(r.created_at) },
      {
        label: 'Действия',
        render: r => {
          const isSelf = App.user && Number(App.user.id) === Number(r.id);
          return `
            <button class="btn btn-ghost btn-sm" data-edit="${r.id}">✎ Редактировать</button>
            ${isSelf ? '' : `<button class="btn btn-ghost btn-sm" data-toggle="${r.id}" data-active="${r.is_active ? 1 : 0}">${r.is_active ? 'Блок.' : 'Разблок.'}</button>`}
          `;
        },
      },
    ];
    table.innerHTML = App.renderTable(rows, cols);
  }

  // ----- Кнопка «Редактировать» в таблице -----
  table.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('button[data-edit]');
    if (editBtn) {
      const id = Number(editBtn.dataset.edit);
      try {
        const data = await App.apiGet('api/users.php?id=' + id);
        openEdit(data.user);
      } catch (err) { App.error(err); }
      return;
    }

    const toggleBtn = e.target.closest('button[data-toggle]');
    if (toggleBtn) {
      const id       = Number(toggleBtn.dataset.toggle);
      const isActive = toggleBtn.dataset.active === '1';
      try {
        await App.apiPost('api/users.php?action=toggle', { id, is_active: !isActive }, 'PUT');
        App.success(!isActive ? 'Пользователь активирован' : 'Пользователь заблокирован');
        load();
      } catch (err) { App.error(err); }
    }
  });

  // ----- Модалка редактирования -----
  function openEdit(user) {
    formEdit.reset();
    errEdit.textContent = '';
    formEdit.id.value         = user.id;
    formEdit.email.value      = user.email;
    formEdit.full_name.value  = user.full_name;
    formEdit.role.value       = user.role;
    formEdit.password.value   = '';
    formEdit.is_active.checked = !!user.is_active;

    App.openDialog('user-edit-dialog');
  }

  formEdit.addEventListener('submit', async (e) => {
    e.preventDefault();
    errEdit.textContent = '';

    const payload = {
        id:        Number(formEdit.id.value),
        email:     formEdit.email.value.trim().toLowerCase(),
        full_name: formEdit.full_name.value.trim(),
        role:      formEdit.role.value,
        is_active: formEdit.is_active.checked ? 1 : 0,
    };
    if (formEdit.password.value) {
      payload.password = formEdit.password.value;
    }

    try {
      await App.apiPost('api/users.php', payload, 'PUT');
      App.success('Пользователь сохранён');
      App.closeDialog('user-edit-dialog');
      load();
    } catch (err) {
      errEdit.textContent = err.message;
    }
  });

  document.getElementById('user-edit-cancel').addEventListener('click', () => {
    App.closeDialog('user-edit-dialog');
  });

  // ----- Создание (как было) -----
  document.getElementById('user-add').addEventListener('click', () => {
    formCreate.reset();
    errCreate.textContent = '';
    App.openDialog('user-dialog');
  });
  document.getElementById('user-cancel').addEventListener('click', () => {
    App.closeDialog('user-dialog');
  });

  formCreate.addEventListener('submit', async (e) => {
    e.preventDefault();
    errCreate.textContent = '';
    const payload = {
        email:     formCreate.email.value.trim().toLowerCase(),
        password:  formCreate.password.value,
        full_name: formCreate.full_name.value.trim(),
        role:      formCreate.role.value,
    };
    try {
      await App.apiPost('api/users.php', payload);
      App.success('Пользователь создан');
      formCreate.reset();
      App.closeDialog('user-dialog');
      load();
    } catch (err) {
      errCreate.textContent = err.message;
    }
  });

  load();
});