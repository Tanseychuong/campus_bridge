<?php
/**
 * Study rooms: the rules, the shared timer and the database queries.
 * The API addresses are in routes.php (same folder).
 *
 * Two kinds of room:
 *   focus - students study together to a shared Pomodoro timer. Chat and
 *           microphones work before the timer starts and during breaks only.
 *   class - one host teaches. Scheduled for a start time; the host can open it
 *           early. The host may talk any time, students only when the host lets
 *           them. Chat is open for the whole class.
 *
 * How "online together" works without a live connection to the server:
 * the room page asks GET /api/rooms/{id} every few seconds. Each of those
 * requests marks the person as still present (last_seen_at). Whoever was
 * seen in the last ROOM_PRESENCE_SECONDS counts as "in the room". The window is
 * generous because browsers slow down timers in background tabs (to about once a
 * minute), and students often switch tabs while they study.
 *
 * Video and sound go DIRECTLY between the browsers (WebRTC, peer to peer).
 * The server never sees them; it only passes on the short "signal" messages
 * two browsers exchange to connect (an offer and an answer). Because of that,
 * the talking rules are also applied by every LISTENER's browser: it only
 * plays the sound of people the server says may talk right now.
 */

/** Someone is "in the room" if their page checked in this recently. */
const ROOM_PRESENCE_SECONDS = 90;

/** At most this many people can be in one room at the same time. */
const ROOM_MAX_PRESENT = 25;

/**
 * Camera spots. Every browser sends its video to every other browser, so more
 * cameras quickly overload laptops and Wi-Fi. A class host's camera is extra.
 */
const ROOM_MAX_CAMERAS = 6;

/** A person can have this many open rooms and upcoming classes of their own at once. */
const ROOM_MAX_OPEN_PER_CREATOR = 3;

/** A room nobody has been in for this long (after its start) is closed automatically. */
const ROOM_IDLE_HOURS = 12;

/** Classes can be scheduled at most this far ahead. */
const ROOM_CLASS_MAX_DAYS_AHEAD = 30;

/** Chat flood control: at most this many messages per person per room in ROOM_MESSAGE_WINDOW seconds. */
const ROOM_MESSAGE_LIMIT = 5;
const ROOM_MESSAGE_WINDOW = 30;

/** How many recent chat messages the room page shows. */
const ROOM_MESSAGES_SHOWN = 40;

/** Connection messages are only useful for a few seconds; older ones are deleted. */
const ROOM_SIGNAL_TTL_SECONDS = 60;
const ROOM_SIGNAL_MAX_BYTES = 16000; // a session description (SDP) is usually 2–6 KB

const ROOM_KINDS = [
    'focus' => 'Focus room: study together to a shared timer',
    'class' => 'Class: you teach, students join at a set time',
];

/** The timer choices for focus rooms: key => [focus minutes, break minutes, label]. */
const ROOM_TIMER_PRESETS = [
    '25-5'  => [25, 5, 'Pomodoro: 25 min focus, 5 min break'],
    '50-10' => [50, 10, 'Long focus: 50 min focus, 10 min break'],
    '90-15' => [90, 15, 'Deep work: 90 min focus, 15 min break'],
];

const ROOM_VISIBILITY = [
    'public'  => 'Public: anyone signed in can find and join it',
    'private' => 'Private: only people with the invite code',
];

const ROOM_SIGNAL_KINDS = ['hello', 'offer', 'answer', 'bye'];

/** Invite code letters: no 0/O or 1/I/L, so codes are easy to read out loud. */
const ROOM_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

/* ------------------------------------------------------------------
 * The shared timer and the talking rules
 * ------------------------------------------------------------------ */

/**
 * Where a focus room's timer is right now. Everyone gets the same answer
 * because it is worked out from the same start time:
 *
 *     elapsed = now - timer_started_at
 *     cycle   = focus + break          (e.g. 30 minutes)
 *     elapsed % cycle < focus  -> focus phase, else break phase
 *
 * Returns ['phase' => 'idle'|'focus'|'break', 'seconds_left' => int|null, 'round' => int].
 */
