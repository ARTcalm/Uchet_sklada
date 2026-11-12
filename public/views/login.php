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
    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=114173620', 'ym');

        ym(114173620, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/114173620" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
</head>
<body class="login-page">
<main class="login-wrap">
    <div class="login-card">
        <h1>Склад</h1>
        <p class="subtitle">Войдите, чтобы продолжить</p>
        <form id="login-form" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <div class="form-row">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" autocomplete="email" required>
            </div>
            <div class="form-row">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <div class="form-error" id="login-error"></div>
            <button type="submit" class="btn btn-primary" style="width:100%">Войти</button>
        </form>
        <p style="text-align:center;margin-top:16px;font-size:13px">
            Нет аккаунта? <a href="?page=register">Зарегистрировать склад</a>
        </p>
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
        window.location.href = data.data.redirect || '?page=menu';
    } catch (e2) {
        err.textContent = e2.message;
    }
  });
});
</script>
</body>
</html>