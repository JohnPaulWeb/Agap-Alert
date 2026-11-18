<?php
/**
 * api/admin/flood-history.php
 * GET — returns the last 100 flood-level entries, oldest first (for charting).
 */

require_once __DIR__ . '/../../api/bootstrap.php';
require_role('admin');

$rows = db()->query(
    "SELECT fl.id, fl.level, fl.wind_kmh, fl.rain_mmh, fl.notes, fl.created_at,
            CONCAT(u.fname, IF(u.mname IS NOT NULL AND u.mname != '',
                   CONCAT(' ', u.mname, ' '), ' '), u.lname) AS marked_by_name
     FROM   flood_levels fl
     JOIN   users u ON u.id = fl.marked_by
     ORDER  BY fl.created_at DESC
     LIMIT  100"
)->fetchAll();

// Reverse so the array goes oldest → newest (easier for the chart to consume)
json_response(['ok' => true, 'history' => array_reverse($rows)]);
