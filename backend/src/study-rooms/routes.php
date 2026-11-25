<?php
/**
 * Study rooms (focus rooms and classes): the API addresses. The rules and queries are in data.php.
 *
 *   GET    /api/rooms                         rooms you can see, and the create-form choices
 *   POST   /api/rooms                         create   {"kind", "name", "topic", "visibility", "timer" | "date" + "time"}
 *   POST   /api/rooms/join                    join a private room by code   {"code": "K7QX3M"}
 *   GET    /api/rooms/{id}                    the room: timer, people, chat (also says "I am still here")
 *   POST   /api/rooms/{id}/join               join a public room
 *   POST   /api/rooms/{id}/leave              leave
 *   PUT    /api/rooms/{id}/task               what you are working on       {"task": "..."}
 *   PUT    /api/rooms/{id}/media              your camera and microphone    {"camera": true, "mic": false}
 *   POST   /api/rooms/{id}/messages           chat                          {"body": "..."}
 *   POST   /api/rooms/{id}/timer              focus rooms: start or stop    {"action": "start" | "stop"}
 *   POST   /api/rooms/{id}/open               classes: the host opens it before the start time
 *   POST   /api/rooms/{id}/members/{userId}/speak   classes: the host lets a student talk   {"allow": true}
 *   GET    /api/rooms/{id}/signals?after=N    connection messages for you (video calls)
 *   POST   /api/rooms/{id}/signals            send one to someone in the room  {"to", "kind", "payload"}
 *   DELETE /api/rooms/{id}                    close the room / end the class (its host, or an admin)
 *
 * Every signed-in account can use study rooms. A private room behaves as if it
 * does not exist (404) for anyone who has not joined it with the code.
 */

/** "25 min focus · 5 min break" for a focus room, "Class" for a class. */
function room_timer_label(array $room): string
{
    return $room['kind'] === 'class' ? 'Class' : "{$room['focus_minutes']} min focus · {$room['break_minutes']} min break";
}

/** A room as the list shows it. */
function present_room_summary(array $room): array
{
    $now = now_on_campus();
    return [
        'id'            => (int) $room['id'],
        'kind'          => $room['kind'],
        'name'          => $room['name'],
        'topic'         => $room['topic'],
        'creator_name'  => $room['creator_name'],
        'visibility'    => $room['visibility'],
        'timer_label'   => room_timer_label($room),
        'is_live'       => room_is_live($room, $now),
        'starts_label'  => $room['starts_at'] ? format_date($room['starts_at'], 'D j M, H:i') : null,
        'present_count' => (int) $room['present_count'],
        'is_member'     => (bool) $room['is_member'],
        'timer'         => room_timer_now($room, $now),
    ];
}

/**
 * The open room with this id, if the signed-in user may see it. Returns [room, membership row or null].
 * Private rooms are only visible to their host and to people who joined with the code.
 */
function visible_room(int $roomId, array $user): array
{
    $room = find_room($roomId);
    $member = $room !== null ? find_room_member($roomId, (int) $user['id']) : null;
    if ($room === null || ($room['visibility'] === 'private' && $member === null && !is_room_host($room, (int) $user['id']))) {
        abort(404, 'This room does not exist.');
    }
    if ($room['status'] !== 'open') {
        abort(410, $room['kind'] === 'class'
            ? 'This class has ended. Find another one on the Study rooms page.'
            : 'This focus room has closed. Find another one on the Study rooms page.');
    }
    return [$room, $member];
}

/** Stops the request unless the user is in the room. Returns [room, membership row]. */
function member_room(int $roomId, array $user, string $message = 'Join the room first.'): array
{
    [$room, $member] = visible_room($roomId, $user);
    if ($member === null) {
        abort(403, $message);
    }
    return [$room, $member];
}

/** Stops the request until a class has started (focus rooms are always live). */
function require_live_room(array $room): void
{
    if (!room_is_live($room, now_on_campus())) {
        abort(409, 'This class has not started yet. It opens at ' . format_date($room['starts_at'], 'H:i \o\n D j M') . '.');
    }
}

/** Only the room's host (or an admin) may control the timer, open, end, or decide who talks. */
function can_manage_room(array $room, array $user): bool
{
    return is_room_host($room, (int) $user['id']) || $user['role'] === 'admin';
}

