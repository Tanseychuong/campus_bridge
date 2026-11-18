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
            'registration' => 'rsvp', 'visibility' => 'campus', 'interests' => ['engineering'],
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
        $errors = validate_registration(['name' => 'A', 'email' => 'not-an-email', 'password' => 'short', 'password_confirm' => 'other', 'campus_id' => '9'], [1, 2]);
        $this->assertSame(['name', 'email', 'password', 'password_confirm', 'campus_id'], array_keys($errors));
        $this->assertSame([], validate_registration(['name' => 'Ama', 'email' => 'ama@example.org', 'password' => 'long-enough-1', 'password_confirm' => 'long-enough-1', 'campus_id' => '2'], [1, 2]));
    }

    public function test_events_need_a_visibility_choice(): void
    {
        $errors = validate_event_form(['visibility' => 'everyone-everywhere'] + $this->validEvent(), test_now());
        $this->assertSame(['visibility'], array_keys($errors));
    }

    private function validResource(): array
    {
        return [
            'title' => 'Calculus I revision notes', 'description' => 'My own summary of limits and derivatives.', 'organization_id' => '1',
            'ip_ownership' => 'sole', 'ip_credits' => '', 'license' => 'cc_by', 'declared_name' => 'Ama Mensah',
            'confirm_rights' => '1', 'confirm_no_confidential' => '1', 'confirm_takedown' => '1',
        ];
    }

    public function test_a_resource_needs_a_complete_ip_declaration(): void
    {
        $this->assertSame([], validate_resource_form($this->validResource(), [1, 2]));

        $errors = validate_resource_form(['ip_ownership' => 'joint', 'confirm_takedown' => '', 'declared_name' => ''] + $this->validResource(), [1, 2]);
        $this->assertSame(['ip_credits', 'declaration', 'declared_name'], array_keys($errors));

        $wrongOrganization = validate_resource_form(['organization_id' => '99'] + $this->validResource(), [1, 2]);
        $this->assertArrayHasKey('organization_id', $wrongOrganization);
    }

    /* ---------- helpers and security ---------- */

    public function test_routes_only_match_positive_whole_number_ids(): void
    {
        $regex = route_regex('/api/events/{id}');
        $this->assertSame(1, preg_match($regex, '/api/events/42', $matches));
        $this->assertSame('42', $matches['id']);
        foreach (['/api/events/0', '/api/events/-5', '/api/events/abc', '/api/events/4/x', '/api/events/'] as $path) {
            $this->assertSame(0, preg_match($regex, $path), $path);
        }
        // A dot in a route is a real dot, not "any character".
        $this->assertSame(0, preg_match(route_regex('/api/calendar.ics'), '/api/calendarXics'));
    }

    public function test_presenters_hide_unsafe_links_and_colours(): void
    {
        $opportunity = present_opportunity(make_opportunity(['source_url' => 'javascript:alert(1)']), test_now(), make_profile());
        $this->assertNull($opportunity['source']['url']);
        $this->assertSame('10 days left', $opportunity['deadline']['label']);

        $organization = present_organization(make_organization(['color' => 'red;background:url(x)', 'campus_name' => 'Hillside']), make_profile(['followed' => [1]]));
        $this->assertSame('#475569', $organization['color']);
        $this->assertTrue($organization['is_following']);
    }

    public function test_text_lines_and_plurals(): void
    {
        $this->assertSame(['One', 'Two'], lines("One\n\n  Two  \n"));
        $this->assertSame('1 event', plural(1, 'event'));
        $this->assertSame('3 events', plural(3, 'event'));
        $this->assertSame('2 schedule clashes', plural(2, 'schedule clash', 'schedule clashes'));
    }
}
