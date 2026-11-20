-- =============================================================================
-- AgapAlert Database — InfinityFree Import
-- Use this file for phpMyAdmin import (no CREATE DATABASE / USE / DELIMITER).
-- =============================================================================

-- USERS
CREATE TABLE IF NOT EXISTS users (
    id                   INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    fname                VARCHAR(50)     NOT NULL,
    mname                VARCHAR(50)     NULL,
    lname                VARCHAR(50)     NOT NULL,
    email                VARCHAR(100)    NULL UNIQUE,
    google_id            VARCHAR(255)    NULL UNIQUE  COMMENT 'Google OAuth ID',
    google_avatar_url    VARCHAR(500)    NULL         COMMENT 'Profile picture URL from Google',
    phone_number         VARCHAR(15)     NULL,
    phone_norm           CHAR(12)        NULL UNIQUE  COMMENT 'Normalized: 639XXXXXXXXX',
    password_hash        VARCHAR(255)    NULL         COMMENT 'NULL for Google-only accounts',
    role                 ENUM('resident','official','admin') NOT NULL DEFAULT 'resident',
    status               ENUM('active','pending','rejected')  NOT NULL DEFAULT 'active',
    avatar_path          VARCHAR(255)    NULL,
    invite_code          VARCHAR(20)     NULL,
    must_change_password TINYINT(1)      NOT NULL DEFAULT 0,
    privacy_accepted_at  TIMESTAMP       NULL,
    created_at           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- INVITE CODES
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

-- Link users.invite_code → invite_codes.code
ALTER TABLE users
    ADD CONSTRAINT fk_user_invite_code
    FOREIGN KEY (invite_code) REFERENCES invite_codes(code);

-- SHELTERS
CREATE TABLE IF NOT EXISTS shelters (
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
);

-- SHELTER CHECK-INS
CREATE TABLE IF NOT EXISTS shelter_checkins (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shelter_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NULL,
    checked_in  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checked_out TIMESTAMP    NULL,
    note        VARCHAR(255) NULL,
    FOREIGN KEY (shelter_id) REFERENCES shelters(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_shelter_time (shelter_id, checked_in)
);

-- WEATHER READINGS
CREATE TABLE IF NOT EXISTS weather_readings (
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
);

-- REPORTS
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

-- FLOOD LEVELS
CREATE TABLE IF NOT EXISTS flood_levels (
    id         INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    level      TINYINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '0=Normal 1=Watch 2=Warning 3=Critical',
    wind_kmh   DECIMAL(5,1)     NOT NULL DEFAULT 0,
    rain_mmh   DECIMAL(5,1)     NOT NULL DEFAULT 0,
    notes      VARCHAR(255)     NULL,
    marked_by  INT UNSIGNED     NOT NULL,
    created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- LOGIN ATTEMPTS
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45)  NOT NULL,
    attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, attempted_at)
);

-- PASSWORD RESETS
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED     NOT NULL,
    code_hash  VARCHAR(255)     NOT NULL,
    used       TINYINT(1)       NOT NULL DEFAULT 0,
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at TIMESTAMP        NOT NULL,
    created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_reset (user_id, used, expires_at)
);

-- RESET REQUESTS
CREATE TABLE IF NOT EXISTS reset_requests (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45)  NOT NULL,
    kind       ENUM('request','verify') NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_kind_time (ip, kind, created_at)
);

-- GOOGLE OTP VERIFICATION
-- ACTIVITY LOG
CREATE TABLE IF NOT EXISTS activity_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id   INT UNSIGNED NOT NULL,
    action     VARCHAR(60)  NOT NULL,
    target_id  INT UNSIGNED NULL,
    details    VARCHAR(255) NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created (created_at)
);

-- Note: Stored procedures are not supported on InfinityFree free hosting.

-- DEFAULT DATA
INSERT IGNORE INTO users (id, fname, lname, email, password_hash, role, status)
VALUES (
    1, 'System', 'Administrator', 'admin@agapalert.ph',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin', 'active'
);

INSERT INTO shelters (name, zone, landmark, capacity, occupancy, contact, latitude, longitude)
SELECT 'Dalongue Barangay Evacuation Hall', 'Dalongue', 'Near the barangay hall',
       180, 0, '(075) 518-2024', 15.9643, 120.4138
WHERE NOT EXISTS (
    SELECT 1 FROM shelters WHERE name = 'Dalongue Barangay Evacuation Hall'
);

INSERT INTO flood_levels (level, wind_kmh, rain_mmh, notes, marked_by)
SELECT 0, 12, 0.5, 'Initial baseline reading', 1
WHERE NOT EXISTS (SELECT 1 FROM flood_levels);
