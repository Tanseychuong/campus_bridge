<?php
/**
 * Public browsing: home page data, search, opportunities, events and organizations.
 * Anyone can call these. Signed-in students also get "is_saved" / "is_following".
 */

/** GET /api/home — deadlines closing soon, this week's events and a few totals. */
route('GET', '/api/home', function (): array {
    $now = now_on_campus();
    $profile = current_profile();

    // Open opportunities that have a deadline, soonest first.
    $openWithDeadline = array_values(array_filter(
        get_opportunities(),
        fn ($o) => $o['deadline'] !== null && !is_closed($o['deadline'], $now)
    ));
    usort($openWithDeadline, 'compare_deadlines');

    // Events that have not ended and start within 7 days.
    $upcomingEvents = array_values(array_filter(get_events(), fn ($e) => timestamp_of($e['ends_at']) > $now->getTimestamp()));
    $thisWeek = array_slice(array_values(array_filter($upcomingEvents, fn ($e) => days_until($e['starts_at'], $now) <= 7)), 0, 3);

    $present = fn (array $o) => present_opportunity($o, $now, $profile);
    return [
        'closing_soon'        => array_map($present, array_slice($openWithDeadline, 0, 3)),
        'deadlines_coming_up' => array_map($present, array_slice($openWithDeadline, 3, 3)),
        'this_week'           => array_map(fn ($e) => present_event($e, $now, $profile), $thisWeek),
        'stats'               => [
            ['label' => 'open opportunities', 'value' => count(array_filter(get_opportunities(), fn ($o) => !is_closed($o['deadline'], $now)))],
            ['label' => 'upcoming events', 'value' => count($upcomingEvents)],
            ['label' => 'student organizations', 'value' => count(get_organizations())],
        ],
    ];
});

/**
 * GET /api/explore?q=data&type=event&interest[]=research&sort=soonest
 * One search across opportunities, events and organizations (see search.php).
 */
route('GET', '/api/explore', function (): array {
    $now = now_on_campus();
    $profile = current_profile();

    $filters = read_explore_filters($_GET);
    $documents = build_search_index(get_opportunities(), get_events(), get_organizations(), $now);
    $search = run_search($documents, $filters, $now);

    $results = array_map(function (array $result) use ($now, $profile): array {
        $document = $result['document'];
        return match ($document['type']) {
            'opportunity' => present_opportunity($document['item'], $now, $profile),
            'event'       => present_event($document['item'], $now, $profile),
            default       => present_organization($document['item'], $profile),
        };
    }, $search['results']);

    return [
        'filters'              => $filters, // the cleaned-up filters, so the page shows what was really applied
        'active_filter_count'  => count_active_filters($filters),
        'counts'               => $search['counts'],
        'results'              => $results,
    ];
});

/** GET /api/opportunities/{id} — one published opportunity and similar ones. */
route('GET', '/api/opportunities/{id}', function (array $params): array {
    $opportunity = find_opportunity($params['id']);
    if ($opportunity === null) {
        abort(404, 'This opportunity does not exist or is not published.');
    }
    $now = now_on_campus();
    $profile = current_profile();
    $o = $opportunity;

    // "Similar opportunities": same category or a shared interest, still open, soonest first.
    $similar = array_filter(get_opportunities(), function ($other) use ($o, $now) {
        $isSimilar = $other['category'] === $o['category'] || array_intersect($other['interests'], $o['interests']) !== [];
        return $other['id'] !== $o['id'] && $isSimilar && !is_closed($other['deadline'], $now);
    });
    usort($similar, 'compare_deadlines');

    return [
        'opportunity' => present_opportunity($o, $now, $profile),
        'similar'     => array_map(fn ($other) => present_opportunity($other, $now, $profile), array_slice($similar, 0, 3)),
    ];
});

/** GET /api/events?category=tech — upcoming events grouped by the day they start. */
route('GET', '/api/events', function (): array {
    $now = now_on_campus();
    $profile = current_profile();

    // Only accept a category from our own list; anything else means "all".
    $category = query_text('category');
    if (!isset(EVENT_CATEGORIES[$category])) {
        $category = '';
    }

    $eventsByDay = [];
    foreach (get_events() as $event) {
        $hasEnded = timestamp_of($event['ends_at']) <= $now->getTimestamp();
        $wrongCategory = $category !== '' && $event['category'] !== $category;
        if (!$hasEnded && !$wrongCategory) {
            $eventsByDay[day_key($event['starts_at'])][] = present_event($event, $now, $profile);
        }
    }

    $days = [];
    foreach ($eventsByDay as $day => $events) {
        // "Today · Wednesday 18 November", "Tomorrow · …", or just the date.
        $date = new DateTimeImmutable($day, campus_timezone());
        $label = $date->format('l j F');
        $heading = match (calendar_days_between($now, $date)) {
            0 => "Today · $label",
            1 => "Tomorrow · $label",
            default => $label,
        };
        $days[] = ['key' => $day, 'heading' => $heading, 'events' => $events];
    }

    return ['category' => $category, 'days' => $days];
});

/** GET /api/events/{id} — one event the visitor may see, its organization and more of its events. */
route('GET', '/api/events/{id}', function (array $params): array {
    $event = find_event($params['id']);
    if ($event === null) {
        abort(404, 'This event does not exist, is not published, or is only for another campus.');
    }
    $now = now_on_campus();
    $profile = current_profile();
    $organization = find_organization((int) $event['organization_id']);

    // Other upcoming events from the same organization.
    $more = array_slice(array_values(array_filter(get_events(), fn ($other) =>
        $other['organization_id'] === $event['organization_id']
        && $other['id'] !== $event['id']
        && timestamp_of($other['ends_at']) > $now->getTimestamp()
    )), 0, 3);

    return [
        'event'        => present_event($event, $now, $profile),
        'organization' => $organization ? present_organization($organization, $profile) : null,
        'more'         => array_map(fn ($other) => present_event($other, $now, $profile), $more),
    ];
});

/** GET /api/organizations?category=tech — the directory of student organizations. */
route('GET', '/api/organizations', function (): array {
    $profile = current_profile();
    $category = query_text('category');
    if (!isset(ORGANIZATION_CATEGORIES[$category])) {
        $category = '';
    }
    $organizations = array_filter(get_organizations(), fn ($org) => $category === '' || $org['category'] === $category);

    return [
        'category'      => $category,
        'organizations' => array_values(array_map(fn ($org) => present_organization($org, $profile), $organizations)),
    ];
});

/** GET /api/organizations/{id} — one organization with its upcoming events and open opportunities. */
route('GET', '/api/organizations/{id}', function (array $params): array {
    $org = find_organization($params['id']);
    if ($org === null) {
        abort(404, 'This organization does not exist.');
    }
    $now = now_on_campus();
    $profile = current_profile();

    $events = array_filter(get_events(), fn ($e) => $e['organization_id'] === $org['id'] && timestamp_of($e['ends_at']) > $now->getTimestamp());
    $opportunities = array_filter(get_opportunities(), fn ($o) => $o['organization_id'] === $org['id'] && !is_closed($o['deadline'], $now));

    return [
        'organization'  => present_organization($org, $profile),
        'events'        => array_values(array_map(fn ($e) => present_event($e, $now, $profile), $events)),
        'opportunities' => array_values(array_map(fn ($o) => present_opportunity($o, $now, $profile), $opportunities)),
    ];
});
