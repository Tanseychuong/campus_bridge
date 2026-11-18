<?php
/**
 * A signed-in student's own data: saving and following, dashboard,
 * saved items, calendar (with clash detection and .ics export) and preferences.
 */

/**
 * POST /api/toggle   {"kind": "opportunity" | "event" | "organization", "id": 5}
 * The Save / I'm interested / Follow buttons. Pressing again undoes it.
 */
route('POST', '/api/toggle', function (): array {
    $user = current_user();
    if ($user === null) {
        abort(401, 'Please sign in to save items and follow organizations.');
    }

    $kind = input_text('kind');
    $id = filter_var(input_text('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    // Which table each kind of button changes, and how to check the item exists.
    $targets = [
        'opportunity'  => ['saved_opportunities', 'opportunity_id', 'find_opportunity', 'Saved', 'Removed from saved items'],
        'event'        => ['saved_events', 'event_id', 'find_event', 'Added to your calendar', 'Removed from your calendar'],
        'organization' => ['follows', 'organization_id', 'find_organization', 'Following', 'Unfollowed'],
    ];
    if (!isset($targets[$kind]) || $id === false) {
        abort(400, 'Unknown item.');
    }

    [$table, $column, $findFunction, $addedMessage, $removedMessage] = $targets[$kind];
    // find_event() also hides campus-only events of other campuses, so they cannot be saved either.
    $item = $findFunction($id);
    if ($item === null) {
        abort(404, 'That item no longer exists.');
    }

    $isOn = toggle_link($table, $column, (int) $user['id'], $id);
    return [
        'kind'    => $kind,
        'id'      => $id,
        'is_on'   => $isOn,
        'message' => ($isOn ? $addedMessage : $removedMessage) . ': ' . ($item['title'] ?? $item['name']),
    ];
});

/** GET /api/dashboard — recommendations, deadlines, calendar events with clashes, followed organizations. */
route('GET', '/api/dashboard', function (): array {
    $user = require_login();
    $now = now_on_campus();
    $profile = get_student_profile((int) $user['id']);

    $opportunities = get_opportunities();
    $events = get_events();

    $recommendations = recommend($opportunities, $events, $profile, $now, 6);

    // Saved opportunities that are still open, soonest deadline first.
    $savedOpportunities = array_filter($opportunities, fn ($o) => in_array((int) $o['id'], $profile['saved_opportunity_ids'], true));
    $upcomingDeadlines = array_values(array_filter($savedOpportunities, fn ($o) => $o['deadline'] !== null && !is_closed($o['deadline'], $now)));
    usort($upcomingDeadlines, 'compare_deadlines');
    $dueThisWeek = array_filter($upcomingDeadlines, fn ($o) => days_until($o['deadline'], $now) <= 7);

    // Saved events that have not ended yet.
    $myEvents = array_values(array_filter($events, fn ($e) =>
        in_array((int) $e['id'], $profile['saved_event_ids'], true) && timestamp_of($e['ends_at']) > $now->getTimestamp()
    ));
    $clashes = find_clashes($myEvents);
    $clashingIds = clashing_event_ids($myEvents);

    $followed = array_filter(get_organizations(), fn ($org) => in_array((int) $org['id'], $profile['followed'], true));

    return [
        'has_interests'      => $profile['interests'] !== [],
        'summary'            => [
            ['label' => 'Saved opportunities', 'value' => count($savedOpportunities), 'link' => '/saved', 'is_alert' => false],
            ['label' => 'Deadlines in 7 days', 'value' => count($dueThisWeek), 'link' => '/saved', 'is_alert' => count($dueThisWeek) > 0],
            ['label' => 'Events in calendar', 'value' => count($myEvents), 'link' => '/calendar', 'is_alert' => false],
            ['label' => 'Schedule clashes', 'value' => count($clashes), 'link' => '/calendar', 'is_alert' => count($clashes) > 0],
        ],
        'recommendations'    => array_map(fn ($r) => present_recommendation($r, $now, $profile), $recommendations),
        'upcoming_deadlines' => array_map(fn ($o) => present_opportunity($o, $now, $profile), array_slice($upcomingDeadlines, 0, 5)),
        'my_events'          => array_map(
            fn ($e) => present_event($e, $now, $profile) + ['clashes' => isset($clashingIds[$e['id']])],
            array_slice($myEvents, 0, 5)
        ),
        'followed'           => array_values(array_map(fn ($org) => present_organization($org, $profile), $followed)),
    ];
});

/** GET /api/saved — saved opportunities grouped by deadline urgency, and saved events. */
route('GET', '/api/saved', function (): array {
    $user = require_login();
    $now = now_on_campus();
    $profile = get_student_profile((int) $user['id']);

    $savedOpportunities = array_filter(get_opportunities(), fn ($o) => in_array((int) $o['id'], $profile['saved_opportunity_ids'], true));
    $groups = [];
    foreach (group_by_deadline($savedOpportunities, $now) as $bucket => $opportunities) {
        $groups[] = [
            'bucket'        => $bucket,
            'label'         => DEADLINE_BUCKET_LABELS[$bucket],
            'is_urgent'     => in_array($bucket, ['today', 'this-week'], true),
            'opportunities' => array_map(fn ($o) => present_opportunity($o, $now, $profile), $opportunities),
        ];
    }
    $savedEvents = array_filter(get_events(), fn ($e) => in_array((int) $e['id'], $profile['saved_event_ids'], true));

    return [
        'deadline_groups' => $groups,
        'events'          => array_values(array_map(fn ($e) => present_event($e, $now, $profile), $savedEvents)),
    ];
});

/**
 * GET /api/calendar?month=2026-12
 * A month grid of saved events and deadlines, plus an agenda list and clash warnings.
 */
route('GET', '/api/calendar', function (): array {
    $user = require_login();
    $now = now_on_campus();
    $profile = get_student_profile((int) $user['id']);

    // Which month to show. Anything that is not "yyyy-mm" falls back to this month.
    $monthParameter = query_text('month');
    $firstOfMonth = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParameter)
        ? new DateTimeImmutable($monthParameter . '-01', campus_timezone())
        : $now->modify('first day of this month')->setTime(0, 0);

    $savedEvents = array_values(array_filter(get_events(), fn ($e) => in_array((int) $e['id'], $profile['saved_event_ids'], true)));
    $savedOpportunities = array_filter(get_opportunities(), fn ($o) => in_array((int) $o['id'], $profile['saved_opportunity_ids'], true) && $o['deadline'] !== null);

    $upcomingEvents = array_filter($savedEvents, fn ($e) => timestamp_of($e['ends_at']) > $now->getTimestamp());
    $clashes = find_clashes($upcomingEvents);
    $clashingIds = clashing_event_ids($savedEvents);

    // Put every entry on its day: ['2026-11-20' => [entry, entry], ...]
    $entriesByDay = [];
    foreach ($savedEvents as $event) {
        $entriesByDay[day_key($event['starts_at'])][] = [
            'kind' => 'event', 'title' => $event['title'], 'time' => $event['starts_at'],
            'link' => '/events/' . $event['id'],
            'detail' => format_event_range($event['starts_at'], $event['ends_at']),
            'clash' => isset($clashingIds[$event['id']]),
        ];
    }
    foreach ($savedOpportunities as $o) {
        $entriesByDay[day_key($o['deadline'])][] = [
            'kind' => 'deadline', 'title' => 'Deadline: ' . $o['title'], 'time' => $o['deadline'],
            'link' => '/opportunities/' . $o['id'],
            'detail' => 'Closes at ' . format_time($o['deadline']),
            'clash' => false,
        ];
    }
    foreach ($entriesByDay as $day => $entries) {
        usort($entries, fn ($a, $b) => strcmp($a['time'], $b['time']));
        $entriesByDay[$day] = array_map(function ($entry) {
            unset($entry['time']); // only needed for sorting
            return $entry;
        }, $entries);
    }

    $monthKey = $firstOfMonth->format('Y-m');
    $today = $now->format('Y-m-d');

    // The grid: weeks start on Monday, and we show whole weeks.
    $gridStart = $firstOfMonth->modify('monday this week');
    $gridEnd = $firstOfMonth->modify('last day of this month')->modify('sunday this week');
    $weeks = [];
    $currentWeek = [];
    for ($day = $gridStart; $day <= $gridEnd; $day = $day->modify('+1 day')) {
        $key = $day->format('Y-m-d');
        $currentWeek[] = [
            'key'        => $key,
            'number'     => (int) $day->format('j'),
            'is_outside' => $day->format('Y-m') !== $monthKey,
            'is_today'   => $key === $today,
            'entries'    => $entriesByDay[$key] ?? [],
        ];
        if (count($currentWeek) === 7) {   // a full week: start a new row
            $weeks[] = $currentWeek;
            $currentWeek = [];
        }
    }

    // The agenda: only the days of this month that have something on them.
    $monthDays = array_filter(array_keys($entriesByDay), fn ($day) => str_starts_with($day, $monthKey));
    sort($monthDays);
    $agenda = array_map(fn ($day) => [
        'key'      => $day,
        'heading'  => (new DateTimeImmutable($day))->format('l j F') . ($day === $today ? ' (today)' : ''),
        'entries'  => $entriesByDay[$day],
    ], $monthDays);

    $describe = fn ($event) => [
        'title' => $event['title'],
        'time'  => format_time($event['starts_at']) . '–' . format_time($event['ends_at']),
    ];

    return [
        'month'        => $monthKey,
        'title'        => $firstOfMonth->format('F Y'),
        'previous'     => $firstOfMonth->modify('-1 month')->format('Y-m'),
        'next'         => $firstOfMonth->modify('+1 month')->format('Y-m'),
        'has_anything' => $savedEvents !== [] || $savedOpportunities !== [],
        'clashes'      => array_map(fn ($pair) => [
            'first'  => $describe($pair[0]),
            'second' => $describe($pair[1]),
            'day'    => format_date($pair[0]['starts_at'], 'D j M'),
        ], $clashes),
        'weeks'        => $weeks,
        'agenda'       => array_values($agenda),
    ];
});

/**
 * GET /api/calendar.ics
 * Downloads saved events and deadlines as a file that Outlook, Google
 * Calendar and Apple Calendar can import.
 */
route('GET', '/api/calendar.ics', function (): ?array {
    $user = require_login();
    $now = now_on_campus();
    $profile = get_student_profile((int) $user['id']);

    $events = array_filter(get_events(), fn ($e) => in_array((int) $e['id'], $profile['saved_event_ids'], true));
    $deadlines = array_filter(get_opportunities(), fn ($o) => in_array((int) $o['id'], $profile['saved_opportunity_ids'], true) && $o['deadline'] !== null);

    // These headers tell the browser "this is a calendar file, download it".
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="campusbridge.ics"');
    header('Cache-Control: private, no-store');
    echo build_ics_calendar($events, $deadlines, $now);
    return null; // we sent the file ourselves, not JSON
});

/** GET /api/preferences — chosen interests, followed organizations and a preview of the top 3 recommendations. */
route('GET', '/api/preferences', function (): array {
    $user = require_login();
    $now = now_on_campus();
    $profile = get_student_profile((int) $user['id']);
    $preview = recommend(get_opportunities(), get_events(), $profile, $now, 3);
    $followed = array_filter(get_organizations(), fn ($org) => in_array((int) $org['id'], $profile['followed'], true));

    return [
        'interests' => $profile['interests'],
        'followed'  => array_values(array_map(fn ($org) => present_organization($org, $profile), $followed)),
        'preview'   => array_map(fn ($r) => present_recommendation($r, $now, $profile), $preview),
    ];
});

/** PUT /api/preferences/interests   {"interests": ["research", "arts"]} */
route('PUT', '/api/preferences/interests', function (): array {
    $user = require_login();
    // Keep only interests that exist in our list.
    $chosen = array_values(array_unique(array_filter(input_list('interests'), fn ($i) => isset(INTEREST_LABELS[$i]))));
    set_user_interests((int) $user['id'], $chosen);
    return [
        'interests' => $chosen,
        'message'   => $chosen === [] ? 'Interests cleared.' : 'Interests saved. Your recommendations are updated.',
    ];
});

/** POST /api/preferences/reset — deletes the student's interests, saved items and follows (the account stays). */
route('POST', '/api/preferences/reset', function (): array {
    $user = require_login();
    reset_student_data((int) $user['id']);
    return ['message' => 'Your interests, saved items and follows were deleted.'];
});
