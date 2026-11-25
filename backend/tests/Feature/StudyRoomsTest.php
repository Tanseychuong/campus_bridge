<?php

/** Study rooms through the API: focus rooms and classes, joining, presence, chat, timer, cameras, microphones and video-call signals. */
final class StudyRoomsTest extends WebTestCase
{
    private function createRoom(array $overrides = []): array
    {
        return $this->post('api/rooms', $overrides + ['kind' => 'focus', 'name' => 'Exam prep: Calculus', 'topic' => 'Past papers', 'visibility' => 'public', 'timer' => '25-5']);
    }

    /** A class starting in an hour (campus time). */
    private function createClass(array $overrides = []): array
    {
        $start = now_on_campus()->modify('+1 hour');
        return $this->post('api/rooms', $overrides + [
            'kind' => 'class', 'name' => 'Intro to SQL joins', 'topic' => 'Bring a laptop', 'visibility' => 'public',
            'date' => $start->format('Y-m-d'), 'time' => $start->format('H:i'),
        ]);
    }

    private function media(int $roomId, bool $camera, bool $mic): array
    {
        return $this->request('PUT', "api/rooms/$roomId/media", ['camera' => $camera, 'mic' => $mic], $this->csrfToken());
    }

    public function test_a_class_is_scheduled_opened_by_the_host_and_students_talk_when_allowed(): void
    {
        $this->signIn('student@example.org');
        $created = $this->createClass();
        $this->assertSame(201, $created['status'], $created['body']);
        $classId = $created['json']['room_id'];
        $hostId = $this->idOf('users', 'student@example.org');

        $class = $this->get("api/rooms/$classId")['json'];
        $this->assertSame('class', $class['room']['kind']);
        $this->assertFalse($class['room']['is_live']);
        $this->assertGreaterThan(3000, $class['room']['seconds_to_start']);
        $this->assertFalse($class['chat_open']);
        // Before it starts: no camera, no chat, and classes have no timer.
        $this->assertSame(409, $this->media($classId, true, false)['status']);
        $this->assertSame(409, $this->post("api/rooms/$classId/messages", ['body' => 'hello?'])['status']);
        $this->assertSame(400, $this->post("api/rooms/$classId/timer", ['action' => 'start'])['status']);
        $this->signOut();

        // A student sees it as upcoming and can join the waiting room, but cannot open it.
        $this->signIn('riverside@example.org');
        $studentId = $this->idOf('users', 'riverside@example.org');
        $listed = array_column($this->get('api/rooms')['json']['rooms'], null, 'id');
        $this->assertFalse($listed[$classId]['is_live']);
        $this->assertNotNull($listed[$classId]['starts_label']);
        $this->assertSame(200, $this->post("api/rooms/$classId/join")['status']);
        $this->assertSame(403, $this->post("api/rooms/$classId/open")['status']);
        $this->signOut();

        // The host opens it early and can talk straight away.
        $this->signIn('student@example.org');
        $this->assertSame(200, $this->post("api/rooms/$classId/open")['status']);
        $this->assertSame(200, $this->media($classId, true, true)['status']);
        $class = $this->get("api/rooms/$classId")['json'];
        $this->assertTrue($class['room']['is_live']);
        $this->assertTrue($class['chat_open']); // chat is open for the whole class
        $host = array_column($class['members'], null, 'id')[$hostId];
        $this->assertTrue($host['camera_on'] && $host['mic_live']);
        $this->assertSame(0, $class['cameras']['used']); // the host's camera does not use a spot
        $this->signOut();

        // The student cannot talk until the host allows it.
        $this->signIn('riverside@example.org');
        $this->assertSame(409, $this->media($classId, true, true)['status']);
        $this->assertSame(200, $this->media($classId, true, false)['status']);
        $this->assertSame(201, $this->post("api/rooms/$classId/messages", ['body' => 'Question about joins'])['status']);
        $this->assertSame(403, $this->post("api/rooms/$classId/members/$hostId/speak", ['allow' => true])['status']);
        $this->signOut();

        $this->signIn('student@example.org');
        $this->assertSame(200, $this->post("api/rooms/$classId/members/$studentId/speak", ['allow' => true])['status']);
        $this->signOut();

        $this->signIn('riverside@example.org');
        $this->assertTrue($this->get("api/rooms/$classId")['json']['me']['may_talk']);
        $this->assertSame(200, $this->media($classId, true, true)['status']);
        $this->signOut();

        // Taking the word back also switches the student's microphone off.
        $this->signIn('student@example.org');
        $this->post("api/rooms/$classId/members/$studentId/speak", ['allow' => false]);
        $student = array_column($this->get("api/rooms/$classId")['json']['members'], null, 'id')[$studentId];
        $this->assertFalse($student['mic_live']);
        $this->assertFalse($student['can_speak']);
        $this->assertTrue($student['camera_on']);

        $ended = $this->request('DELETE', "api/rooms/$classId", [], $this->csrfToken());
        $this->assertStringContainsString('has ended', $ended['json']['message']);
    }

