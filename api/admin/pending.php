<?php
/**
 * api/admin/pending.php
 * GET  — list pending official requests
 * POST — approve or reject { user_id, action: "approve"|"reject" }
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
                email, phone_number, status, invite_code, created_at
         FROM   users
         WHERE  role = 'official' AND status = 'pending'
         ORDER  BY created_at ASC"
    )->fetchAll();

    json_response(['ok' => true, 'pending' => $rows]);
}

// ── POST — approve or reject ──────────────────────────────────────────────────
if ($method === 'POST') {
    $body    = json_body();
    $userId  = (int) ($body['user_id'] ?? 0);
    $action  = $body['action'] ?? '';

    if (!in_array($action, ['approve', 'reject'], true) || $userId < 1)
        json_response(['ok' => false, 'error' => 'Invalid request.'], 400);

    // Fetch the target's name for the log
    $targetRow = $db->prepare("SELECT fname, lname, email FROM users WHERE id = ? LIMIT 1");
    $targetRow->execute([$userId]);
    $targetUser = $targetRow->fetch();
    $targetName = $targetUser ? $targetUser['fname'] . ' ' . $targetUser['lname'] : "user #$userId";

    if ($action === 'approve') {
        $db->prepare(
            "UPDATE users SET status = 'active' WHERE id = ? AND role = 'official' AND status = 'pending'"
        )->execute([$userId]);
        agap_log($db, $admin['id'], 'approve_official', $userId, "Approved official access for $targetName");

        // Email the user to confirm approval
        if (!empty($targetUser['email'])) {
            require_once __DIR__ . '/../../config/mail_helper.php';
            agap_send_mail(
                $targetUser['email'],
                'Your AgapAlert official access has been approved',
                "Hi {$targetUser['fname']},\n\n"
                . "Great news! Your request for official access to AgapAlert has been approved.\n\n"
                . "You now have access to the barangay official panel where you can manage "
                . "evacuation centers, view incident reports, and publish flood advisories.\n\n"
                . "Sign in at: http://localhost/agapalert/login.html\n\n"
                . "— AgapAlert, Barangay Dalongue DRRMC"
            );
        }

        json_response(['ok' => true, 'message' => 'Official access granted.']);
    }

    // reject
    $db->prepare(
        "UPDATE users SET status = 'rejected' WHERE id = ? AND status = 'pending'"
    )->execute([$userId]);
    agap_log($db, $admin['id'], 'reject_official', $userId, "Rejected official request from $targetName");

    // Email the user if they have an email address
    if (!empty($targetUser['email'])) {
        require_once __DIR__ . '/../../config/mail_helper.php';
        agap_send_mail(
            $targetUser['email'],
            'Your AgapAlert official access request was not approved',
            "Hi {$targetUser['fname']},\n\n"
            . "We're sorry to inform you that your request for official access to AgapAlert "
            . "has not been approved at this time.\n\n"
            . "If you believe this is a mistake or would like more information, "
            . "please contact your barangay administrator directly.\n\n"
            . "You may still use AgapAlert as a resident to receive flood alerts and updates.\n\n"
            . "— AgapAlert, Barangay Dalongue DRRMC"
        );
    }

    json_response(['ok' => true, 'message' => 'Request rejected.']);
}

json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
