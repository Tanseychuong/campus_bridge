<?php
/**
 * Numbers for the admin analytics charts.
 * They describe the content in the database (sample data), not real student usage.
 */

/**
 * Counts items per category and returns [label => count] for EVERY category,
 * including ones with zero items, sorted from largest to smallest.
 */
function count_by_category(array $items, array $categoryLabels): array
{
    $counts = [];
    foreach ($categoryLabels as $key => $label) {
        $counts[$label] = 0;
    }
    foreach ($items as $item) {
        $label = $categoryLabels[$item['category']] ?? null;
        if ($label !== null) {
            $counts[$label]++;
        }
    }
    arsort($counts); // biggest first
    return $counts;
}

/** How many opportunity deadlines fall in each of the next $weeks weeks. */
function deadlines_per_week(array $opportunities, DateTimeImmutable $now, int $weeks = 8): array
{
    $weekStart = $now->setTime(0, 0);
    $counts = [];
    for ($week = 0; $week < $weeks; $week++) {
        $counts['w/c ' . $weekStart->modify("+{$week} week")->format('j M')] = 0;
    }
    $labels = array_keys($counts);

    foreach ($opportunities as $opportunity) {
        if ($opportunity['deadline'] === null) {
            continue;
        }
        $secondsAfterStart = timestamp_of($opportunity['deadline']) - $weekStart->getTimestamp();
        $weekNumber = intdiv($secondsAfterStart, 7 * 86400);
        if ($secondsAfterStart >= 0 && $weekNumber < $weeks) {
            $counts[$labels[$weekNumber]]++;
        }
    }
    return $counts;
}
