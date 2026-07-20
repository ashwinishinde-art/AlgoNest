-- Faculty Notifications Migration
-- Run this after notifications_migration.sql
-- 1. Make problem_id nullable (faculty notifications have no related problem)
ALTER TABLE notifications
    MODIFY COLUMN problem_id INT NULL DEFAULT NULL;

-- 2. Drop the FK constraint on problem_id so NULL is valid
--    (MySQL requires dropping the named FK before modifying the column)
--    Find and drop your existing FK — name may vary; check with:
--    SHOW CREATE TABLE notifications;
--    Then drop it:
-- ALTER TABLE notifications DROP FOREIGN KEY <fk_name>;

-- 3. Add faculty notification types to the ENUM
ALTER TABLE notifications
    MODIFY COLUMN type ENUM(
        'problem_approved',
        'problem_rejected',
        'faculty_approved',
        'faculty_declined'
    ) NOT NULL;
