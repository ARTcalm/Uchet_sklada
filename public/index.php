<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';

$page = $_GET['page'] ?? 'menu';

if ($page === 'login') {
    require __DIR__ . '/views/login.php';
    exit;
}

if (!is_logged_in()) {
    header('Location: ?page=login');
    exit;
}

$allowedPages = ['menu', 'dashboard', 'products', 'product', 'operations', 'users', 'reports', 'audit', 'profile'];
if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    echo 'Страница не найдена';
    exit;
}

$pageRoles = [
    'menu'       => ['admin', 'manager', 'worker'],
    'dashboard'  => ['admin', 'manager', 'worker'],
    'products'   => ['admin', 'manager'],
    'product'    => ['admin', 'manager'],
    'operations' => ['admin', 'manager', 'worker'],
    'users'      => ['admin'],
    'reports'    => ['admin', 'manager'],
    'audit'      => ['admin'],
    'profile'    => ['admin', 'manager', 'worker'],
];
if (!has_role($pageRoles[$page])) {
    http_response_code(403);
    require __DIR__ . '/views/403.php';
    exit;
}

require __DIR__ . '/views/' . $page . '.php';