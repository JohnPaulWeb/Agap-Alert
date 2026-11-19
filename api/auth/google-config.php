<?php
/**
 * api/auth/google-config.php
 * GET — returns the Google OAuth client_id for the frontend to use.
 * This lets the HTML pages load the client ID without it being hardcoded.
 */
require_once __DIR__ . '/../../api/bootstrap.php';
require_method('GET');

$path = __DIR__ . '/../../config/google.php';
if (!is_file($path)) {
    json_response(['ok' => true, 'client_id' => '']);
}
$cfg = require $path;
json_response(['ok' => true, 'client_id' => $cfg['client_id'] ?? '']);
