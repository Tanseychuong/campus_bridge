<?php
/**
 * Test data builders. Each test changes only the fields it cares about:
 *     make_opportunity(['category' => 'scholarship'])
 */

/** A fixed "now" so tests give the same result every day: Wed 18 Nov 2026, 10:00 Accra time. */
function test_now(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-11-18 10:00:00', new DateTimeZone('Africa/Accra'));
}

/** A database-style date N days after test_now(), at the given time. */
function days_from_now(int $days, string $time = '23:59'): string
{
    return test_now()->modify("$days days")->format('Y-m-d') . ' ' . $time . ':00';
}

function make_opportunity(array $overrides = []): array
{
    return array_merge([
        'id' => 1, 'title' => 'Test Opportunity', 'provider' => 'Test Provider', 'organization_id' => null,
        'organization_name' => null, 'category' => 'internship', 'summary' => 'A short summary.',
        'description' => 'A longer description used in tests.', 'eligibility' => 'Any student',
        'location_mode' => 'remote', 'location_place' => null, 'deadline' => days_from_now(10),
        'published_at' => days_from_now(-5, '09:00'), 'source_name' => 'Test', 'source_url' => 'https://example.org/test',
        'status' => 'published', 'review_note' => null, 'interests' => ['computer-science'],
    ], $overrides);
}

function make_event(array $overrides = []): array
{
    return array_merge([
        'id' => 1, 'title' => 'Test Event', 'organization_id' => 1, 'organization_name' => 'Org A Society',
        'category' => 'tech', 'summary' => 'A short summary.', 'description' => 'A longer description used in tests.',
        'venue' => 'Room 1', 'starts_at' => days_from_now(3, '18:00'), 'ends_at' => days_from_now(3, '19:30'),
        'capacity' => 30, 'registration' => 'open', 'contact' => 'test@example.org', 'status' => 'published',
        'review_note' => null, 'interests' => ['computer-science'],
    ], $overrides);
}

function make_organization(array $overrides = []): array
{
    return array_merge([
        'id' => 1, 'name' => 'Org A Society', 'short_name' => 'OA', 'category' => 'tech',
        'summary' => 'A test organization.', 'description' => 'A longer description.', 'activities' => "Workshops\nTalks",
        'contact_email' => 'orga@example.org', 'meeting_info' => 'Mondays', 'membership' => 'Open to all',
        'founded_year' => 2020, 'member_count' => 10, 'color' => '#123456', 'interests' => ['computer-science'],
    ], $overrides);
}

/** A student profile with nothing chosen yet. */
function make_profile(array $overrides = []): array
{
    return array_merge(empty_profile(), $overrides);
}
