<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/models/companies.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_method('POST');
$input = json_input();
verify_csrf($input['csrf'] ?? null);

try {
    $pdo = db();
    $result = companies_register($pdo, $input);

    if (empty($result['ok'])) {
        json_response(false, null, $result['error'] ?? 'Ошибка регистрации', 422);
    }

    json_response(true, [
        'company_id' => $result['company_id'],
        'user_id'    => $result['user_id'],
        'redirect'   => '?page=thanks',
    ]);
} catch (Throwable $e) {
    json_response(false, null, 'Ошибка сервера: ' . $e->getMessage(), 500);
}