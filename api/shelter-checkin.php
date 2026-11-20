<?php
/**
 * api/shelter-checkin.php
 *
 * POST   { shelter_id }         — check the current user in to a shelter
 * DELETE { shelter_id }         — check the current user out of a shelter
 * GET    ?shelter_id=X          — check if the current user is checked in
 */
require_once __DIR__ . '/bootstrap.php';
require_auth();
$me     = current_user();
$db     = db();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET — check status ────────────────────────────────────────────────────────
if ($method === 'GET') {
    $shelterId = (int) ($_GET['shelter_id'] ?? 0);
    if ($shelterId < 1) json_response(['ok' => false, 'error' => 'Invalid shelter_id.'], 400);

    $stmt = $db->prepare(
        'SELECT id FROM shelter_checkins
         WHERE shelter_id = ? AND user_id = ? AND checked_out IS NULL
         ORDER BY checked_in DESC LIMIT 1'
    );
    $stmt->execute([$shelterId, $me['id']]);
    $row = $stmt->fetch();

    json_response(['ok' => true, 'checked_in' => (bool) $row]);
}

// ── POST — check in ───────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body      = json_body();
    $shelterId = (int) ($body['shelter_id'] ?? 0);
    if ($shelterId < 1) json_response(['ok' => false, 'error' => 'Invalid shelter_id.'], 400);

    // Check if already checked in
    $stmt = $db->prepare(
        'SELECT id FROM shelter_checkins
         WHERE shelter_id = ? AND user_id = ? AND checked_out IS NULL LIMIT 1'
    );
    $stmt->execute([$shelterId, $me['id']]);
    if ($stmt->fetch()) {
        json_response(['ok' => false, 'error' => 'You are already checked in to this shelter.'], 409);
    }

    // Check shelter exists and is open
    $stmt = $db->prepare('SELECT id, capacity, occupancy FROM shelters WHERE id = ? AND is_open = 1 LIMIT 1');
    $stmt->execute([$shelterId]);
    $shelter = $stmt->fetch();
    if (!$shelter) json_response(['ok' => false, 'error' => 'Shelter not found or closed.'], 404);
    if ($shelter['occupancy'] >= $shelter['capacity']) {
        json_response(['ok' => false, 'error' => 'This shelter is at full capacity.'], 409);
    }

    // Log check-in and increment occupancy
    $db->prepare(
        'INSERT INTO shelter_checkins (shelter_id, user_id) VALUES (?, ?)'
    )->execute([$shelterId, $me['id']]);

    $db->prepare(
        'UPDATE shelters SET occupancy = occupancy + 1 WHERE id = ? AND occupancy < capacity'
    )->execute([$shelterId]);

    // Return fresh occupancy
    $newOcc = (int) $db->query("SELECT occupancy FROM shelters WHERE id = $shelterId")->fetchColumn();

    json_response(['ok' => true, 'message' => 'Checked in successfully.', 'occupancy' => $newOcc]);
}

// ── DELETE — check out ────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $body      = json_body();
    $shelterId = (int) ($body['shelter_id'] ?? 0);
    if ($shelterId < 1) json_response(['ok' => false, 'error' => 'Invalid shelter_id.'], 400);

    // Find open check-in record
    $stmt = $db->prepare(
        'SELECT id FROM shelter_checkins
         WHERE shelter_id = ? AND user_id = ? AND checked_out IS NULL
         ORDER BY checked_in DESC LIMIT 1'
    );
    $stmt->execute([$shelterId, $me['id']]);
    $record = $stmt->fetch();
    if (!$record) json_response(['ok' => false, 'error' => 'You are not checked in to this shelter.'], 409);

    // Set check-out time and decrement occupancy
    $db->prepare(
        'UPDATE shelter_checkins SET checked_out = NOW() WHERE id = ?'
    )->execute([$record['id']]);

    $db->prepare(
        'UPDATE shelters SET occupancy = GREATEST(0, occupancy - 1) WHERE id = ?'
    )->execute([$shelterId]);

    $newOcc = (int) $db->query("SELECT occupancy FROM shelters WHERE id = $shelterId")->fetchColumn();

    json_response(['ok' => true, 'message' => 'Checked out successfully.', 'occupancy' => $newOcc]);
}

json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
