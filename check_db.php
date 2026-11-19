<?php
require_once __DIR__ . '/config/db.php';
$db = db();
$cols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . ' | ' . $c['Type'] . ' | Null:' . $c['Null'] . "\n";
}
