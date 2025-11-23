-- QuickAnnonces - Database Initialization Script (XAMPP/WAMP)
-- Run this file locally to set up the database, tables, stored objects, and seed data.

DROP DATABASE IF EXISTS quickannonce;
CREATE DATABASE quickannonce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quickannonce;

-- Tables
CREATE TABLE utilisateurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL,
  nb_annonces INT NOT NULL DEFAULT 0,
  role ENUM('user','admin') NOT NULL DEFAULT 'user'
);

CREATE TABLE annonces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  date_publication DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  etat ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  image_path VARCHAR(255) NULL,
  id_utilisateur INT NOT NULL,
  CONSTRAINT fk_annonces_utilisateur FOREIGN KEY (id_utilisateur)
    REFERENCES utilisateurs(id) ON DELETE CASCADE
);

CREATE TABLE annonces_archive (
  id INT PRIMARY KEY,
  titre VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  date_publication DATETIME NOT NULL,
  etat ENUM('active','inactive','archived') NOT NULL,
  image_path VARCHAR(255) NULL,
  id_utilisateur INT NOT NULL
);

-- Admins table: separate table for admin users
CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL,
  actif BOOLEAN NOT NULL DEFAULT TRUE,
  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Function: temps_depuis_publication (returns days since ad was published)
DELIMITER $$
CREATE FUNCTION temps_depuis_publication(p_date DATETIME)
RETURNS INT DETERMINISTIC
BEGIN
  RETURN TIMESTAMPDIFF(DAY, p_date, NOW());
END$$
DELIMITER ;

-- Trigger: after insert on annonces, increment nb_annonces
DELIMITER $$
CREATE TRIGGER after_insert_annonce
AFTER INSERT ON annonces
FOR EACH ROW
BEGIN
  UPDATE utilisateurs SET nb_annonces = COALESCE(nb_annonces,0) + 1
  WHERE id = NEW.id_utilisateur;
END$$
DELIMITER ;

-- Procedure: supprimer_utilisateur_complet (delete a user and their ads)
DELIMITER $$
CREATE PROCEDURE supprimer_utilisateur_complet(IN p_user_id INT)
BEGIN
  -- Ads are ON DELETE CASCADE, but ensure counters are consistent
  DELETE FROM annonces WHERE id_utilisateur = p_user_id;
  DELETE FROM utilisateurs WHERE id = p_user_id;
END$$
DELIMITER ;

-- Procedure: archiver_annonces_expirees (move >30 days to archive)
DELIMITER $$
CREATE PROCEDURE archiver_annonces_expirees()
BEGIN
  INSERT INTO annonces_archive (id, titre, description, date_publication, etat, image_path, id_utilisateur)
  SELECT id, titre, description, date_publication, etat, image_path, id_utilisateur
  FROM annonces
  WHERE date_publication < (CURRENT_DATE - INTERVAL 30 DAY);

  DELETE FROM annonces
  WHERE date_publication < (CURRENT_DATE - INTERVAL 30 DAY);
END$$
DELIMITER ;

-- Seed admin login record (password: admin123)
-- Email: admin@qa.local
-- Password: admin123
INSERT INTO admins (nom, email, mot_de_passe, actif)
VALUES ('Admin', 'admin@qa.local', '$2y$10$P5X36TzwAtFMQnqPD9Yx8etMvWYapRWz7L8JbL/9kvVmx2bV0ZRQC', 1);

-- Seed regular user (password: user123)
-- Email: user@qa.local
-- Password: user123
INSERT INTO utilisateurs (nom, email, mot_de_passe, nb_annonces, role)
VALUES ('User One', 'user@qa.local', '$2y$10$8b8f3t1g5QZkB2bVfG0r9u1B5bV3XbYyRjvV0sP5o7lQ7lE3c4x6a', 0, 'user');

-- Example annonces
INSERT INTO annonces (titre, description, image_path, id_utilisateur) VALUES
('2015 Sedan', 'Well-maintained, single owner. Abidjan • $5,500', NULL, 2),
('2BR Apartment', 'Downtown, near metro. Paris • $1,200/mo', NULL, 2),
('Gaming Laptop', 'High performance, like new. Casablanca • $900', NULL, 2),
('Smartphone Pro', 'Excellent condition. Dakar • $450', NULL, 2);

-- Note:
-- Stored objects were tested locally then simulated via PHP on InfinityFree due to privileges restrictions.


