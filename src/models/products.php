<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

/**
 * Список товаров с поиском и пагинацией.
 * Возвращает: ['items' => [...], 'total' => int, 'page' => int, 'per_page' => int]
 */
if (isset($_GET['categories'])) {
    json_response(true, ['categories' => categories_list($pdo)]);
}

function products_list(PDO $pdo, string $search, int $limit, int $offset): array
{
    $where  = '';
    $params = [];

    if ($search !== '') {
        $where = 'WHERE p.sku LIKE :s OR p.name LIKE :s';
        $params[':s'] = '%' . $search . '%';
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
    // Приводим числовые поля к правильным типам (MySQL PDO может вернуть строки)
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

/**
 * Список категорий (для селектов).
 */
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

/**
 * Валидация товара.
 * $id — при обновлении (нужен для проверки уникальности sku).
 */
function validate_product(array $input, ?int $id = null): array
{
    $errors = validate_fields($input, [
        'sku'       => ['required' => true, 'maxlen' => 64],
        'name'      => ['required' => true, 'maxlen' => 255],
        'unit'      => ['required' => true, 'maxlen' => 16],
        'quantity'  => ['type' => 'float', 'min' => 0],
        'min_stock' => ['type' => 'float', 'min' => 0],
        'price'     => ['type' => 'float', 'min' => 0],
    ]);

    if ($errors) {
        return $errors;
    }

    // Уникальность sku
    $pdo = db();
    $sql = 'SELECT id FROM products WHERE sku = :sku' . ($id ? ' AND id <> :id' : '') . ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $params = [':sku' => $input['sku']];
    if ($id) {
        $params[':id'] = $id;
    }
    $stmt->execute($params);
    if ($stmt->fetchColumn()) {
        $errors[] = 'SKU уже занят';
    }

    // Проверка категории
    if (!empty($input['category_id'])) {
        $stmt = $pdo->prepare('SELECT 1 FROM categories WHERE id = :id');
        $stmt->execute([':id' => (int)$input['category_id']]);
        if (!$stmt->fetchColumn()) {
            $errors[] = 'Категория не найдена';
        }
    }

    return $errors;
}

/**
 * Создание товара. Возвращает id.
 */
function products_create(PDO $pdo, array $input): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO products (sku, name, category_id, unit, quantity, min_stock, price, created_at)
         VALUES (:sku, :name, :cat, :unit, :qty, :min, :price, NOW())'
    );
    $stmt->execute([
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

/**
 * Обновление товара.
 * ВАЖНО: quantity здесь НЕ меняется — остаток меняется только через операции.
 */
function products_update(PDO $pdo, int $id, array $input): void
{
    $stmt = $pdo->prepare(
        'UPDATE products
         SET sku = :sku, name = :name, category_id = :cat,
             unit = :unit, min_stock = :min, price = :price
         WHERE id = :id'
    );
    $stmt->execute([
        ':sku'   => $input['sku'],
        ':name'  => $input['name'],
        ':cat'   => !empty($input['category_id']) ? (int)$input['category_id'] : null,
        ':unit'  => $input['unit'],
        ':min'   => to_float($input['min_stock'] ?? 0),
        ':price' => to_float($input['price'] ?? 0),
        ':id'    => $id,
    ]);
}

/**
 * Удаление товара.
 * Запрещаем удалять, если по нему уже есть операции — иначе история "повиснет".
 */
function products_delete(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM operations WHERE product_id = :id');
    $stmt->execute([':id' => $id]);
    if ((int)$stmt->fetchColumn() > 0) {
        json_response(false, null, 'Нельзя удалить товар: по нему есть операции', 400);
    }

    $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

/**
 * Один товар по id (с названием категории).
 */
function products_get(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.id, p.sku, p.name, p.category_id, p.unit, p.quantity,
                p.min_stock, p.price, p.created_at,
                COALESCE(c.name, "") AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['id']          = (int)$row['id'];
    $row['category_id'] = $row['category_id'] !== null ? (int)$row['category_id'] : null;
    $row['quantity']    = (float)$row['quantity'];
    $row['min_stock']   = (float)$row['min_stock'];
    $row['price']       = (float)$row['price'];
    return $row;
}