<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/models/audit.php';

session_start();
require_login();
require_role(['admin']); // только admin

try {
    $pdo = db();

    // Список + фильтры + пагинация
    if (!empty($_GET['distinct'])) {
        json_response(true, audit_distinct($pdo));
    }

    $filters = [
        'user_id' => $_GET['user_id'] ?? null,
        'action'  => sanitize($_GET['action'] ?? ''),
        'entity'  => sanitize($_GET['entity'] ?? ''),
        'from'    => sanitize($_GET['from'] ?? ''),
        'to'      => sanitize($_GET['to'] ?? ''),
    ];

    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 30)));
    $offset  = ($page - 1) * $perPage;

    $data = audit_list($pdo, $filters, $perPage, $offset);
    json_response(true, $data);
} catch (Throwable $e) {
    json_response(false, null, 'Ошибка сервера: ' . $e->getMessage(), 500);
}