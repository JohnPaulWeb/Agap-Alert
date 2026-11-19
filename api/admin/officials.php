<?php
/**
 * api/admin/officials.php
 * GET  — list approved barangay officials
 * POST — revoke official access { user_id }
 */

require_once __DIR__ . '/../../api/bootstrap.php';
require_once __DIR__ . '/../../config/helpers.php';

$admin  = require_role('admin');
$db     = db();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $rows = $db->query(
        "SELECT id, fname, mname, lname,
                CONCAT(fname, IF(mname IS NOT NULL AND mname != '', CONCAT(' ', mname, ' '), ' '), lname) AS full_name,
                email, phone_number, avatar_path, invite_code, created_at
         FROM   users
         WHERE  role = 'official' AND status = 'active'
         ORDER  BY lname ASC, fname ASC"
    )->fetchAll();

    json_response(['ok' => true, 'officials' => $rows]);
}

// ── POST — revoke ─────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body   = json_body();
    $userId = (int) ($body['user_id'] ?? 0);

    if ($userId < 1)
        json_response(['ok' => false, 'error' => 'Invalid user_id.'], 400);

    // Fetch the target's name for the log
    $targetRow = $db->prepare("SELECT fname, lname, email FROM users WHERE id = ? LIMIT 1");
    $targetRow->execute([$userId]);
    $targetUser = $targetRow->fetch();
    $targetName = $targetUser ? $targetUser['fname'] . ' ' . $targetUser['lname'] : "user #$userId";

    $db->prepare(
        "UPDATE users SET role = 'resident', status = 'active'
         WHERE  id = ? AND role = 'official'"
    )->execute([$userId]);

    agap_log($db, $admin['id'], 'revoke_official', $userId, "Revoked official access from $targetName");

    // Notify the user by email
    if (!empty($targetUser['email'])) {
        require_once __DIR__ . '/../../config/mail_helper.php';
        agap_send_mail(
            $targetUser['email'],
            'Your AgapAlert official access has been removed',
            "Hi {$targetUser['fname']},\n\n"
            . "This is to inform you that your official access to AgapAlert has been removed "
            . "by an administrator. Your account remains active as a resident.\n\n"
            . "If you believe this was done in error, please contact your barangay administrator.\n\n"
            . "— AgapAlert, Barangay Dalongue DRRMC"
        );
    }

    json_response(['ok' => true, 'message' => 'Official access revoked.']);
}

json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
