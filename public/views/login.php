<?php
declare(strict_types=1);
if (!function_exists('csrf_token')) {
    require_once __DIR__ . '/../../src/helpers.php';
}
$csrf = csrf_token();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — Склад</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<main class="login-wrap">
    <div class="login-card">
        <h1>Склад</h1>
        <p class="subtitle">Войдите, чтобы продолжить</p>

        <form id="login-form" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

            <div class="form-row">
                <label for="login">Логин</label>
                <input type="text" id="login" name="login" autocomplete="username" required>
            </div>
            <div class="form-row">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>

            <div class="form-error" id="login-error"></div>

            <button type="submit" class="btn btn-primary" style="width:100%">Войти</button>
        </form>

        <p class="hint">admin / admin &nbsp;·&nbsp; manager / manager &nbsp;·&nbsp; worker / worker</p>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  const err  = document.getElementById('login-error');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    err.textContent = '';
    const body = new URLSearchParams(new FormData(form));
    try {
      const res  = await fetch('api/auth.php?action=login', { method: 'POST', body });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Ошибка входа');
    window.location.href = '?page=menu';
    } catch (e2) {
      err.textContent = e2.message;
    }
  });
});
</script>
</body>
</html>