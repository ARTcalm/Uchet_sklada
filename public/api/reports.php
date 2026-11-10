<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/models/reports.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_login();

$pdo  = db();
$cid  = current_company_id();
$type = $_GET['type'] ?? 'stock';

try {
    // Дашборд — всем ролям
    if ($type === 'dashboard') {
        json_response(true, reports_dashboard($pdo, $cid));
    }

    // Всё остальное — только admin/manager
    require_role(['admin', 'manager']);

    if ($type === 'stock') {
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== ''
            ? (int)$_GET['category_id'] : null;
        $productId  = isset($_GET['product_id']) && $_GET['product_id'] !== ''
            ? (int)$_GET['product_id'] : null;
        $search     = trim((string)($_GET['search'] ?? ''));

        $rows = reports_stock($pdo, $cid, $categoryId, $productId, $search);
        json_response(true, ['rows' => $rows]);
    }

    if ($type === 'movement') {
        $from      = sanitize($_GET['from'] ?? '');
        $to        = sanitize($_GET['to'] ?? '');
        $productId = isset($_GET['product_id']) && $_GET['product_id'] !== ''
            ? (int)$_GET['product_id'] : null;

        if ($from === '' || $to === '') {
            json_response(false, null, 'Укажите период (from, to)', 422);
        }
        if ($from > $to) {
            json_response(false, null, 'Дата «с» больше даты «по»', 422);
        }

        $rows = reports_movement($pdo, $cid, $from, $to, $productId);
        json_response(true, ['rows' => $rows]);
    }

    if ($type === 'export_stock') {
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== ''
            ? (int)$_GET['category_id'] : null;

        $rows = reports_stock($pdo, $cid, $categoryId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="stock_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['SKU', 'Название', 'Категория', 'Ед.', 'Остаток', 'Мин. остаток', 'Цена', 'Стоимость']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['sku'], $r['name'], $r['category_name'], $r['unit'],
                $r['quantity'], $r['min_stock'], $r['price'],
                number_format((float)$r['quantity'] * (float)$r['price'], 2, '.', ''),
            ]);
        }
        fclose($out);
        exit;
    }

    if ($type === 'export_movement') {
        $from      = sanitize($_GET['from'] ?? '');
        $to        = sanitize($_GET['to'] ?? '');
        $productId = isset($_GET['product_id']) && $_GET['product_id'] !== ''
            ? (int)$_GET['product_id'] : null;

        if ($from === '' || $to === '') {
            json_response(false, null, 'Укажите период (from, to)', 422);
        }

        $rows = reports_movement($pdo, $cid, $from, $to, $productId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="movement_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Дата', 'Тип', 'SKU', 'Товар', 'Кол-во', 'Комментарий', 'Пользователь']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['created_at'], $r['type'], $r['sku'],
                $r['product_name'], $r['qty'], $r['comment'], $r['user_name'],
            ]);
        }
        fclose($out);
        exit;
    }

    json_response(false, null, 'Неизвестный тип отчёта: ' . $type, 400);
} catch (Throwable $e) {
    json_response(false, null, 'Ошибка сервера: ' . $e->getMessage(), 500);
}