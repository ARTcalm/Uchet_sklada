<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

/**
 * Расширенный дашборд (метрики только по своей компании).
 */
function reports_dashboard(PDO $pdo, int $companyId): array
{
    // 1) Всего товаров
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE company_id = :cid');
    $stmt->execute([':cid' => $companyId]);
    $totalProducts = (int)$stmt->fetchColumn();

    // 2) Стоимость склада
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(quantity * price), 0) FROM products WHERE company_id = :cid'
    );
    $stmt->execute([':cid' => $companyId]);
    $totalValue = (float)$stmt->fetchColumn();

    // 3) Ниже минимума
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM products WHERE company_id = :cid AND quantity <= min_stock'
    );
    $stmt->execute([':cid' => $companyId]);
    $lowStock = (int)$stmt->fetchColumn();

    // 4) Операции за сегодня
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM operations
         WHERE company_id = :cid AND DATE(created_at) = CURDATE()'
    );
    $stmt->execute([':cid' => $companyId]);
    $opsToday = (int)$stmt->fetchColumn();

    // 5) Новые товары за 7 дней
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM products
         WHERE company_id = :cid AND created_at >= NOW() - INTERVAL 7 DAY'
    );
    $stmt->execute([':cid' => $companyId]);
    $newProducts7d = (int)$stmt->fetchColumn();

    // 6) Без движения 30 дней
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM products p
         WHERE p.company_id = :cid
           AND p.quantity > 0
           AND NOT EXISTS (
               SELECT 1 FROM operations o
               WHERE o.product_id = p.id
                 AND o.company_id = :cid2
                 AND o.created_at >= NOW() - INTERVAL 30 DAY
           )'
    );
    $stmt->execute([':cid' => $companyId, ':cid2' => $companyId]);
    $stale30d = (int)$stmt->fetchColumn();

    // 7) Активность за 7 дней (по дням)
    $stmt = $pdo->prepare(
        "SELECT DATE(created_at) AS d, type,
                SUM(qty) AS total_qty,
                COUNT(*) AS cnt
         FROM operations
         WHERE company_id = :cid
           AND created_at >= CURDATE() - INTERVAL 6 DAY
         GROUP BY DATE(created_at), type
         ORDER BY d ASC"
    );
    $stmt->execute([':cid' => $companyId]);
    $rawActivity = $stmt->fetchAll();

    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} day"));
        $days[$date] = [
            'date'    => $date,
            'in_qty'  => 0.0, 'in_cnt'  => 0,
            'out_qty' => 0.0, 'out_cnt' => 0,
            'wo_qty'  => 0.0, 'wo_cnt'  => 0,
        ];
    }
    foreach ($rawActivity as $row) {
        $d = (string)$row['d'];
        if (!isset($days[$d])) continue;
        $qty = (float)$row['total_qty'];
        $cnt = (int)$row['cnt'];
        switch ($row['type']) {
            case 'in':       $days[$d]['in_qty']  = $qty; $days[$d]['in_cnt']  = $cnt; break;
            case 'out':      $days[$d]['out_qty'] = $qty; $days[$d]['out_cnt'] = $cnt; break;
            case 'writeoff': $days[$d]['wo_qty']  = $qty; $days[$d]['wo_cnt']  = $cnt; break;
        }
    }
    $activity = array_values($days);

    // 8) Итоги за 7 дней
    $stmt = $pdo->prepare(
        "SELECT type, SUM(qty) AS qty, COUNT(*) AS cnt
         FROM operations
         WHERE company_id = :cid
           AND created_at >= CURDATE() - INTERVAL 6 DAY
         GROUP BY type"
    );
    $stmt->execute([':cid' => $companyId]);
    $sums7d = $stmt->fetchAll();

    $summary7d = [
        'in'       => ['qty' => 0.0, 'cnt' => 0],
        'out'      => ['qty' => 0.0, 'cnt' => 0],
        'writeoff' => ['qty' => 0.0, 'cnt' => 0],
    ];
    foreach ($sums7d as $r) {
        $summary7d[$r['type']] = ['qty' => (float)$r['qty'], 'cnt' => (int)$r['cnt']];
    }

    // 9) Топ-5 дефицитных товаров
    $stmt = $pdo->prepare(
        'SELECT id, sku, name, unit, quantity, min_stock
         FROM products
         WHERE company_id = :cid AND quantity <= min_stock
         ORDER BY (min_stock - quantity) DESC, quantity ASC
         LIMIT 5'
    );
    $stmt->execute([':cid' => $companyId]);
    $lowStockItems = $stmt->fetchAll();
    foreach ($lowStockItems as &$r) {
        $r['id']        = (int)$r['id'];
        $r['quantity']  = (float)$r['quantity'];
        $r['min_stock'] = (float)$r['min_stock'];
        $r['deficit']   = max(0.0, $r['min_stock'] - $r['quantity']);
    }
    unset($r);

    // 10) Топ-5 самых дорогих позиций
    $stmt = $pdo->prepare(
        'SELECT id, sku, name, unit, quantity, price,
                (quantity * price) AS total_value
         FROM products
         WHERE company_id = :cid AND quantity > 0
         ORDER BY (quantity * price) DESC
         LIMIT 5'
    );
    $stmt->execute([':cid' => $companyId]);
    $topValue = $stmt->fetchAll();
    foreach ($topValue as &$r) {
        $r['id']          = (int)$r['id'];
        $r['quantity']    = (float)$r['quantity'];
        $r['price']       = (float)$r['price'];
        $r['total_value'] = (float)$r['total_value'];
    }
    unset($r);

    // 11) Последние 10 операций
    $stmt = $pdo->prepare(
        'SELECT o.id, o.type, o.qty, o.comment, o.created_at,
                p.sku, p.name AS product_name,
                u.full_name AS user_name
         FROM operations o
         JOIN products p ON p.id = o.product_id
         JOIN users    u ON u.id = o.user_id
         WHERE o.company_id = :cid
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 10'
    );
    $stmt->execute([':cid' => $companyId]);
    $recent = $stmt->fetchAll();
    foreach ($recent as &$r) {
        $r['id']  = (int)$r['id'];
        $r['qty'] = (float)$r['qty'];
    }
    unset($r);

    return [
        'totals' => [
            'products'       => $totalProducts,
            'value'          => $totalValue,
            'low_stock'      => $lowStock,
            'ops_today'      => $opsToday,
            'new_products'   => $newProducts7d,
            'stale_products' => $stale30d,
        ],
        'activity'          => $activity,
        'summary_7d'        => $summary7d,
        'low_stock_items'   => $lowStockItems,
        'top_value_items'   => $topValue,
        'recent_operations' => $recent,
    ];
}

