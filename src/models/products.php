<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

function products_list(PDO $pdo, int $companyId, string $search, int $limit, int $offset): array
{
    $where  = 'WHERE p.company_id = :cid';
    $params = [':cid' => $companyId];

    if ($search !== '') {
        $where .= ' AND (p.sku LIKE :s1 OR p.name LIKE :s2)';
        $params[':s1'] = '%' . $search . '%';
        $params[':s2'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "
        SELECT p.id, p.sku, p.name, p.category_id, p.unit, p.quantity, p.min_stock, p.price,
               p.created_at, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        $where
        ORDER BY p.name ASC
        LIMIT :lim OFFSET :off
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $items = $stmt->fetchAll();
    foreach ($items as &$row) {
        $row['id']          = (int)$row['id'];
        $row['category_id'] = $row['category_id'] !== null ? (int)$row['category_id'] : null;
        $row['quantity']    = (float)$row['quantity'];
        $row['min_stock']   = (float)$row['min_stock'];
        $row['price']       = (float)$row['price'];
    }
    unset($row);

    $perPage = (int)($limit ?: 20);
    $page    = (int)floor($offset / $perPage) + 1;

    return [
        'items'    => $items,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
    ];
}

function categories_list(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
    }
    unset($r);
    return $rows;
}

function validate_product(array $input, ?int $id = null): array
{
    $errors = validate_fields($input, [
        'sku'       => ['required' => true, 'maxlen' => 64],
        'name'      => ['required' => true, 'maxlen' => 255],
        'unit'      => ['required' => true, 'maxlen' => 16],
        'quantity'  => ['type' => 'float', 'min' => 0, 'max' => 999999999.999],
        'min_stock' => ['type' => 'float', 'min' => 0, 'max' => 999999999.999],
        'price'     => ['type' => 'float', 'min' => 0, 'max' => 9999999999.99],
    ]);

    if ($errors) return $errors;

    $pdo = db();
    $cid = current_company_id();

    // Уникальность SKU — только внутри своей компании
    $sql = 'SELECT id FROM products WHERE sku = :sku AND company_id = :cid'
         . ($id ? ' AND id <> :id' : '') . ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $params = [':sku' => $input['sku'], ':cid' => $cid];
    if ($id) $params[':id'] = $id;
    $stmt->execute($params);
    if ($stmt->fetchColumn()) $errors[] = 'SKU уже занят';

    if (!empty($input['category_id'])) {
        $stmt = $pdo->prepare('SELECT 1 FROM categories WHERE id = :id');
        $stmt->execute([':id' => (int)$input['category_id']]);
        if (!$stmt->fetchColumn()) $errors[] = 'Категория не найдена';
    }

    return $errors;
}

function products_create(PDO $pdo, array $input, int $companyId): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO products (company_id, sku, name, category_id, unit, quantity, min_stock, price, created_at)
         VALUES (:cid, :sku, :name, :cat, :unit, :qty, :min, :price, NOW())'
    );
    $stmt->execute([
        ':cid'   => $companyId,
        ':sku'   => $input['sku'],
        ':name'  => $input['name'],
        ':cat'   => !empty($input['category_id']) ? (int)$input['category_id'] : null,
        ':unit'  => $input['unit'],
        ':qty'   => to_float($input['quantity'] ?? 0),
        ':min'   => to_float($input['min_stock'] ?? 0),
        ':price' => to_float($input['price'] ?? 0),
    ]);
    return (int)$pdo->lastInsertId();
}

function products_update(PDO $pdo, int $id, int $companyId, array $input): void
{
    $stmt = $pdo->prepare(
        'UPDATE products
         SET sku = :sku, name = :name, category_id = :cat,
             unit = :unit, min_stock = :min, price = :price
         WHERE id = :id AND company_id = :cid'
    );
    $stmt->execute([
        ':sku'   => $input['sku'],
        ':name'  => $input['name'],
        ':cat'   => !empty($input['category_id']) ? (int)$input['category_id'] : null,
        ':unit'  => $input['unit'],
        ':min'   => to_float($input['min_stock'] ?? 0),
        ':price' => to_float($input['price'] ?? 0),
        ':id'    => $id,
        ':cid'   => $companyId,
    ]);

    // ВОТ СЮДА ЕЕ НУЖНО ПОСТАВИТЬ:
    error_log("DEBUG: products_update executed. rowCount = " . $stmt->rowCount());
}

function products_delete(PDO $pdo, int $id, int $companyId): void
{
    // Проверяем, что товар принадлежит компании
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM operations WHERE product_id = :id AND company_id = :cid');
    $stmt->execute([':id' => $id, ':cid' => $companyId]);
    if ((int)$stmt->fetchColumn() > 0) {
        json_response(false, null, 'Нельзя удалить товар: по нему есть операции', 400);
    }

    $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id AND company_id = :cid');
    $stmt->execute([':id' => $id, ':cid' => $companyId]);
}

function products_get(PDO $pdo, int $id, int $companyId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.id, p.sku, p.name, p.category_id, p.unit, p.quantity,
                p.min_stock, p.price, p.created_at,
                COALESCE(c.name, "") AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = :id AND p.company_id = :cid LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':cid' => $companyId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['id']          = (int)$row['id'];
    $row['category_id'] = $row['category_id'] !== null ? (int)$row['category_id'] : null;
    $row['quantity']    = (float)$row['quantity'];
    $row['min_stock']   = (float)$row['min_stock'];
    $row['price']       = (float)$row['price'];
    return $row;
}