/** Stops the request unless there is space for one more person (people already here always fit). */
function require_room_space(int $roomId, int $userId): void
{
    $present = array_map('intval', array_column(get_present_members($roomId), 'user_id'));
    if (!in_array($userId, $present, true) && count($present) >= ROOM_MAX_PRESENT) {
        abort(409, 'This room is full (' . ROOM_MAX_PRESENT . ' people). Try another room or create your own.');
    }
}

/** GET /api/rooms */
route('GET', '/api/rooms', function (): array {
    $user = require_login();
    close_idle_rooms();
    $options = fn (array $labels) => array_map(fn ($value, $label) => ['value' => $value, 'label' => is_array($label) ? $label[2] : $label], array_keys($labels), $labels);
    return [
        'rooms'      => array_map('present_room_summary', get_listed_rooms((int) $user['id'])),
        'can_create' => count_open_rooms_created_by((int) $user['id']) < ROOM_MAX_OPEN_PER_CREATOR,
        'max_open'   => ROOM_MAX_OPEN_PER_CREATOR,
        'kinds'      => $options(ROOM_KINDS),
        'timers'     => $options(ROOM_TIMER_PRESETS),
        'visibility' => $options(ROOM_VISIBILITY),
        'today'      => now_on_campus()->format('Y-m-d'),
        'max_date'   => now_on_campus()->modify('+' . ROOM_CLASS_MAX_DAYS_AHEAD . ' days')->format('Y-m-d'),
    ];
});

/** POST /api/rooms */
route('POST', '/api/rooms', function (): ?array {
    $user = require_login();
    $input = [];
    foreach (['kind', 'name', 'topic', 'visibility', 'timer', 'date', 'time'] as $field) {
        $input[$field] = input_text($field);
    }
    $errors = validate_room_form($input, now_on_campus());
    if ($errors !== []) {
        abort_with_form_errors($errors);
    }
    if (count_open_rooms_created_by((int) $user['id']) >= ROOM_MAX_OPEN_PER_CREATOR) {
        abort(409, 'You already have ' . ROOM_MAX_OPEN_PER_CREATOR . ' open rooms or upcoming classes. Close one before creating another.');
    }
    $isClass = $input['kind'] === 'class';
    $roomId = create_room(
        (int) $user['id'],
        $input['kind'],
        $input['name'],
        $input['topic'] !== '' ? $input['topic'] : null,
        $input['visibility'],
        $isClass ? null : $input['timer'],
        $isClass ? room_form_start($input) : null,
    );
    send_json([
        'room_id' => $roomId,
        'message' => $isClass
            ? 'Your class is scheduled. Students can join from its start time; you can open it earlier.'
            : 'Your focus room is open. Start the timer when everyone is ready.',
    ], 201);
    return null;
});

/** POST /api/rooms/join   {"code": "K7QX3M"} */
route('POST', '/api/rooms/join', function (): array {
    $user = require_login();
    // Slow down code guessing: after 8 wrong codes, wait a minute.
    if (time() < ($_SESSION['room_code_locked_until'] ?? 0)) {
        abort(429, 'Too many wrong codes. Please wait a minute and try again.');
    }
    $room = find_open_room_by_code(normalize_room_code(input_text('code')));
    if ($room === null) {
        $_SESSION['room_code_failures'] = ($_SESSION['room_code_failures'] ?? 0) + 1;
        if ($_SESSION['room_code_failures'] >= 8) {
            $_SESSION['room_code_failures'] = 0;
            $_SESSION['room_code_locked_until'] = time() + 60;
        }
        abort(422, 'No open room has that code. Check it with the person who invited you.', ['code' => 'No open room has that code.']);
    }
    $_SESSION['room_code_failures'] = 0;
    require_room_space((int) $room['id'], (int) $user['id']);
    mark_present((int) $room['id'], (int) $user['id']);
    return ['room_id' => (int) $room['id'], 'message' => 'You joined ' . $room['name'] . '.'];
});

