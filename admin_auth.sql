-- Admins table for QuickAnnonce
-- Run this on your MySQL server (phpMyAdmin or CLI)

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL,
  actif BOOLEAN NOT NULL DEFAULT TRUE,
  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Example admin user (password: admin123)
-- You can generate a hash via PHP: password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO admins (nom, email, mot_de_passe, actif)
VALUES (
  'Admin',
  'admin@quickannonce.com',
  '$2y$10$P5X36TzwAtFMQnqPD9Yx8etMvWYapRWz7L8JbL/9kvVmx2bV0ZRQC',
  TRUE
)
ON DUPLICATE KEY UPDATE email = email;
