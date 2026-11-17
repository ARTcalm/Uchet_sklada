<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/helpers.php';

$isLogged = is_logged_in();

// Если авторизован — ведем в меню, если нет — на главную
$btnLink = $isLogged ? '?page=menu' : '?page=welcome';
$btnText = $isLogged ? 'Перейти в склад' : 'На главную';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Страница не найдена</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <script>
        (function () {
            try {
                const t = localStorage.getItem('theme');
                if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
                else if (t === 'light') document.documentElement.removeAttribute('data-theme');
            } catch (e) {}
        })();
    </script>
</head>
<body>

<div class="error-page">
    <h1 class="error-code">404</h1>
    <h2 class="error-title">Страница не найдена</h2>
    <p class="error-text">
        Кажется, вы свернули не туда. Такой страницы не существует или она была удалена.
    </p>
    
    <div class="error-actions">
        <a href="<?= $btnLink ?>" class="btn btn-primary btn-large">
            <?= $btnText ?>
        </a>
    </div>
</div>

</body>
</html>