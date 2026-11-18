<?php

use PHPUnit\Framework\TestCase;

/** Dates, deadlines and clash detection. */
final class TimeTest extends TestCase
{
    public function test_calendar_days_are_counted_by_date_not_by_24_hours(): void
    {
        $lateMonday = new DateTimeImmutable('2026-11-16 23:00', campus_timezone());
        $earlyTuesday = new DateTimeImmutable('2026-11-17 01:00', campus_timezone());
        $this->assertSame(1, calendar_days_between($lateMonday, $earlyTuesday));
        $this->assertSame(-1, calendar_days_between($earlyTuesday, $lateMonday));
    }

    public function test_event_ranges_are_formatted_for_people(): void
    {
        $this->assertSame('Fri 20 Nov, 18:30 – 20:30', format_event_range('2026-11-20 18:30:00', '2026-11-20 20:30:00'));
    }

    public function test_database_dates_round_trip(): void
    {
        $this->assertSame('2026-11-20 18:30:00', to_database(from_database('2026-11-20 18:30:00')));
    }

    public function test_deadlines_are_bucketed_by_urgency(): void
    {
        $now = test_now();
        $this->assertSame('rolling', deadline_bucket(null, $now));
        $this->assertSame('closed', deadline_bucket(days_from_now(-1), $now));
        $this->assertSame('closed', deadline_bucket('2026-11-18 08:00:00', $now)); // earlier today
        $this->assertSame('today', deadline_bucket(days_from_now(0), $now));
        $this->assertSame('this-week', deadline_bucket(days_from_now(7), $now));
        $this->assertSame('next-two-weeks', deadline_bucket(days_from_now(8), $now));
        $this->assertSame('later', deadline_bucket(days_from_now(15), $now));
    }

    public function test_deadline_labels(): void
    {
        $now = test_now();
        $this->assertSame('Closes today', deadline_label(days_from_now(0), $now));
        $this->assertSame('Closes tomorrow', deadline_label(days_from_now(1), $now));
        $this->assertSame('5 days left', deadline_label(days_from_now(5), $now));
        $this->assertSame('Rolling deadline', deadline_label(null, $now));
        $this->assertSame('Closed', deadline_label(days_from_now(-3), $now));
    }

    public function test_deadline_groups_are_in_a_fixed_order_and_sorted(): void
    {
        $groups = group_by_deadline([
            ['deadline' => days_from_now(20)], ['deadline' => days_from_now(4)], ['deadline' => null], ['deadline' => days_from_now(2)],
        ], test_now());
        $this->assertSame(['this-week', 'later', 'rolling'], array_keys($groups));
        $this->assertSame([days_from_now(2), days_from_now(4)], array_column($groups['this-week'], 'deadline'));
    }

    private function event(int $id, string $start, string $end): array
    {
        return ['id' => $id, 'title' => "E$id", 'starts_at' => "2026-11-20 $start:00", 'ends_at' => "2026-11-20 $end:00"];
    }

    public function test_overlapping_events_clash(): void
    {
        $this->assertTrue(events_overlap($this->event(1, '17:00', '19:00'), $this->event(2, '18:00', '19:30')));
    }

    public function test_back_to_back_events_do_not_clash(): void
    {
        $this->assertFalse(events_overlap($this->event(1, '17:00', '18:00'), $this->event(2, '18:00', '19:00')));
    }

    public function test_every_clashing_pair_is_found(): void
    {
        $events = [$this->event(1, '09:00', '17:00'), $this->event(2, '12:00', '13:00'), $this->event(3, '12:30', '14:00'), $this->event(4, '18:00', '19:00')];
        $pairs = array_map(fn ($pair) => $pair[0]['id'] . '-' . $pair[1]['id'], find_clashes($events));
        sort($pairs);
        $this->assertSame(['1-2', '1-3', '2-3'], $pairs);
        $this->assertSame([1 => true, 2 => true, 3 => true], clashing_event_ids($events));
    }

    public function test_no_events_means_no_clashes(): void
    {
        $this->assertSame([], find_clashes([]));
    }
}
