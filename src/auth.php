<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Авторизован ли текущий посетитель.
 */
function is_logged_in(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return !empty($_SESSION['user']['id']);
}

/**
 * Текущий пользователь (из сессии) или null.
 */
function current_user(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return $_SESSION['user'] ?? null;
}

/**
 * ID текущего пользователя (0, если не авторизован).
 */
function current_user_id(): int
{
    $u = current_user();
    return $u ? (int)$u['id'] : 0;
}

/**
 * Роль текущего пользователя.
 */
function current_role(): ?string
{
    $u = current_user();
    return $u['role'] ?? null;
}

/**
 * Есть ли у текущего пользователя одна из ролей.
 */
function has_role(array $roles): bool
{
    $role = current_role();
    return $role !== null && in_array($role, $roles, true);
}

/**
 * Жёсткая проверка авторизации.
 * Для API (JSON-ответ 401), для страниц — редирект на логин.
 */
function require_login(bool $json = true): void
{
    if (is_logged_in()) {
        return;
    }

    if ($json) {
        json_response(false, null, 'Требуется авторизация', 401);
    }
    header('Location: ?page=login');
    exit;
}

/**
 * Жёсткая проверка роли.
 * Для API (JSON-ответ 403), для страниц — рендер 403.php или редирект.
 */
function require_role(array $roles, bool $json = true): void
{
    require_login($json);

    if (has_role($roles)) {
        return;
    }

    if ($json) {
        json_response(false, null, 'Доступ запрещён', 403);
    }
    http_response_code(403);
    require __DIR__ . '/../public/views/403.php';
    exit;
}

function current_company_id(): int
{
    $u = current_user();
    return $u ? (int)($u['company_id'] ?? 0) : 0;
}