    public function test_focus_room_microphones_only_work_in_breaks_and_cameras_have_six_spots(): void
    {
        $this->signIn('student@example.org');
        $roomId = $this->createRoom(['name' => 'Camera spots test'])['json']['room_id'];
        // Before the timer starts, talking is fine.
        $this->assertSame(200, $this->media($roomId, true, true)['status']);
        $this->post("api/rooms/$roomId/timer", ['action' => 'start']);
        $room = $this->get("api/rooms/$roomId")['json'];
        $this->assertFalse($room['me']['may_talk']);
        $this->assertFalse($room['members'][0]['mic_live']); // nobody hears it while focusing
        $this->assertSame(409, $this->media($roomId, true, true)['status']);
        $this->assertSame(200, $this->media($roomId, true, false)['status']);
        $this->signOut();

        // Fill the other five spots; then the next camera is refused.
        foreach (['rep@example.org', 'rep2@example.org', 'rep3@example.org', 'rep4@example.org', 'rep5@example.org'] as $email) {
            $this->signIn($email);
            $this->post("api/rooms/$roomId/join");
            $this->assertSame(200, $this->media($roomId, true, false)['status'], $email);
            $this->signOut();
        }
        $this->signIn('riverside@example.org');
        $this->post("api/rooms/$roomId/join");
        $full = $this->media($roomId, true, false);
        $this->assertSame(409, $full['status']);
        $this->assertStringContainsString('camera spots are taken', $full['json']['error']);
        $this->assertSame(6, $this->get("api/rooms/$roomId")['json']['cameras']['used']);
        $this->signOut();

        $this->signIn('student@example.org');
        $this->request('DELETE', "api/rooms/$roomId", [], $this->csrfToken());
    }

