<?php

use PHPUnit\Framework\TestCase;

/** Calendar export, moderation hints, analytics, validation and helper functions. */
final class OtherLogicTest extends TestCase
{
    /* ---------- .ics export ---------- */

    public function test_ics_text_is_escaped(): void
    {
        $this->assertSame('a\\,b\\;c\\\\d\\ne', ics_escape("a,b;c\\d\ne"));
    }

    public function test_long_ics_lines_are_folded_at_75_bytes(): void
    {
        $folded = ics_fold_line('SUMMARY:' . str_repeat('x', 200));
        foreach (explode("\r\n", $folded) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
        $this->assertSame('SUMMARY:' . str_repeat('x', 200), str_replace("\r\n ", '', $folded));
    }

    public function test_ics_calendar_contains_events_and_all_day_deadlines(): void
    {
        $ics = build_ics_calendar(
            [make_event(['id' => 7, 'title' => 'Workshop, part 1', 'starts_at' => '2026-11-20 18:30:00', 'ends_at' => '2026-11-20 20:30:00'])],
            [make_opportunity(['id' => 3, 'title' => 'Scholarship', 'deadline' => '2026-11-30 23:59:00'])],
            test_now()
        );
        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('SUMMARY:Workshop\\, part 1', $ics);
        $this->assertStringContainsString('DTSTART:20261120T183000Z', $ics);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261130', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20261201', $ics);
        $this->assertSame(2, substr_count($ics, 'BEGIN:VEVENT'));
    }

    /* ---------- moderation ---------- */

    public function test_scam_signals_are_flagged_as_critical(): void
    {
        $flags = moderation_flags(make_opportunity([
            'title' => 'Survey jobs', 'description' => 'Pay a small fee to register.', 'provider' => 'Unknown poster', 'eligibility' => '',
        ]), test_now());
        $this->assertCount(2, array_filter($flags, fn ($f) => $f['severity'] === 'critical'));
        $this->assertContains('No eligibility information', array_column($flags, 'message'));
    }

    public function test_clean_listings_have_no_flags(): void
    {
        $this->assertSame([], moderation_flags(make_opportunity(['deadline' => days_from_now(20)]), test_now()));
    }

    /* ---------- analytics ---------- */

    public function test_counts_include_empty_categories_and_sort_biggest_first(): void
    {
        $counts = count_by_category([['category' => 'tech'], ['category' => 'tech'], ['category' => 'arts']], EVENT_CATEGORIES);
        $this->assertSame(2, $counts['Tech']);
        $this->assertSame(0, $counts['Sports']);
        $this->assertSame('Tech', array_key_first($counts));
    }

    public function test_deadlines_are_counted_per_week(): void
    {
        $weeks = deadlines_per_week([
            make_opportunity(['deadline' => days_from_now(1)]),
            make_opportunity(['deadline' => days_from_now(8)]),
            make_opportunity(['deadline' => days_from_now(100)]),
            make_opportunity(['deadline' => null]),
        ], test_now(), 4);
        $this->assertSame([1, 1, 0, 0], array_values($weeks));
        $this->assertSame('w/c 18 Nov', array_key_first($weeks));
    }

    /* ---------- validation ---------- */

    private function validEvent(): array
    {
        return [
            'title' => 'Intro to Robotics', 'category' => 'tech', 'summary' => 'Build your first robot.',
            'description' => 'A hands-on session for beginners covering motors and sensors.', 'venue' => 'Fabrication Lab',
            'date' => '2026-11-25', 'start_time' => '14:00', 'end_time' => '16:00', 'capacity' => '20',
            'registration' => 'rsvp', 'interests' => ['engineering'],
        ];
    }

    public function test_a_valid_event_has_no_errors(): void
    {
        $this->assertSame([], validate_event_form($this->validEvent(), test_now()));
        $this->assertSame(['2026-11-25 14:00:00', '2026-11-25 16:00:00'], event_form_times($this->validEvent()));
    }

    public function test_invalid_events_get_one_message_per_field(): void
    {
        $errors = validate_event_form(['title' => 'Hi', 'end_time' => '13:00', 'capacity' => '-5', 'interests' => ['made-up']] + $this->validEvent(), test_now());
        $this->assertArrayHasKey('title', $errors);
        $this->assertArrayHasKey('end_time', $errors);
        $this->assertArrayHasKey('capacity', $errors);
        $this->assertArrayHasKey('interests', $errors);
    }

    public function test_events_cannot_start_in_the_past(): void
    {
        $errors = validate_event_form(['date' => '2026-11-01'] + $this->validEvent(), test_now());
        $this->assertSame('The event must start in the future.', $errors['date']);
    }

    public function test_registration_rules(): void
    {
        $errors = validate_registration(['name' => 'A', 'email' => 'not-an-email', 'password' => 'short', 'password_confirm' => 'other']);
        $this->assertSame(['name', 'email', 'password', 'password_confirm'], array_keys($errors));
        $this->assertSame([], validate_registration(['name' => 'Ama', 'email' => 'ama@example.org', 'password' => 'long-enough-1', 'password_confirm' => 'long-enough-1']));
    }

    /* ---------- helpers and security ---------- */

    public function test_html_is_escaped(): void
    {
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
        $this->assertSame('', e(null));
    }

    public function test_only_local_paths_are_allowed_as_redirect_targets(): void
    {
        $this->assertSame('/event.php?id=3', safe_return_path('/event.php?id=3', '/fallback'));
        $this->assertSame('/fallback', safe_return_path('https://evil.example', '/fallback'));
        $this->assertSame('/fallback', safe_return_path('//evil.example', '/fallback'));
        $this->assertSame('/fallback', safe_return_path('/\\evil.example', '/fallback'));
        $this->assertSame('/fallback', safe_return_path(null, '/fallback'));
    }

    public function test_text_lines_and_plurals(): void
    {
        $this->assertSame(['One', 'Two'], lines("One\n\n  Two  \n"));
        $this->assertSame('1 event', plural(1, 'event'));
        $this->assertSame('3 events', plural(3, 'event'));
        $this->assertSame('2 schedule clashes', plural(2, 'schedule clash', 'schedule clashes'));
    }
}
