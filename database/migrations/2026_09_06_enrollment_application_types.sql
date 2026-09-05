-- College enrollment types: new student or moving up within this institution.
UPDATE enrollment_applications
  SET application_type = 'new'
  WHERE application_type IN ('transferee', 'returnee');

ALTER TABLE enrollment_applications
  MODIFY application_type ENUM('new', 'moving_up') NOT NULL;
