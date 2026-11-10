<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/models/users.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            require_method('POST');
            verify_csrf();

            $email    = sanitize($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            if ($email === '' || $password === '') {
                json_response(false, null, 'Email и пароль обязательны');
            }

            $pdo  = db();
            $stmt = $pdo->prepare(
                'SELECT u.id, u.company_id, u.email, u.password_hash, u.full_name, u.role, u.is_active,
                        c.name AS company_name
                FROM users u
                JOIN companies c ON c.id = u.company_id
                WHERE u.email = :email LIMIT 1'
            );
            $stmt->execute([':email' => mb_strtolower(trim($email))]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                json_response(false, null, 'Неверный email или пароль');
            }
            if ((int)$user['is_active'] !== 1) {
                json_response(false, null, 'Пользователь заблокирован');
            }

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'           => (int)$user['id'],
                'company_id'   => (int)$user['company_id'],
                'company_name' => $user['company_name'],
                'email'        => $user['email'],
                'full_name'    => $user['full_name'],
                'role'         => $user['role'],
            ];
            rotate_csrf();

            log_action($pdo, (int)$user['id'], 'login', 'users', (int)$user['id']);
            json_response(true, [
                'user' => $_SESSION['user'],
                'csrf' => $_SESSION['csrf'],
                'redirect' => '?page=menu',
            ]);
            break;

        case 'logout':
            require_method('POST');
            verify_csrf();

            if (is_logged_in()) {
                $pdo = db();
                log_action($pdo, (int)$_SESSION['user']['id'], 'logout', 'users', (int)$_SESSION['user']['id']);
            }
            $_SESSION = [];
            session_destroy();
            json_response(true, ['redirect' => '?page=login']);
            break;

        case 'me':
            json_response(true, [
                'user' => $_SESSION['user'] ?? null,
                'csrf' => $_SESSION['csrf'] ?? null,
            ]);
            break;

        case 'change_password':
            require_method('POST');
            require_login();

            $input = json_input();
            verify_csrf($input['csrf'] ?? null);

            $old = (string)($input['old_password'] ?? '');
            $new = (string)($input['new_password'] ?? '');
            $rep = (string)($input['repeat_password'] ?? '');

            if ($new !== $rep) {
                json_response(false, null, 'Новый пароль и подтверждение не совпадают', 422);
            }
            if ($new === '') {
                json_response(false, null, 'Введите новый пароль', 422);
            }

            $pdo    = db();
            $result = users_change_password($pdo, current_user_id(), $old, $new);

            if (empty($result['ok'])) {
                json_response(false, null, $result['error'] ?? 'Ошибка смены пароля', 400);
            }

            log_action($pdo, current_user_id(), 'change_password', 'users', current_user_id());
            rotate_csrf();

            json_response(true, ['csrf' => $_SESSION['csrf']]);
            break;

        default:
            json_response(false, null, 'Неизвестное действие', 400);
    }
} catch (Throwable $e) {
    json_response(false, null, 'Ошибка сервера: ' . $e->getMessage(), 500);
}