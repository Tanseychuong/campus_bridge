<?php
/**
 * Deletes one of the org rep's own events (POST only).
 */
require __DIR__ . '/../src/bootstrap.php';

require_post_with_csrf();
$user = require_role('org_rep');

$eventId = (int) post_text('id');
// Only finds the event if it belongs to this rep's organization.
$event = find_event_for_organization($eventId, (int) $user['organization_id']);

if ($event === null) {
    http_response_code(404);
    exit('Event not found.');
}

delete_event((int) $event['id']);
flash('“' . $event['title'] . '” was deleted.', 'info');
redirect(url('manage.php'));
