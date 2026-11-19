USE agapalert;
ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) NULL UNIQUE COMMENT 'Google OAuth subject ID' AFTER email;
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_avatar_url VARCHAR(500) NULL COMMENT 'Profile picture URL from Google' AFTER google_id;
