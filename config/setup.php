<?php
/**
 * config/setup.php
 * Run once from browser: http://localhost/agapalert/config/setup.php
 * Creates the database, all tables, seeds data, and the default admin account.
 * DELETE this file after running it.
 */

define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'agapalert');
define('DB_CHARSET', 'utf8mb4');

header('Content-Type: text/plain; charset=utf-8');

try {
    // Connect without selecting a database first
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '`
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');
    echo "✓ Database created or already exists.\n";

    // ── Execute each table/index statement directly ───────────────────────────
    // This avoids all SQL-file parsing issues.

    $tables = [

        // USERS
        "CREATE TABLE IF NOT EXISTS users (
            id                   INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
            fname                VARCHAR(50)     NOT NULL,
            mname                VARCHAR(50)     NULL,
            lname                VARCHAR(50)     NOT NULL,
            email                VARCHAR(100)    NULL UNIQUE,
            google_id            VARCHAR(255)    NULL UNIQUE,
            google_avatar_url    VARCHAR(500)    NULL,
            phone_number         VARCHAR(15)     NULL,
            phone_norm           CHAR(12)        NULL UNIQUE,
            password_hash        VARCHAR(255)    NULL,
            role                 ENUM('resident','official','admin') NOT NULL DEFAULT 'resident',
            status               ENUM('active','pending','rejected') NOT NULL DEFAULT 'active',
            avatar_path          VARCHAR(255)    NULL,
            invite_code          VARCHAR(20)     NULL,
            must_change_password TINYINT(1)      NOT NULL DEFAULT 0,
            privacy_accepted_at  TIMESTAMP       NULL,
            created_at           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
        )",

        // INVITE CODES
        "CREATE TABLE IF NOT EXISTS invite_codes (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code       VARCHAR(20)  NOT NULL UNIQUE,
            note       VARCHAR(255) NULL,
            created_by INT UNSIGNED NOT NULL,
            used_by    INT UNSIGNED NULL,
            status     ENUM('unused','used','expired') NOT NULL DEFAULT 'unused',
            created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (used_by)    REFERENCES users(id) ON DELETE SET NULL
        )",

        // Add FK from users.invite_code → invite_codes.code (safe to fail if exists)
        "ALTER TABLE users
            ADD CONSTRAINT fk_user_invite_code
            FOREIGN KEY (invite_code) REFERENCES invite_codes(code)",

        // SHELTERS
        "CREATE TABLE IF NOT EXISTS shelters (
            id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(255)  NOT NULL,
            zone       VARCHAR(120)  NOT NULL,
            landmark   VARCHAR(120)  NOT NULL,
            capacity   INT UNSIGNED  NOT NULL DEFAULT 0,
            occupancy  INT UNSIGNED  NOT NULL DEFAULT 0,
            contact    VARCHAR(60)   NULL,
            is_open    TINYINT(1)    NOT NULL DEFAULT 1,
            latitude   DECIMAL(9,6)  NULL,
            longitude  DECIMAL(9,6)  NULL,
            updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",

        // WEATHER READINGS
        "CREATE TABLE IF NOT EXISTS weather_readings (
            id                 INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
            zone               VARCHAR(120)      NULL,
            latitude           DECIMAL(9,6)      NOT NULL,
            longitude          DECIMAL(9,6)      NOT NULL,
            weather_condition  VARCHAR(60)       NULL,
            temperature        DECIMAL(5,2)      NULL,
            rain_mm            DECIMAL(6,2)      NULL,
            wind_speed_kmh     DECIMAL(6,2)      NULL,
            wind_direction_deg SMALLINT UNSIGNED NULL,
            recorded_at        TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP
        )",

        // REPORTS
        "CREATE TABLE IF NOT EXISTS reports (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     INT UNSIGNED NULL,
            title       VARCHAR(255) NOT NULL,
            description TEXT         NULL,
            zone        VARCHAR(255) NULL,
            status      ENUM('new','acknowledged','dispatched','resolved') NOT NULL DEFAULT 'new',
            is_sos      TINYINT(1)   NOT NULL DEFAULT 0,
            created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )",

        // FLOOD LEVELS
        "CREATE TABLE IF NOT EXISTS flood_levels (
            id         INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
            level      TINYINT UNSIGNED NOT NULL DEFAULT 0,
            wind_kmh   DECIMAL(5,1)     NOT NULL DEFAULT 0,
            rain_mmh   DECIMAL(5,1)     NOT NULL DEFAULT 0,
            notes      VARCHAR(255)     NULL,
            marked_by  INT UNSIGNED     NOT NULL,
            created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE RESTRICT
        )",

        // LOGIN ATTEMPTS
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip           VARCHAR(45)  NOT NULL,
            attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip, attempted_at)
        )",

        // PASSWORD RESETS
        "CREATE TABLE IF NOT EXISTS password_resets (
            id         INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED     NOT NULL,
            code_hash  VARCHAR(255)     NOT NULL,
            used       TINYINT(1)       NOT NULL DEFAULT 0,
            attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
            expires_at TIMESTAMP        NOT NULL,
            created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_reset (user_id, used, expires_at)
        )",

        // RESET REQUESTS
        "CREATE TABLE IF NOT EXISTS reset_requests (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip         VARCHAR(45)  NOT NULL,
            kind       ENUM('request','verify') NOT NULL,
            created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_kind_time (ip, kind, created_at)
        )",

        // ACTIVITY LOG
        "CREATE TABLE IF NOT EXISTS activity_log (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            actor_id   INT UNSIGNED NOT NULL,
            action     VARCHAR(60)  NOT NULL,
            target_id  INT UNSIGNED NULL,
            details    VARCHAR(255) NULL,
            created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_created (created_at)
        )",
    ];

    foreach ($tables as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Skip "already exists" and "duplicate key name" errors
            $skip = ['42S01', '42S21', '42000', 'HY000'];
            if (!in_array($e->getCode(), $skip, true)) {
                echo "⚠ Skipped: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "✓ Tables created.\n";

    // ── Stored procedure ───────────────────────────────────────────────────────
    try {
        $pdo->exec('DROP PROCEDURE IF EXISTS RecordWeather');
        $pdo->exec("CREATE PROCEDURE RecordWeather(
            IN p_zone               VARCHAR(120),
            IN p_latitude           DECIMAL(9,6),
            IN p_longitude          DECIMAL(9,6),
            IN p_weather_condition  VARCHAR(60),
            IN p_temperature        DECIMAL(5,2),
            IN p_rain_mm            DECIMAL(6,2),
            IN p_wind_speed_kmh     DECIMAL(6,2),
            IN p_wind_direction_deg SMALLINT UNSIGNED
        )
        BEGIN
            DELETE FROM weather_readings
            WHERE zone = p_zone
              AND recorded_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE);

            INSERT INTO weather_readings (
                zone, latitude, longitude, weather_condition,
                temperature, rain_mm, wind_speed_kmh,
                wind_direction_deg, recorded_at
            ) VALUES (
                p_zone, p_latitude, p_longitude, p_weather_condition,
                p_temperature, p_rain_mm, p_wind_speed_kmh,
                p_wind_direction_deg, NOW()
            );
        END");
        echo "✓ Stored procedure created.\n";
    } catch (PDOException $e) {
        echo "⚠ Procedure skipped: " . $e->getMessage() . "\n";
    }

    // ── Seed default admin account ─────────────────────────────────────────────
    $adminEmail    = 'admin@agapalert.ph';
    $adminPassword = 'admin123';
    $adminHash     = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->execute([$adminEmail]);

    if ($check->fetch()) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?')
            ->execute([$adminHash, $adminEmail]);
        echo "✓ Admin password reset.\n";
    } else {
        $pdo->prepare(
            "INSERT INTO users (id, fname, lname, email, password_hash, role, status)
             VALUES (1, 'System', 'Administrator', ?, ?, 'admin', 'active')"
        )->execute([$adminEmail, $adminHash]);
        echo "✓ Admin account created.\n";
    }

    // ── Seed default shelter ───────────────────────────────────────────────────
    $exists = $pdo->query("SELECT COUNT(*) FROM shelters WHERE name = 'Dalongue Barangay Evacuation Hall'")->fetchColumn();
    if (!$exists) {
        $pdo->exec("INSERT INTO shelters (name, zone, landmark, capacity, occupancy, contact, latitude, longitude)
                    VALUES ('Dalongue Barangay Evacuation Hall', 'Dalongue', 'Near the barangay hall',
                            180, 0, '(075) 518-2024', 15.9643, 120.4138)");
        echo "✓ Default shelter created.\n";
    }

    // ── Seed initial flood level ───────────────────────────────────────────────
    $exists = $pdo->query('SELECT COUNT(*) FROM flood_levels')->fetchColumn();
    if (!$exists) {
        $pdo->exec("INSERT INTO flood_levels (level, wind_kmh, rain_mmh, notes, marked_by)
                    VALUES (0, 12, 0.5, 'Initial baseline reading', 1)");
        echo "✓ Initial flood level set.\n";
    }

    echo "\n✅ Setup complete!\n";
    echo "   Email:    " . $adminEmail . "\n";
    echo "   Password: " . $adminPassword . "\n\n";
    echo "⚠️  Change the admin password after logging in.\n";
    echo "⚠️  Delete this file after setup: config/setup.php\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
