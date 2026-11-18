<?php
/**
 * Calendar export (.ics files).
 *
 * The iCalendar format (standard "RFC 5545") is plain text that Outlook,
 * Google Calendar and Apple Calendar can all import. A file looks like:
 *
 *   BEGIN:VCALENDAR
 *   BEGIN:VEVENT
 *   SUMMARY:Intro to Web Development Workshop
 *   DTSTART:20261120T183000Z
 *   ...
 *   END:VEVENT
 *   END:VCALENDAR
 */

/** Some characters have special meaning in .ics text, so they get a backslash. */
function ics_escape(string $text): string
{
    return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $text);
}

/** "2026-11-20 18:30:00" (UTC) -> "20261120T183000Z" */
function ics_utc_time(string $utcDateTime): string
{
    return (new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC')))->format('Ymd\THis\Z');
}

/**
 * Lines in an .ics file should be at most 75 bytes long. Longer lines are
 * "folded": split, with each extra piece starting on a new line with a space.
 */
function ics_fold_line(string $line): string
{
    if (strlen($line) <= 75) {
        return $line;
    }
    $pieces = [];
    $current = '';
    $limit = 75;
    // mb_str_split keeps multi-byte characters (like é) in one piece.
    foreach (mb_str_split($line) as $character) {
        if (strlen($current . $character) > $limit) {
            $pieces[] = $current;
            $current = $character;
            $limit = 74; // the leading space on continuation lines uses one byte
        } else {
            $current .= $character;
        }
    }
    $pieces[] = $current;
    return implode("\r\n ", $pieces);
}

/**
 * Builds the whole .ics file.
 * $events    list of events (title, starts_at, ends_at, venue, summary, id)
 * $deadlines list of opportunities with a deadline (title, deadline, id) — added as all-day entries
 */
function build_ics_calendar(array $events, array $deadlines, DateTimeImmutable $now): string
{
    $stamp = ics_utc_time(to_database($now));
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//CampusBridge//Student Planner//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
    ];

    foreach ($events as $event) {
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:event-' . $event['id'] . '@campusbridge.local';
        $lines[] = 'DTSTAMP:' . $stamp;
        $lines[] = 'DTSTART:' . ics_utc_time($event['starts_at']);
        $lines[] = 'DTEND:' . ics_utc_time($event['ends_at']);
        $lines[] = 'SUMMARY:' . ics_escape($event['title']);
        $lines[] = 'LOCATION:' . ics_escape($event['venue']);
        $lines[] = 'DESCRIPTION:' . ics_escape($event['summary']);
        $lines[] = 'END:VEVENT';
    }

    foreach ($deadlines as $opportunity) {
        $day = from_database($opportunity['deadline']);
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:deadline-' . $opportunity['id'] . '@campusbridge.local';
        $lines[] = 'DTSTAMP:' . $stamp;
        $lines[] = 'DTSTART;VALUE=DATE:' . $day->format('Ymd');                    // all-day entry
        $lines[] = 'DTEND;VALUE=DATE:' . $day->modify('+1 day')->format('Ymd');
        $lines[] = 'SUMMARY:' . ics_escape('Deadline: ' . $opportunity['title']);
        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", array_map('ics_fold_line', $lines)) . "\r\n";
}
