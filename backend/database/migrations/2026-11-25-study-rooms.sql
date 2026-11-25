-- Adds study focus rooms to a database created before 25 Nov 2026.
-- Run once, e.g. in phpMyAdmin (SQL tab) or:
--   docker compose exec -T db mysql -uroot -pcampusbridge-dev campus_bridge < backend/database/migrations/2026-11-25-study-rooms.sql
SET NAMES utf8mb4;
SET time_zone = '+00:00';

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
CREATE TABLE IF NOT EXISTS study_rooms (
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
CREATE TABLE IF NOT EXISTS study_room_members (
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
CREATE TABLE IF NOT EXISTS study_room_messages (
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
CREATE TABLE IF NOT EXISTS study_room_signals (
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