/** GET /api/rooms/{id} — the page asks for this every few seconds. */
route('GET', '/api/rooms/{id}', function (array $params): array {
    $user = require_login();
    $userId = (int) $user['id'];
    [$room, $member] = visible_room($params['id'], $user);
    $roomId = (int) $room['id'];
    if ($member !== null) {
        mark_present($roomId, $userId); // still here
        $member = find_room_member($roomId, $userId);
    }

    $now = now_on_campus();
    $isLive = room_is_live($room, $now);
    $timer = room_timer_now($room, $now);
    $chatOpen = $isLive && room_chat_is_open($room, $timer);
    $isHost = is_room_host($room, $userId);
    $canManage = can_manage_room($room, $user);
    $present = get_present_members($roomId);

    $members = array_map(function (array $m) use ($room, $timer, $userId, $isLive) {
        $id = (int) $m['user_id'];
        $mayTalk = $isLive && room_may_talk($room, $timer, is_room_host($room, $id), (bool) $m['can_speak']);
        return [
            'id'        => $id,
            'name'      => $m['name'],
            'campus'    => $m['campus'],
            'task'      => $m['task'],
            'is_me'     => $id === $userId,
            'is_host'   => is_room_host($room, $id),
            'camera_on' => $isLive && (bool) $m['camera_on'],
            // Browsers only play this person's sound when mic_live is true.
            'mic_live'  => $mayTalk && (bool) $m['mic_on'],
            'can_speak' => (bool) $m['can_speak'],
        ];
    }, $present);

    $mayTalk = $member !== null && $isLive && room_may_talk($room, $timer, $isHost, (bool) $member['can_speak']);
    return [
        'room' => [
            'id'            => $roomId,
            'kind'          => $room['kind'],
            'name'          => $room['name'],
            'topic'         => $room['topic'],
            'creator_name'  => $room['creator_name'],
            'visibility'    => $room['visibility'],
            'timer_label'   => room_timer_label($room),
            'focus_minutes' => (int) $room['focus_minutes'],
            'break_minutes' => (int) $room['break_minutes'],
            'is_live'       => $isLive,
            'starts_label'  => $room['starts_at'] ? format_date($room['starts_at'], 'D j M, H:i') : null,
            'seconds_to_start' => !$isLive && $room['starts_at'] ? max(0, timestamp_of($room['starts_at']) - $now->getTimestamp()) : null,
            // The code is only shown to people who are already inside.
            'invite_code'   => $member !== null || $canManage ? $room['invite_code'] : null,
            'max_present'   => ROOM_MAX_PRESENT,
        ],
        'timer'     => $timer,
        'chat_open' => $chatOpen,
        'members'   => $members,
        'cameras'   => [
            'used' => count(array_filter($members, fn ($m) => $m['camera_on'] && !($room['kind'] === 'class' && $m['is_host']))),
            'max'  => ROOM_MAX_CAMERAS,
        ],
        // Only people in the room can read its chat.
        'messages' => $member !== null ? array_map(fn ($m) => [
            'id'    => (int) $m['id'],
            'name'  => $m['name'],
            'body'  => $m['body'],
            'time'  => format_time($m['created_at']),
            'is_me' => (int) $m['user_id'] === $userId,
        ], get_room_messages($roomId)) : [],
        'me' => [
            'id'         => $userId,
            'is_member'  => $member !== null,
            'is_host'    => $isHost,
            'can_manage' => $canManage,
            'task'       => $member['task'] ?? null,
            'camera_on'  => (bool) ($member['camera_on'] ?? false),
            'mic_on'     => (bool) ($member['mic_on'] ?? false),
            'may_talk'   => $mayTalk,
        ],
        'call' => [
            'ice_servers'   => app_config()['ice_servers'],
            'signal_cursor' => $member !== null ? latest_room_signal_id($roomId, $userId) : 0,
        ],
        'poll_seconds' => 5,
    ];
});

/** POST /api/rooms/{id}/join */
route('POST', '/api/rooms/{id}/join', function (array $params): array {
    $user = require_login();
    [$room] = visible_room($params['id'], $user); // a private room needs the code first (POST /api/rooms/join)
    require_room_space((int) $room['id'], (int) $user['id']);
    mark_present((int) $room['id'], (int) $user['id']);
    $message = room_is_live($room, now_on_campus())
        ? 'You joined ' . $room['name'] . '.'
        : 'You joined ' . $room['name'] . '. It starts at ' . format_date($room['starts_at'], 'H:i \o\n D j M') . '.';
    return ['message' => $message];
});

