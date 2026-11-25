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

DROP TABLE IF EXISTS study_room_signals;
DROP TABLE IF EXISTS study_room_messages;
DROP TABLE IF EXISTS study_room_members;
DROP TABLE IF EXISTS study_rooms;
DROP TABLE IF EXISTS resources;
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
DROP TABLE IF EXISTS campuses;

-- The fixed list of interests students can choose (e.g. "research").
CREATE TABLE interests (
    slug  VARCHAR(40)  NOT NULL PRIMARY KEY,
    label VARCHAR(60)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The campuses that share this CampusBridge. Organizations and students
-- belong to one campus; events can be shown to everyone or only to their campus.
CREATE TABLE campuses (
    id         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(60)  NOT NULL UNIQUE,
    short_name VARCHAR(20)  NOT NULL
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
    color          CHAR(7)      NOT NULL COMMENT 'Hex colour for the monogram, e.g. #2457C5',
    campus_id      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_organizations_campus FOREIGN KEY (campus_id) REFERENCES campuses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- People who can sign in. Passwords are NEVER stored, only a one-way hash.
CREATE TABLE users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(80)  NOT NULL,
    email           VARCHAR(120) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    -- campus_rep: approves the students who sign up for their campus.
    role            ENUM('student','org_rep','campus_rep','admin') NOT NULL DEFAULT 'student',
    -- Only organization representatives belong to an organization.
    organization_id INT UNSIGNED NULL,
    -- Home campus. NULL for administrators, who see every campus.
    campus_id       TINYINT UNSIGNED NULL,
    -- New students start 'pending' and cannot sign in until a campus rep (or an admin)
    -- approves them. 'rejected' accounts cannot sign in; review_note says why.
    status          ENUM('pending','active','rejected') NOT NULL DEFAULT 'active',
    review_note     VARCHAR(300) NULL,
    reviewed_by     INT UNSIGNED NULL,
    reviewed_at     DATETIME     NULL,
    -- Profile and cover pictures: random file names in storage/uploads/profile (outside public/).
    avatar_name     VARCHAR(40)  NULL,
    cover_name      VARCHAR(40)  NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_organization FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE SET NULL,
    CONSTRAINT fk_users_campus FOREIGN KEY (campus_id) REFERENCES campuses (id),
    CONSTRAINT fk_users_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_users_status (status, campus_id)
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
    -- 'public' = every campus can see it; 'campus' = only students of the organizer's campus.
    visibility       ENUM('public','campus') NOT NULL DEFAULT 'public',
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

-- ---------------------------------------------------------------------
-- Study resources shared by students (notes, slides, templates...).
-- A student uploads a file and signs an intellectual-property declaration.
-- The rep of the chosen organization decides whether it is public and can
-- take it down again at any time.
--   pending   -> waiting for the rep
--   published -> everyone can see and download it
--   hidden    -> taken down by the rep (can be published again)
--   rejected  -> not accepted; review_note says why
-- The file itself is stored OUTSIDE public/ under a random name.
-- ---------------------------------------------------------------------
CREATE TABLE resources (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(120) NOT NULL,
    description      VARCHAR(300) NOT NULL,
    organization_id  INT UNSIGNED NOT NULL COMMENT 'The organization whose rep reviews it',
    uploaded_by      INT UNSIGNED NOT NULL,
    stored_name      CHAR(40)     NOT NULL UNIQUE COMMENT 'Random file name on disk',
    original_name    VARCHAR(160) NOT NULL,
    mime_type        VARCHAR(120) NOT NULL,
    file_size        INT UNSIGNED NOT NULL,
    -- Intellectual-property declaration
    ip_ownership     ENUM('sole','joint','third_party') NOT NULL,
    ip_credits       VARCHAR(400) NULL COMMENT 'Co-authors or sources; required unless sole',
    license          ENUM('all_rights','cc_by','cc_by_nc','cc_by_sa') NOT NULL,
    declared_name    VARCHAR(80)  NOT NULL COMMENT 'Full name typed as a signature',
    declared_at      DATETIME     NOT NULL,
    -- Automated scan (bin/scan-worker.php fills these in the background)
    scan_status      ENUM('queued','scanning','done','failed') NOT NULL DEFAULT 'queued',
    scan_flags       JSON         NULL COMMENT 'List of {severity, kind, message} found by the scanner',
    file_sha256      CHAR(64)     NULL COMMENT 'Used to spot the same file uploaded by different students',
    scanned_at       DATETIME     NULL,
    -- Review
    status           ENUM('pending','published','hidden','rejected') NOT NULL DEFAULT 'pending',
    review_note      VARCHAR(300) NULL,
    reviewed_by      INT UNSIGNED NULL,
    reviewed_at      DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resources_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_resources_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_resources_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_resources_status (status, organization_id),
    INDEX idx_resources_scan (scan_status),
    INDEX idx_resources_hash (file_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Study rooms come in two kinds:
--   focus -> students study "together" online. Everyone follows the same
--            Pomodoro timer (focus, then a short break, repeated), sees who
--            else is studying, can turn on their camera (6 camera spots), and
--            can chat and talk during breaks only.
--   class -> one host teaches. It is scheduled (starts_at) and opens at that
--            time, or earlier when the host opens it (opened_at). The host can
--            talk any time; students talk only when the host lets them
--            (can_speak). Chat is open for the whole class.
-- Video and audio go directly between the browsers (WebRTC); the server only
-- passes on the short messages they need to connect (study_room_signals).
--   public  -> listed on the Focus rooms page; anyone signed in can join
--   private -> not listed; you join with the 6-character invite code
-- The timer is not stored second by second: timer_started_at plus the
-- focus and break lengths are enough to work out the current phase.
-- ---------------------------------------------------------------------
CREATE TABLE study_rooms (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(60)  NOT NULL,
    topic            VARCHAR(160) NULL,
    created_by       INT UNSIGNED NOT NULL,
    kind             ENUM('focus','class') NOT NULL DEFAULT 'focus',
    visibility       ENUM('public','private') NOT NULL DEFAULT 'public',
    invite_code      CHAR(6)      NULL UNIQUE COMMENT 'Only private rooms have one',
    focus_minutes    TINYINT UNSIGNED NOT NULL DEFAULT 25,
    break_minutes    TINYINT UNSIGNED NOT NULL DEFAULT 5,
    timer_started_at DATETIME     NULL COMMENT 'NULL = the timer is stopped (focus rooms only)',
    starts_at        DATETIME     NULL COMMENT 'When a class is scheduled to start (classes only)',
    opened_at        DATETIME     NULL COMMENT 'Set when the host opens a class before starts_at',
    status           ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at        DATETIME     NULL,
    CONSTRAINT fk_study_rooms_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_study_rooms_status (status, visibility),
    INDEX idx_study_rooms_starts (starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who has joined a room. Someone counts as "studying now" while their open
-- room page keeps checking in (last_seen_at in the last 90 seconds).
CREATE TABLE study_room_members (
    room_id      INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    task         VARCHAR(120) NULL COMMENT 'What they are working on, e.g. "Calculus problem set 4"',
    camera_on    TINYINT(1)   NOT NULL DEFAULT 0,
    mic_on       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Only heard when this person may talk (see may_talk in data.php)',
    can_speak    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Classes: the host let this student talk',
    joined_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME     NOT NULL,
    PRIMARY KEY (room_id, user_id),
    FOREIGN KEY (room_id) REFERENCES study_rooms (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_study_room_members_seen (room_id, last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat messages, allowed during breaks (and before the timer starts).
CREATE TABLE study_room_messages (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    room_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    body       VARCHAR(300) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES study_rooms (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_study_room_messages_room (room_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Connection messages between two browsers in a room (WebRTC "signalling"):
-- an offer, the answer to it, a hello (please send me an offer) or a bye.
-- They are only needed for a few seconds; old ones are deleted.
CREATE TABLE study_room_signals (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    room_id    INT UNSIGNED NOT NULL,
    from_user  INT UNSIGNED NOT NULL,
    to_user    INT UNSIGNED NOT NULL,
    kind       ENUM('hello','offer','answer','bye') NOT NULL,
    payload    TEXT         NULL COMMENT 'The session description (SDP) for offers and answers',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id)   REFERENCES study_rooms (id) ON DELETE CASCADE,
    FOREIGN KEY (from_user) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (to_user)   REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_study_room_signals_inbox (room_id, to_user, id),
    INDEX idx_study_room_signals_age (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
