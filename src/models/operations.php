<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

function operations_list(PDO $pdo, int $companyId, array $filters, int $limit, int $offset): array
{
    $where  = ['o.company_id = :cid'];
    $params = [':cid' => $companyId];

    if (!empty($filters['type'])) {
        $where[] = 'o.type = :type';
        $params[':type'] = $filters['type'];
    }
    if (!empty($filters['product_id'])) {
        $where[] = 'o.product_id = :pid';
        $params[':pid'] = (int)$filters['product_id'];
    }
    if (!empty($filters['user_id'])) {
        $where[] = 'o.user_id = :uid';
        $params[':uid'] = (int)$filters['user_id'];
    }
    if (!empty($filters['from'])) {
        $where[] = 'o.created_at >= :from';
        $params[':from'] = $filters['from'] . ' 00:00:00';
    }
    if (!empty($filters['to'])) {
        $where[] = 'o.created_at < DATE_ADD(:to, INTERVAL 1 DAY)';
        $params[':to'] = $filters['to'];
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM operations o $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "
        SELECT o.id, o.type, o.qty, o.comment, o.created_at,
               p.sku, p.name AS product_name,
               u.full_name AS user_name
        FROM operations o
        JOIN products p ON p.id = o.product_id
        JOIN users    u ON u.id = o.user_id
        $whereSql
        ORDER BY o.created_at DESC, o.id DESC
        LIMIT :lim OFFSET :off
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id']  = (int)$r['id'];
        $r['qty'] = (float)$r['qty'];
    }
    unset($r);

    return [
        'items'    => $rows,
        'total'    => $total,
        'page'     => (int)floor($offset / max(1, $limit)) + 1,
        'per_page' => $limit,
    ];
}

function validate_operation(array $input): array
{
    return validate_fields($input, [
        'type'       => ['required' => true, 'in' => ['in', 'out', 'writeoff']],
        'product_id' => ['required' => true, 'type' => 'int', 'min' => 1],
        'qty'        => ['required' => true, 'type' => 'float', 'min' => 0.0001],
        'comment'    => ['maxlen' => 255],
    ]);
}

function operations_create(PDO $pdo, array $input, int $userId, int $companyId): array
{
    $type      = (string)$input['type'];
    $productId = (int)$input['product_id'];
    $qty       = (float)$input['qty'];
    $comment   = isset($input['comment']) ? sanitize((string)$input['comment']) : '';

    try {
        $pdo->beginTransaction();

        // Блокируем строку товара, но только своей компании
        $stmt = $pdo->prepare(
            'SELECT id, quantity FROM products WHERE id = :id AND company_id = :cid FOR UPDATE'
        );
        $stmt->execute([':id' => $productId, ':cid' => $companyId]);
        $product = $stmt->fetch();

        if (!$product) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Товар не найден'];
        }

        $current = (float)$product['quantity'];
        $delta   = 0.0;

        switch ($type) {
            case 'in':
                $delta = $qty;
                break;
            case 'out':
            case 'writeoff':
                if ($current < $qty) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Недостаточно товара'];
                }
                $delta = -$qty;
                break;
            default:
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Неизвестный тип операции'];
        }

        $upd = $pdo->prepare('UPDATE products SET quantity = quantity + :d WHERE id = :id AND company_id = :cid');
        $upd->execute([':d' => $delta, ':id' => $productId, ':cid' => $companyId]);

        $ins = $pdo->prepare(
            'INSERT INTO operations (company_id, type, product_id, qty, comment, user_id, created_at)
             VALUES (:cid, :t, :pid, :qty, :c, :uid, NOW())'
        );
        $ins->execute([
            ':cid' => $companyId,
            ':t'   => $type,
            ':pid' => $productId,
            ':qty' => $qty,
            ':c'   => $comment !== '' ? $comment : null,
            ':uid' => $userId,
        ]);
        $opId = (int)$pdo->lastInsertId();

        $pdo->commit();

        return ['ok' => true, 'id' => $opId, 'quantity' => $current + $delta];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[operation] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Ошибка при проведении операции'];
    }
}

function operations_delete(PDO $pdo, int $operationId, int $companyId): array
{
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT id, type, product_id, qty FROM operations WHERE id = :id AND company_id = :cid FOR UPDATE'
        );
        $stmt->execute([':id' => $operationId, ':cid' => $companyId]);
        $op = $stmt->fetch();

        if (!$op) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Операция не найдена'];
        }

        $productId = (int)$op['product_id'];
        $type      = (string)$op['type'];
        $qty       = (float)$op['qty'];

        $stmt = $pdo->prepare('SELECT id, quantity FROM products WHERE id = :id AND company_id = :cid FOR UPDATE');
        $stmt->execute([':id' => $productId, ':cid' => $companyId]);
        $product = $stmt->fetch();

        if (!$product) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Товар операции не найден'];
        }

        $current = (float)$product['quantity'];
        $delta = 0.0;

        switch ($type) {
            case 'in':
                if ($current < $qty) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Невозможно удалить приход: текущий остаток меньше количества операции'];
                }
                $delta = -$qty;
                break;
            case 'out':
            case 'writeoff':
                $delta = $qty;
                break;
            default:
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Неизвестный тип операции'];
        }

        $upd = $pdo->prepare('UPDATE products SET quantity = quantity + :d WHERE id = :id AND company_id = :cid');
        $upd->execute([':d' => $delta, ':id' => $productId, ':cid' => $companyId]);

        $del = $pdo->prepare('DELETE FROM operations WHERE id = :id AND company_id = :cid');
        $del->execute([':id' => $operationId, ':cid' => $companyId]);

        $pdo->commit();

        return ['ok' => true, 'product_id' => $productId, 'delta' => $delta, 'new_quantity' => $current + $delta];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[operations_delete] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Ошибка при удалении операции'];
    }
}