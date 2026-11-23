-- Lab 7: User Management upgrade (UM-01 .. UM-10)
-- Run ONCE after setup-database.sql:
--   sqlite3 tutoring.db < upgrade-to-user-management.sql
--
-- Adds the USER entity from the design document and links it to the
-- existing STUDENTS table. The Lab 6 tables and data are preserved.
--   users.user_id 1 -> 0..1 students.user_id 1 -> 0..many tutoring_requests
-- (The design proposes USER -> TUTORING_REQUEST; we reconcile it through the
--  existing student_id foreign key instead of changing tutoring_requests.)

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    user_id       INTEGER PRIMARY KEY,
    full_name     TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL CHECK (role IN ('Student', 'Tutor', 'Administrator')),
    status        TEXT NOT NULL DEFAULT 'Active' CHECK (status IN ('Active', 'Inactive')),
    created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Link a student profile to the login account that owns it.
-- NULL is allowed so existing Lab 6 student rows remain valid.
ALTER TABLE students ADD COLUMN user_id INTEGER REFERENCES users(user_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_students_user_id ON students(user_id);
CREATE INDEX IF NOT EXISTS idx_users_role_status ON users(role, status);
