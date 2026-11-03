<?php
declare(strict_types=1);
require_once __DIR__ . '/../partials/layout_header.php';

$tiles = [
    'dashboard'  => [
        'label' => 'Дашборд',
        'desc'  => 'Ключевые метрики склада и последние операции',
        'icon'  => '📊',
        'roles' => ['admin', 'manager', 'worker'],
    ],
    'products'   => [
        'label' => 'Товары',
        'desc'  => 'Каталог, остатки, цены, категории',
        'icon'  => '📦',
        'roles' => ['admin', 'manager'],
    ],
    'operations' => [
        'label' => 'Операции',
        'desc'  => 'Приёмка, отгрузка, списание',
        'icon'  => '🔄',
        'roles' => ['admin', 'manager', 'worker'],
    ],
    'reports'    => [
        'label' => 'Отчёты',
        'desc'  => 'Остатки и движение за период, экспорт CSV',
        'icon'  => '📈',
        'roles' => ['admin', 'manager'],
    ],
    'users'      => [
        'label' => 'Пользователи',
        'desc'  => 'Учётные записи и роли',
        'icon'  => '👥',
        'roles' => ['admin'],
    ],
    'audit'      => [
        'label' => 'Журнал действий',
        'desc'  => 'Кто, что и когда делал в системе',
        'icon'  => '📜',
        'roles' => ['admin'],
    ],
    'profile'    => [
        'label' => 'Профиль',
        'desc'  => 'Личные данные и смена пароля',
        'icon'  => '👤',
        'roles' => ['admin', 'manager', 'worker'],
    ],
];

// Нумерация только по видимым для роли плиткам
$visible = array_filter($tiles, fn($t) => in_array($user['role'], $t['roles'], true));
$num = 0;
?>

<h1 class="page-title">Добро пожаловать, <?= e($user['full_name']) ?></h1>
<p class="page-subtitle">Выберите раздел для работы</p>

<div class="menu-grid">
    <?php foreach ($visible as $key => $tile): ?>
        <?php $num++; ?>
        <a class="menu-tile" href="?page=<?= urlencode($key) ?>">
            <div class="menu-tile-icon"><?= $tile['icon'] ?></div>
            <div class="menu-tile-body">
                <div class="menu-tile-title"><?= e($tile['label']) ?></div>
                <div class="menu-tile-desc"><?= e($tile['desc']) ?></div>
            </div>
            <div class="menu-tile-arrow">→</div>
            <div class="menu-tile-num"><?= str_pad((string)$num, 2, '0', STR_PAD_LEFT) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../partials/layout_footer.php'; ?>