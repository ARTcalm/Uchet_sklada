<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

/**
 * Список audit-записей с фильтрами и пагинацией.
 */
function audit_list(PDO $pdo, int $companyId, array $filters, int $limit, int $offset): array
{
    $where  = ['a.company_id = :cid'];
    $params = [':cid' => $companyId];

    if (!empty($filters['user_id'])) {
        $where[] = 'a.user_id = :uid';
        $params[':uid'] = (int)$filters['user_id'];
    }
    if (!empty($filters['action'])) {
        $where[] = 'a.action = :action';
        $params[':action'] = $filters['action'];
    }
    if (!empty($filters['entity'])) {
        $where[] = 'a.entity = :entity';
        $params[':entity'] = $filters['entity'];
    }
    if (!empty($filters['from'])) {
        $where[] = 'a.created_at >= :from';
        $params[':from'] = $filters['from'] . ' 00:00:00';
    }
    if (!empty($filters['to'])) {
        $where[] = 'a.created_at < DATE_ADD(:to, INTERVAL 1 DAY)';
        $params[':to'] = $filters['to'];
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log a $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "
        SELECT a.id, a.user_id, a.action, a.entity, a.entity_id, a.created_at,
            COALESCE(u.full_name, '—') AS user_name,
            u.email AS user_email
        FROM audit_log a
        LEFT JOIN users u ON u.id = a.user_id
        $whereSql
        ORDER BY a.created_at DESC, a.id DESC
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
        $r['id']        = (int)$r['id'];
        $r['user_id']   = $r['user_id'] !== null ? (int)$r['user_id'] : null;
        $r['entity_id'] = $r['entity_id'] !== null ? (int)$r['entity_id'] : null;
    }
    unset($r);

    return [
        'items'    => $rows,
        'total'    => $total,
        'page'     => (int)floor($offset / max(1, $limit)) + 1,
        'per_page' => $limit,
    ];
}

/**
 * Уникальные значения action / entity для фильтров.
 */
function audit_distinct(PDO $pdo, int $companyId): array
{
    $stmt = $pdo->prepare('SELECT DISTINCT action FROM audit_log WHERE company_id = :cid ORDER BY action');
    $stmt->execute([':cid' => $companyId]);
    $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare('SELECT DISTINCT entity FROM audit_log WHERE company_id = :cid ORDER BY entity');
    $stmt->execute([':cid' => $companyId]);
    $entities = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return ['actions' => $actions, 'entities' => $entities];
}