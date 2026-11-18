<?php
/**
 * api/profile/password.php
 * POST — changes the signed-in user's password.
 */

require_once __DIR__ . '/../../api/bootstrap.php';
require_once __DIR__ . '/../../config/helpers.php';
require_method('POST');

$me   = require_auth();
$body = json_body();

$currentPassword = $body['current_password']  ?? '';
$newPassword     = $body['password']           ?? '';
$confirmPassword = $body['password_confirm']   ?? '';

$db = db();

// When a temporary password was issued the user has no "current" password to
// verify against — skip the current-password check in that case.
$mustChange = (int) ($me['must_change_password'] ?? 0) === 1;

if (!$mustChange) {
    // Normal password change — require current password verification
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$me['id']]);
    $row = $stmt->fetch();

    if (empty($currentPassword) || !$row || !password_verify($currentPassword, $row['password_hash']))
        json_response(['ok' => false, 'errors' => ['current_password' => 'Current password is incorrect.']], 422);
}

if (strlen($newPassword) < 8)
    json_response(['ok' => false, 'errors' => ['password' => 'Password must be at least 8 characters.']], 422);

if ($newPassword !== $confirmPassword)
    json_response(['ok' => false, 'errors' => ['password_confirm' => 'Passwords do not match.']], 422);

$hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$db->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
    ->execute([$hash, $me['id']]);

agap_log($db, $me['id'], 'change_password', $me['id'], 'User changed their own password');

json_response(['ok' => true, 'message' => 'Password updated.']);
