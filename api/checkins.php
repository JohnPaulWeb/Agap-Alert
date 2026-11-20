<?php
/**
 * Resident evacuation-center check-in and check-out requests.
 * GET  — current stay and recent requests
 * POST — request { action: "check_in", shelter_id } or { action: "check_out" }
 */
require_once __DIR__ . '/bootstrap.php';

$user = require_role('resident');
$db = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stay = $db->prepare(
        "SELECT c.id, c.shelter_id, c.checked_in, s.name AS shelter_name
         FROM shelter_checkins c
         JOIN shelters s ON s.id = c.shelter_id
         WHERE c.user_id = ? AND c.checked_out IS NULL
         ORDER BY c.checked_in DESC
         LIMIT 1"
    );
    $stay->execute([$user['id']]);

    $requests = $db->prepare(
        "SELECT r.id, r.request_type, r.status, r.requested_at, r.reviewed_at,
                s.name AS shelter_name
         FROM shelter_checkin_requests r
         JOIN shelters s ON s.id = r.shelter_id
         WHERE r.user_id = ?
         ORDER BY r.requested_at DESC, r.id DESC
         LIMIT 10"
    );
    $requests->execute([$user['id']]);

    json_response([
        'ok' => true,
        'active_stay' => $stay->fetch() ?: null,
        'requests' => $requests->fetchAll(),
    ]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$body = json_body();
$action = $body['action'] ?? '';
if (!in_array($action, ['check_in', 'check_out'], true)) {
    json_response(['ok' => false, 'error' => 'Invalid request.'], 400);
}

$db->beginTransaction();
try {
    // Serialize this resident's requests to prevent duplicate active stays or requests.
    $lockUser = $db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
    $lockUser->execute([$user['id']]);

    $pending = $db->prepare(
        "SELECT id FROM shelter_checkin_requests
         WHERE user_id = ? AND status = 'pending'
         LIMIT 1"
    );
    $pending->execute([$user['id']]);
    if ($pending->fetch()) {
        $db->rollBack();
        json_response(['ok' => false, 'error' => 'You already have a pending evacuation-center request.'], 409);
    }

    if ($action === 'check_in') {
        $shelterId = (int) ($body['shelter_id'] ?? 0);
        if ($shelterId < 1) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'Select an evacuation center.'], 422);
        }

        $active = $db->prepare(
            'SELECT id FROM shelter_checkins WHERE user_id = ? AND checked_out IS NULL LIMIT 1'
        );
        $active->execute([$user['id']]);
        if ($active->fetch()) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'You are already checked in at an evacuation center.'], 409);
        }

        $shelter = $db->prepare('SELECT id, is_open FROM shelters WHERE id = ? LIMIT 1');
        $shelter->execute([$shelterId]);
        $center = $shelter->fetch();
        if (!$center || !(int) $center['is_open']) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'This evacuation center is not available for check-in.'], 409);
        }

        $db->prepare(
            "INSERT INTO shelter_checkin_requests (shelter_id, user_id, request_type)
             VALUES (?, ?, 'check_in')"
        )->execute([$shelterId, $user['id']]);
    } else {
        $active = $db->prepare(
            'SELECT id, shelter_id FROM shelter_checkins WHERE user_id = ? AND checked_out IS NULL LIMIT 1 FOR UPDATE'
        );
        $active->execute([$user['id']]);
        $stay = $active->fetch();
        if (!$stay) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'You are not currently checked in at an evacuation center.'], 409);
        }

        $db->prepare(
            "INSERT INTO shelter_checkin_requests (shelter_id, user_id, request_type, checkin_id)
             VALUES (?, ?, 'check_out', ?)"
        )->execute([$stay['shelter_id'], $user['id'], $stay['id']]);
    }

    $requestId = (int) $db->lastInsertId();
    $db->commit();
    json_response(['ok' => true, 'request_id' => $requestId, 'message' => 'Request sent for admin review.']);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}
