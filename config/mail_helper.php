<?php
require_once __DIR__ . '/helpers.php';

function agap_mail_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/mail.php';
        $cfg  = is_file($path) ? require $path : [];
    }
    return is_array($cfg) ? $cfg : [];
}

function agap_mail_enabled(): bool
{
    $c = agap_mail_config();
    return !empty($c['enabled']) && !empty($c['host']);
}

/**
 * Send a plain-text email through the configured SMTP account.
 * Returns true on success; failures are logged, never shown to the caller.
 */
function agap_send_mail(string $to, string $subject, string $body): bool
{
    if (!agap_mail_enabled()) return false;
    $c = agap_mail_config();

    require_once __DIR__ . '/../lib/phpmailer/Exception.php';
    require_once __DIR__ . '/../lib/phpmailer/PHPMailer.php';
    require_once __DIR__ . '/../lib/phpmailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host    = (string) $c['host'];
        $mail->Port    = (int) ($c['port'] ?? 587);
        $secure        = (string) ($c['secure'] ?? '');
        $mail->SMTPSecure   = $secure;
        $mail->SMTPAutoTLS  = $secure !== '';
        $mail->Timeout      = 15;
        if (!empty($c['username'])) {
            $mail->SMTPAuth = true;
            $mail->Username = (string) $c['username'];
            $mail->Password = str_replace(' ', '', (string) ($c['password'] ?? ''));
        }
        $from = $c['from_email'] ?: ($c['username'] ?? '');
        $mail->setFrom((string) $from, (string) ($c['from_name'] ?? 'AgapAlert'));
        $mail->addAddress($to);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('AgapAlert mail failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Find the account a reset request refers to. $identifier is an email address
 * or a Philippine mobile number. Pending officials are allowed; rejected are not.
 * Returns ['id' => .., 'email' => .., 'phone_norm' => ..] or null.
 */
function agap_find_reset_user(PDO $db, string $identifier): ?array
{
    $identifier = trim($identifier);
    if ($identifier === '') return null;

    if (str_contains($identifier, '@')) {
        $stmt = $db->prepare(
            "SELECT id, email, phone_norm FROM users
             WHERE email = ? AND status IN ('active','pending') LIMIT 1"
        );
        $stmt->execute([strtolower($identifier)]);
    } else {
        $norm = agap_normalize_ph_mobile($identifier);
        if ($norm === null) return null;
        $stmt = $db->prepare(
            "SELECT id, email, phone_norm FROM users
             WHERE phone_norm = ? AND status IN ('active','pending') LIMIT 1"
        );
        $stmt->execute([$norm]);
    }
    return $stmt->fetch() ?: null;
}
