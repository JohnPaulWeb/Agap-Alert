<?php
/**
 * api/river.php
 * GET — returns 7-day river discharge forecast for Sinocalan River
 *       using the Open-Meteo Flood API (free, no key required).
 *
 * Response:
 * {
 *   "ok": true,
 *   "river": {
 *     "today":    { "date": "2024-11-18", "discharge": 12.4, "level": 1 },
 *     "peak":     { "date": "2024-11-20", "discharge": 45.2, "level": 2 },
 *     "forecast": [ { "date": "...", "discharge": 12.4, "level": 0, "label": "Mon" }, ... ]
 *   }
 * }
 *
 * Alert level thresholds for Sinocalan River at Santa Barbara Bridge Gauge:
 *   Level 0 (Normal)   < 5   m³/s
 *   Level 1 (Watch)    5–20  m³/s
 *   Level 2 (Warning)  20–50 m³/s
 *   Level 3 (Critical) ≥ 50  m³/s
 */
require_once __DIR__ . '/bootstrap.php';
require_method('GET');

// Sinocalan River coordinates near Santa Barbara Bridge Gauge
$lat = 15.9643;
$lon = 120.4138;

$url = "https://flood-api.open-meteo.com/v1/flood"
     . "?latitude=$lat&longitude=$lon"
     . "&daily=river_discharge"
     . "&forecast_days=7";

$ctx      = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: AgapAlert/1.0\r\n"]]);
$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    json_response(['ok' => false, 'error' => 'Could not reach river forecast service.'], 502);
}

$data = json_decode($response, true);
if (!isset($data['daily']['river_discharge'])) {
    json_response(['ok' => false, 'error' => 'Unexpected river forecast response.'], 502);
}

$dates     = $data['daily']['time'];
$discharge = $data['daily']['river_discharge'];

function dischargeToLevel(float $q): int {
    if ($q >= 50) return 3;
    if ($q >= 20) return 2;
    if ($q >= 5)  return 1;
    return 0;
}

// Build forecast array
$forecast = [];
$peakIdx  = 0;
for ($i = 0; $i < count($dates); $i++) {
    $q   = (float) ($discharge[$i] ?? 0);
    $lv  = dischargeToLevel($q);
    $forecast[] = [
        'date'      => $dates[$i],
        'label'     => date('D', strtotime($dates[$i])),
        'discharge' => round($q, 2),
        'level'     => $lv,
    ];
    if ($q > ($discharge[$peakIdx] ?? 0)) $peakIdx = $i;
}

$today = $forecast[0] ?? null;
$peak  = $forecast[$peakIdx] ?? $today;

json_response([
    'ok'    => true,
    'river' => [
        'today'    => $today,
        'peak'     => $peak,
        'forecast' => $forecast,
    ],
]);
