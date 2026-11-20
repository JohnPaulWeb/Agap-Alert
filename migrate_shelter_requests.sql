USE agapalert;

CREATE TABLE IF NOT EXISTS shelter_checkin_requests (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shelter_id   INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    request_type ENUM('check_in','check_out') NOT NULL,
    checkin_id   INT UNSIGNED NULL,
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by  INT UNSIGNED NULL,
    reviewed_at  TIMESTAMP NULL,

    FOREIGN KEY (shelter_id) REFERENCES shelters(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (checkin_id) REFERENCES shelter_checkins(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_request_status (user_id, status),
    INDEX idx_request_status_time (status, requested_at)
);
