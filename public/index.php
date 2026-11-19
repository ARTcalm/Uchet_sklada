<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';

// Обработка API-запросов для встроенного сервера
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($uri, '/api/')) {
    $apiFile = __DIR__ . $uri;
    if (file_exists($apiFile)) {
        require $apiFile;
        exit;
    }
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'API endpoint not found']);
    exit;
}

// Если параметра page нет — показываем лендинг
$page = $_GET['page'] ?? 'welcome';

// Если запросили лендинг напрямую
if ($page === 'welcome') {
    require __DIR__ . '/views/welcome.php';
    exit;
}

// Публичные страницы (доступны без авторизации)
$publicPages = ['login', 'register', 'thanks', 'blog'];
if (in_array($page, $publicPages, true)) {
    $file = __DIR__ . '/views/' . $page . '.php';
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

// Проверка авторизации для защищенных страниц
if (!is_logged_in()) {
    header('Location: ?page=login');
    exit;
}

$allowedPages = ['menu', 'dashboard', 'products', 'product', 'operations', 'users', 'reports', 'audit', 'profile', 'welcome'];
if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    require __DIR__ . '/views/404.php'; // <-- Изменено здесь
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
    'welcome'    => ['admin', 'manager', 'worker'],
];
if (!has_role($pageRoles[$page])) {
    http_response_code(403);
    require __DIR__ . '/views/403.php';
    exit;
}

require __DIR__ . '/views/' . $page . '.php';