    public function test_browsers_exchange_connection_messages_through_the_room(): void
    {
        $this->signIn('student@example.org');
        $roomId = $this->createRoom(['name' => 'Signals test'])['json']['room_id'];
        $amaId = $this->idOf('users', 'student@example.org');
        $kwameId = $this->idOf('users', 'riverside@example.org');
        // Only to people in the room.
        $this->assertSame(404, $this->post("api/rooms/$roomId/signals", ['to' => $kwameId, 'kind' => 'hello'])['status']);
        $this->signOut();

        $this->signIn('riverside@example.org');
        $this->assertSame(403, $this->get("api/rooms/$roomId/signals?after=0")['status']); // not in the room yet
        $this->post("api/rooms/$roomId/join");
        $cursor = $this->get("api/rooms/$roomId")['json']['call']['signal_cursor'];
        $this->assertSame(201, $this->post("api/rooms/$roomId/signals", ['to' => $amaId, 'kind' => 'hello'])['status']);
        $this->signOut();

        $this->signIn('student@example.org');
        $this->assertSame(422, $this->post("api/rooms/$roomId/signals", ['to' => $kwameId, 'kind' => 'offer'])['status']); // offers need a description
        $this->assertSame(400, $this->post("api/rooms/$roomId/signals", ['to' => $kwameId, 'kind' => 'shout'])['status']);
        $inbox = $this->get("api/rooms/$roomId/signals?after=0")['json'];
        $this->assertCount(1, $inbox['signals']);
        $this->assertSame(['from' => $kwameId, 'kind' => 'hello', 'payload' => null], array_diff_key($inbox['signals'][0], ['id' => 0]));
        $offer = json_encode(['type' => 'offer', 'sdp' => "v=0\r\n"]);
        $this->assertSame(201, $this->post("api/rooms/$roomId/signals", ['to' => $kwameId, 'kind' => 'offer', 'payload' => $offer])['status']);
        $this->assertSame([], $this->get("api/rooms/$roomId/signals?after={$inbox['cursor']}")['json']['signals']); // nothing new for Ama
        $this->signOut();

        $this->signIn('riverside@example.org');
        $received = $this->get("api/rooms/$roomId/signals?after=$cursor")['json']['signals'];
        $this->assertCount(1, $received);
        $this->assertSame('offer', $received[0]['kind']);
        $this->assertSame($offer, $received[0]['payload']);
        $this->signOut();

        $this->signIn('student@example.org');
        $this->request('DELETE', "api/rooms/$roomId", [], $this->csrfToken());
    }

    public function test_guests_cannot_use_focus_rooms(): void
    {
        $this->assertSame(401, $this->get('api/rooms')['status']);
        $this->assertSame(401, $this->createRoom()['status']);
    }

    public function test_the_create_form_is_checked_on_the_server(): void
    {
        $this->signIn('student@example.org');
        $answer = $this->createRoom(['name' => 'X', 'timer' => '1-1']);
        $this->assertSame(422, $answer['status']);
        $this->assertSame(['name', 'timer'], array_keys($answer['json']['errors']));
        // A class needs its start time instead of a timer.
        $class = $this->createRoom(['kind' => 'class', 'timer' => '']);
        $this->assertSame(['starts_at'], array_keys($class['json']['errors']));
    }

    public function test_students_study_together_in_a_public_room(): void
    {
        $this->signIn('student@example.org');
        $created = $this->createRoom();
        $this->assertSame(201, $created['status'], $created['body']);
        $roomId = $created['json']['room_id'];

        // The creator is inside straight away and controls the timer.
        $room = $this->get("api/rooms/$roomId")['json'];
        $this->assertTrue($room['me']['is_member']);
        $this->assertTrue($room['me']['can_manage']);
        $this->assertSame('idle', $room['timer']['phase']);
        $this->assertNull($room['room']['invite_code']); // public rooms need no code
        $this->signOut();

        // Another student finds it in the list and joins.
        $this->signIn('riverside@example.org');
        $listed = array_column($this->get('api/rooms')['json']['rooms'], null, 'id');
        $this->assertArrayHasKey($roomId, $listed);
        $this->assertSame(1, $listed[$roomId]['present_count']);

        $before = $this->get("api/rooms/$roomId")['json'];
        $this->assertFalse($before['me']['is_member']);
        $this->assertSame([], $before['messages']); // the chat is for people inside only
        $this->assertSame(403, $this->post("api/rooms/$roomId/messages", ['body' => 'hi'])['status']);

        $this->assertSame(200, $this->post("api/rooms/$roomId/join")['status']);
        $this->assertSame(200, $this->request('PUT', "api/rooms/$roomId/task", ['task' => 'Problem set 4'], $this->csrfToken())['status']);
        $this->assertSame(201, $this->post("api/rooms/$roomId/messages", ['body' => 'Hello! Ready when you are.'])['status']);

        $room = $this->get("api/rooms/$roomId")['json'];
        $this->assertCount(2, $room['members']);
        $this->assertSame('Problem set 4', array_column($room['members'], 'task', 'name')['Kwame Darko (demo student)']);
        $this->assertSame('Hello! Ready when you are.', end($room['messages'])['body']);
        $this->assertFalse($room['me']['can_manage']);

        // Only the creator controls the timer.
        $this->assertSame(403, $this->post("api/rooms/$roomId/timer", ['action' => 'start'])['status']);
        $this->signOut();

        $this->signIn('student@example.org');
        $this->assertSame(200, $this->post("api/rooms/$roomId/timer", ['action' => 'start'])['status']);
        $this->assertSame('focus', $this->get("api/rooms/$roomId")['json']['timer']['phase']);
        // Chat is paused during focus time.
        $this->assertSame(409, $this->post("api/rooms/$roomId/messages", ['body' => 'psst'])['status']);
        $this->post("api/rooms/$roomId/timer", ['action' => 'stop']);

        // Closing the room ends it for everyone.
        $this->assertSame(200, $this->request('DELETE', "api/rooms/$roomId", [], $this->csrfToken())['status']);
        $this->assertSame(410, $this->get("api/rooms/$roomId")['status']);
    }

