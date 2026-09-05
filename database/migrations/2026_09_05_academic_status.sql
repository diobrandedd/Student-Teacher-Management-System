-- Expand ENUM so remaps can land, then shrink to final values.
ALTER TABLE students MODIFY academic_status ENUM('Active','On Leave','Graduated','Inactive','Dropped out') NOT NULL DEFAULT 'Active';
UPDATE students SET academic_status='Dropped out' WHERE academic_status IN ('On Leave','Inactive');
ALTER TABLE students MODIFY academic_status ENUM('Active','Dropped out','Graduated') NOT NULL DEFAULT 'Active';
