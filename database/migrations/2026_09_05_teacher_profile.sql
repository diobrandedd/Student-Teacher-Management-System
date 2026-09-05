-- Teacher profile fields for registrar edit + staff self-service
ALTER TABLE teachers ADD COLUMN first_name VARCHAR(80) NULL;
ALTER TABLE teachers ADD COLUMN middle_name VARCHAR(80) NULL;
ALTER TABLE teachers ADD COLUMN last_name VARCHAR(80) NULL;
ALTER TABLE teachers ADD COLUMN phone VARCHAR(20) NULL;
ALTER TABLE teachers ADD COLUMN address VARCHAR(255) NULL;
