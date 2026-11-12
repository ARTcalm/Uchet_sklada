<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

/**
 * Создание компании вместе с первым администратором.
 * Возвращает ['ok' => true, 'company_id' => int, 'user_id' => int]
 *         или ['ok' => false, 'error' => string]
 */
function companies_register(PDO $pdo, array $input): array
{
    $errors = validate_fields($input, [
        'company_name' => ['required' => true, 'maxlen' => 255],
        'full_name'    => ['required' => true, 'maxlen' => 255],
        'email'        => ['required' => true, 'maxlen' => 255],
        'password'     => ['required' => true, 'maxlen' => 72],
        'plan'         => ['required' => true, 'in' => ['start', 'business', 'corporate']],
    ]);
    if ($errors) {
        return ['ok' => false, 'error' => implode('; ', $errors)];
    }

    $email = mb_strtolower(trim((string)$input['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Некорректный email'];
    }
    if (mb_strlen((string)$input['password']) < 6) {
        return ['ok' => false, 'error' => 'Пароль должен быть не короче 6 символов'];
    }

    try {
        $pdo->beginTransaction();

        // ... проверки уникальности как были ...

        // Компания
        $stmt = $pdo->prepare(
            'INSERT INTO companies (name, email, plan, is_active, created_at)
             VALUES (:n, :e, :p, 1, NOW())'
        );
        $stmt->execute([
            ':n' => trim((string)$input['company_name']),
            ':e' => $email,
            ':p' => (string)$input['plan'],
        ]);
        $companyId = (int)$pdo->lastInsertId();

        // Админ — теперь с full_name из формы
        $stmt = $pdo->prepare(
            'INSERT INTO users (company_id, email, password_hash, full_name, role, is_active, created_at)
             VALUES (:cid, :e, :h, :f, "admin", 1, NOW())'
        );
        $stmt->execute([
            ':cid' => $companyId,
            ':e'   => $email,
            ':h'   => password_hash((string)$input['password'], PASSWORD_BCRYPT),
            ':f'   => trim((string)$input['full_name']),
        ]);
        $userId = (int)$pdo->lastInsertId();

        $pdo->commit();
        return ['ok' => true, 'company_id' => $companyId, 'user_id' => $userId];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("DEBUG: companies_register PDOException: " . $e->getMessage());
        return ['ok' => false, 'error' => 'Не удалось зарегистрировать компанию'];
    }
}