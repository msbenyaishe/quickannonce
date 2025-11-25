-- Add profile_picture and created_at columns to utilisateurs table if they don't exist
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT 'default.png';
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Make sure role column exists with default value 'user'
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS role VARCHAR(10) DEFAULT 'user';

-- Create an admin user if none exists (password: admin123)
INSERT INTO utilisateurs (nom, email, mot_de_passe, role, profile_picture, created_at)
SELECT 'Admin', 'admin@quickannonce.com', '$2y$10$8tGIXzGpB.qZhJE4MxBZmeYiun3Oi0QQrOPH1jrKMqWGQmcXlIlEe', 'admin', 'default.png', NOW()
WHERE NOT EXISTS (SELECT 1 FROM utilisateurs WHERE role = 'admin' LIMIT 1);