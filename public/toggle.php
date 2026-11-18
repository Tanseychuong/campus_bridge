<?php
/**
 * Handles the Save / I'm interested / Follow buttons.
 *
 * Pattern used here — "Post/Redirect/Get":
 *   1. The button POSTs a small form to this file.
 *   2. We update the database.
 *   3. We REDIRECT back to the page the student was on.
 * Because the browser ends on a normal GET page, pressing Refresh never
 * submits the form twice.
 */
require __DIR__ . '/../src/bootstrap.php';

require_post_with_csrf();

$returnTo = safe_return_path(post_text('return'), url('index.php'));

$user = current_user();
if ($user === null) {
    flash('Please sign in to save items and follow organizations.', 'info');
    redirect(url('login.php', ['return' => $returnTo]));
}

$kind = post_text('kind');
$id = filter_var(post_text('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

// Which table each kind of button changes, and how to check the item exists.
$targets = [
    'opportunity'  => ['saved_opportunities', 'opportunity_id', 'find_opportunity', 'Saved', 'Removed from saved items'],
    'event'        => ['saved_events', 'event_id', 'find_event', 'Added to your calendar', 'Removed from your calendar'],
    'organization' => ['follows', 'organization_id', 'find_organization', 'Following', 'Unfollowed'],
];

if (!isset($targets[$kind]) || $id === false) {
    http_response_code(400);
    exit('Unknown item.');
}

[$table, $column, $findFunction, $addedMessage, $removedMessage] = $targets[$kind];
$item = $findFunction($id);
if ($item === null) {
    http_response_code(404);
    exit('That item no longer exists.');
}

$nowSaved = toggle_link($table, $column, (int) $user['id'], $id);
$name = $item['title'] ?? $item['name'];
flash(($nowSaved ? $addedMessage : $removedMessage) . ': ' . $name);
redirect($returnTo);