/** POST /api/rooms/{id}/leave */
route('POST', '/api/rooms/{id}/leave', function (array $params): array {
    $user = require_login();
    $room = find_room($params['id']);
    if ($room === null) {
        abort(404, 'This room does not exist.');
    }
    leave_room((int) $room['id'], (int) $user['id']);
    return ['message' => 'You left ' . $room['name'] . '.'];
});

/** PUT /api/rooms/{id}/task   {"task": "Calculus problem set 4"} */
route('PUT', '/api/rooms/{id}/task', function (array $params): array {
    $user = require_login();
    [$room] = member_room($params['id'], $user);
    $task = input_text('task');
    if (mb_strlen($task) > 120) {
        abort(422, 'Keep it under 120 characters.', ['task' => 'Keep it under 120 characters.']);
    }
    set_member_task((int) $room['id'], (int) $user['id'], $task !== '' ? $task : null);
    return ['message' => $task !== '' ? 'Others can now see what you are working on.' : 'Your task was cleared.'];
});

/**
 * PUT /api/rooms/{id}/media   {"camera": true, "mic": false}
 * The page asks BEFORE turning the camera on, so the server can hand out the camera spots fairly.
 */
route('PUT', '/api/rooms/{id}/media', function (array $params): array {
    $user = require_login();
    $userId = (int) $user['id'];
    [$room, $member] = member_room($params['id'], $user);
    $camera = (request_input()['camera'] ?? false) === true;
    $mic = (request_input()['mic'] ?? false) === true;

    if ($camera || $mic) {
        require_live_room($room);
    }
    $isHost = is_room_host($room, $userId);
    $isClassHost = $isHost && $room['kind'] === 'class';
    if ($camera && !$member['camera_on'] && !$isClassHost && count_cameras_in_use($room, $userId) >= ROOM_MAX_CAMERAS) {
        abort(409, 'All ' . ROOM_MAX_CAMERAS . ' camera spots are taken. You can still watch, listen and chat.');
    }
    $timer = room_timer_now($room, now_on_campus());
    if ($mic && !room_may_talk($room, $timer, $isHost, (bool) $member['can_speak'])) {
        abort(409, $room['kind'] === 'class'
            ? 'The host has not let you talk yet. Ask in the chat.'
            : 'Microphones are off while everyone is focusing. You can talk at the break.');
    }
    set_member_media((int) $room['id'], $userId, $camera, $mic);
    return ['camera_on' => $camera, 'mic_on' => $mic];
});

/** POST /api/rooms/{id}/messages   {"body": "..."} */
route('POST', '/api/rooms/{id}/messages', function (array $params): ?array {
    $user = require_login();
    [$room] = member_room($params['id'], $user, 'Join the room to chat.');
    require_live_room($room);
    if (!room_chat_is_open($room, room_timer_now($room, now_on_campus()))) {
        abort(409, 'Chat is paused while everyone is focusing. It opens again at the break.');
    }
    $body = input_text('body');
    if (mb_strlen($body) < 1 || mb_strlen($body) > 300) {
        abort(422, 'Write a message of 1–300 characters.', ['body' => 'Write a message of 1–300 characters.']);
    }
    if (count_recent_messages((int) $room['id'], (int) $user['id']) >= ROOM_MESSAGE_LIMIT) {
        abort(429, 'You are sending messages very quickly. Wait a few seconds.');
    }
    add_room_message((int) $room['id'], (int) $user['id'], $body);
    send_json(['message' => 'Sent.'], 201);
    return null;
});

/** POST /api/rooms/{id}/timer   {"action": "start" | "stop"} — start always begins a fresh focus block. */
route('POST', '/api/rooms/{id}/timer', function (array $params): array {
    $user = require_login();
    [$room] = visible_room($params['id'], $user);
    if ($room['kind'] !== 'focus') {
        abort(400, 'Classes have no timer.');
    }
    if (!can_manage_room($room, $user)) {
        abort(403, 'Only the person who created this room can control the timer.');
    }
    $action = input_text('action');
    if (!in_array($action, ['start', 'stop'], true)) {
        abort(400, 'Invalid timer action.');
    }
    set_room_timer((int) $room['id'], $action === 'start');
    return ['message' => $action === 'start' ? 'Focus time! Chat and microphones are paused until the break.' : 'The timer was stopped.'];
});

