-- Moderation support for annonces
-- Adds a moderation_status column to manage pending/approved/rejected

ALTER TABLE annonces
  ADD COLUMN IF NOT EXISTS moderation_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved';

-- Optional data migration examples (run if needed):
-- UPDATE annonces SET moderation_status = 'approved' WHERE etat = 'active';
-- UPDATE annonces SET moderation_status = 'pending' WHERE etat = 'inactive';
-- UPDATE annonces SET moderation_status = 'rejected' WHERE etat = 'archived';
