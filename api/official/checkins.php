<?php
/**
 * api/official/checkins.php
 * GET  — list pending resident check-in/check-out requests
 * POST — approve or reject { request_id, action: "approve"|"reject" }
 */
require_once __DIR__ . '/../../api/bootstrap.php';
require_once __DIR__ . '/../../config/helpers.php';

$official = require_role('official');
$db = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $db->query(
        "SELECT r.id, r.request_type, r.requested_at, r.shelter_id,
                CONCAT(u.fname, IF(u.mname IS NOT NULL AND u.mname != '',
                    CONCAT(' ', u.mname, ' '), ' '), u.lname) AS full_name,
                s.name AS shelter_name
         FROM shelter_checkin_requests r
         JOIN users u ON u.id = r.user_id
         JOIN shelters s ON s.id = r.shelter_id
         WHERE r.status = 'pending'
         ORDER BY r.requested_at ASC, r.id ASC"
    )->fetchAll();

    json_response(['ok' => true, 'requests' => $rows]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$body = json_body();
$requestId = (int) ($body['request_id'] ?? 0);
$action = $body['action'] ?? '';
if ($requestId < 1 || !in_array($action, ['approve', 'reject'], true)) {
    json_response(['ok' => false, 'error' => 'Invalid request.'], 400);
}

$db->beginTransaction();
try {
    $requestStmt = $db->prepare(
        'SELECT id, user_id, shelter_id, request_type, checkin_id
         FROM shelter_checkin_requests
         WHERE id = ? AND status = "pending"
         LIMIT 1 FOR UPDATE'
    );
    $requestStmt->execute([$requestId]);
    $request = $requestStmt->fetch();
    if (!$request) {
        $db->rollBack();
        json_response(['ok' => false, 'error' => 'Request not found or already reviewed.'], 409);
    }

    $shelterStmt = $db->prepare('SELECT name, is_open, capacity, occupancy FROM shelters WHERE id = ? LIMIT 1 FOR UPDATE');
    $shelterStmt->execute([$request['shelter_id']]);
    $shelter = $shelterStmt->fetch();
    if (!$shelter) {
        $db->rollBack();
        json_response(['ok' => false, 'error' => 'Evacuation center not found.'], 404);
    }

    $targetUser = $db->prepare('SELECT fname, lname FROM users WHERE id = ? LIMIT 1');
    $targetUser->execute([$request['user_id']]);
    $residentName = $targetUser->fetch();
    $residentLabel = $residentName ? $residentName['fname'] . ' ' . $residentName['lname'] : 'resident';

    if ($action === 'approve' && $request['request_type'] === 'check_in') {
        $lockResident = $db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $lockResident->execute([$request['user_id']]);

        $activeStay = $db->prepare(
            'SELECT id FROM shelter_checkins
             WHERE user_id = ? AND checked_out IS NULL
             LIMIT 1 FOR UPDATE'
        );
        $activeStay->execute([$request['user_id']]);
        if ($activeStay->fetch()) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'The resident is already checked in at another evacuation center.'], 409);
        }

        if (!(int) $shelter['is_open']) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'This evacuation center is closed.'], 409);
        }
        if ((int) $shelter['occupancy'] >= (int) $shelter['capacity']) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'This evacuation center is at capacity.'], 409);
        }

        $db->prepare(
            'INSERT INTO shelter_checkins (shelter_id, user_id) VALUES (?, ?)'
        )->execute([$request['shelter_id'], $request['user_id']]);
        $checkinId = (int) $db->lastInsertId();
        $db->prepare('UPDATE shelters SET occupancy = occupancy + 1 WHERE id = ?')
           ->execute([$request['shelter_id']]);
        $db->prepare(
            "UPDATE shelter_checkin_requests
             SET status = 'approved', checkin_id = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        )->execute([$checkinId, $official['id'], $requestId]);
    } elseif ($action === 'approve') {
        $checkin = $db->prepare(
            'SELECT id FROM shelter_checkins
             WHERE id = ? AND user_id = ? AND shelter_id = ? AND checked_out IS NULL
             LIMIT 1 FOR UPDATE'
        );
        $checkin->execute([$request['checkin_id'], $request['user_id'], $request['shelter_id']]);
        if (!$checkin->fetch()) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'The resident is no longer checked in at this center.'], 409);
        }

        $db->prepare('UPDATE shelter_checkins SET checked_out = CURRENT_TIMESTAMP WHERE id = ?')
           ->execute([$request['checkin_id']]);
        $db->prepare(
            'UPDATE shelters SET occupancy = CASE WHEN occupancy > 0 THEN occupancy - 1 ELSE 0 END WHERE id = ?'
        )->execute([$request['shelter_id']]);
        $db->prepare(
            "UPDATE shelter_checkin_requests
             SET status = 'approved', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        )->execute([$official['id'], $requestId]);
    } else {
        $db->prepare(
            "UPDATE shelter_checkin_requests
             SET status = 'rejected', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        )->execute([$official['id'], $requestId]);
    }

    $verb = $action === 'approve' ? 'Approved' : 'Rejected';
    $requestLabel = $request['request_type'] === 'check_in' ? 'check-in' : 'check-out';
    agap_log(
        $db,
        (int) $official['id'],
        $action === 'approve' ? 'approve_shelter_request' : 'reject_shelter_request',
        (int) $request['user_id'],
        "$verb $requestLabel request for $residentLabel at {$shelter['name']}"
    );

    $db->commit();
    json_response(['ok' => true, 'message' => "$requestLabel request $action" . ($action === 'approve' ? 'd' : 'ed') . '.']);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}
