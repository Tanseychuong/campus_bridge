<?php
/**
 * The recommendation engine.
 *
 * This is NOT artificial intelligence or machine learning. It is a list of
 * simple rules. Each rule that applies to an item gives it points and a
 * human-readable reason. Items with the most points are recommended first,
 * and the student sees exactly why ("Matches your interests: Research +3").
 */

/** How many points each rule is worth. Change a number here to tune the ranking. */
const RECOMMENDATION_POINTS = [
    'per_matching_interest'  => 3,  // for each interest the item shares with the student...
    'max_matching_interests' => 3,  // ...counting at most this many
    'followed_organization'  => 2,  // posted by an organization the student follows
    'similar_to_saved'       => 1,  // same category as something the student already saved
    'deadline_soon'          => 2,  // deadline 3–14 days away: the best time to apply
    'deadline_near'          => 1,  // deadline 0–2 days or 15–30 days away
    'event_this_week'        => 2,  // event starts within 7 days
    'event_next_week'        => 1,  // event starts in 8–14 days
    'missing_information'    => -1, // penalty: no eligibility information
];

/**
 * Scores ONE item (an opportunity or an event) for a student.
 *
 * $type    'opportunity' or 'event'
 * $item    the item (array from the database, with an 'interests' list)
 * $profile ['interests' => [...], 'followed' => [orgIds], 'saved_categories' => ['opportunity:internship' => 2, ...]]
 *
 * Returns ['score' => 7, 'reasons' => [['text' => '...', 'points' => 3, 'kind' => 'interest'], ...]]
 */
function score_item(string $type, array $item, array $profile, DateTimeImmutable $now): array
{
    $points = RECOMMENDATION_POINTS;
    $reasons = [];

    // Rule 1: shared interests
    $sharedInterests = array_values(array_intersect($item['interests'], $profile['interests']));
    if ($sharedInterests !== []) {
        $counted = array_slice($sharedInterests, 0, $points['max_matching_interests']);
        $reasons[] = [
            'kind'   => 'interest',
            'text'   => 'Matches your interests: ' . interest_labels($counted),
            'points' => count($counted) * $points['per_matching_interest'],
        ];
    }

    // Rule 2: posted by an organization the student follows
    $organizationId = $item['organization_id'] ?? null;
    if ($organizationId !== null && in_array((int) $organizationId, $profile['followed'], true)) {
        $reasons[] = [
            'kind'   => 'followed',
            'text'   => 'From ' . ($item['organization_name'] ?? 'an organization') . ', which you follow',
            'points' => $points['followed_organization'],
        ];
    }

    // Rule 3: similar to things the student already saved
    $savedInSameCategory = $profile['saved_categories'][$type . ':' . $item['category']] ?? 0;
    if ($savedInSameCategory > 0) {
        $reasons[] = [
            'kind'   => 'similar',
            'text'   => 'Similar to ' . plural($savedInSameCategory, 'item') . ' you saved',
            'points' => $points['similar_to_saved'],
        ];
    }

    // Rule 4: timing
    if ($type === 'opportunity' && $item['deadline'] !== null) {
        $days = days_until($item['deadline'], $now);
        if ($days >= 3 && $days <= 14) {
            $reasons[] = ['kind' => 'timing', 'text' => "Deadline in $days days — a good time to apply", 'points' => $points['deadline_soon']];
        } elseif ($days >= 0 && $days <= 2) {
            $when = [0 => 'today', 1 => 'tomorrow', 2 => 'in 2 days'][$days];
            $reasons[] = ['kind' => 'timing', 'text' => "Closes $when — act fast", 'points' => $points['deadline_near']];
        } elseif ($days > 14 && $days <= 30) {
            $reasons[] = ['kind' => 'timing', 'text' => "Deadline in $days days", 'points' => $points['deadline_near']];
        }
    }
    if ($type === 'event') {
        $days = days_until($item['starts_at'], $now);
        if ($days >= 0 && $days <= 7) {
            $when = $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in $days days");
            $reasons[] = ['kind' => 'timing', 'text' => "Happening $when", 'points' => $points['event_this_week']];
        } elseif ($days > 7 && $days <= 14) {
            $reasons[] = ['kind' => 'timing', 'text' => "Happening in $days days", 'points' => $points['event_next_week']];
        }
    }

    // Rule 5: penalty for missing information
    if ($type === 'opportunity' && trim($item['eligibility']) === '') {
        $reasons[] = ['kind' => 'missing-info', 'text' => 'Eligibility details are missing', 'points' => $points['missing_information']];
    }

    $score = array_sum(array_column($reasons, 'points'));
    return ['score' => $score, 'reasons' => $reasons];
}

/**
 * Picks the best items for a student.
 *
 * $profile needs: interests, followed (org ids), saved_opportunity_ids, saved_event_ids
 *
 * Steps:
 *   1. Keep only items that are published, still open/upcoming, and not saved yet.
 *   2. Score every remaining item.
 *   3. Keep items that match an interest or come from a followed organization
 *      (otherwise an urgent but irrelevant item could appear).
 *   4. Sort: highest score first; ties go to the sooner date, then A–Z by title.
 */
function recommend(array $opportunities, array $events, array $profile, DateTimeImmutable $now, int $limit = 6): array
{
    // Count how many saved items the student has in each category (used by rule 3).
    $profile['saved_categories'] = [];
    foreach ($opportunities as $opportunity) {
        if (in_array((int) $opportunity['id'], $profile['saved_opportunity_ids'], true)) {
            $key = 'opportunity:' . $opportunity['category'];
            $profile['saved_categories'][$key] = ($profile['saved_categories'][$key] ?? 0) + 1;
        }
    }
    foreach ($events as $event) {
        if (in_array((int) $event['id'], $profile['saved_event_ids'], true)) {
            $key = 'event:' . $event['category'];
            $profile['saved_categories'][$key] = ($profile['saved_categories'][$key] ?? 0) + 1;
        }
    }

    // Step 1: candidates
    $candidates = [];
    foreach ($opportunities as $opportunity) {
        $isAvailable = $opportunity['status'] === 'published'
            && !is_closed($opportunity['deadline'], $now)
            && !in_array((int) $opportunity['id'], $profile['saved_opportunity_ids'], true);
        if ($isAvailable) {
            $candidates[] = ['type' => 'opportunity', 'item' => $opportunity, 'date' => $opportunity['deadline'] ?? '9999-12-31'];
        }
    }
    foreach ($events as $event) {
        $isAvailable = $event['status'] === 'published'
            && timestamp_of($event['ends_at']) > $now->getTimestamp()
            && !in_array((int) $event['id'], $profile['saved_event_ids'], true);
        if ($isAvailable) {
            $candidates[] = ['type' => 'event', 'item' => $event, 'date' => $event['starts_at']];
        }
    }

    // Steps 2 and 3: score and keep relevant items
    $recommendations = [];
    foreach ($candidates as $candidate) {
        $result = score_item($candidate['type'], $candidate['item'], $profile, $now);
        $reasonKinds = array_column($result['reasons'], 'kind');
        $isRelevant = in_array('interest', $reasonKinds, true) || in_array('followed', $reasonKinds, true);

        if ($result['score'] > 0 && $isRelevant) {
            $recommendations[] = $candidate + $result;
        }
    }

    // Step 4: sort (the "<=>" spaceship operator returns -1, 0 or 1)
    usort($recommendations, function ($a, $b) {
        return [$b['score'], $a['date'], $a['item']['title']] <=> [$a['score'], $b['date'], $b['item']['title']];
    });

    return array_slice($recommendations, 0, $limit);
}