function room_timer(?string $startedAt, int $focusMinutes, int $breakMinutes, DateTimeImmutable $now): array
{
    if ($startedAt === null) {
        return ['phase' => 'idle', 'seconds_left' => null, 'round' => 0];
    }
    $elapsed = max(0, $now->getTimestamp() - timestamp_of($startedAt));
    $focus = $focusMinutes * 60;
    $cycle = $focus + $breakMinutes * 60;
    $intoCycle = $elapsed % $cycle;
    $round = intdiv($elapsed, $cycle) + 1;

    if ($intoCycle < $focus) {
        return ['phase' => 'focus', 'seconds_left' => $focus - $intoCycle, 'round' => $round];
    }
    return ['phase' => 'break', 'seconds_left' => $cycle - $intoCycle, 'round' => $round];
}

/** The timer of any room (classes have none: always 'idle'). */
function room_timer_now(array $room, DateTimeImmutable $now): array
{
    if ($room['kind'] === 'class') {
        return ['phase' => 'idle', 'seconds_left' => null, 'round' => 0];
    }
    return room_timer($room['timer_started_at'], (int) $room['focus_minutes'], (int) $room['break_minutes'], $now);
}

/** Has the room started? Focus rooms always; a class at its start time, or once the host opened it. */
function room_is_live(array $room, DateTimeImmutable $now): bool
{
    if ($room['status'] !== 'open') {
        return false;
    }
    if ($room['kind'] !== 'class') {
        return true;
    }
    return $room['opened_at'] !== null || ($room['starts_at'] !== null && timestamp_of($room['starts_at']) <= $now->getTimestamp());
}

/** Chat: classes keep it open; focus rooms open it during breaks and before the timer starts. */
function room_chat_is_open(array $room, array $timer): bool
{
    return $room['kind'] === 'class' || $timer['phase'] !== 'focus';
}

/**
 * May this person talk right now?
 *   focus room: everyone, but only while chat is open (breaks / before the timer)
 *   class:      the host always; a student only if the host let them (can_speak)
 */
function room_may_talk(array $room, array $timer, bool $isHost, bool $canSpeak): bool
{
    if ($room['kind'] === 'class') {
        return $isHost || $canSpeak;
    }
    return room_chat_is_open($room, $timer);
}

/** Is this user the room's host (its creator)? */
function is_room_host(array $room, int $userId): bool
{
    return (int) $room['created_by'] === $userId;
}

/* ------------------------------------------------------------------
 * Checking the forms
 * ------------------------------------------------------------------ */

/**
 * The "create a room" form. Returns [field => message]; empty means valid.
 * Focus rooms need a timer; classes need a date and time in the future.
 */
function validate_room_form(array $input, DateTimeImmutable $now): array
{
    $errors = [];
    $kind = $input['kind'] ?? '';
    if (!isset(ROOM_KINDS[$kind])) {
        $errors['kind'] = 'Choose a focus room or a class.';
    }
    $name = $input['name'] ?? '';
    if (mb_strlen($name) < 3 || mb_strlen($name) > 60) {
        $errors['name'] = 'Give it a name of 3–60 characters.';
    }
    if (mb_strlen($input['topic'] ?? '') > 160) {
        $errors['topic'] = 'Keep the topic under 160 characters.';
    }
    if (!isset(ROOM_VISIBILITY[$input['visibility'] ?? ''])) {
        $errors['visibility'] = 'Choose who can join.';
    }
    if ($kind === 'focus' && !isset(ROOM_TIMER_PRESETS[$input['timer'] ?? ''])) {
        $errors['timer'] = 'Choose a timer.';
    }
    if ($kind === 'class') {
        $start = room_form_start($input);
        if ($start === null) {
            $errors['starts_at'] = 'Choose the date and time the class starts.';
        } elseif ($start < $now->modify('-5 minutes')) {
            $errors['starts_at'] = 'The class must start in the future.';
        } elseif ($start > $now->modify('+' . ROOM_CLASS_MAX_DAYS_AHEAD . ' days')) {
            $errors['starts_at'] = 'Classes can be scheduled up to ' . ROOM_CLASS_MAX_DAYS_AHEAD . ' days ahead.';
        }
    }
    return $errors;
}

