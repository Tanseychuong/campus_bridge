<?php
/**
 * Clash detection: do any of a student's saved events overlap in time?
 *
 * Two events overlap when:
 *     A starts before B ends   AND   B starts before A ends
 *
 * Back-to-back events (one ends at 18:00, the next starts at 18:00) do NOT clash.
 */

function events_overlap(array $a, array $b): bool
{
    return timestamp_of($a['starts_at']) < timestamp_of($b['ends_at'])
        && timestamp_of($b['starts_at']) < timestamp_of($a['ends_at']);
}

/**
 * Finds every pair of clashing events.
 *
 * Method ("sweep line"):
 *   1. Sort events by start time.
 *   2. Walk through them, keeping a list of events that are still running.
 *   3. Before adding an event, drop running events that already ended.
 *   4. The new event clashes with every event still running.
 *
 * This is faster than comparing every event with every other event when a
 * student has many events, and it is easy to follow.
 *
 * Returns a list of pairs: [[eventA, eventB], ...]
 */
function find_clashes(array $events): array
{
    usort($events, fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']));

    $clashes = [];
    $stillRunning = [];

    foreach ($events as $event) {
        $eventStart = timestamp_of($event['starts_at']);

        // Keep only the events that end after this one starts.
        $stillRunning = array_filter($stillRunning, fn ($running) => timestamp_of($running['ends_at']) > $eventStart);

        foreach ($stillRunning as $running) {
            $clashes[] = [$running, $event];
        }
        $stillRunning[] = $event;
    }

    return $clashes;
}

/** The ids of every event involved in at least one clash, e.g. [4 => true, 9 => true]. */
function clashing_event_ids(array $events): array
{
    $ids = [];
    foreach (find_clashes($events) as [$first, $second]) {
        $ids[$first['id']] = true;
        $ids[$second['id']] = true;
    }
    return $ids;
}
