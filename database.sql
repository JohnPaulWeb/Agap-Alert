CREATE DATABASE IF NOT EXISTS agapalert
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE agapalert;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fname         VARCHAR(50)  NOT NULL,
    mname         VARCHAR(50)  NULL,
    lname         VARCHAR(50)  NOT NULL,
    email         VARCHAR(100) NULL UNIQUE,
    phone_number  VARCHAR(15)  NULL,
    phone_norm    CHAR(12)     NULL UNIQUE COMMENT '639XXXXXXXXX — one account per mobile number',
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('resident','official','admin') NOT NULL DEFAULT 'resident',
    status        ENUM('active','pending','rejected') NOT NULL DEFAULT 'active',
    avatar_path   VARCHAR(255) NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS invite_codes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(20)  NOT NULL UNIQUE,
    note       VARCHAR(255) NULL,
    created_by INT UNSIGNED NOT NULL,
    used_by    INT UNSIGNED NULL,
    status     ENUM('unused','used','expired') NOT NULL DEFAULT 'unused',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (used_by)    REFERENCES users(id) ON DELETE SET NULL
);

-- Safe column/FK adds for MySQL 8 (no ADD COLUMN IF NOT EXISTS).
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'invite_code'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN invite_code VARCHAR(20)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND CONSTRAINT_NAME = 'fk_user_invite_code'
);
SET @fk_sql := IF(
    @fk_exists = 0,
    'ALTER TABLE users ADD CONSTRAINT fk_user_invite_code FOREIGN KEY (invite_code) REFERENCES invite_codes(code)',
    'SELECT 1'
);
PREPARE stmt FROM @fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS shelters (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    zone        VARCHAR(120) NOT NULL,
    landmark    VARCHAR(120) NOT NULL,
    capacity    INT UNSIGNED NOT NULL DEFAULT 0,
    occupancy   INT UNSIGNED NOT NULL DEFAULT 0,
    contact     VARCHAR(60)  NULL,
    is_open     TINYINT(1)   NOT NULL DEFAULT 1,
    latitude    DECIMAL(9,6) NULL,
    longitude   DECIMAL(9,6) NULL,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS weather_readings (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone               VARCHAR(120),
    latitude           DECIMAL(9,6)  NOT NULL,
    longitude          DECIMAL(9,6)  NOT NULL,
    weather_condition  VARCHAR(60)   NULL,
    temperature        DECIMAL(5,2)  NULL,
    rain_mm            DECIMAL(6,2)  NULL,
    wind_speed_kmh     DECIMAL(6,2)  NULL,
    wind_direction_deg SMALLINT UNSIGNED NULL,
    recorded_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reports (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT         NULL,
    zone        VARCHAR(255) NULL,
    status      ENUM('new','acknowledged','dispatched','resolved') NOT NULL DEFAULT 'new',
    is_sos      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS flood_levels (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level      TINYINT UNSIGNED  NOT NULL DEFAULT 0 COMMENT '0=Normal 1=Watch 2=Warning 3=Critical',
    wind_kmh   DECIMAL(5,1)      NOT NULL DEFAULT 0,
    rain_mmh   DECIMAL(5,1)      NOT NULL DEFAULT 0,
    notes      VARCHAR(255)      NULL,
    created_at TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    marked_by  INT UNSIGNED      NOT NULL,

    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45)  NOT NULL,
    attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, attempted_at)
);

CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    code_hash  VARCHAR(255) NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at TIMESTAMP    NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_reset (user_id, used, expires_at)
);

CREATE TABLE IF NOT EXISTS reset_requests (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45)  NOT NULL,
    kind       ENUM('request','verify') NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_kind_time (ip, kind, created_at)
);

DROP PROCEDURE IF EXISTS RecordWeather;
DELIMITER $$
CREATE PROCEDURE RecordWeather(
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
    START TRANSACTION;
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
    COMMIT;
END$$
DELIMITER ;

-- ── Seeds ─────────────────────────────────────────────────────────────────────
-- Default admin account (password: admin123 — change after first login)
INSERT IGNORE INTO users (id, fname, lname, email, password_hash, role, status)
VALUES (1, 'System', 'Administrator', 'admin@agapalert.ph',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin', 'active');

-- Default evacuation center
INSERT INTO shelters (name, zone, landmark, capacity, occupancy, contact, latitude, longitude)
SELECT 'Dalongue Barangay Evacuation Hall', 'Dalongue',
       'Near the barangay hall', 180, 0, '(075) 518-2024', 15.9643, 120.4138
WHERE NOT EXISTS (
    SELECT 1 FROM shelters WHERE name = 'Dalongue Barangay Evacuation Hall' LIMIT 1
);

-- Initial flood level (marked_by = admin user id 1)
INSERT INTO flood_levels (level, wind_kmh, rain_mmh, notes, marked_by)
SELECT 0, 12, 0.5, 'Initial baseline reading', 1
WHERE NOT EXISTS (SELECT 1 FROM flood_levels LIMIT 1);

-- ═══════════════════════════════════════════════════════════════════════════
-- v2 additions — Residents tab, Privacy consent, Flood history, Activity log
-- ═══════════════════════════════════════════════════════════════════════════

-- Allow admin to issue a temporary password that the user must change.
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'must_change_password'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = temporary password issued; user must set a new one''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Record consent to the Data Privacy Notice at registration.
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'privacy_accepted_at'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE users ADD COLUMN privacy_accepted_at TIMESTAMP NULL DEFAULT NULL COMMENT ''Timestamp when the user accepted the privacy notice''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Activity log — covers approvals, flood-level changes and password resets.
CREATE TABLE IF NOT EXISTS activity_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id    INT UNSIGNED NOT NULL
                COMMENT 'Admin or official who performed the action',
    action      VARCHAR(60)  NOT NULL
                COMMENT 'e.g. approve_official, reset_password, set_flood_level',
    target_id   INT UNSIGNED NULL
                COMMENT 'User id or resource id affected (nullable for non-user actions)',
    details     VARCHAR(255) NULL
                COMMENT 'Human-readable summary of what changed',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created (created_at)
);
