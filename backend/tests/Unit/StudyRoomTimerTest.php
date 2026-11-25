<?php

use PHPUnit\Framework\TestCase;

/** The shared focus-room timer and the room form rules. */
final class StudyRoomTimerTest extends TestCase
{
    /** test_now() minus $seconds, as a database (UTC) date. */
    private function startedSecondsAgo(int $seconds): string
    {
        return to_database(test_now()->modify("-$seconds seconds"));
    }

    public function test_a_stopped_timer_is_idle_and_chat_is_open(): void
    {
        $timer = room_timer(null, 25, 5, test_now());
        $this->assertSame(['phase' => 'idle', 'seconds_left' => null, 'round' => 0], $timer);
        $this->assertTrue(room_chat_is_open(['kind' => 'focus'], $timer));
    }

    public function test_the_first_minutes_are_focus_time_and_chat_is_closed(): void
    {
        $timer = room_timer($this->startedSecondsAgo(60), 25, 5, test_now());
        $this->assertSame(['phase' => 'focus', 'seconds_left' => 24 * 60, 'round' => 1], $timer);
        $this->assertFalse(room_chat_is_open(['kind' => 'focus'], $timer));
    }

    public function test_after_the_focus_block_comes_the_break(): void
    {
        $timer = room_timer($this->startedSecondsAgo(25 * 60), 25, 5, test_now());
        $this->assertSame(['phase' => 'break', 'seconds_left' => 5 * 60, 'round' => 1], $timer);
        $this->assertTrue(room_chat_is_open(['kind' => 'focus'], $timer));
    }

    public function test_the_cycle_repeats_in_rounds(): void
    {
        // 30 min cycle: 65 minutes in = round 3, 5 minutes into focus.
        $this->assertSame(['phase' => 'focus', 'seconds_left' => 20 * 60, 'round' => 3], room_timer($this->startedSecondsAgo(65 * 60), 25, 5, test_now()));
        // 50/10: 59 minutes in = last minute of the first break.
        $this->assertSame(['phase' => 'break', 'seconds_left' => 60, 'round' => 1], room_timer($this->startedSecondsAgo(59 * 60), 50, 10, test_now()));
    }

    public function test_a_start_time_in_the_future_counts_as_just_started(): void
    {
        $this->assertSame('focus', room_timer(to_database(test_now()->modify('+10 seconds')), 25, 5, test_now())['phase']);
    }

    public function test_the_focus_room_form_is_checked(): void
    {
        $this->assertSame([], validate_room_form(['kind' => 'focus', 'name' => 'Exam prep', 'topic' => '', 'visibility' => 'public', 'timer' => '25-5'], test_now()));
        $errors = validate_room_form(['kind' => 'focus', 'name' => 'X', 'topic' => str_repeat('a', 161), 'visibility' => 'secret', 'timer' => '10-1'], test_now());
        $this->assertSame(['name', 'topic', 'visibility', 'timer'], array_keys($errors));
        $this->assertArrayHasKey('kind', validate_room_form(['kind' => 'party', 'name' => 'Exam prep', 'visibility' => 'public'], test_now()));
    }

    public function test_a_class_needs_a_start_time_in_the_next_30_days(): void
    {
        $class = ['kind' => 'class', 'name' => 'Intro to SQL', 'topic' => '', 'visibility' => 'public', 'time' => '18:00'];
        $this->assertSame([], validate_room_form($class + ['date' => '2026-11-20'], test_now())); // no timer needed
        $this->assertArrayHasKey('starts_at', validate_room_form($class + ['date' => '2026-11-17'], test_now())); // the past
        $this->assertArrayHasKey('starts_at', validate_room_form($class + ['date' => '2027-01-30'], test_now())); // too far ahead
        $this->assertArrayHasKey('starts_at', validate_room_form($class + ['date' => '2026-02-30'], test_now())); // not a date
        $this->assertSame('2026-11-20 18:00', room_form_start($class + ['date' => '2026-11-20'])->format('Y-m-d H:i'));
    }

    public function test_classes_open_at_their_start_time_or_when_the_host_opens_them(): void
    {
        $class = ['kind' => 'class', 'status' => 'open', 'opened_at' => null, 'starts_at' => to_database(test_now()->modify('+1 hour'))];
        $this->assertFalse(room_is_live($class, test_now()));
        $this->assertTrue(room_is_live($class, test_now()->modify('+61 minutes')));
        $this->assertTrue(room_is_live(['opened_at' => to_database(test_now())] + $class, test_now()));
        $this->assertFalse(room_is_live(['status' => 'closed'] + $class, test_now()->modify('+2 hours')));
        $this->assertTrue(room_is_live(['kind' => 'focus', 'status' => 'open', 'opened_at' => null, 'starts_at' => null], test_now()));
    }

    public function test_who_may_talk(): void
    {
        $focus = ['kind' => 'focus'];
        $class = ['kind' => 'class'];
        $focusTime = ['phase' => 'focus', 'seconds_left' => 60, 'round' => 1];
        $breakTime = ['phase' => 'break', 'seconds_left' => 60, 'round' => 1];
        // Focus rooms: everyone during breaks, nobody (not even the host) while focusing.
        $this->assertTrue(room_may_talk($focus, $breakTime, false, false));
        $this->assertFalse(room_may_talk($focus, $focusTime, true, true));
        // Classes: the host always, students only when allowed. Chat is always open.
        $idle = ['phase' => 'idle', 'seconds_left' => null, 'round' => 0];
        $this->assertTrue(room_may_talk($class, $idle, true, false));
        $this->assertFalse(room_may_talk($class, $idle, false, false));
        $this->assertTrue(room_may_talk($class, $idle, false, true));
        $this->assertTrue(room_chat_is_open($class, $idle));
    }

    public function test_invite_codes_are_easy_to_type(): void
    {
        $this->assertSame('K7QX3M', normalize_room_code(' k7q-x3m '));
        $code = new_room_code();
        $this->assertMatchesRegularExpression('/^[' . ROOM_CODE_ALPHABET . ']{6}$/', $code);
    }
}
