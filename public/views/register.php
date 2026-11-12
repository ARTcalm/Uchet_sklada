<?php
declare(strict_types=1);
if (!function_exists('csrf_token')) require_once __DIR__ . '/../../src/helpers.php';
$csrf = csrf_token();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Регистрация склада — Склад</title>
    <link rel="stylesheet" href="assets/style.css">
    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=114161247', 'ym');

        ym(114161247, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/114161247" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
</head>
<body class="login-page">
<main class="login-wrap">
    <div class="login-card">
        <h1>Регистрация склада</h1>
        <p class="subtitle">Создайте аккаунт для своей компании</p>

        <form id="register-form" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

            <div class="form-row">
                <label for="company_name">Название компании *</label>
                <input type="text" id="company_name" name="company_name" required maxlength="255" autocomplete="organization">
            </div>
            <div class="form-row">
                <label for="full_name">Ваше имя *</label>
                <input type="text" id="full_name" name="full_name" required maxlength="255" autocomplete="name">
            </div>
            <div class="form-row">
                <label for="email">Email администратора *</label>
                <input type="email" id="email" name="email" autocomplete="email" required maxlength="255">
            </div>
            <div class="form-row">
                <label for="password">Пароль администратора * (мин. 6 символов)</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required minlength="6">
            </div>
            <div class="form-row">
                <label for="plan">Тариф *</label>
                <?php $selectedPlan = $_GET['plan'] ?? 'business'; ?>
                <select id="plan" name="plan" required>
                    <option value="start"     <?= $selectedPlan === 'start'     ? 'selected' : '' ?>>Старт — 0 ₽/мес (до 50 товаров)</option>
                    <option value="business"  <?= $selectedPlan === 'business'  ? 'selected' : '' ?>>Бизнес — 990 ₽/мес</option>
                    <option value="corporate" <?= $selectedPlan === 'corporate' ? 'selected' : '' ?>>Корпоративный — 2990 ₽/мес</option>
                </select>
            </div>

            <div class="form-error" id="register-error"></div>
            <button type="submit" class="btn btn-primary" style="width:100%">Создать склад</button>
        </form>

        <p style="text-align:center;margin-top:16px;font-size:13px">
            Уже есть аккаунт? <a href="?page=login">Войти</a>
        </p>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('register-form');
  const err  = document.getElementById('register-error');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    err.textContent = '';

    const payload = {
        csrf:         form.csrf.value,
        company_name: form.company_name.value.trim(),
        full_name:    form.full_name.value.trim(),
        email:        form.email.value.trim().toLowerCase(),
        password:     form.password.value,
        plan:         form.plan.value,
    };

    if (!payload.company_name) { err.textContent = 'Введите название компании'; return; }
    if (!payload.full_name)    { err.textContent = 'Введите ваше имя'; return; }
    if (!payload.email)        { err.textContent = 'Введите email'; return; }
    if (payload.password.length < 6) { err.textContent = 'Пароль должен быть не короче 6 символов'; return; }

    try {
        const res  = await fetch('api/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Ошибка регистрации');

        if (window.ym) {
        window.ym(114161247, 'reachGoal', 'form_submit');
        }
        window.location.href = data.data.redirect || '?page=thanks';
    } catch (e2) {
      err.textContent = e2.message;
    }
  });
});
</script>
</body>
</html>