    public function test_private_rooms_need_the_invite_code(): void
    {
        $this->signIn('student@example.org');
        $roomId = $this->createRoom(['name' => 'Study group (private)', 'visibility' => 'private'])['json']['room_id'];
        $code = $this->get("api/rooms/$roomId")['json']['room']['invite_code'];
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $code);
        $this->signOut();

        $this->signIn('riverside@example.org');
        // Not listed, and it looks like it does not exist.
        $this->assertNotContains($roomId, array_column($this->get('api/rooms')['json']['rooms'], 'id'));
        $this->assertSame(404, $this->get("api/rooms/$roomId")['status']);
        $this->assertSame(404, $this->post("api/rooms/$roomId/join")['status']);

        $this->assertSame(422, $this->post('api/rooms/join', ['code' => 'ZZZZZZ'])['status']);
        $joined = $this->post('api/rooms/join', ['code' => strtolower($code)]);
        $this->assertSame($roomId, $joined['json']['room_id']);
        $this->assertTrue($this->get("api/rooms/$roomId")['json']['me']['is_member']);

        // Another student cannot close it.
        $this->assertSame(403, $this->request('DELETE', "api/rooms/$roomId", [], $this->csrfToken())['status']);

        // After leaving, the code is needed again.
        $this->post("api/rooms/$roomId/leave");
        $this->assertSame(404, $this->get("api/rooms/$roomId")['status']);
        $this->signOut();

        $this->signIn('student@example.org');
        $this->request('DELETE', "api/rooms/$roomId", [], $this->csrfToken());
    }

    public function test_a_person_can_have_only_a_few_open_rooms(): void
    {
        $this->signIn('rep@example.org');
        $ids = [];
        for ($i = 1; $i <= ROOM_MAX_OPEN_PER_CREATOR; $i++) {
            $ids[] = $this->createRoom(['name' => "Room number $i"])['json']['room_id'];
        }
        $this->assertFalse($this->get('api/rooms')['json']['can_create']);
        $this->assertSame(409, $this->createRoom(['name' => 'One too many'])['status']);

        foreach ($ids as $id) {
            $this->request('DELETE', "api/rooms/$id", [], $this->csrfToken());
        }
        $this->assertTrue($this->get('api/rooms')['json']['can_create']);
    }

    public function test_an_admin_can_close_any_public_room(): void
    {
        $this->signIn('student@example.org');
        $roomId = $this->createRoom(['name' => 'Room to moderate'])['json']['room_id'];
        $this->signOut();

        $this->signIn('admin@example.org');
        $this->assertSame(200, $this->request('DELETE', "api/rooms/$roomId", [], $this->csrfToken())['status']);
    }
}
