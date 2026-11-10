<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

/**
 * Собирает уведомления для текущего пользователя.
 * Все данные фильтруются по компании пользователя.
 *
 * Возвращает: [
 *   'count' => int,
 *   'items' => [
 *      ['type' => 'low_stock', 'title' => '...', 'link' => '...', 'severity' => 'warn'],
 *      ...
 *   ]
 * ]
 */
function notifications_build(PDO $pdo, array $user): array
{
    $role      = $user['role'] ?? 'worker';
    $companyId = (int)($user['company_id'] ?? 0);
    $items     = [];

    if ($companyId <= 0) {
        return ['count' => 0, 'items' => []];
    }

    // ---------- 1. Низкий остаток ----------
    $stmt = $pdo->prepare(
        'SELECT id, sku, name, quantity, min_stock, unit
         FROM products
         WHERE company_id = :cid
           AND quantity <= min_stock
         ORDER BY (min_stock - quantity) DESC
         LIMIT 10'
    );
    $stmt->execute([':cid' => $companyId]);
    $low = $stmt->fetchAll();

    foreach ($low as $r) {
        $deficit = max(0, (float)$r['min_stock'] - (float)$r['quantity']);
        $items[] = [
            'type'     => 'low_stock',
            'severity' => 'danger',
            'title'    => sprintf(
                '«%s» — остаток %s %s (мин. %s)',
                $r['name'],
                rtrim(rtrim(number_format((float)$r['quantity'], 3, '.', ''), '0'), '.'),
                $r['unit'],
                rtrim(rtrim(number_format((float)$r['min_stock'], 3, '.', ''), '0'), '.')
            ),
            'subtitle' => 'Дефицит: ' . $deficit,
            'link'     => '?page=product&id=' . (int)$r['id'],
        ];
    }

    // ---------- 2. Без движения 30 дней (manager, admin) ----------
    if (in_array($role, ['admin', 'manager'], true)) {
        $stmt = $pdo->prepare(
            'SELECT p.id, p.sku, p.name
             FROM products p
             WHERE p.company_id = :cid
               AND p.quantity > 0
               AND NOT EXISTS (
                   SELECT 1 FROM operations o
                   WHERE o.product_id = p.id
                     AND o.company_id = :cid2
                     AND o.created_at >= NOW() - INTERVAL 30 DAY
               )
             ORDER BY p.name ASC
             LIMIT 5'
        );
        $stmt->execute([':cid' => $companyId, ':cid2' => $companyId]);
        foreach ($stmt->fetchAll() as $r) {
            $items[] = [
                'type'     => 'stale',
                'severity' => 'info',
                'title'    => '«' . $r['name'] . '» без движения 30 дней',
                'subtitle' => 'SKU: ' . $r['sku'],
                'link'     => '?page=product&id=' . (int)$r['id'],
            ];
        }
    }

    // ---------- 3. Свежие audit-события (только admin) ----------
    if ($role === 'admin') {
        $stmt = $pdo->prepare(
            'SELECT a.action, a.entity, a.entity_id, a.created_at,
                    COALESCE(u.full_name, "—") AS user_name
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.company_id = :cid
               AND a.created_at >= NOW() - INTERVAL 24 HOUR
               AND a.action IN ("create", "update", "delete")
             ORDER BY a.created_at DESC
             LIMIT 5'
        );
        $stmt->execute([':cid' => $companyId]);

        $actionLabels = ['create' => 'Создание', 'update' => 'Изменение', 'delete' => 'Удаление'];
        $entityLabels = ['products' => 'товара', 'operations' => 'операции', 'users' => 'пользователя'];

        foreach ($stmt->fetchAll() as $r) {
            $items[] = [
                'type'     => 'audit',
                'severity' => 'info',
                'title'    => sprintf(
                    '%s %s #%d — %s',
                    $actionLabels[$r['action']] ?? $r['action'],
                    $entityLabels[$r['entity']] ?? $r['entity'],
                    (int)$r['entity_id'],
                    $r['user_name']
                ),
                'subtitle' => $r['created_at'],
                'link'     => '?page=audit',
            ];
        }
    }

    return [
        'count' => count($items),
        'items' => $items,
    ];
}