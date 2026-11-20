<?php
require_once __DIR__ . '/config/db.php';
$db = db();
try {
    $db->exec("CREATE TABLE IF NOT EXISTS google_otps (
        id         INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
        user_id    INT UNSIGNED     NOT NULL,
        otp_hash   VARCHAR(255)     NOT NULL,
        token      CHAR(64)         NOT NULL UNIQUE,
        expires_at TIMESTAMP        NOT NULL,
        used       TINYINT(1)       NOT NULL DEFAULT 0,
        created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_expires (expires_at)
    )");
    echo "✓ google_otps table created.\n";
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
}