/** The class start typed in the form ("2026-11-28" + "18:00", campus time), or null if it is not a real date and time. */
function room_form_start(array $input): ?DateTimeImmutable
{
    $date = $input['date'] ?? '';
    $time = $input['time'] ?? '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
        return null;
    }
    if (!checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
        return null;
    }
    return new DateTimeImmutable("$date $time", campus_timezone());
}

/** "abc 123" -> "ABC123". Codes are typed by hand, so spaces and case do not matter. */
function normalize_room_code(string $code): string
{
    return strtoupper(preg_replace('/[^a-z0-9]/i', '', $code));
}

function new_room_code(): string
{
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= ROOM_CODE_ALPHABET[random_int(0, strlen(ROOM_CODE_ALPHABET) - 1)];
    }
    return $code;
}

/* ------------------------------------------------------------------
 * Database: rooms
 * ------------------------------------------------------------------ */

function find_room(int $roomId): ?array
{
    return fetch_one(
        'SELECT study_rooms.*, users.name AS creator_name
           FROM study_rooms JOIN users ON users.id = study_rooms.created_by
          WHERE study_rooms.id = ?',
        [$roomId]
    );
}

function find_open_room_by_code(string $code): ?array
{
    return fetch_one("SELECT * FROM study_rooms WHERE invite_code = ? AND status = 'open'", [$code]);
}

/**
 * Closes rooms nobody has been in for ROOM_IDLE_HOURS (run before listing rooms).
 * A class only starts counting from its start time, so upcoming classes stay open.
 */
function close_idle_rooms(): void
{
    execute(
        "UPDATE study_rooms
            SET status = 'closed', closed_at = UTC_TIMESTAMP(), timer_started_at = NULL
          WHERE status = 'open'
            AND GREATEST(created_at, COALESCE(starts_at, created_at)) < UTC_TIMESTAMP() - INTERVAL ? HOUR
            AND NOT EXISTS (SELECT 1 FROM study_room_members
                             WHERE study_room_members.room_id = study_rooms.id
                               AND study_room_members.last_seen_at > UTC_TIMESTAMP() - INTERVAL ? HOUR)",
        [ROOM_IDLE_HOURS, ROOM_IDLE_HOURS]
    );
}

/** The open rooms someone may see in the list: every public room, plus private rooms they created or joined. */
function get_listed_rooms(int $userId): array
{
    return fetch_all(
        "SELECT study_rooms.*, users.name AS creator_name,
                (SELECT COUNT(*) FROM study_room_members
                  WHERE study_room_members.room_id = study_rooms.id
                    AND study_room_members.last_seen_at >= UTC_TIMESTAMP() - INTERVAL ? SECOND) AS present_count,
                EXISTS (SELECT 1 FROM study_room_members
                         WHERE study_room_members.room_id = study_rooms.id AND study_room_members.user_id = ?) AS is_member
           FROM study_rooms JOIN users ON users.id = study_rooms.created_by
          WHERE study_rooms.status = 'open'
            AND (study_rooms.visibility = 'public' OR study_rooms.created_by = ?
                 OR EXISTS (SELECT 1 FROM study_room_members
                             WHERE study_room_members.room_id = study_rooms.id AND study_room_members.user_id = ?))
          ORDER BY present_count DESC, COALESCE(study_rooms.starts_at, study_rooms.created_at)",
        [ROOM_PRESENCE_SECONDS, $userId, $userId, $userId]
    );
}

function count_open_rooms_created_by(int $userId): int
{
    return (int) fetch_one("SELECT COUNT(*) AS total FROM study_rooms WHERE created_by = ? AND status = 'open'", [$userId])['total'];
}

/**
 * Creates a room (the creator joins it straight away) and returns its id.
 * $timer is a ROOM_TIMER_PRESETS key (focus rooms); $startsAt the class start (classes).
 */
