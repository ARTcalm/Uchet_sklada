<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

/**
 * Расширенный дашборд.
 */
function reports_dashboard(PDO $pdo): array
{
    $totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

    $totalValue = (float)$pdo->query(
        'SELECT COALESCE(SUM(quantity * price), 0) FROM products'
    )->fetchColumn();

    $lowStock = (int)$pdo->query(
        'SELECT COUNT(*) FROM products WHERE quantity <= min_stock'
    )->fetchColumn();

    $opsToday = (int)$pdo->query(
        'SELECT COUNT(*) FROM operations WHERE DATE(created_at) = CURDATE()'
    )->fetchColumn();

    $newProducts7d = (int)$pdo->query(
        'SELECT COUNT(*) FROM products WHERE created_at >= NOW() - INTERVAL 7 DAY'
    )->fetchColumn();

    $stale30d = (int)$pdo->query(
        'SELECT COUNT(*) FROM products p
         WHERE p.quantity > 0
           AND NOT EXISTS (
               SELECT 1 FROM operations o
               WHERE o.product_id = p.id
                 AND o.created_at >= NOW() - INTERVAL 30 DAY
           )'
    )->fetchColumn();

    // Активность за 7 дней
    $rawActivity = $pdo->query(
        "SELECT DATE(created_at) AS d, type,
                SUM(qty) AS total_qty,
                COUNT(*) AS cnt
         FROM operations
         WHERE created_at >= CURDATE() - INTERVAL 6 DAY
         GROUP BY DATE(created_at), type
         ORDER BY d ASC"
    )->fetchAll();

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

    $sums7d = $pdo->query(
        "SELECT type, SUM(qty) AS qty, COUNT(*) AS cnt
         FROM operations
         WHERE created_at >= CURDATE() - INTERVAL 6 DAY
         GROUP BY type"
    )->fetchAll();
    $summary7d = [
        'in'       => ['qty' => 0.0, 'cnt' => 0],
        'out'      => ['qty' => 0.0, 'cnt' => 0],
        'writeoff' => ['qty' => 0.0, 'cnt' => 0],
    ];
    foreach ($sums7d as $r) {
        $summary7d[$r['type']] = ['qty' => (float)$r['qty'], 'cnt' => (int)$r['cnt']];
    }

    // Топ-5 дефицитных
    $lowStockItems = $pdo->query(
        'SELECT id, sku, name, unit, quantity, min_stock
         FROM products
         WHERE quantity <= min_stock
         ORDER BY (min_stock - quantity) DESC, quantity ASC
         LIMIT 5'
    )->fetchAll();
    foreach ($lowStockItems as &$r) {
        $r['id']        = (int)$r['id'];
        $r['quantity']  = (float)$r['quantity'];
        $r['min_stock'] = (float)$r['min_stock'];
        $r['deficit']   = max(0.0, $r['min_stock'] - $r['quantity']);
    }
    unset($r);

    // Топ-5 по стоимости
    $topValue = $pdo->query(
        'SELECT id, sku, name, unit, quantity, price,
                (quantity * price) AS total_value
         FROM products
         WHERE quantity > 0
         ORDER BY (quantity * price) DESC
         LIMIT 5'
    )->fetchAll();
    foreach ($topValue as &$r) {
        $r['id']          = (int)$r['id'];
        $r['quantity']    = (float)$r['quantity'];
        $r['price']       = (float)$r['price'];
        $r['total_value'] = (float)$r['total_value'];
    }
    unset($r);

    // Последние 10 операций
    $recent = $pdo->query(
        'SELECT o.id, o.type, o.qty, o.comment, o.created_at,
                p.sku, p.name AS product_name,
                u.full_name AS user_name
         FROM operations o
         JOIN products p ON p.id = o.product_id
         JOIN users    u ON u.id = o.user_id
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 10'
    )->fetchAll();
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
 * Отчёт «Остатки» (с фильтром по категории).
 */
function reports_stock(PDO $pdo, ?int $categoryId): array
{
    $where  = '';
    $params = [];
    if ($categoryId !== null) {
        $where = 'WHERE p.category_id = :cid';
        $params[':cid'] = $categoryId;
    }

    $sql = "
        SELECT p.id, p.sku, p.name, p.unit, p.quantity, p.min_stock, p.price,
               COALESCE(c.name, '') AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        $where
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
 */
function reports_movement(PDO $pdo, string $from, string $to, ?int $productId): array
{
    $where  = 'WHERE o.created_at >= :from AND o.created_at < DATE_ADD(:to, INTERVAL 1 DAY)';
    $params = [':from' => $from . ' 00:00:00', ':to' => $to];

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
 * Всё для страницы товара: операции по дням (для графика),
 * последние 50 операций, audit по товару.
 */
function reports_product_detail(PDO $pdo, int $productId): array
{
    // Операции по дням за 30 дней (для графика)
    $stmt = $pdo->prepare(
        "SELECT DATE(created_at) AS d, type, SUM(qty) AS total_qty
         FROM operations
         WHERE product_id = :pid
           AND created_at >= CURDATE() - INTERVAL 29 DAY
         GROUP BY DATE(created_at), type"
    );
    $stmt->execute([':pid' => $productId]);
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

    // Последние 50 операций по товару
    $stmt = $pdo->prepare(
        'SELECT o.id, o.type, o.qty, o.comment, o.created_at,
                u.full_name AS user_name
         FROM operations o
         JOIN users u ON u.id = o.user_id
         WHERE o.product_id = :pid
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 50'
    );
    $stmt->execute([':pid' => $productId]);
    $operations = $stmt->fetchAll();
    foreach ($operations as &$r) {
        $r['id']  = (int)$r['id'];
        $r['qty'] = (float)$r['qty'];
    }
    unset($r);

    // Audit по товару
    $stmt = $pdo->prepare(
        'SELECT a.id, a.action, a.created_at,
                COALESCE(u.full_name, "—") AS user_name
         FROM audit_log a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.entity = "products" AND a.entity_id = :pid
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT 50'
    );
    $stmt->execute([':pid' => $productId]);
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