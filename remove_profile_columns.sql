-- SQL to remove profile columns from utilisateurs table
ALTER TABLE utilisateurs 
DROP COLUMN IF EXISTS profile_picture,
DROP COLUMN IF EXISTS created_at;