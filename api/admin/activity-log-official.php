<?php
/**
 * api/admin/activity-log-official.php
 * GET — paginated activity log for the signed-in official.
 *       Admins see all entries; officials only see their own.
 *
 * Query params: page, from (YYYY-MM-DD), to (YYYY-MM-DD)
 */

require_once __DIR__ . '/../../api/bootstrap.php';

$me    = require_role(['admin', 'official']);
$page  = max(1, (int) ($_GET['page'] ?? 1));
$limit = 50;
$off   = ($page - 1) * $limit;

$from    = trim($_GET['from'] ?? '');
$to      = trim($_GET['to']   ?? '');
$isAdmin = $me['role'] === 'admin';

$dateWhere  = '';
$dateParams = [];
if ($from) { $dateWhere .= ' AND al.created_at >= ?'; $dateParams[] = $from . ' 00:00:00'; }
if ($to)   { $dateWhere .= ' AND al.created_at <= ?'; $dateParams[] = $to   . ' 23:59:59'; }

if ($isAdmin) {
    $rows = db()->prepare(
        "SELECT al.id, al.action, al.details, al.created_at,
                CONCAT(a.fname, IF(a.mname IS NOT NULL AND a.mname != '',
                       CONCAT(' ', a.mname, ' '), ' '), a.lname) AS actor_name,
                CONCAT(t.fname, IF(t.mname IS NOT NULL AND t.mname != '',
                       CONCAT(' ', t.mname, ' '), ' '), t.lname) AS target_name
         FROM   activity_log al
         JOIN   users a ON a.id = al.actor_id
         LEFT   JOIN users t ON t.id = al.target_id
         WHERE  1=1 $dateWhere
         ORDER  BY al.created_at DESC
         LIMIT  $limit OFFSET $off"
    );
    $rows->execute($dateParams);

    $countStmt = db()->prepare("SELECT COUNT(*) FROM activity_log al WHERE 1=1 $dateWhere");
    $countStmt->execute($dateParams);
    $total = (int) $countStmt->fetchColumn();
} else {
    $params = array_merge([$me['id']], $dateParams);
    $rows = db()->prepare(
        "SELECT al.id, al.action, al.details, al.created_at,
                CONCAT(a.fname, IF(a.mname IS NOT NULL AND a.mname != '',
                       CONCAT(' ', a.mname, ' '), ' '), a.lname) AS actor_name,
                CONCAT(t.fname, IF(t.mname IS NOT NULL AND t.mname != '',
                       CONCAT(' ', t.mname, ' '), ' '), t.lname) AS target_name
         FROM   activity_log al
         JOIN   users a ON a.id = al.actor_id
         LEFT   JOIN users t ON t.id = al.target_id
         WHERE  al.actor_id = ? $dateWhere
         ORDER  BY al.created_at DESC
         LIMIT  $limit OFFSET $off"
    );
    $rows->execute($params);

    $countStmt = db()->prepare("SELECT COUNT(*) FROM activity_log al WHERE al.actor_id = ? $dateWhere");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
}

json_response([
    'ok'  => true,
    'log' => $rows->fetchAll(),
    'meta' => [
        'total' => $total,
        'page'  => $page,
        'pages' => max(1, (int) ceil($total / $limit)),
    ],
]);
