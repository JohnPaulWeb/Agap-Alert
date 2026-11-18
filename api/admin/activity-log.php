<?php
/**
 * api/admin/activity-log.php
 * GET — paginated list of admin activity log entries, newest first.
 */

require_once __DIR__ . '/../../api/bootstrap.php';
require_role('admin');

$page  = max(1, (int) ($_GET['page'] ?? 1));
$limit = 50;
$off   = ($page - 1) * $limit;

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

json_response([
    'ok'  => true,
    'log' => $rows->fetchAll(),
    'meta' => [
        'total' => $total,
        'page'  => $page,
        'pages' => max(1, (int) ceil($total / $limit)),
    ],
]);
