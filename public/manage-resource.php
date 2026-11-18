<?php
/**
 * An organization rep's decision on a resource (POST only):
 *   publish  - make it public (also used to bring a taken-down resource back)
 *   reject   - refuse a pending resource; a reason is required
 *   hide     - take a public resource down at any time
 * Reps can only act on resources sent to THEIR organization.
 */
require __DIR__ . '/../src/bootstrap.php';

require_post_with_csrf();
$user = require_role('org_rep');

$resource = find_resource((int) post_text('id'));
if ($resource === null || (int) $resource['organization_id'] !== (int) $user['organization_id']) {
    http_response_code(404);
    exit('Resource not found.');
}

$decision = post_text('decision');
$note = mb_substr(post_text('note'), 0, 300);

// Which decisions are allowed from which status.
$allowed = [
    'publish' => ['pending', 'hidden'],
    'reject'  => ['pending'],
    'hide'    => ['published'],
];
if (!isset($allowed[$decision]) || !in_array($resource['status'], $allowed[$decision], true)) {
    http_response_code(400);
    exit('That action is not possible for this resource.');
}

if ($decision === 'reject' && mb_strlen($note) < 5) {
    flash('Please write a short reason (at least 5 characters) so the student knows what to fix.', 'error');
    redirect(url('manage.php') . '#resources-heading');
}

$newStatus = ['publish' => 'published', 'reject' => 'rejected', 'hide' => 'hidden'][$decision];
set_resource_status((int) $resource['id'], $newStatus, $note !== '' ? $note : null, (int) $user['id']);

$messages = [
    'publish' => '“' . $resource['title'] . '” is now public.',
    'reject'  => '“' . $resource['title'] . '” was not accepted. The student can see your note.',
    'hide'    => '“' . $resource['title'] . '” was taken down. You can publish it again at any time.',
];
flash($messages[$decision]);
redirect(url('manage.php') . '#resources-heading');
