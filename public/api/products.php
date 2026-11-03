<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/models/products.php';
require_once __DIR__ . '/../../src/models/reports.php';

session_start();

require_login();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();

    if ($method === 'GET') {
        // Категории для селекта
        if (isset($_GET['categories'])) {
            json_response(true, ['categories' => categories_list($pdo)]);
        }

        // Один товар
        if (!empty($_GET['id'])) {
            $product = products_get($pdo, (int)$_GET['id']);
            if (!$product) {
                json_response(false, null, 'Товар не найден', 404);
            }

            // ?id=X&detail=1 — детальная информация (график, операции, audit)
            if (!empty($_GET['detail'])) {
                $detail = reports_product_detail($pdo, (int)$_GET['id']);
                json_response(true, array_merge(['product' => $product], $detail));
            }

            json_response(true, ['product' => $product]);
        }

        // Список
        $search  = sanitize($_GET['search'] ?? '');
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        $data = products_list($pdo, $search, $perPage, $offset);
        json_response(true, $data);
    }

    require_role(['admin', 'manager']);

    if ($method === 'POST') {
        $input = json_input();
        verify_csrf($input['csrf'] ?? null);

        $errors = validate_product($input);
        if ($errors) {
            json_response(false, null, implode('; ', $errors), 422);
        }

        $id = products_create($pdo, $input);
        log_action($pdo, current_user_id(), 'create', 'products', $id);
        json_response(true, ['id' => $id]);
    }

    if ($method === 'PUT') {
        $input = json_input();
        verify_csrf($input['csrf'] ?? null);

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Не указан id', 422);
        }
        $errors = validate_product($input, $id);
        if ($errors) {
            json_response(false, null, implode('; ', $errors), 422);
        }

        products_update($pdo, $id, $input);
        log_action($pdo, current_user_id(), 'update', 'products', $id);
        json_response(true, ['id' => $id]);
    }

    if ($method === 'DELETE') {
        $input = json_input();
        verify_csrf($input['csrf'] ?? null);

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Не указан id', 422);
        }
        products_delete($pdo, $id);
        log_action($pdo, current_user_id(), 'delete', 'products', $id);
        json_response(true, ['id' => $id]);
    }

    json_response(false, null, 'Метод не поддерживается', 405);
} catch (Throwable $e) {
    json_response(false, null, 'Ошибка сервера: ' . $e->getMessage(), 500);
}