function create_room(int $userId, string $kind, string $name, ?string $topic, string $visibility, ?string $timer, ?DateTimeImmutable $startsAt): int
{
    [$focus, $break] = ROOM_TIMER_PRESETS[$timer ?? '25-5'];
    db()->beginTransaction();
    try {
        // A clash with an existing code is very unlikely (31^6 codes), but try again if it happens.
        for ($attempt = 0; ; $attempt++) {
            $code = $visibility === 'private' ? new_room_code() : null;
            try {
                execute(
                    'INSERT INTO study_rooms (name, topic, created_by, kind, visibility, invite_code, focus_minutes, break_minutes, starts_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$name, $topic, $userId, $kind, $visibility, $code, $focus, $break, $startsAt ? to_database($startsAt) : null]
                );
                break;
            } catch (PDOException $error) {
                if ($attempt >= 3 || $error->errorInfo[1] !== 1062) { // 1062 = duplicate key
                    throw $error;
                }
            }
        }
        $roomId = (int) db()->lastInsertId();
        mark_present($roomId, $userId);
        db()->commit();
        return $roomId;
    } catch (Throwable $error) {
        db()->rollBack();
        throw $error;
    }
}

/** Starts the shared timer from zero now, or stops it ($start = false). */
function set_room_timer(int $roomId, bool $start): void
{
    execute('UPDATE study_rooms SET timer_started_at = ' . ($start ? 'UTC_TIMESTAMP()' : 'NULL') . ' WHERE id = ?', [$roomId]);
}

/** The host opens a class before its start time. */
function open_class_now(int $roomId): void
{
    execute('UPDATE study_rooms SET opened_at = UTC_TIMESTAMP() WHERE id = ? AND opened_at IS NULL', [$roomId]);
}

function close_room(int $roomId): void
{
    execute("UPDATE study_rooms SET status = 'closed', closed_at = UTC_TIMESTAMP(), timer_started_at = NULL WHERE id = ?", [$roomId]);
    execute('DELETE FROM study_room_signals WHERE room_id = ?', [$roomId]);
}

/* ------------------------------------------------------------------
 * Database: who is in the room, cameras and microphones
 * ------------------------------------------------------------------ */

function find_room_member(int $roomId, int $userId): ?array
{
    return fetch_one('SELECT * FROM study_room_members WHERE room_id = ? AND user_id = ?', [$roomId, $userId]);
}

function is_room_member(int $roomId, int $userId): bool
{
    return find_room_member($roomId, $userId) !== null;
}

/**
 * Joins the room, or (if already a member) records that they are still here.
 * Someone coming back after being away starts with camera and microphone off:
 * their old page (and its camera) is gone. (MySQL applies the assignments from
 * left to right, so camera_on and mic_on still see the OLD last_seen_at.)
 */
function mark_present(int $roomId, int $userId): void
{
    execute(
        'INSERT INTO study_room_members (room_id, user_id, last_seen_at) VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                camera_on    = IF(last_seen_at < UTC_TIMESTAMP() - INTERVAL ? SECOND, 0, camera_on),
                mic_on       = IF(last_seen_at < UTC_TIMESTAMP() - INTERVAL ? SECOND, 0, mic_on),
                last_seen_at = UTC_TIMESTAMP()',
        [$roomId, $userId, ROOM_PRESENCE_SECONDS, ROOM_PRESENCE_SECONDS]
    );
}

/** Leaving a room removes the membership (a private room then needs the code again). */
function leave_room(int $roomId, int $userId): void
{
    execute('DELETE FROM study_room_members WHERE room_id = ? AND user_id = ?', [$roomId, $userId]);
}

/** The people in the room right now, in the order they joined. */
function get_present_members(int $roomId): array
{
    return fetch_all(
        'SELECT study_room_members.user_id, study_room_members.task, study_room_members.camera_on, study_room_members.mic_on,
                study_room_members.can_speak, study_room_members.joined_at, users.name, campuses.short_name AS campus
           FROM study_room_members
           JOIN users ON users.id = study_room_members.user_id
           LEFT JOIN campuses ON campuses.id = users.campus_id
          WHERE study_room_members.room_id = ?
            AND study_room_members.last_seen_at >= UTC_TIMESTAMP() - INTERVAL ? SECOND
          ORDER BY study_room_members.joined_at',
        [$roomId, ROOM_PRESENCE_SECONDS]
    );
}

