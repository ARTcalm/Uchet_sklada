document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('password-form');
  const err  = document.getElementById('password-form-error');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    err.textContent = '';

    const payload = {
      old_password:    form.old_password.value,
      new_password:    form.new_password.value,
      repeat_password: form.repeat_password.value,
    };

    if (payload.new_password !== payload.repeat_password) {
      err.textContent = 'Пароли не совпадают';
      return;
    }

    try {
      const data = await App.apiPost('api/auth.php?action=change_password', payload);
      // API ротирует CSRF — сохраняем новый токен
      if (data && data.csrf) {
        App.csrf = data.csrf;
      }
      App.success('Пароль изменён');
      form.reset();
    } catch (e2) {
      err.textContent = e2.message;
    }
  });
});