-- =====================================================================
-- CampusBridge database schema (MySQL 8)
--
-- How to read this file:
--   * Each CREATE TABLE describes one kind of thing we store.
--   * PRIMARY KEY   = the column that uniquely identifies a row.
--   * FOREIGN KEY   = a column that points at a row in another table.
--   * "Join tables" (e.g. opportunity_interests) connect two tables in a
--     many-to-many relationship: one opportunity has many interests, and
--     one interest belongs to many opportunities.
--
-- All dates are stored in UTC. Ghana (Africa/Accra) is UTC+0 all year,
-- so stored times equal campus local times.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

DROP TABLE IF EXISTS user_interests;
DROP TABLE IF EXISTS follows;
DROP TABLE IF EXISTS saved_events;
DROP TABLE IF EXISTS saved_opportunities;
DROP TABLE IF EXISTS event_interests;
DROP TABLE IF EXISTS opportunity_interests;
DROP TABLE IF EXISTS organization_interests;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS opportunities;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS organizations;
DROP TABLE IF EXISTS interests;

-- The fixed list of interests students can choose (e.g. "research").
CREATE TABLE interests (
    slug  VARCHAR(40)  NOT NULL PRIMARY KEY,
    label VARCHAR(60)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student clubs and societies.
CREATE TABLE organizations (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    short_name     VARCHAR(12)  NOT NULL,
    category       ENUM('academic','professional','tech','entrepreneurship','service','arts','sports','cultural') NOT NULL,
    summary        VARCHAR(200) NOT NULL,
    description    TEXT         NOT NULL,
    activities     TEXT         NOT NULL COMMENT 'One activity per line',
    contact_email  VARCHAR(120) NOT NULL,
    meeting_info   VARCHAR(200) NOT NULL,
    membership     VARCHAR(500) NOT NULL,
    founded_year   SMALLINT UNSIGNED NOT NULL,
    member_count   SMALLINT UNSIGNED NOT NULL,
    color          CHAR(7)      NOT NULL COMMENT 'Hex colour for the monogram, e.g. #2457C5'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- People who can sign in. Passwords are NEVER stored, only a one-way hash.
CREATE TABLE users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(80)  NOT NULL,
    email           VARCHAR(120) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('student','org_rep','admin') NOT NULL DEFAULT 'student',
    -- Only organization representatives belong to an organization.
    organization_id INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_organization FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scholarships, internships, competitions and so on.
CREATE TABLE opportunities (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(140) NOT NULL,
    provider         VARCHAR(100) NOT NULL,
    organization_id  INT UNSIGNED NULL COMMENT 'Set when a CampusBridge organization posted it',
    category         ENUM('scholarship','internship','research','fellowship','competition','hackathon','leadership','volunteering','career') NOT NULL,
    summary          VARCHAR(220) NOT NULL,
    description      TEXT         NOT NULL,
    eligibility      TEXT         NOT NULL COMMENT 'One requirement per line (may be empty)',
    location_mode    ENUM('on-campus','in-person','remote','hybrid') NOT NULL,
    location_place   VARCHAR(100) NULL,
    deadline         DATETIME     NULL COMMENT 'NULL means rolling / no fixed deadline',
    published_at     DATETIME     NOT NULL,
    source_name      VARCHAR(100) NOT NULL,
    source_url       VARCHAR(255) NOT NULL,
    status           ENUM('published','pending','rejected') NOT NULL DEFAULT 'pending',
    review_note      VARCHAR(300) NULL,
    CONSTRAINT fk_opportunities_organization FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE SET NULL,
    INDEX idx_opportunities_status_deadline (status, deadline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campus events run by organizations.
CREATE TABLE events (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(120) NOT NULL,
    organization_id  INT UNSIGNED NOT NULL,
    category         ENUM('academic','career','social','tech','arts','sports','wellness','community') NOT NULL,
    summary          VARCHAR(220) NOT NULL,
    description      TEXT         NOT NULL,
    venue            VARCHAR(120) NOT NULL,
    starts_at        DATETIME     NOT NULL,
    ends_at          DATETIME     NOT NULL,
    capacity         SMALLINT UNSIGNED NULL COMMENT 'NULL means no limit',
    registration     ENUM('open','rsvp') NOT NULL DEFAULT 'open',
    contact          VARCHAR(200) NOT NULL,
    status           ENUM('published','pending','rejected') NOT NULL DEFAULT 'pending',
    review_note      VARCHAR(300) NULL,
    created_by       INT UNSIGNED NULL COMMENT 'The org rep who submitted it',
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_organization FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_events_end_after_start CHECK (ends_at > starts_at),
    INDEX idx_events_status_start (status, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Many-to-many "join tables": which interests each item is tagged with.
-- ---------------------------------------------------------------------
CREATE TABLE organization_interests (
    organization_id INT UNSIGNED NOT NULL,
    interest_slug   VARCHAR(40)  NOT NULL,
    PRIMARY KEY (organization_id, interest_slug),
    FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    FOREIGN KEY (interest_slug)   REFERENCES interests (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE opportunity_interests (
    opportunity_id INT UNSIGNED NOT NULL,
    interest_slug  VARCHAR(40)  NOT NULL,
    PRIMARY KEY (opportunity_id, interest_slug),
    FOREIGN KEY (opportunity_id) REFERENCES opportunities (id) ON DELETE CASCADE,
    FOREIGN KEY (interest_slug)  REFERENCES interests (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_interests (
    event_id      INT UNSIGNED NOT NULL,
    interest_slug VARCHAR(40)  NOT NULL,
    PRIMARY KEY (event_id, interest_slug),
    FOREIGN KEY (event_id)      REFERENCES events (id) ON DELETE CASCADE,
    FOREIGN KEY (interest_slug) REFERENCES interests (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Personal data for each signed-in student.
-- ---------------------------------------------------------------------
CREATE TABLE user_interests (
    user_id       INT UNSIGNED NOT NULL,
    interest_slug VARCHAR(40)  NOT NULL,
    PRIMARY KEY (user_id, interest_slug),
    FOREIGN KEY (user_id)       REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (interest_slug) REFERENCES interests (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_opportunities (
    user_id        INT UNSIGNED NOT NULL,
    opportunity_id INT UNSIGNED NOT NULL,
    saved_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, opportunity_id),
    FOREIGN KEY (user_id)        REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (opportunity_id) REFERENCES opportunities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_events (
    user_id  INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    saved_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, event_id),
    FOREIGN KEY (user_id)  REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE follows (
    user_id         INT UNSIGNED NOT NULL,
    organization_id INT UNSIGNED NOT NULL,
    followed_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, organization_id),
    FOREIGN KEY (user_id)         REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