/** Camera spots in use by people other than $userId. A class host's camera does not use a spot. */
function count_cameras_in_use(array $room, int $userId): int
{
    $used = 0;
    foreach (get_present_members((int) $room['id']) as $member) {
        $memberId = (int) $member['user_id'];
        $isClassHost = $room['kind'] === 'class' && is_room_host($room, $memberId);
        if ($member['camera_on'] && $memberId !== $userId && !$isClassHost) {
            $used++;
        }
    }
    return $used;
}

function set_member_media(int $roomId, int $userId, bool $camera, bool $mic): void
{
    execute('UPDATE study_room_members SET camera_on = ?, mic_on = ? WHERE room_id = ? AND user_id = ?', [(int) $camera, (int) $mic, $roomId, $userId]);
}

/** The class host lets a student talk, or stops them (which also turns their microphone off). */
function set_member_can_speak(int $roomId, int $userId, bool $allow): void
{
    execute(
        'UPDATE study_room_members SET can_speak = ?, mic_on = IF(?, mic_on, 0) WHERE room_id = ? AND user_id = ?',
        [(int) $allow, (int) $allow, $roomId, $userId]
    );
}

function set_member_task(int $roomId, int $userId, ?string $task): void
{
    execute('UPDATE study_room_members SET task = ? WHERE room_id = ? AND user_id = ?', [$task, $roomId, $userId]);
}

/* ------------------------------------------------------------------
 * Database: chat
 * ------------------------------------------------------------------ */

/** The latest chat messages, oldest first (the order a chat is read in). */
function get_room_messages(int $roomId): array
{
    $rows = fetch_all(
        'SELECT study_room_messages.id, study_room_messages.user_id, study_room_messages.body, study_room_messages.created_at, users.name
           FROM study_room_messages JOIN users ON users.id = study_room_messages.user_id
          WHERE study_room_messages.room_id = ?
          ORDER BY study_room_messages.id DESC
          LIMIT ' . ROOM_MESSAGES_SHOWN,
        [$roomId]
    );
    return array_reverse($rows);
}

function count_recent_messages(int $roomId, int $userId): int
{
    return (int) fetch_one(
        'SELECT COUNT(*) AS total FROM study_room_messages
          WHERE room_id = ? AND user_id = ? AND created_at >= UTC_TIMESTAMP() - INTERVAL ? SECOND',
        [$roomId, $userId, ROOM_MESSAGE_WINDOW]
    )['total'];
}

function add_room_message(int $roomId, int $userId, string $body): void
{
    execute('INSERT INTO study_room_messages (room_id, user_id, body) VALUES (?, ?, ?)', [$roomId, $userId, $body]);
}

/* ------------------------------------------------------------------
 * Database: connection messages between browsers ("signalling")
 * ------------------------------------------------------------------ */

function add_room_signal(int $roomId, int $fromUser, int $toUser, string $kind, ?string $payload): void
{
    // Old messages are useless; clean up as we go.
    execute('DELETE FROM study_room_signals WHERE created_at < UTC_TIMESTAMP() - INTERVAL ? SECOND', [ROOM_SIGNAL_TTL_SECONDS]);
    execute(
        'INSERT INTO study_room_signals (room_id, from_user, to_user, kind, payload) VALUES (?, ?, ?, ?, ?)',
        [$roomId, $fromUser, $toUser, $kind, $payload]
    );
}

/** Messages for $userId in this room that arrived after message $afterId (oldest first). */
function get_room_signals(int $roomId, int $userId, int $afterId): array
{
    return fetch_all(
        'SELECT id, from_user, kind, payload FROM study_room_signals
          WHERE room_id = ? AND to_user = ? AND id > ? AND created_at >= UTC_TIMESTAMP() - INTERVAL ? SECOND
          ORDER BY id LIMIT 100',
        [$roomId, $userId, $afterId, ROOM_SIGNAL_TTL_SECONDS]
    );
}

/** The newest message id for $userId, so a freshly opened page skips messages meant for an older page. */
function latest_room_signal_id(int $roomId, int $userId): int
{
    return (int) (fetch_one('SELECT MAX(id) AS id FROM study_room_signals WHERE room_id = ? AND to_user = ?', [$roomId, $userId])['id'] ?? 0);
}
