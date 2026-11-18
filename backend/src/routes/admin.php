<?php
/**
 * Administrator area: review new listings and see content analytics.
 * Only users with the "admin" role get an answer; everyone else gets 401/403.
 */

/** GET /api/admin — the review queue (with automated hints) and the analytics charts. */
route('GET', '/api/admin', function (): array {
    require_role('admin');
    $now = now_on_campus();

    // Everything waiting for a decision, opportunities and events together.
    $queue = [];
    foreach (get_opportunities('pending') as $o) {
        $queue[] = [
            'type' => 'opportunity', 'id' => (int) $o['id'], 'title' => $o['title'], 'by' => $o['provider'], 'summary' => $o['summary'],
            'when' => $o['deadline'] ? 'Deadline ' . format_date($o['deadline'], 'j M Y') : 'Rolling deadline',
            'flags' => moderation_flags($o, $now),
        ];
    }
    foreach (get_events('pending') as $event) {
        $queue[] = [
            'type' => 'event', 'id' => (int) $event['id'], 'title' => $event['title'], 'by' => $event['organization_name'], 'summary' => $event['summary'],
            'when' => format_event_range($event['starts_at'], $event['ends_at']),
            'flags' => moderation_flags($event, $now),
        ];
    }

    // Analytics, calculated from the published content.
    $publishedOpportunities = get_opportunities();
    $openOpportunities = array_filter($publishedOpportunities, fn ($o) => !is_closed($o['deadline'], $now));
    $upcomingEvents = array_filter(get_events(), fn ($e) => timestamp_of($e['ends_at']) > $now->getTimestamp());

    // ['Tech' => 3, ...] -> [['label' => 'Tech', 'value' => 3], ...] (JSON objects do not keep key order reliably)
    $rows = fn (array $counts) => array_map(fn ($label, $value) => ['label' => (string) $label, 'value' => (int) $value], array_keys($counts), $counts);

    return [
        'queue'    => $queue,
        'charts'   => [
            ['title' => 'Open opportunities by category', 'description' => 'Published opportunities that have not closed.', 'unit' => 'opportunities',
             'data' => $rows(count_by_category($openOpportunities, OPPORTUNITY_CATEGORIES))],
            ['title' => 'Upcoming events by category', 'description' => 'Published events that have not ended.', 'unit' => 'events',
             'data' => $rows(count_by_category($upcomingEvents, EVENT_CATEGORIES))],
            ['title' => 'Application deadlines per week', 'description' => 'How many deadlines fall in each of the next 8 weeks — useful for timing reminders.', 'unit' => 'deadlines',
             'data' => $rows(deadlines_per_week($publishedOpportunities, $now))],
        ],
        'accounts' => array_map(
            fn ($row) => ['role' => ROLE_LABELS[$row['role']], 'total' => (int) $row['total']],
            fetch_all('SELECT role, COUNT(*) AS total FROM users GROUP BY role')
        ),
    ];
});

/**
 * POST /api/admin/review   {"type": "opportunity" | "event", "id": 5, "decision": "approve" | "reject", "note": "..."}
 * Approved items become visible to everyone immediately.
 */
route('POST', '/api/admin/review', function (): array {
    require_role('admin');

    $type = input_text('type');
    $id = (int) input_text('id');
    $decision = input_text('decision');
    $note = mb_substr(input_text('note'), 0, 300);

    if (!in_array($type, ['opportunity', 'event'], true) || !in_array($decision, ['approve', 'reject'], true) || $id < 1) {
        abort(400, 'Invalid review request.');
    }
    // Rejections must explain why, so the organizer can fix the listing.
    if ($decision === 'reject' && mb_strlen($note) < 5) {
        abort(422, 'Please write a short reason (at least 5 characters) when rejecting.', ['note' => 'Write at least 5 characters.']);
    }

    $newStatus = $decision === 'approve' ? 'published' : 'rejected';
    if ($type === 'opportunity') {
        set_opportunity_status($id, $newStatus, $note !== '' ? $note : null);
    } else {
        set_event_status($id, $newStatus, $note !== '' ? $note : null);
    }

    return ['message' => $decision === 'approve' ? 'Approved — it is now visible to everyone.' : 'Rejected. The organizer can see your note.'];
});
