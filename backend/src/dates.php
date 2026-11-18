<?php
/**
 * Date and time helpers.
 *
 * Rule of the project:
 *   - The database stores every date in UTC, as text like "2026-11-30 23:59:00".
 *   - We DISPLAY and COMPARE dates in the campus time zone (Africa/Accra).
 *
 * Ghana is UTC+0 all year, so the numbers look the same — but being explicit
 * means the app still behaves correctly on a server in another country.
 */

function campus_timezone(): DateTimeZone
{
    return new DateTimeZone(app_config()['timezone']);
}

/** The current moment, in campus time. */
function now_on_campus(): DateTimeImmutable
{
    return new DateTimeImmutable('now', campus_timezone());
}

/** Converts a UTC date from the database into a campus-time date object. */
function from_database(string $utcDateTime): DateTimeImmutable
{
    $utc = new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC'));
    return $utc->setTimezone(campus_timezone());
}

/** Converts a date object into UTC text for saving in the database. */
function to_database(DateTimeImmutable $moment): string
{
    return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

/** Seconds since 1970 — handy for comparing two dates. */
function timestamp_of(string $utcDateTime): int
{
    return from_database($utcDateTime)->getTimestamp();
}

/**
 * Number of CALENDAR days from one moment to another (campus time).
 * Monday 23:00 -> Tuesday 01:00 is 1 day, even though only 2 hours passed.
 */
function calendar_days_between(DateTimeImmutable $from, DateTimeImmutable $to): int
{
    $fromMidnight = $from->setTimezone(campus_timezone())->setTime(0, 0);
    $toMidnight = $to->setTimezone(campus_timezone())->setTime(0, 0);
    return (int) $fromMidnight->diff($toMidnight)->format('%r%a'); // %r adds a minus sign when negative
}

/** Calendar days from now until a database date (negative if it has passed). */
function days_until(string $utcDateTime, DateTimeImmutable $now): int
{
    return calendar_days_between($now, from_database($utcDateTime));
}

/** "Mon 30 Nov 2026" */
function format_date(string $utcDateTime, string $pattern = 'D j M Y'): string
{
    return from_database($utcDateTime)->format($pattern);
}

/** "18:30" */
function format_time(string $utcDateTime): string
{
    return from_database($utcDateTime)->format('H:i');
}

/** "Fri 20 Nov, 18:30 – 20:30", or two full dates if the event crosses midnight. */
function format_event_range(string $startsAt, string $endsAt): string
{
    $sameDay = format_date($startsAt, 'Y-m-d') === format_date($endsAt, 'Y-m-d');
    if ($sameDay) {
        return format_date($startsAt, 'D j M') . ', ' . format_time($startsAt) . ' – ' . format_time($endsAt);
    }
    return format_date($startsAt, 'D j M, H:i') . ' – ' . format_date($endsAt, 'D j M, H:i');
}

/** The "yyyy-mm-dd" day an event or deadline falls on (campus time). */
function day_key(string $utcDateTime): string
{
    return format_date($utcDateTime, 'Y-m-d');
}
