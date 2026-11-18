<?php
/**
 * Approve or reject a pending opportunity or event (admins only, POST only).
 * Approved items become visible to everyone immediately.
 */
require __DIR__ . '/../src/bootstrap.php';

require_post_with_csrf();
require_role('admin');

$type = post_text('type');
$id = (int) post_text('id');
$decision = post_text('decision');
$note = mb_substr(post_text('note'), 0, 300);

if (!in_array($type, ['opportunity', 'event'], true) || !in_array($decision, ['approve', 'reject'], true) || $id < 1) {
    http_response_code(400);
    exit('Invalid review request.');
}

// Rejections must explain why, so the organizer can fix the listing.
if ($decision === 'reject' && mb_strlen($note) < 5) {
    flash('Please write a short reason (at least 5 characters) when rejecting.', 'error');
    redirect(url('admin.php'));
}

$newStatus = $decision === 'approve' ? 'published' : 'rejected';
if ($type === 'opportunity') {
    set_opportunity_status($id, $newStatus, $note !== '' ? $note : null);
} else {
    set_event_status($id, $newStatus, $note !== '' ? $note : null);
}

flash($decision === 'approve' ? 'Approved — it is now visible to everyone.' : 'Rejected. The organizer can see your note.');
redirect(url('admin.php'));
