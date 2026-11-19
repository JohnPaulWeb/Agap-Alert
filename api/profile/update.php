<?php
/**
 * api/profile/update.php
 * POST — updates the signed-in user's personal information.
 */
require_once __DIR__ . '/../../api/bootstrap.php';
require_once __DIR__ . '/../../config/helpers.php';
require_method('POST');

$me   = require_auth();
$body = json_body();
$db   = db();

$fname = trim($body['fname'] ?? '');
$mname = trim($body['mname'] ?? '');
$lname = trim($body['lname'] ?? '');
$phone = trim($body['phone_number'] ?? '');
$email = strtolower(trim($body['email'] ?? ''));

$errors = [];

if (mb_strlen($fname) < 1 || mb_strlen($fname) > 50)
    $errors['fname'] = 'Enter your first name.';
if (mb_strlen($lname) < 1 || mb_strlen($lname) > 50)
    $errors['lname'] = 'Enter your last name.';
if (!empty($mname) && mb_strlen($mname) > 50)
    $errors['mname'] = 'Middle name is too long.';
$phoneNorm = null;
if ($phone !== '') {
    $phoneNorm = agap_normalize_ph_mobile($phone);
    if ($phoneNorm === null)
        $errors['phone_number'] = 'Enter a valid Philippine mobile number (e.g. 09XX XXX XXXX).';
}
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors['email'] = 'Email address looks invalid.';

if (!empty($email)) {
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
    $stmt->execute([$email, $me['id']]);
    if ($stmt->fetch())
        $errors['email'] = 'This email is already in use.';
}

if ($phoneNorm !== null) {
    $stmt = $db->prepare('SELECT id FROM users WHERE phone_norm = ? AND id != ? LIMIT 1');
    $stmt->execute([$phoneNorm, $me['id']]);
    if ($stmt->fetch())
        $errors['phone_number'] = 'This mobile number is already registered.';
}

if (!empty($errors))
    json_response(['ok' => false, 'errors' => $errors], 422);

try {
    $db->prepare(
        'UPDATE users SET fname = ?, mname = ?, lname = ?, email = ?, phone_number = ?, phone_norm = ? WHERE id = ?'
    )->execute([
        $fname,
        $mname ?: null,
        $lname,
        $email ?: null,
        $phoneNorm !== null ? '0' . substr($phoneNorm, 2) : null,
        $phoneNorm,
        $me['id'],
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000')
        json_response(['ok' => false, 'errors' => ['phone_number' => 'This mobile number or email is already in use.']], 409);
    throw $e;
}

$stmt = $db->prepare(
    'SELECT id, fname, mname, lname,
            CONCAT(fname, IF(mname IS NOT NULL AND mname != "", CONCAT(" ", mname, " "), " "), lname) AS full_name,
            email, phone_number, role, status, avatar_path, google_avatar_url, invite_code, created_at
     FROM   users WHERE id = ?'
);
$stmt->execute([$me['id']]);

json_response(['ok' => true, 'user' => $stmt->fetch()]);
