<?php
/**
 * api/admin/activity-log-official.php
 * GET — paginated activity log for the signed-in official.
 *       Admins see all entries; officials only see their own.
 */

require_once __DIR__ . '/../../api/bootstrap.php';

$me     = require_role(['admin', 'official']);
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = 50;
$off    = ($page - 1) * $limit;

$isAdmin = $me['role'] === 'admin';

if ($isAdmin) {
    // Admin: all entries
    $rows = db()->prepare(
        "SELECT al.id, al.action, al.details, al.created_at,
                CONCAT(a.fname, IF(a.mname IS NOT NULL AND a.mname != '',
                       CONCAT(' ', a.mname, ' '), ' '), a.lname) AS actor_name,
                CONCAT(t.fname, IF(t.mname IS NOT NULL AND t.mname != '',
                       CONCAT(' ', t.mname, ' '), ' '), t.lname) AS target_name
         FROM   activity_log al
         JOIN   users a ON a.id = al.actor_id
         LEFT   JOIN users t ON t.id = al.target_id
         ORDER  BY al.created_at DESC
         LIMIT  $limit OFFSET $off"
    );
    $rows->execute();
    $total = (int) db()->query('SELECT COUNT(*) FROM activity_log')->fetchColumn();
} else {
    // Official: only their own actions
    $rows = db()->prepare(
        "SELECT al.id, al.action, al.details, al.created_at,
                CONCAT(a.fname, IF(a.mname IS NOT NULL AND a.mname != '',
                       CONCAT(' ', a.mname, ' '), ' '), a.lname) AS actor_name,
                CONCAT(t.fname, IF(t.mname IS NOT NULL AND t.mname != '',
                       CONCAT(' ', t.mname, ' '), ' '), t.lname) AS target_name
         FROM   activity_log al
         JOIN   users a ON a.id = al.actor_id
         LEFT   JOIN users t ON t.id = al.target_id
         WHERE  al.actor_id = ?
         ORDER  BY al.created_at DESC
         LIMIT  $limit OFFSET $off"
    );
    $rows->execute([$me['id']]);

    $countStmt = db()->prepare('SELECT COUNT(*) FROM activity_log WHERE actor_id = ?');
    $countStmt->execute([$me['id']]);
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
