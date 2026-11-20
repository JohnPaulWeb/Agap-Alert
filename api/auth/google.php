<?php
/**
 * api/auth/google.php
 * POST — verify a Google ID token and sign the user in (or create an account).
 *
 * Request body (JSON):
 *   { "credential": "<Google ID token JWT>" }
 *
 * Behaviour:
 *   - Existing account  → sign in directly.
 *   - No account found  → create one automatically, return { ok: true, is_new: true }.
 *     The frontend decides what to do next (login page redirects to register to
 *     collect the remaining required fields; register page proceeds normally).
 */
require_once __DIR__ . '/../../api/bootstrap.php';
require_method('POST');

// ── Read the client ID from config ───────────────────────────────────────────
$googleCfgPath = __DIR__ . '/../../config/google.php';
if (!is_file($googleCfgPath)) {
    json_response(['ok' => false, 'error' => 'Google sign-in is not configured on this server.'], 503);
}
$googleCfg = require $googleCfgPath;
$clientId  = $googleCfg['client_id'] ?? '';
if (empty($clientId)) {
    json_response(['ok' => false, 'error' => 'Google sign-in is not configured on this server.'], 503);
}

// ── Get the credential from the request ──────────────────────────────────────
$body       = json_body();
$credential = trim($body['credential'] ?? '');
$mode       = $body['mode'] ?? 'register';  // 'login' or 'register'

if (empty($credential)) {
    json_response(['ok' => false, 'error' => 'Missing Google credential.'], 400);
}

// ── Verify the ID token with Google ──────────────────────────────────────────
$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
$ctx = stream_context_create(['http' => ['timeout' => 10]]);
$raw = @file_get_contents($verifyUrl, false, $ctx);
if ($raw === false) {
    json_response(['ok' => false, 'error' => 'Could not reach Google to verify your sign-in. Try again.'], 502);
}

$payload = json_decode($raw, true);
if (!is_array($payload) || !empty($payload['error_description'])) {
    json_response(['ok' => false, 'error' => 'Invalid Google token. Please try again.'], 401);
}

if (($payload['aud'] ?? '') !== $clientId) {
    json_response(['ok' => false, 'error' => 'Token audience mismatch.'], 401);
}

$googleId      = $payload['sub']            ?? '';
$email         = strtolower(trim($payload['email'] ?? ''));
$emailVerified = ($payload['email_verified'] ?? 'false') === 'true';
$givenName     = trim($payload['given_name']  ?? '');
$familyName    = trim($payload['family_name'] ?? '');
$picture       = trim($payload['picture']     ?? '');

if (empty($googleId) || empty($email) || !$emailVerified) {
    json_response(['ok' => false, 'error' => 'Google account does not have a verified email address.'], 400);
}

// ── Helper: fetch a fresh user row ───────────────────────────────────────────
function fetchUser(PDO $db, int $id): array|false {
    $stmt = $db->prepare(
        'SELECT id, fname, mname, lname,
                CONCAT(fname, IF(mname IS NOT NULL AND mname != "", CONCAT(" ", mname, " "), " "), lname) AS full_name,
                email, phone_number, role, status, avatar_path, google_avatar_url, google_id,
                (password_hash IS NOT NULL) AS has_password,
                invite_code, must_change_password, created_at
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// ── Find the user ─────────────────────────────────────────────────────────────
$db     = db();
$row    = null;
$isNew  = false;

// 1. Find by google_id (returning user)
$stmt = $db->prepare('SELECT id FROM users WHERE google_id = ? LIMIT 1');
$stmt->execute([$googleId]);
$row = $stmt->fetch() ?: null;

// 2. Find by email (had a manual account before — link it)
if (!$row && !empty($email)) {
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch() ?: null;

    if ($row) {
        $db->prepare('UPDATE users SET google_id = ?, google_avatar_url = ? WHERE id = ?')
           ->execute([$googleId, $picture ?: null, $row['id']]);
    }
}

// ── If no account exists ─────────────────────────────────────────────────────
if (!$row) {
    if ($mode === 'login') {
        json_response([
            'ok'             => false,
            'needs_register' => true,
            'error'          => 'No AgapAlert account found for this Google account. Please register first.',
        ], 404);
    }

    // Register mode — don't create account yet, just return Google profile data
    // so the form can be pre-filled. Account is created when the form is submitted.
    json_response([
        'ok'          => true,
        'prefill_only'=> true,
        'profile'     => [
            'fname'            => $givenName,
            'lname'            => $familyName,
            'email'            => $email,
            'google_id'        => $googleId,
            'google_avatar_url'=> $picture,
        ],
    ]);
}

// Always refresh the Google avatar URL in case it changed
if (!empty($picture)) {
    $db->prepare('UPDATE users SET google_avatar_url = ? WHERE id = ?')
       ->execute([$picture, $row['id']]);
}

$user = fetchUser($db, (int) $row['id']);

// ── Guard: rejected accounts cannot sign in ──────────────────────────────────
if ($user['status'] === 'rejected') {
    json_response(['ok' => false, 'error' => 'Your account access has been denied. Contact the administrator.'], 403);
}

// ── Sign in directly — no OTP ─────────────────────────────────────────────────
sign_in((int) $user['id']);

json_response(['ok' => true, 'is_new' => $isNew, 'user' => $user]);

// New user — generate OTP and email it before granting access
require_once __DIR__ . '/../../config/mail_helper.php';

// Clean up expired OTPs for this user
$db->prepare('DELETE FROM google_otps WHERE user_id = ? OR expires_at < NOW()')
   ->execute([$user['id']]);

// Generate 6-digit OTP and a secure token for the verify page
$otp        = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$otpHash    = password_hash($otp, PASSWORD_BCRYPT, ['cost' => 10]);
$otpToken   = bin2hex(random_bytes(32));   // 64-char hex token
$expiresAt  = date('Y-m-d H:i:s', time() + 10 * 60);   // 10 minutes

$db->prepare(
    'INSERT INTO google_otps (user_id, otp_hash, token, expires_at) VALUES (?, ?, ?, ?)'
)->execute([$user['id'], $otpHash, $otpToken, $expiresAt]);

// Send OTP email
$emailSent = false;
if (!empty($user['email'])) {
    $emailSent = agap_send_mail(
        $user['email'],
        'Your AgapAlert verification code',
        "Hi {$user['fname']},\n\n"
        . "Your verification code is:\n\n"
        . "  {$otp}\n\n"
        . "This code expires in 10 minutes.\n"
        . "Do not share this code with anyone.\n\n"
        . "If you did not request this, ignore this email.\n\n"
        . "— AgapAlert, Barangay Dalongue DRRMC"
    );

