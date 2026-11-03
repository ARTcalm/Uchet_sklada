<?php
declare(strict_types=1);

/* ============================================================
 * JSON-ответы API
 * ============================================================ */

/**
 * Отдаёт JSON в едином формате и прекращает выполнение.
 *
 * Успех: {"ok": true, "data": ...}
 * Ошибка: {"ok": false, "error": "..."}
 */
function json_response(bool $ok, $data = null, ?string $error = null, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    if ($ok) {
        echo json_encode(
            ['ok' => true, 'data' => $data],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    } else {
        echo json_encode(
            ['ok' => false, 'error' => $error ?? 'Неизвестная ошибка'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
    exit;
}

/* ============================================================
 * Входные данные
 * ============================================================ */

/**
 * Читает тело запроса как JSON-объект.
 * При невалидном JSON — возвращает пустой массив.
 */
function json_input(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return $cached = [];
    }
    $decoded = json_decode($raw, true);
    return $cached = is_array($decoded) ? $decoded : [];
}

/**
 * Очистка строки: trim + удаление управляющих символов (кроме \n, \t).
 * НЕ заменяет экранирование в HTML — это делает htmlspecialchars() при выводе.
 */
function sanitize(string $value): string
{
    $value = trim($value);
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
}

/**
 * Приведение произвольного значения к int >= 0.
 */
function to_int($value): int
{
    if (is_int($value)) {
        return max(0, $value);
    }
    return max(0, (int)$value);
}

/**
 * Приведение к float >= 0.
 */
function to_float($value): float
{
    $f = (float)$value;
    return $f < 0 ? 0.0 : $f;
}

/**
 * Проверка HTTP-метода (в API).
 */
function require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        json_response(false, null, 'Метод не поддерживается', 405);
    }
}

/* ============================================================
 * CSRF
 * ============================================================ */

/**
 * Текущий CSRF-токен. Генерируется при первом обращении.
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Ротация токена (после логина/логаута).
 */
function rotate_csrf(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

/**
 * Проверка CSRF.
 * $token — если передан, используется он; иначе берётся из POST или JSON-поля csrf.
 * При ошибке — JSON-ответ 403 и exit.
 */
function verify_csrf(?string $token = null): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if ($token === null) {
        $token = $_POST['csrf'] ?? null;
        if ($token === null) {
            $input = json_input();
            $token = is_string($input['csrf'] ?? null) ? $input['csrf'] : null;
        }
    }

    $expected = $_SESSION['csrf'] ?? null;

    if (!is_string($token) || !is_string($expected) || $expected === '' || !hash_equals($expected, $token)) {
        json_response(false, null, 'Неверный CSRF-токен', 403);
    }
}

/* ============================================================
 * Аудит
 * ============================================================ */

/**
 * Пишет запись в audit_log. Не бросает исключений — ошибка логируется,
 * чтобы не ломать основной бизнес-поток.
 *
 * @param PDO    $pdo
 * @param int    $userId
 * @param string $action  login|logout|create|update|delete|operation
 * @param string $entity  users|products|operations|...
 * @param int    $entityId
 */
function log_action(PDO $pdo, int $userId, string $action, string $entity, int $entityId): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (user_id, action, entity, entity_id, created_at)
             VALUES (:uid, :action, :entity, :eid, NOW())'
        );
        $stmt->execute([
            ':uid'    => $userId,
            ':action' => $action,
            ':entity' => $entity,
            ':eid'    => $entityId,
        ]);
    } catch (Throwable $e) {
        error_log('[audit] ' . $e->getMessage());
    }
}

/* ============================================================
 * Прочее
 * ============================================================ */

/**
 * Безопасный htmlspecialchars (короткий алиас).
 */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Универсальная валидация: возвращает массив ошибок (пустой = ок).
 * $rules — ['field' => ['required' => bool, 'type' => 'int|float|string', 'min' => ..., 'max' => ...]]
 */
function validate_fields(array $input, array $rules): array
{
    $errors = [];
    foreach ($rules as $field => $r) {
        $value = $input[$field] ?? null;

        $required = !empty($r['required']);
        $isEmpty  = ($value === null || $value === '' || (is_array($value) && empty($value)));

        if ($required && $isEmpty) {
            $errors[] = "Поле «{$field}» обязательно";
            continue;
        }
        if ($isEmpty) {
            continue;
        }

        $type = $r['type'] ?? null;
        if ($type === 'int' && !is_numeric($value)) {
            $errors[] = "Поле «{$field}» должно быть числом";
            continue;
        }
        if ($type === 'float' && !is_numeric($value)) {
            $errors[] = "Поле «{$field}» должно быть числом";
            continue;
        }

        if (isset($r['min']) && is_numeric($value) && (float)$value < (float)$r['min']) {
            $errors[] = "Поле «{$field}» должно быть ≥ {$r['min']}";
        }
        if (isset($r['max']) && is_numeric($value) && (float)$value > (float)$r['max']) {
            $errors[] = "Поле «{$field}» должно быть ≤ {$r['max']}";
        }
        if (isset($r['maxlen']) && is_string($value) && mb_strlen($value) > (int)$r['maxlen']) {
            $errors[] = "Поле «{$field}» длиннее {$r['maxlen']} символов";
        }
        if (!empty($r['in']) && !in_array($value, $r['in'], true)) {
            $errors[] = "Поле «{$field}» имеет недопустимое значение";
        }
    }
    return $errors;
}

/**
 * Возвращает следующую доступную страницу для текущей роли (по кругу).
 * Если страница неизвестна — возвращает null.
 */
function next_page_for(string $current, array $menu, string $role): ?array
{
    // Список страниц в фиксированном порядке
    $order = ['dashboard', 'products', 'operations', 'reports', 'users', 'audit', 'profile'];

    // Отфильтровываем доступные для роли
    $available = array_values(array_filter(
        $order,
        fn($key) => isset($menu[$key]) && in_array($role, $menu[$key]['roles'], true)
    ));

    if (empty($available)) {
        return null;
    }

    // Если текущей нет в списке (например, menu или product) — ведём на первую доступную
    $idx = array_search($current, $available, true);
    if ($idx === false) {
        $nextKey = $available[0];
    } else {
        $nextKey = $available[($idx + 1) % count($available)];
    }

    return [
        'key'   => $nextKey,
        'label' => $menu[$nextKey]['label'] ?? $nextKey,
        'icon'  => $menu[$nextKey]['icon']  ?? '',
    ];
}