<?php
/**
 * Deadline tracking: how urgent is each opportunity?
 *
 * Every deadline goes into one "bucket":
 *   today           - closes today
 *   this-week       - closes in 1 to 7 days
 *   next-two-weeks  - closes in 8 to 14 days
 *   later           - closes in 15 or more days
 *   rolling         - no fixed deadline (deadline is NULL)
 *   closed          - the deadline has already passed
 */

const DEADLINE_BUCKET_LABELS = [
    'today'          => 'Due today',
    'this-week'      => 'Due in the next 7 days',
    'next-two-weeks' => 'Due in 8–14 days',
    'later'          => 'Due later',
    'rolling'        => 'Rolling / no fixed deadline',
    'closed'         => 'Closed',
];

/** True when the deadline has already passed. A NULL (rolling) deadline never closes. */
function is_closed(?string $deadline, DateTimeImmutable $now): bool
{
    return $deadline !== null && timestamp_of($deadline) < $now->getTimestamp();
}

function deadline_bucket(?string $deadline, DateTimeImmutable $now): string
{
    if ($deadline === null) {
        return 'rolling';
    }
    if (is_closed($deadline, $now)) {
        return 'closed';
    }

    $days = days_until($deadline, $now);
    if ($days <= 0) {
        return 'today';
    }
    if ($days <= 7) {
        return 'this-week';
    }
    if ($days <= 14) {
        return 'next-two-weeks';
    }
    return 'later';
}

/** Short text for a badge: "Closes today", "5 days left", "Closed"… */
function deadline_label(?string $deadline, DateTimeImmutable $now): string
{
    if ($deadline === null) {
        return 'Rolling deadline';
    }
    if (is_closed($deadline, $now)) {
        return 'Closed';
    }

    $days = days_until($deadline, $now);
    if ($days <= 0) {
        return 'Closes today';
    }
    if ($days === 1) {
        return 'Closes tomorrow';
    }
    return $days . ' days left';
}

/** Sorting rule: soonest deadline first, rolling (NULL) deadlines last. */
function compare_deadlines(array $a, array $b): int
{
    if ($a['deadline'] === $b['deadline']) {
        return 0;
    }
    if ($a['deadline'] === null) {
        return 1;
    }
    if ($b['deadline'] === null) {
        return -1;
    }
    return strcmp($a['deadline'], $b['deadline']); // "Y-m-d H:i:s" text sorts like dates
}

/**
 * Groups opportunities by bucket, in a fixed order, each group soonest first.
 * Returns: ['this-week' => [opportunity, ...], 'later' => [...], ...]
 */
function group_by_deadline(array $opportunities, DateTimeImmutable $now): array
{
    $groups = [];
    foreach (array_keys(DEADLINE_BUCKET_LABELS) as $bucket) {
        $groups[$bucket] = [];
    }

    foreach ($opportunities as $opportunity) {
        $groups[deadline_bucket($opportunity['deadline'], $now)][] = $opportunity;
    }

    foreach ($groups as $bucket => $items) {
        usort($items, 'compare_deadlines');
        $groups[$bucket] = $items;
    }

    // Leave out empty groups.
    return array_filter($groups, fn ($items) => $items !== []);
}
