-- Blocks are unique per college year (same name allowed across 1st–4th year).
ALTER TABLE blocks ADD COLUMN year_level TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER name;
-- Replace global unique name with (name, year_level).
ALTER TABLE blocks DROP INDEX name;
ALTER TABLE blocks ADD UNIQUE KEY uq_blocks_name_year (name, year_level);
