<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/models/users.php';

session_start();
require_login();
require_role(['admin']); // только admin

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();

    if ($method === 'GET') {
        // Один пользователь по id — для модалки редактирования
        if (!empty($_GET['id'])) {
            $user = users_get($pdo, (int)$_GET['id']);
            if (!$user) {
                json_response(false, null, 'Пользователь не найден', 404);
            }
            json_response(true, ['user' => $user]);
        }

        // Список
        $rows = users_list($pdo);
        json_response(true, ['users' => $rows]);
    }

    if ($method === 'POST') {
        $input = json_input();
        verify_csrf($input['csrf'] ?? null);

        $errors = validate_user($input);
        if ($errors) {
            json_response(false, null, implode('; ', $errors), 422);
        }

        $id = users_create($pdo, $input);
        log_action($pdo, current_user_id(), 'create', 'users', $id);
        json_response(true, ['id' => $id]);
    }

    if ($method === 'PUT') {
        $input = json_input();
        verify_csrf($input['csrf'] ?? null);

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Не указан id', 422);
        }

        // Сам себя не заблокируешь и не сменишь роль
        if ($id === current_user_id()) {
            if (isset($input['role']) && $input['role'] !== current_user()['role']) {
                json_response(false, null, 'Нельзя менять свою роль', 400);
            }
            if (isset($input['is_active']) && !$input['is_active']) {
                json_response(false, null, 'Нельзя заблокировать себя', 400);
            }
        }

        $errors = validate_user_update($input, $id);
        if ($errors) {
            json_response(false, null, implode('; ', $errors), 422);
        }

        users_update($pdo, $id, $input);
        log_action($pdo, current_user_id(), 'update', 'users', $id);
        json_response(true, ['id' => $id]);
    }

    json_response(false, null, 'Метод не поддерживается', 405);
} catch (Throwable $e) {
    json_response(false, null, 'Ошибка сервера: ' . $e->getMessage(), 500);
}