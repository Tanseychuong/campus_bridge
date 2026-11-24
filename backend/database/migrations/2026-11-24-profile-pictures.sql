-- ---------------------------------------------------------------------
-- Upgrade an EXISTING CampusBridge database to store profile and cover pictures.
-- Run after 2026-11-24-campus-rep-approvals.sql. New databases do not need this.
--
-- Docker:  docker compose exec -T db mysql -uroot -pcampusbridge-dev campus_bridge < backend/database/migrations/2026-11-24-profile-pictures.sql
-- AMPPS:   phpMyAdmin > campus_bridge > Import this file.
-- ---------------------------------------------------------------------

ALTER TABLE users
    ADD COLUMN avatar_name VARCHAR(40) NULL AFTER reviewed_at,
    ADD COLUMN cover_name  VARCHAR(40) NULL AFTER avatar_name;
