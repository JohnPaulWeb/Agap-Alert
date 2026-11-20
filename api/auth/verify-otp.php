<?php
/**
 * api/auth/verify-otp.php
 * POST — verify the 6-digit OTP sent after Google sign-in.
 *
 * Request body (JSON):
 *   { "otp_token": "<64-char hex>", "otp": "123456" }
 *
 * On success: creates the session and returns the user object.
 */
require_once __DIR__ . '/../../api/bootstrap.php';
require_method('POST');

$body     = json_body();
$otpToken = trim($body['otp_token'] ?? '');
$otp      = trim($body['otp']       ?? '');

if (empty($otpToken) || empty($otp)) {
    json_response(['ok' => false, 'error' => 'Missing verification code.'], 400);
}

$db = db();

// Find the OTP record
$stmt = $db->prepare(
    'SELECT id, user_id, otp_hash, expires_at, used
     FROM google_otps
     WHERE token = ? LIMIT 1'
);
$stmt->execute([$otpToken]);
$record = $stmt->fetch();

if (!$record) {
    json_response(['ok' => false, 'error' => 'Invalid or expired verification session. Please try again.'], 400);
}

if ($record['used']) {
    json_response(['ok' => false, 'error' => 'This code has already been used. Please sign in again.'], 400);
}

if (strtotime($record['expires_at']) < time()) {
    $db->prepare('DELETE FROM google_otps WHERE id = ?')->execute([$record['id']]);
    json_response(['ok' => false, 'error' => 'Verification code has expired. Please sign in again.'], 400);
}

if (!password_verify($otp, $record['otp_hash'])) {
    json_response(['ok' => false, 'error' => 'Incorrect verification code. Please try again.'], 401);
}

// Mark OTP as used
$db->prepare('UPDATE google_otps SET used = 1 WHERE id = ?')->execute([$record['id']]);

// Fetch the user
$stmt = $db->prepare(
    'SELECT id, fname, mname, lname,
            CONCAT(fname, IF(mname IS NOT NULL AND mname != "", CONCAT(" ", mname, " "), " "), lname) AS full_name,
            email, phone_number, role, status, avatar_path, google_avatar_url, google_id,
            (password_hash IS NOT NULL) AS has_password,
            invite_code, must_change_password, created_at
     FROM users WHERE id = ? LIMIT 1'
);
$stmt->execute([$record['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['ok' => false, 'error' => 'Account not found.'], 404);
}

if ($user['status'] === 'rejected') {
    json_response(['ok' => false, 'error' => 'Your account access has been denied. Contact the administrator.'], 403);
}

// ── All good — create the session ────────────────────────────────────────────
sign_in((int) $user['id']);

json_response(['ok' => true, 'user' => $user]);
