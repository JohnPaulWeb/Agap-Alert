<?php
/**
 * POST /api/forgot-password.php
 * Body: { "identifier": "name@example.com" | "09XXXXXXXXX" }  
 *
 * Finds the account by email or mobile number, generates a 6-digit OTP, stores
 * it (hashed) in password_resets, and emails it to the address on the account.
 * Every outcome returns the same reply, so the response never reveals whether
 * an email or number is registered.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/mail_helper.php';

require_method('POST');

$body       = json_body();
$identifier = trim((string) ($body['identifier'] ?? ''));

if ($identifier === '') {
    json_response(['ok' => false, 'error' => 'Ilagay ang iyong email o numero ng mobile.'], 400);
}

$byEmail = str_contains($identifier, '@');
if ($byEmail) {
    if (!filter_var($identifier, FILTER_VALIDATE_EMAIL))
        json_response(['ok' => false, 'error' => 'Hindi wastong email address.'], 400);
} elseif (agap_normalize_ph_mobile($identifier) === null) {
    json_response(['ok' => false, 'error' => 'Hindi wastong numero ng mobile.'], 400);
}

$db = db();

$generic = ['ok' => true, 'message' => 'Kung naka-rehistro ang account, makakatanggap ka ng verification code.'];

// ── Per-IP throttle: 5 requests per 15 minutes (counts unknown accounts too) ──
if (!agap_reset_throttle($db, 'request', 5, 15 * 60)) {
    json_response(['ok' => false, 'error' => 'Masyadong maraming kahilingan. Subukan muli pagkatapos ng 15 minuto.'], 429);
}

// ── Find user ─────────────────────────────────────────────────────────────────
$user = agap_find_reset_user($db, $identifier);
if (!$user) json_response($generic);

// ── Rate-limit: max 3 codes per account per 10 minutes (silent) ───────────────
$limitStmt = $db->prepare(
    "SELECT COUNT(*) FROM password_resets
     WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
);
$limitStmt->execute([$user['id']]);
if ((int) $limitStmt->fetchColumn() >= 3) json_response($generic);

// ── Generate OTP ──────────────────────────────────────────────────────────────
$otp     = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$hash    = password_hash($otp, PASSWORD_DEFAULT);

$db->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0")
   ->execute([$user['id']]);
// Expiry is computed by the database (NOW()) so PHP's and MySQL's time zones can never disagree.
$db->prepare("INSERT INTO password_resets (user_id, code_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))")
   ->execute([$user['id'], $hash]);

// ── Deliver (email only) ──────────────────────────────────────────────────────
if (!empty($user['email'])) {
    $text = "Ang iyong AgapAlert verification code ay: $otp\n"
          . "Your AgapAlert verification code is: $otp\n\n"
          . "Nag-e-expire ito sa loob ng 10 minuto. / It expires in 10 minutes.\n"
          . "Huwag ibahagi ito sa iba. Kung hindi ikaw ang humiling, huwag mo na lang pansinin ang email na ito.\n"
          . "Do not share this code. If you didn't ask for it, ignore this email.\n";
    agap_send_mail($user['email'], 'AgapAlert verification code', $text);
}

json_response($generic);
