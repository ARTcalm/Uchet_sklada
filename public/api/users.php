<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/models/users.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_login();
require_role(['admin']); // только admin

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();
    $cid = current_company_id();
    $uid = current_user_id();

    if ($method === 'GET') {
        // Один пользователь
        if (!empty($_GET['id'])) {
            $user = users_get($pdo, (int)$_GET['id'], $cid);
            if (!$user) {
                json_response(false, null, 'Пользователь не найден', 404);
            }
            json_response(true, ['user' => $user]);
        }

        // Список
        $rows = users_list($pdo, $cid);
        json_response(true, ['users' => $rows]);
    }

    if ($method === 'POST') {
        $input = json_input();
        verify_csrf($input['csrf'] ?? null);

        $errors = validate_user($input, $cid);
        if ($errors) {
            json_response(false, null, implode('; ', $errors), 422);
        }

        $id = users_create($pdo, $input, $cid);
        log_action($pdo, $uid, 'create', 'users', $id);
        json_response(true, ['id' => $id]);
    }

    if ($method === 'PUT') {
        $input = json_input();
        verify_csrf($input['csrf'] ?? null);

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Не указан id', 422);
        }

        // Быстрая блокировка/разблокировка
        if (isset($_GET['action']) && $_GET['action'] === 'toggle') {
            if ($id === $uid) {
                json_response(false, null, 'Нельзя заблокировать себя', 400);
            }
            if (!isset($input['is_active'])) {
                json_response(false, null, 'Не указан is_active', 422);
            }

            // Проверяем, что пользователь из этой компании
            $check = $pdo->prepare('SELECT 1 FROM users WHERE id = :id AND company_id = :cid');
            $check->execute([':id' => $id, ':cid' => $cid]);
            if (!$check->fetchColumn()) {
                json_response(false, null, 'Пользователь не найден', 404);
            }

            $stmt = $pdo->prepare('UPDATE users SET is_active = :a WHERE id = :id AND company_id = :cid');
            $stmt->execute([':a' => $input['is_active'] ? 1 : 0, ':id' => $id, ':cid' => $cid]);

            log_action($pdo, $uid, 'update', 'users', $id);
            json_response(true, ['id' => $id]);
        }

        // Полное редактирование
        if ($id === $uid) {
            if (isset($input['role']) && $input['role'] !== current_user()['role']) {
                json_response(false, null, 'Нельзя менять свою роль', 400);
            }
            if (isset($input['is_active']) && !$input['is_active']) {
                json_response(false, null, 'Нельзя заблокировать себя', 400);
            }
        }

        $errors = validate_user_update($input, $id, $cid);
        if ($errors) {
            json_response(false, null, implode('; ', $errors), 422);
        }

        users_update($pdo, $id, $cid, $input);
        log_action($pdo, $uid, 'update', 'users', $id);
        json_response(true, ['id' => $id]);
    }

    json_response(false, null, 'Метод не поддерживается', 405);
} catch (Throwable $e) {
    json_response(false, null, 'Ошибка сервера: ' . $e->getMessage(), 500);
}