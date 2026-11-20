ALTER TABLE users 
ADD COLUMN google_avatar_url VARCHAR(500) NULL COMMENT 'Profile picture URL from Google' AFTER google_id;
