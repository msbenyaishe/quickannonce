-- Add required columns if they don't exist
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT 'default.png';
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS role VARCHAR(10) DEFAULT 'user';

-- Create admin user directly (password: admin123)
INSERT INTO utilisateurs (nom, email, mot_de_passe, role, profile_picture, created_at, nb_annonces) 
VALUES ('Admin', 'admin@quickannonce.com', '$2y$10$8tGIXzGpB.qZhJE4MxBZmeYiun3Oi0QQrOPH1jrKMqWGQmcXlIlEe', 'admin', 'default.png', NOW(), 0);