/** POST /api/rooms/{id}/open — the host opens a class before its start time. */
route('POST', '/api/rooms/{id}/open', function (array $params): array {
    $user = require_login();
    [$room] = visible_room($params['id'], $user);
    if ($room['kind'] !== 'class') {
        abort(400, 'Only classes are scheduled.');
    }
    if (!can_manage_room($room, $user)) {
        abort(403, 'Only the host can open this class.');
    }
    open_class_now((int) $room['id']);
    return ['message' => 'Your class is open. Students can join now.'];
});

/** POST /api/rooms/{id}/members/{userId}/speak   {"allow": true} */
route('POST', '/api/rooms/{id}/members/{userId}/speak', function (array $params): array {
    $user = require_login();
    [$room] = visible_room($params['id'], $user);
    if ($room['kind'] !== 'class') {
        abort(400, 'In focus rooms everyone can talk during breaks.');
    }
    if (!can_manage_room($room, $user)) {
        abort(403, 'Only the host decides who can talk.');
    }
    $student = find_room_member((int) $room['id'], $params['userId']);
    if ($student === null || is_room_host($room, $params['userId'])) {
        abort(404, 'That person is not a student in this class.');
    }
    $allow = (request_input()['allow'] ?? false) === true;
    set_member_can_speak((int) $room['id'], $params['userId'], $allow);
    $name = find_user_by_id($params['userId'])['name'];
    return ['message' => $allow ? "$name can talk now." : "$name is muted."];
});

/** GET /api/rooms/{id}/signals?after=N — connection messages for me, newer than N. */
route('GET', '/api/rooms/{id}/signals', function (array $params): array {
    $user = require_login();
    [$room] = member_room($params['id'], $user);
    $after = max(0, (int) query_text('after', '0'));
    $signals = get_room_signals((int) $room['id'], (int) $user['id'], $after);
    return [
        'signals' => array_map(fn ($s) => [
            'id'      => (int) $s['id'],
            'from'    => (int) $s['from_user'],
            'kind'    => $s['kind'],
            'payload' => $s['payload'],
        ], $signals),
        'cursor' => $signals === [] ? $after : (int) end($signals)['id'],
    ];
});

/** POST /api/rooms/{id}/signals   {"to": 4, "kind": "offer", "payload": "{...session description...}"} */
route('POST', '/api/rooms/{id}/signals', function (array $params): ?array {
    $user = require_login();
    [$room] = member_room($params['id'], $user);
    require_live_room($room);
    $to = (int) (request_input()['to'] ?? 0);
    $kind = input_text('kind');
    $payload = request_input()['payload'] ?? null;
    if (!in_array($kind, ROOM_SIGNAL_KINDS, true)) {
        abort(400, 'Invalid signal.');
    }
    if ($to === (int) $user['id'] || !is_room_member((int) $room['id'], $to)) {
        abort(404, 'That person is not in this room.');
    }
    $needsPayload = in_array($kind, ['offer', 'answer'], true);
    if ($needsPayload && (!is_string($payload) || $payload === '' || strlen($payload) > ROOM_SIGNAL_MAX_BYTES)) {
        abort(422, 'Invalid connection details.');
    }
    add_room_signal((int) $room['id'], (int) $user['id'], $to, $kind, $needsPayload ? $payload : null);
    send_json(['ok' => true], 201);
    return null;
});

/** DELETE /api/rooms/{id} */
route('DELETE', '/api/rooms/{id}', function (array $params): array {
    $user = require_login();
    $room = find_room($params['id']);
    // Someone who cannot see a private room gets the same answer as for a missing one.
    $hidden = $room !== null && $room['visibility'] === 'private'
        && !can_manage_room($room, $user) && !is_room_member((int) $room['id'], (int) $user['id']);
    if ($room === null || $hidden) {
        abort(404, 'This room does not exist.');
    }
    if (!can_manage_room($room, $user)) {
        abort(403, 'Only the person who created this room can close it.');
    }
    close_room((int) $room['id']);
    return ['message' => $room['name'] . ($room['kind'] === 'class' ? ' has ended.' : ' was closed.')];
});
