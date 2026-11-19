<?php
/**
 * api/forecast.php
 * GET — returns a 5-day rainfall forecast using OpenWeatherMap.
 *
 * Response:
 * {
 *   "ok": true,
 *   "forecast": [
 *     { "date": "2024-11-18", "label": "Mon", "rain_mm": 12.4, "condition": "Rain", "temp_max": 31.2, "temp_min": 24.6 },
 *     ...
 *   ]
 * }
 */
require_once __DIR__ . '/bootstrap.php';
require_method('GET');

$cfgPath = __DIR__ . '/../config/openweather.php';
if (!is_file($cfgPath)) {
    json_response(['ok' => false, 'error' => 'OpenWeatherMap not configured.'], 503);
}
$cfg    = require $cfgPath;
$apiKey = $cfg['api_key'] ?? '';
if (empty($apiKey)) {
    json_response(['ok' => false, 'error' => 'OpenWeatherMap API key missing.'], 503);
}

// Santa Barbara, Pangasinan coordinates
$lat = 15.9643;
$lon = 120.4138;

$url = "https://api.openweathermap.org/data/2.5/forecast"
     . "?lat=$lat&lon=$lon&appid=$apiKey&units=metric&cnt=40";

$ctx      = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: AgapAlert/1.0\r\n"]]);
$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    json_response(['ok' => false, 'error' => 'Could not reach OpenWeatherMap.'], 502);
}

$data = json_decode($response, true);
if (!isset($data['list'])) {
    json_response(['ok' => false, 'error' => 'Unexpected forecast response.'], 502);
}

// Group the 3-hour intervals by day and aggregate
$days = [];
foreach ($data['list'] as $entry) {
    $date = date('Y-m-d', $entry['dt']);
    if (!isset($days[$date])) {
        $days[$date] = [
            'date'      => $date,
            'label'     => date('D', $entry['dt']),   // e.g. "Mon"
            'rain_mm'   => 0.0,
            'temp_max'  => -999.0,
            'temp_min'  =>  999.0,
            'condition' => $entry['weather'][0]['main'] ?? 'Clear',
        ];
    }
    $days[$date]['rain_mm']  += (float) ($entry['rain']['3h'] ?? 0);
    $days[$date]['temp_max']  = max($days[$date]['temp_max'], (float) $entry['main']['temp_max']);
    $days[$date]['temp_min']  = min($days[$date]['temp_min'], (float) $entry['main']['temp_min']);
    // Use midday condition as the day's condition
    if (date('H', $entry['dt']) === '12') {
        $days[$date]['condition'] = $entry['weather'][0]['main'] ?? $days[$date]['condition'];
    }
}

// Return up to 5 days, skip today if it's nearly over
$forecast = array_values(array_slice($days, 0, 5));
foreach ($forecast as &$d) {
    $d['rain_mm']  = round($d['rain_mm'], 1);
    $d['temp_max'] = round($d['temp_max'], 1);
    $d['temp_min'] = round($d['temp_min'], 1);
}

json_response(['ok' => true, 'forecast' => $forecast]);
