-- ---------------------------------------------------------------------
-- Upgrade an EXISTING CampusBridge database (schema.sql + seed.sql already
-- imported) to add campus reps and sign-up approval.
--
-- New databases do not need this: schema.sql and seed.sql already include it.
--
-- Docker:  docker compose exec -T db mysql -uroot -pcampusbridge-dev campus_bridge < backend/database/migrations/2026-11-24-campus-rep-approvals.sql
-- AMPPS:   phpMyAdmin > campus_bridge > Import this file.
-- ---------------------------------------------------------------------

ALTER TABLE users
    MODIFY role ENUM('student','org_rep','campus_rep','admin') NOT NULL DEFAULT 'student',
    ADD COLUMN status      ENUM('pending','active','rejected') NOT NULL DEFAULT 'active' AFTER campus_id,
    ADD COLUMN review_note VARCHAR(300) NULL AFTER status,
    ADD COLUMN reviewed_by INT UNSIGNED NULL AFTER review_note,
    ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
    ADD CONSTRAINT fk_users_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL,
    ADD INDEX idx_users_status (status, campus_id);

-- Existing accounts keep working: the new column defaults to 'active'.

-- One campus rep per campus (password: CampusBridge2026!).
INSERT INTO users (name, email, password_hash, role, organization_id, campus_id) VALUES
    ('Adjoa Mensah (demo campus rep)', 'campus@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'campus_rep', NULL, 1),
    ('Kwaku Boadu (demo campus rep)', 'campus2@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'campus_rep', NULL, 2);

-- A sample student waiting for approval.
INSERT INTO users (name, email, password_hash, role, organization_id, campus_id, status) VALUES
    ('Selorm Agbeko (waiting for approval)', 'pending@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'student', NULL, 1, 'pending');
