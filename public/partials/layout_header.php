<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/auth.php';

if (!is_logged_in()) {
    header('Location: ?page=login');
    exit;
}

$user    = current_user();
$current = $_GET['page'] ?? 'menu';
$csrf    = csrf_token();

$menu = [
    'menu'       => ['label' => 'Главное меню',  'icon' => '🏠', 'roles' => ['admin', 'manager', 'worker']],
    'dashboard'  => ['label' => 'Дашборд',       'icon' => '📊', 'roles' => ['admin', 'manager', 'worker']],
    'products'   => ['label' => 'Товары',        'icon' => '📦', 'roles' => ['admin', 'manager']],
    'operations' => ['label' => 'Операции',      'icon' => '🔄', 'roles' => ['admin', 'manager', 'worker']],
    'reports'    => ['label' => 'Отчёты',        'icon' => '📈', 'roles' => ['admin', 'manager']],
    'users'      => ['label' => 'Пользователи',  'icon' => '👥', 'roles' => ['admin']],
    'audit'      => ['label' => 'Журнал действий','icon' => '📜', 'roles' => ['admin']],
    'profile'    => ['label' => 'Профиль',       'icon' => '👤', 'roles' => ['admin', 'manager', 'worker']],
];

// Следующая функциональная страница (круговой переход) — ПОСЛЕ $menu
$nextPage = in_array($current, ['menu', 'login', '403'], true)
    ? null
    : next_page_for($current, $menu, $user['role']);

$extraTitles = [
    'product' => 'Карточка товара',
];
$pageTitle = $menu[$current]['label'] ?? $extraTitles[$current] ?? 'Склад';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Склад — <?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <script>
        (function () {
            try {
                const t = localStorage.getItem('theme');
                if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            } catch (e) {}
        })();
    </script>
</head>
<body>

<!-- Плавающие кнопки (без топбара) -->
<div class="floating-actions">
    <div class="notif-wrap" id="notif-wrap">
        <button type="button" class="floating-btn" id="notif-toggle" aria-label="Уведомления">
            <span class="notif-icon">🔔</span>
            <span class="notif-badge" id="notif-badge" style="display:none">0</span>
        </button>
        <div class="notif-popover" id="notif-popover">
            <div class="notif-header">
                <strong>Уведомления</strong>
                <span class="muted" id="notif-count-label"></span>
            </div>
            <div class="notif-list" id="notif-list">
                <div class="notif-empty">Загрузка</div>
            </div>
        </div>
    </div>

    <button type="button" class="floating-btn" id="theme-toggle" aria-label="Тема" title="Сменить тему">
        <span class="theme-icon">🌙</span>
    </button>

    <button type="button" class="floating-btn" id="menu-toggle" aria-label="Меню">
        <span class="topbar-menu-icon">☰</span>
    </button>
</div>

<!-- Выезжающее меню -->
<div class="drawer-backdrop" id="drawer-backdrop"></div>
<aside class="drawer" id="drawer">
    <div class="drawer-header">
        <div class="drawer-user">
            <div class="drawer-user-name"><?= e($user['full_name']) ?></div>
            <div class="drawer-user-role"><?= e($user['role']) ?></div>
        </div>
        <button type="button" class="drawer-close" id="drawer-close" aria-label="Закрыть">×</button>
    </div>

    <nav class="drawer-nav">
        <?php foreach ($menu as $key => $item): ?>
            <?php if (in_array($user['role'], $item['roles'], true)): ?>
                <a href="?page=<?= urlencode($key) ?>"
                   class="<?= $key === $current ? 'active' : '' ?>">
                    <span class="nav-icon"><?= $item['icon'] ?></span>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="drawer-footer">
        <button type="button" class="btn btn-ghost" style="width:100%" onclick="App.logout()">Выйти</button>
    </div>
</aside>

<?php if ($nextPage): ?>
    <a class="next-page-btn" href="?page=<?= urlencode($nextPage['key']) ?>" title="Перейти: <?= e($nextPage['label']) ?>">
        <span class="next-page-arrow">→</span>
        <span class="next-page-label"><?= e($nextPage['label']) ?></span>
    </a>
<?php endif; ?>

<main class="main">