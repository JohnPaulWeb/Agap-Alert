<?php
/**
 * config/helpers.php
 * Small shared helpers: Philippine mobile-number normalising and the
 * per-IP throttle used by the password-reset endpoints.
 */

function agap_normalize_ph_mobile(?string $raw): ?string
{
    if ($raw === null || $raw === '') return null;
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') return null;
    if (str_starts_with($digits, '63') && strlen($digits) === 12) return $digits;
    if (str_starts_with($digits, '0')  && strlen($digits) === 11) return '63' . substr($digits, 1);
    if (strlen($digits) === 10 && str_starts_with($digits, '9'))  return '63' . $digits;
    return null;
}

/**
 * Throttle for the password-reset endpoints. Returns true when the caller's
 * IP is under the limit, and records this hit. Uses REMOTE_ADDR only —
 * X-Forwarded-For can be spoofed by the client.
 */
function agap_reset_throttle(PDO $db, string $kind, int $max, int $windowSeconds): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $db->prepare("DELETE FROM reset_requests WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)")->execute();

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM reset_requests
         WHERE ip = ? AND kind = ? AND created_at >= DATE_SUB(NOW(), INTERVAL $windowSeconds SECOND)"
    );
    $stmt->execute([$ip, $kind]);
    if ((int) $stmt->fetchColumn() >= $max) return false;

    $db->prepare("INSERT INTO reset_requests (ip, kind) VALUES (?, ?)")->execute([$ip, $kind]);
    return true;
}

/**
 * Record an entry in the activity_log table.
 *
 * @param PDO        $db       Database connection.
 * @param int        $actorId  User who performed the action.
 * @param string     $action   Short action key (e.g. 'approve_official').
 * @param int|null   $targetId User or resource affected (optional).
 * @param string     $details  Human-readable summary (optional).
 */
function agap_log(PDO $db, int $actorId, string $action, ?int $targetId = null, string $details = ''): void
{
    $db->prepare(
        'INSERT INTO activity_log (actor_id, action, target_id, details) VALUES (?, ?, ?, ?)'
    )->execute([$actorId, $action, $targetId, $details ?: null]);
}
