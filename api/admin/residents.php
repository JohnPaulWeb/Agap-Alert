<?php
/**
 * api/admin/residents.php
 * GET  — list all resident accounts (paginated, 50/page)
 * POST { action:'reset_password', user_id } — issue a temporary password
 */

require_once __DIR__ . '/../../api/bootstrap.php';
require_once __DIR__ . '/../../config/helpers.php';

$admin  = require_role('admin');
$db     = db();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET — list residents ──────────────────────────────────────────────────────
if ($method === 'GET') {
    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 50;
    $off   = ($page - 1) * $limit;

    $search = trim($_GET['search'] ?? '');

    if ($search !== '') {
        $like  = '%' . $search . '%';
        $rows  = $db->prepare(
            "SELECT id,
                    CONCAT(fname, IF(mname IS NOT NULL AND mname != '', CONCAT(' ', mname, ' '), ' '), lname) AS full_name,
                    email, phone_number, status, must_change_password, created_at
             FROM   users
             WHERE  role = 'resident'
               AND  (fname LIKE ? OR lname LIKE ? OR email LIKE ? OR phone_number LIKE ?)
             ORDER  BY created_at DESC
             LIMIT  $limit OFFSET $off"
        );
        $rows->execute([$like, $like, $like, $like]);

        $totalStmt = $db->prepare(
            "SELECT COUNT(*) FROM users
             WHERE  role = 'resident'
               AND  (fname LIKE ? OR lname LIKE ? OR email LIKE ? OR phone_number LIKE ?)"
        );
        $totalStmt->execute([$like, $like, $like, $like]);
    } else {
        $rows = $db->prepare(
            "SELECT id,
                    CONCAT(fname, IF(mname IS NOT NULL AND mname != '', CONCAT(' ', mname, ' '), ' '), lname) AS full_name,
                    email, phone_number, status, must_change_password, created_at
             FROM   users
             WHERE  role = 'resident'
             ORDER  BY created_at DESC
             LIMIT  $limit OFFSET $off"
        );
        $rows->execute();

        $totalStmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'resident'");
    }

    $total = (int) $totalStmt->fetchColumn();

    json_response([
        'ok'        => true,
        'residents' => $rows->fetchAll(),
        'meta'      => [
            'total' => $total,
            'page'  => $page,
            'pages' => max(1, (int) ceil($total / $limit)),
        ],
    ]);
}

// ── POST — reset password ─────────────────────────────────────────────────────
if ($method === 'POST') {
    $body   = json_body();
    $action = $body['action'] ?? '';
    $userId = (int) ($body['user_id'] ?? 0);

    if ($action !== 'reset_password' || $userId < 1)
        json_response(['ok' => false, 'error' => 'Invalid request.'], 400);

    // Confirm target is a resident (or at least not another admin)
    $target = $db->prepare("SELECT id, fname, lname, role FROM users WHERE id = ? LIMIT 1");
    $target->execute([$userId]);
    $user = $target->fetch();

    if (!$user)
        json_response(['ok' => false, 'error' => 'User not found.'], 404);
    if ($user['role'] === 'admin')
        json_response(['ok' => false, 'error' => 'Cannot reset another administrator\'s password.'], 403);

    // Generate a readable temporary password (12 chars: letters + digits)
    $chars    = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $tempPass = '';
    for ($i = 0; $i < 12; $i++) {
        $tempPass .= $chars[random_int(0, strlen($chars) - 1)];
    }

    $hash = password_hash($tempPass, PASSWORD_BCRYPT, ['cost' => 12]);

    $db->prepare(
        'UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?'
    )->execute([$hash, $userId]);

    agap_log(
        $db, $admin['id'], 'reset_password', $userId,
        'Temporary password issued for ' . $user['fname'] . ' ' . $user['lname']
    );

    json_response([
        'ok'           => true,
        'temp_password' => $tempPass,
        'message'      => 'Temporary password issued. The user will be required to change it on next login.',
    ]);
}

json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
