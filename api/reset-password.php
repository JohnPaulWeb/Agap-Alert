<?php
/**
 * POST /api/reset-password.php
 * Body: { "identifier": "email or 09XXXXXXXXX", "otp": "123456", "password": "newpass" }
 *
 * Verifies the OTP and updates the user's password.
 */
require_once __DIR__ . '/bootstrap.php';
require_method('POST');

$body     = json_body();
$identifier = trim((string) ($body['identifier'] ?? ''));
$otp      = trim($body['otp']      ?? '');
$password = $body['password']      ?? '';

if ($identifier === '' || $otp === '' || $password === '') {
    json_response(['ok' => false, 'error' => 'Kinakailangan ang lahat ng field.'], 400);
}

if (strlen($password) < 8) {
    json_response(['ok' => false, 'error' => 'Ang password ay dapat hindi bababa sa 8 character.'], 400);
}

require_once __DIR__ . '/../config/mail_helper.php';

$db = db();

$badCode = 'Mali o nag-expire na ang verification code.';   // same text for every failure

// ── Per-IP throttle: 10 verification tries per 15 minutes ─────────────────────
if (!agap_reset_throttle($db, 'verify', 10, 15 * 60)) {
    json_response(['ok' => false, 'error' => 'Masyadong maraming pagtatangka. Subukan muli pagkatapos ng 15 minuto.'], 429);
}

// ── Find user (email or unique mobile number; pending allowed, rejected not) ──
$user = agap_find_reset_user($db, $identifier);

if (!$user) {
    json_response(['ok' => false, 'error' => $badCode], 400);
}

// ── Fetch latest unused, non-expired OTP ──────────────────────────────────────
$codeStmt = $db->prepare(
    "SELECT id, code_hash FROM password_resets
     WHERE user_id = ? AND used = 0 AND attempts < 5 AND expires_at > NOW()
     ORDER BY created_at DESC
     LIMIT 1"
);
$codeStmt->execute([$user['id']]);
$row = $codeStmt->fetch();

if (!$row) {
    json_response(['ok' => false, 'error' => $badCode], 400);
}

// Spend one of the code's 5 attempts first (atomic, so parallel guesses can't
// exceed the limit), then check the code.
$spend = $db->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE id = ? AND used = 0 AND attempts < 5");
$spend->execute([$row['id']]);
if ($spend->rowCount() !== 1 || !password_verify($otp, $row['code_hash'])) {
    json_response(['ok' => false, 'error' => $badCode], 400);
}

// ── Mark code as used (only one request can win this) ─────────────────────────
$claim = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = ? AND used = 0");
$claim->execute([$row['id']]);
if ($claim->rowCount() !== 1) {
    json_response(['ok' => false, 'error' => $badCode], 400);
}

// ── Update password ───────────────────────────────────────────────────────────
$newHash = password_hash($password, PASSWORD_DEFAULT);
$db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
   ->execute([$newHash, $user['id']]);

json_response(['ok' => true, 'message' => 'Na-reset na ang iyong password.']);
