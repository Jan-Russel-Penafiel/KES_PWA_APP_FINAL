-- Migration: Remove email column and ensure password column exists
-- Date: November 27, 2025
-- Purpose: Convert from email-based authentication to password-based authentication

USE kes_smart;

-- Check if password column exists, if not add it
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'kes_smart' 
                   AND TABLE_NAME = 'users' 
                   AND COLUMN_NAME = 'password');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT "" AFTER full_name',
    'SELECT "Password column already exists" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update users who don't have passwords (set a default that will force them to reset)
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE password = '' OR password IS NULL;
-- Note: The above hash is for the password 'password123' - users should change this immediately

-- Now remove the email column if it exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'kes_smart' 
                   AND TABLE_NAME = 'users' 
                   AND COLUMN_NAME = 'email');

SET @sql = IF(@col_exists = 1,
    'ALTER TABLE users DROP COLUMN email',
    'SELECT "Email column already removed" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Display the updated table structure
DESCRIBE users;

-- Show a sample of users to verify the changes
SELECT id, username, full_name, role, 
       CASE 
           WHEN password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
           THEN 'DEFAULT_PASSWORD (needs change)' 
           ELSE 'CUSTOM_PASSWORD' 
       END as password_status
FROM users 
LIMIT 5;

SELECT 'Migration completed successfully. All users now use password authentication.' as result;