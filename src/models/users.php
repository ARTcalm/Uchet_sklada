<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

function users_list(PDO $pdo, int $companyId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, email, full_name, role, is_active, created_at
         FROM users WHERE company_id = :cid ORDER BY id ASC'
    );
    $stmt->execute([':cid' => $companyId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id']        = (int)$r['id'];
        $r['is_active'] = (int)$r['is_active'];
    }
    unset($r);
    return $rows;
}

function users_get(PDO $pdo, int $id, int $companyId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, email, full_name, role, is_active, created_at
         FROM users WHERE id = :id AND company_id = :cid LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':cid' => $companyId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['id']        = (int)$row['id'];
    $row['is_active'] = (int)$row['is_active'];
    return $row;
}

function validate_user(array $input, int $companyId): array
{
    $errors = validate_fields($input, [
        'email'     => ['required' => true, 'maxlen' => 255],
        'password'  => ['required' => true, 'maxlen' => 72],
        'full_name' => ['required' => true, 'maxlen' => 255],
        'role'      => ['required' => true, 'in' => ['admin', 'manager', 'worker']],
    ]);
    if ($errors) return $errors;

    $email = mb_strtolower(trim((string)$input['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email';
        return $errors;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :e AND company_id = :cid LIMIT 1');
    $stmt->execute([':e' => $email, ':cid' => $companyId]);
    if ($stmt->fetchColumn()) $errors[] = 'Пользователь с таким email уже есть в вашей компании';
    return $errors;
}

function validate_user_update(array $input, int $id, int $companyId): array
{
    $errors = validate_fields($input, [
        'email'     => ['required' => true, 'maxlen' => 255],
        'full_name' => ['required' => true, 'maxlen' => 255],
        'role'      => ['required' => true, 'in' => ['admin', 'manager', 'worker']],
    ]);
    if ($errors) return $errors;

    $email = mb_strtolower(trim((string)$input['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email';
        return $errors;
    }

    if (isset($input['password']) && $input['password'] !== '') {
        if (mb_strlen((string)$input['password']) < 4) $errors[] = 'Пароль слишком короткий';
        if (mb_strlen((string)$input['password']) > 72) $errors[] = 'Пароль слишком длинный';
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :e AND company_id = :cid AND id <> :id LIMIT 1');
    $stmt->execute([':e' => $email, ':cid' => $companyId, ':id' => $id]);
    if ($stmt->fetchColumn()) $errors[] = 'Email уже занят другим пользователем';
    return $errors;
}

function users_create(PDO $pdo, array $input, int $companyId): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (company_id, email, password_hash, full_name, role, is_active, created_at)
         VALUES (:cid, :e, :p, :f, :r, 1, NOW())'
    );
    $stmt->execute([
        ':cid' => $companyId,
        ':e'   => mb_strtolower(trim((string)$input['email'])),
        ':p'   => password_hash((string)$input['password'], PASSWORD_BCRYPT),
        ':f'   => $input['full_name'],
        ':r'   => $input['role'],
    ]);
    return (int)$pdo->lastInsertId();
}

function users_update(PDO $pdo, int $id, int $companyId, array $input): void
{
    $sets = [];
    $params = [':id' => $id, ':cid' => $companyId];

    if (isset($input['email'])) {
        $sets[] = 'email = :email';
        $params[':email'] = mb_strtolower(trim((string)$input['email']));
    }
    if (isset($input['full_name'])) {
        $sets[] = 'full_name = :full_name';
        $params[':full_name'] = (string)$input['full_name'];
    }
    if (isset($input['role'])) {
        if (!in_array($input['role'], ['admin','manager','worker'], true)) {
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
    if (!$sets) json_response(false, null, 'Нет данных для обновления', 422);

    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id AND company_id = :cid';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function users_change_password(PDO $pdo, int $userId, string $oldPassword, string $newPassword): array
{
    if (mb_strlen($newPassword) < 4) return ['ok' => false, 'error' => 'Новый пароль должен быть не короче 4 символов'];
    if (mb_strlen($newPassword) > 72) return ['ok' => false, 'error' => 'Новый пароль слишком длинный'];

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($oldPassword, $hash)) {
        return ['ok' => false, 'error' => 'Старый пароль неверен'];
    }

    $upd = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
    $upd->execute([':h' => password_hash($newPassword, PASSWORD_BCRYPT), ':id' => $userId]);

    return ['ok' => true];
}