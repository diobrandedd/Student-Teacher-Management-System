-- Per-registrar duty flags (admin assigns; new accounts default to none).
ALTER TABLE users ADD COLUMN can_enrollments BOOLEAN NOT NULL DEFAULT FALSE AFTER is_active;
ALTER TABLE users ADD COLUMN can_blocking BOOLEAN NOT NULL DEFAULT FALSE AFTER can_enrollments;
ALTER TABLE users ADD COLUMN can_assign_teachers BOOLEAN NOT NULL DEFAULT FALSE AFTER can_blocking;
ALTER TABLE users ADD COLUMN can_view_students BOOLEAN NOT NULL DEFAULT FALSE AFTER can_assign_teachers;
