<?php
/**
 * Organization rep area: create, edit and delete YOUR organization's events,
 * and review the resources students send to your organization.
 *
 * Security: every query includes the rep's own organization id, so changing
 * an id in a request can never reach another organization's events or files.
 */

/** The signed-in rep and their organization (403 for everyone else). */
function rep_and_organization(): array
{
    $user = require_role('org_rep');
    $organization = $user['organization_id'] ? find_organization((int) $user['organization_id']) : null;
    if ($organization === null) {
        abort(403, 'Your account is not linked to an organization yet.');
    }
    return [$user, $organization];
}

/** The scan result of a resource, ready for the rep's review list. */
function present_scan(array $resource): array
{
    $flags = resource_scan_flags($resource);
    $warning = resource_breach_warning($resource); // "Potential rule breach" label, or null
    if ($flags === null) {
        return ['state' => 'pending', 'flags' => [], 'critical_count' => 0, 'breach_warning' => $warning];
    }
    return [
        'breach_warning' => $warning,
        'state'          => $resource['scan_status'] === 'failed' ? 'failed' : ($flags === [] ? 'clean' : 'flagged'),
        'flags'          => array_map(fn ($flag) => [
            'severity'   => $flag['severity'],
            'kind_label' => SCAN_KIND_LABELS[$flag['kind']] ?? 'Check',
            'message'    => $flag['message'],
        ], $flags),
        'critical_count' => count(array_filter($flags, fn ($f) => $f['severity'] === 'critical')),
    ];
}

/** GET /api/manage — the rep's organization, its events (any status) and resources to review. */
route('GET', '/api/manage', function (): array {
    [, $organization] = rep_and_organization();
    $now = now_on_campus();
    $statusBadges = ['pending' => ['Pending review', 'warning'], 'published' => ['Published', 'success'], 'rejected' => ['Rejected', 'danger']];

    $events = array_map(function ($event) use ($now, $statusBadges) {
        [$label, $tone] = $statusBadges[$event['status']];
        return present_event($event, $now, empty_profile()) + [
            'status' => $event['status'], 'status_label' => $label, 'status_tone' => $tone, 'review_note' => $event['review_note'],
        ];
    }, get_events_for_organization((int) $organization['id']));

    $resources = array_map(fn ($r) => present_resource($r) + [
        'scan'        => present_scan($r),
        'declaration' => [
            'ownership'     => IP_OWNERSHIP[$r['ip_ownership']],
            'credits'       => $r['ip_credits'],
            'license'       => RESOURCE_LICENSES[$r['license']],
            'declared_name' => $r['declared_name'],
            'declared_at'   => format_date($r['declared_at'], 'j M Y, H:i'),
        ],
    ], get_resources_for_organization((int) $organization['id']));

    return [
        'organization'  => present_organization($organization, empty_profile()),
        'events'        => $events,
        'resources'     => $resources,
        'pending_count' => count(array_filter($resources, fn ($r) => $r['status'] === 'pending')),
    ];
});

/** The event form values as sent by the React form. */
function event_form_input(): array
{
    $values = [];
    foreach (['title', 'category', 'summary', 'description', 'venue', 'date', 'start_time', 'end_time', 'capacity', 'registration', 'visibility'] as $field) {
        $values[$field] = input_text($field);
    }
    $values['interests'] = input_list('interests');
    return $values;
}

/** GET /api/manage/events/new — starting values for a new event. */
route('GET', '/api/manage/events/new', function (): array {
    [, $organization] = rep_and_organization();
    $now = now_on_campus();
    return [
        'organization' => present_organization($organization, empty_profile()),
        'values'       => [
            'title' => '', 'category' => 'tech', 'summary' => '', 'description' => '', 'venue' => '',
            'date' => $now->modify('+7 days')->format('Y-m-d'), 'start_time' => '18:00', 'end_time' => '19:30',
            'capacity' => '', 'registration' => 'open', 'visibility' => 'public', 'interests' => [],
        ],
    ];
});

/** GET /api/manage/events/{id} — the current values of one of the rep's own events. */
route('GET', '/api/manage/events/{id}', function (array $params): array {
    [, $organization] = rep_and_organization();
    // Only finds the event if both the event id AND the organization id match.
    $event = find_event_for_organization($params['id'], (int) $organization['id']);
    if ($event === null) {
        abort(404, 'Event not found.');
    }
    return [
        'organization' => present_organization($organization, empty_profile()),
        'values'       => [
            'title' => $event['title'], 'category' => $event['category'], 'summary' => $event['summary'],
            'description' => $event['description'], 'venue' => $event['venue'],
            'date' => format_date($event['starts_at'], 'Y-m-d'),
            'start_time' => format_time($event['starts_at']), 'end_time' => format_time($event['ends_at']),
            'capacity' => $event['capacity'] === null ? '' : (string) $event['capacity'],
            'registration' => $event['registration'], 'visibility' => $event['visibility'],
            'interests' => $event['interests'],
        ],
    ];
});

