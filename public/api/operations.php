<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/models/operations.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_login();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();
    $cid = current_company_id();
    $uid = current_user_id();

    if ($method === 'GET') {
        $filters = [
            'type'       => in_array($_GET['type'] ?? '', ['in', 'out', 'writeoff'], true) ? $_GET['type'] : null,
            'product_id' => !empty($_GET['product_id']) ? (int)$_GET['product_id'] : null,
            'user_id'    => !empty($_GET['user_id'])    ? (int)$_GET['user_id']    : null,
            'from'       => sanitize($_GET['from'] ?? ''),
            'to'         => sanitize($_GET['to'] ?? ''),
        ];

        // worker видит только свои операции
        if (current_role() === 'worker' && empty($_GET['all'])) {
            $filters['user_id'] = $uid;
        }

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 50)));
        $offset  = ($page - 1) * $perPage;

        error_log("DEBUG: operations list. from = " . ($_GET['from'] ?? 'null') . ", to = " . ($_GET['to'] ?? 'null'));

        $data = operations_list($pdo, $cid, $filters, $perPage, $offset);
        json_response(true, $data);
    }

    if ($method === 'POST') {
        $input = json_input();
        verify_csrf($input['csrf'] ?? null);

        $errors = validate_operation($input);
        if ($errors) {
            json_response(false, null, implode('; ', $errors), 422);
        }

        $result = operations_create($pdo, $input, $uid, $cid);
        if (empty($result['ok'])) {
            json_response(false, null, $result['error'] ?? 'Ошибка операции', 400);
        }

        log_action($pdo, $uid, 'create', 'operations', (int)$result['id']);
        json_response(true, $result);
    }

    if ($method === 'DELETE') {
        require_role(['admin']);
        $input = json_input();
        verify_csrf($input['csrf'] ?? null);

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Не указан id операции', 422);
        }

        $result = operations_delete($pdo, $id, $cid);
        if (empty($result['ok'])) {
            json_response(false, null, $result['error'] ?? 'Ошибка удаления', 400);
        }

        log_action($pdo, $uid, 'delete', 'operations', $id);
        json_response(true, $result);
    }

    json_response(false, null, 'Метод не поддерживается', 405);
} catch (Throwable $e) {
    json_response(false, null, 'Ошибка сервера: ' . $e->getMessage(), 500);
}