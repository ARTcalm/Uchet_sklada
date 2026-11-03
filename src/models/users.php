<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

function users_list(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, login, full_name, role, is_active, created_at
         FROM users ORDER BY id ASC'
    );
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id']        = (int)$r['id'];
        $r['is_active'] = (int)$r['is_active'];
    }
    unset($r);
    return $rows;
}

/**
 * Один пользователь по id.
 */
function users_get(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, login, full_name, role, is_active, created_at
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['id']        = (int)$row['id'];
    $row['is_active'] = (int)$row['is_active'];
    return $row;
}

/**
 * Валидация создания.
 */
function validate_user(array $input): array
{
    $errors = validate_fields($input, [
        'login'     => ['required' => true, 'maxlen' => 64],
        'password'  => ['required' => true, 'maxlen' => 72],
        'full_name' => ['required' => true, 'maxlen' => 255],
        'role'      => ['required' => true, 'in' => ['admin', 'manager', 'worker']],
    ]);

    if ($errors) {
        return $errors;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE login = :l LIMIT 1');
    $stmt->execute([':l' => $input['login']]);
    if ($stmt->fetchColumn()) {
        $errors[] = 'Логин уже занят';
    }

    return $errors;
}

/**
 * Валидация редактирования.
 * Пароль НЕ обязателен — если пусто, пароль не меняется.
 */
function validate_user_update(array $input, int $id): array
{
    $errors = validate_fields($input, [
        'login'     => ['required' => true, 'maxlen' => 64],
        'full_name' => ['required' => true, 'maxlen' => 255],
        'role'      => ['required' => true, 'in' => ['admin', 'manager', 'worker']],
    ]);

    if ($errors) {
        return $errors;
    }

    if (isset($input['password']) && $input['password'] !== '') {
        if (mb_strlen((string)$input['password']) < 4) {
            $errors[] = 'Пароль должен быть не короче 4 символов';
        }
        if (mb_strlen((string)$input['password']) > 72) {
            $errors[] = 'Пароль слишком длинный';
        }
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE login = :l AND id <> :id LIMIT 1');
    $stmt->execute([':l' => $input['login'], ':id' => $id]);
    if ($stmt->fetchColumn()) {
        $errors[] = 'Логин уже занят другим пользователем';
    }

    return $errors;
}

function users_create(PDO $pdo, array $input): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (login, password_hash, full_name, role, is_active, created_at)
         VALUES (:l, :p, :f, :r, 1, NOW())'
    );
    $stmt->execute([
        ':l' => $input['login'],
        ':p' => password_hash((string)$input['password'], PASSWORD_BCRYPT),
        ':f' => $input['full_name'],
        ':r' => $input['role'],
    ]);
    return (int)$pdo->lastInsertId();
}

/**
 * Обновление пользователя.
 * Может менять: login, full_name, role, is_active, password (опционально).
 */
function users_update(PDO $pdo, int $id, array $input): void
{
    $sets   = [];
    $params = [':id' => $id];

    if (isset($input['login'])) {
        $sets[] = 'login = :login';
        $params[':login'] = (string)$input['login'];
    }
    if (isset($input['full_name'])) {
        $sets[] = 'full_name = :full_name';
        $params[':full_name'] = (string)$input['full_name'];
    }
    if (isset($input['role'])) {
        if (!in_array($input['role'], ['admin', 'manager', 'worker'], true)) {
            json_response(false, null, 'Недопустимая роль', 422);
        }
        $sets[] = 'role = :role';
        $params[':role'] = $input['role'];
    }
    if (isset($input['is_active'])) {
        $sets[] = 'is_active = :active';
        $params[':active'] = $input['is_active'] ? 1 : 0;
    }
    if (!empty($input['password'])) {
        $sets[] = 'password_hash = :hash';
        $params[':hash'] = password_hash((string)$input['password'], PASSWORD_BCRYPT);
    }

    if (!$sets) {
        json_response(false, null, 'Нет данных для обновления', 422);
    }

    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

/**
 * Смена пароля пользователя с проверкой старого.
 * Возвращает ['ok'=>true] или ['ok'=>false,'error'=>string].
 */
function users_change_password(PDO $pdo, int $userId, string $oldPassword, string $newPassword): array
{
    if (mb_strlen($newPassword) < 4) {
        return ['ok' => false, 'error' => 'Новый пароль должен быть не короче 4 символов'];
    }
    if (mb_strlen($newPassword) > 72) {
        return ['ok' => false, 'error' => 'Новый пароль слишком длинный'];
    }

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($oldPassword, $hash)) {
        return ['ok' => false, 'error' => 'Старый пароль неверен'];
    }

    $upd = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
    $upd->execute([
        ':h'  => password_hash($newPassword, PASSWORD_BCRYPT),
        ':id' => $userId,
    ]);

    return ['ok' => true];
}