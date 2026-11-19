<?php
require_once __DIR__ . '/config/db.php';
$db = db();
try {
    $db->exec("CREATE TABLE IF NOT EXISTS shelter_checkins (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        shelter_id  INT UNSIGNED NOT NULL,
        user_id     INT UNSIGNED NULL,
        checked_in  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        checked_out TIMESTAMP    NULL,
        note        VARCHAR(255) NULL,
        FOREIGN KEY (shelter_id) REFERENCES shelters(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL,
        INDEX idx_shelter_time (shelter_id, checked_in)
    )");
    echo "✓ shelter_checkins table created or already exists.\n";

    $cols = $db->query("SHOW COLUMNS FROM shelter_checkins")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo $c['Field'] . ' | ' . $c['Type'] . "\n";
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
}
