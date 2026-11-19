USE agapalert;

CREATE TABLE IF NOT EXISTS shelter_checkins (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shelter_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NULL         COMMENT 'NULL if checked in anonymously',
    checked_in  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checked_out TIMESTAMP    NULL         COMMENT 'NULL if still inside',
    note        VARCHAR(255) NULL,

    FOREIGN KEY (shelter_id) REFERENCES shelters(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_shelter_time (shelter_id, checked_in)
);
