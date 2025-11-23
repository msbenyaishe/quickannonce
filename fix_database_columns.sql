-- Comprehensive Database Update Script
-- Run this ONCE to ensure all required columns exist
-- Works with MySQL 5.7+ and MariaDB 10.2+

-- Add moderation_status to annonces table
ALTER TABLE annonces 
ADD COLUMN moderation_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'
WHERE NOT EXISTS (
  SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'annonces' 
  AND COLUMN_NAME = 'moderation_status'
);

-- Set default moderation_status for existing ads
UPDATE annonces SET moderation_status = 'approved' WHERE moderation_status IS NULL OR moderation_status = '';

-- Add profile_picture to utilisateurs table
ALTER TABLE utilisateurs 
ADD COLUMN profile_picture VARCHAR(255) DEFAULT 'default.png'
WHERE NOT EXISTS (
  SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'utilisateurs' 
  AND COLUMN_NAME = 'profile_picture'
);

-- Add created_at to utilisateurs table
ALTER TABLE utilisateurs 
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
WHERE NOT EXISTS (
  SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'utilisateurs' 
  AND COLUMN_NAME = 'created_at'
);

-- Add phone to utilisateurs table (optional)
ALTER TABLE utilisateurs 
ADD COLUMN phone VARCHAR(50) NULL
WHERE NOT EXISTS (
  SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'utilisateurs' 
  AND COLUMN_NAME = 'phone'
);

-- Ensure role column exists with default
ALTER TABLE utilisateurs 
MODIFY COLUMN role ENUM('user','admin') NOT NULL DEFAULT 'user';

-- Update existing rows to have defaults if NULL
UPDATE utilisateurs SET role = 'user' WHERE role IS NULL;
UPDATE utilisateurs SET created_at = NOW() WHERE created_at IS NULL;
UPDATE utilisateurs SET profile_picture = 'default.png' WHERE profile_picture IS NULL OR profile_picture = '';

