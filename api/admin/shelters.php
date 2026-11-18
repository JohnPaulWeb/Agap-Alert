<?php
/**
 * api/admin/shelters.php
 * GET   — list all shelters
 * POST  — add a new shelter { name, zone, landmark, capacity, contact }
 * PATCH — update occupancy/status { id, occupancy, is_open? }
 */
require_once __DIR__ . '/../../api/bootstrap.php';
require_once __DIR__ . '/../../config/helpers.php';
$admin = require_role('admin');

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $db->query(
        "SELECT id, name, zone, landmark, capacity, occupancy, contact, is_open,
                latitude, longitude
         FROM   shelters
         ORDER  BY id ASC"
    )->fetchAll();
    json_response(['ok' => true, 'shelters' => $rows]);
}

if ($method === 'POST') {
    $body     = json_body();
    $name     = trim($body['name']     ?? '');
    $zone     = trim($body['zone']     ?? '');
    $landmark = trim($body['landmark'] ?? '');
    $capacity = (int) ($body['capacity'] ?? 0);
    $contact  = trim($body['contact']  ?? '');
    $lat      = isset($body['latitude'])  ? (float) $body['latitude']  : null;
    $lon      = isset($body['longitude']) ? (float) $body['longitude'] : null;

    $errors = [];
    if ($name     === '') $errors['name']     = 'Shelter name is required.';
    if ($zone     === '') $errors['zone']     = 'Zone is required.';
    if ($landmark === '') $errors['landmark'] = 'Landmark is required.';
    if ($capacity  <  1)  $errors['capacity'] = 'Capacity must be at least 1.';

    if (!empty($errors))
        json_response(['ok' => false, 'errors' => $errors], 422);

    $db->prepare(
        "INSERT INTO shelters (name, zone, landmark, capacity, occupancy, contact, is_open, latitude, longitude)
         VALUES (?, ?, ?, ?, 0, ?, 1, ?, ?)"
    )->execute([$name, $zone, $landmark, $capacity, $contact ?: null, $lat, $lon]);

    $id = (int) $db->lastInsertId();
    agap_log($db, $admin['id'], 'add_shelter', $id, "Added shelter: $name (capacity $capacity)");

    json_response(['ok' => true, 'id' => $id, 'message' => 'Shelter added.']);
}

if ($method === 'PATCH') {
    $body      = json_body();
    $id        = (int)   ($body['id']        ?? 0);
    $occupancy = (int)   ($body['occupancy'] ?? 0);
    $isOpen    = isset($body['is_open']) ? (int) $body['is_open'] : null;

    if ($id < 1 || $occupancy < 0)
        json_response(['ok' => false, 'error' => 'Invalid data.'], 400);

    $nameStmt = $db->prepare('SELECT name FROM shelters WHERE id = ? LIMIT 1');
    $nameStmt->execute([$id]);
    $shelterName = $nameStmt->fetchColumn() ?: ("#$id");

    if ($isOpen !== null) {
        $db->prepare("UPDATE shelters SET occupancy = ?, is_open = ? WHERE id = ?")
           ->execute([$occupancy, $isOpen, $id]);
        $openLabel = $isOpen ? 'open' : 'closed';
        agap_log(
            $db,
            $admin['id'],
            'update_shelter',
            $id,
            "Updated shelter $shelterName: occupancy $occupancy, status $openLabel"
        );
    } else {
        $db->prepare("UPDATE shelters SET occupancy = ? WHERE id = ?")
           ->execute([$occupancy, $id]);
        agap_log(
            $db,
            $admin['id'],
            'update_shelter',
            $id,
            "Updated shelter $shelterName: occupancy $occupancy"
        );
    }

    json_response(['ok' => true, 'message' => 'Shelter na-update.']);
}

json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