/** POST /api/manage/events — create an event; it waits for an admin's approval. */
route('POST', '/api/manage/events', function (): array {
    [$user, $organization] = rep_and_organization();
    $values = event_form_input();
    $errors = validate_event_form($values, now_on_campus());
    if ($errors !== []) {
        abort_with_form_errors($errors);
    }
    [$startsAt, $endsAt] = event_form_times($values);
    $id = create_event($values, $startsAt, $endsAt, $organization, (int) $user['id']);
    http_response_code(201);
    return ['id' => $id, 'message' => '“' . $values['title'] . '” submitted for admin review.'];
});

/** PUT /api/manage/events/{id} — edit an event; it goes back to "pending" for another review. */
route('PUT', '/api/manage/events/{id}', function (array $params): array {
    [, $organization] = rep_and_organization();
    $event = find_event_for_organization($params['id'], (int) $organization['id']);
    if ($event === null) {
        abort(404, 'Event not found.');
    }
    $values = event_form_input();
    $errors = validate_event_form($values, now_on_campus());
    if ($errors !== []) {
        abort_with_form_errors($errors);
    }
    [$startsAt, $endsAt] = event_form_times($values);
    update_event((int) $event['id'], $values, $startsAt, $endsAt);
    return ['id' => (int) $event['id'], 'message' => '“' . $values['title'] . '” updated and sent for review again.'];
});

/** DELETE /api/manage/events/{id} — delete one of the rep's own events. */
route('DELETE', '/api/manage/events/{id}', function (array $params): array {
    [, $organization] = rep_and_organization();
    $event = find_event_for_organization($params['id'], (int) $organization['id']);
    if ($event === null) {
        abort(404, 'Event not found.');
    }
    delete_event((int) $event['id']);
    return ['message' => '“' . $event['title'] . '” was deleted.'];
});

/**
 * POST /api/manage/resources/{id}   {"decision": "publish" | "reject" | "hide", "note": "..."}
 *   publish - make it public (also used to bring a taken-down resource back)
 *   reject  - refuse a pending resource; a reason is required
 *   hide    - take a public resource down at any time
 */
route('POST', '/api/manage/resources/{id}', function (array $params): array {
    [$user, $organization] = rep_and_organization();

    $resource = find_resource($params['id']);
    if ($resource === null || (int) $resource['organization_id'] !== (int) $organization['id']) {
        abort(404, 'Resource not found.');
    }

    $decision = input_text('decision');
    $note = mb_substr(input_text('note'), 0, 300);

    // Which decisions are allowed from which status.
    $allowed = [
        'publish' => ['pending', 'hidden'],
        'reject'  => ['pending'],
        'hide'    => ['published'],
    ];
    if (!isset($allowed[$decision]) || !in_array($resource['status'], $allowed[$decision], true)) {
        abort(400, 'That action is not possible for this resource.');
    }
    if ($decision === 'reject' && mb_strlen($note) < 5) {
        abort(422, 'Please write a short reason (at least 5 characters) so the student knows what to fix.', ['note' => 'Write at least 5 characters.']);
    }
    // The automatic check failed, found serious problems or has not run yet:
    // publishing is allowed only after the rep confirms they checked the file themselves.
    $warning = resource_breach_warning($resource);
    if ($decision === 'publish' && $warning !== null && input_text('confirm_breach') !== 'yes') {
        abort(409, 'Potential rule breach: ' . $warning . ' Open the file, check it yourself, then confirm to publish.', ['confirm_breach' => 'Confirm that you checked the file yourself.']);
    }

    $newStatus = ['publish' => 'published', 'reject' => 'rejected', 'hide' => 'hidden'][$decision];
    set_resource_status((int) $resource['id'], $newStatus, $note !== '' ? $note : null, (int) $user['id']);

    $messages = [
        'publish' => '“' . $resource['title'] . '” is now public.',
        'reject'  => '“' . $resource['title'] . '” was not accepted. The student can see your note.',
        'hide'    => '“' . $resource['title'] . '” was taken down. You can publish it again at any time.',
    ];
    return ['status' => $newStatus, 'message' => $messages[$decision]];
});