/**
 * Отчёт «Остатки» с фильтрами по категории, товару и названию.
 * Только для своей компании.
 */
function reports_stock(PDO $pdo, int $companyId, ?int $categoryId, ?int $productId = null, string $search = ''): array
{
    $where  = ['p.company_id = :cid'];
    $params = [':cid' => $companyId];

    if ($categoryId !== null) {
        $where[] = 'p.category_id = :cat';
        $params[':cat'] = $categoryId;
    }
    if ($productId !== null) {
        $where[] = 'p.id = :pid';
        $params[':pid'] = $productId;
    }
    if ($search !== '') {
        $where[] = '(p.name LIKE :search OR p.sku LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $sql = "
        SELECT p.id, p.sku, p.name, p.unit, p.quantity, p.min_stock, p.price,
               COALESCE(c.name, '') AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        $whereSql
        ORDER BY c.name ASC, p.name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id']        = (int)$r['id'];
        $r['quantity']  = (float)$r['quantity'];
        $r['min_stock'] = (float)$r['min_stock'];
        $r['price']     = (float)$r['price'];
    }
    unset($r);
    return $rows;
}

/**
 * Отчёт «Движение товара за период».
 * Только для своей компании.
 */
function reports_movement(PDO $pdo, int $companyId, string $from, string $to, ?int $productId): array
{
    $where  = 'WHERE o.company_id = :cid AND o.created_at >= :from AND o.created_at < DATE_ADD(:to, INTERVAL 1 DAY)';
    $params = [':cid' => $companyId, ':from' => $from . ' 00:00:00', ':to' => $to];

    if ($productId !== null) {
        $where .= ' AND o.product_id = :pid';
        $params[':pid'] = $productId;
    }

    $sql = "
        SELECT o.id, o.type, o.qty, o.comment, o.created_at,
               p.sku, p.name AS product_name,
               u.full_name AS user_name
        FROM operations o
        JOIN products p ON p.id = o.product_id
        JOIN users    u ON u.id = o.user_id
        $where
        ORDER BY o.created_at ASC, o.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id']  = (int)$r['id'];
        $r['qty'] = (float)$r['qty'];
    }
    unset($r);
    return $rows;
}

/**
 * Всё для страницы товара: операции по дням за 30 дней,
 * последние 50 операций и audit по товару.
 * Только для своей компании.
 */
function reports_product_detail(PDO $pdo, int $productId, int $companyId): array
{
    // 1) Операции по дням за 30 дней (для графика)
    $stmt = $pdo->prepare(
        "SELECT DATE(created_at) AS d, type, SUM(qty) AS total_qty
         FROM operations
         WHERE product_id = :pid
           AND company_id = :cid
           AND created_at >= CURDATE() - INTERVAL 29 DAY
         GROUP BY DATE(created_at), type"
    );
    $stmt->execute([':pid' => $productId, ':cid' => $companyId]);
    $raw = $stmt->fetchAll();

    $days = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} day"));
        $days[$date] = ['date' => $date, 'in_qty' => 0.0, 'out_qty' => 0.0, 'wo_qty' => 0.0];
    }
    foreach ($raw as $r) {
        $d = (string)$r['d'];
        if (!isset($days[$d])) continue;
        switch ($r['type']) {
            case 'in':       $days[$d]['in_qty']  = (float)$r['total_qty']; break;
            case 'out':      $days[$d]['out_qty'] = (float)$r['total_qty']; break;
            case 'writeoff': $days[$d]['wo_qty']  = (float)$r['total_qty']; break;
        }
    }

    // 2) Последние 50 операций по товару
    $stmt = $pdo->prepare(
        'SELECT o.id, o.type, o.qty, o.comment, o.created_at,
                u.full_name AS user_name
         FROM operations o
         JOIN users u ON u.id = o.user_id
         WHERE o.product_id = :pid AND o.company_id = :cid
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 50'
    );
    $stmt->execute([':pid' => $productId, ':cid' => $companyId]);
    $operations = $stmt->fetchAll();
    foreach ($operations as &$r) {
        $r['id']  = (int)$r['id'];
        $r['qty'] = (float)$r['qty'];
    }
    unset($r);

    // 3) Audit по товару
    $stmt = $pdo->prepare(
        'SELECT a.id, a.action, a.created_at,
                COALESCE(u.full_name, "—") AS user_name
         FROM audit_log a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.entity = "products"
           AND a.entity_id = :pid
           AND a.company_id = :cid
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT 50'
    );
    $stmt->execute([':pid' => $productId, ':cid' => $companyId]);
    $audit = $stmt->fetchAll();
    foreach ($audit as &$r) {
        $r['id'] = (int)$r['id'];
    }
    unset($r);

    return [
        'activity_30d' => array_values($days),
        'operations'   => $operations,
        'audit'        => $audit,
    ];
}