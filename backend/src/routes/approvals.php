<?php
/**
 * Sign-up approvals: a campus rep approves or rejects the students who sign up
 * for THEIR campus. Admins can decide on any campus.
 *
 * Security: a campus rep's queries always include their own campus id, so
 * changing an id in a request can never reach another campus's students.
 */

/** The signed-in reviewer and the campus they may decide on (null = every campus, for admins). */
function approval_reviewer(): array
{
    $user = require_role('campus_rep', 'admin');
    if ($user['role'] === 'admin') {
        return [$user, null];
    }
    if ($user['campus_id'] === null) {
        abort(403, 'Your account is not linked to a campus yet.');
    }
    return [$user, (int) $user['campus_id']];
}

function present_signup(array $row): array
{
    $labels = ['pending' => ['Waiting for approval', 'warning'], 'active' => ['Approved', 'success'], 'rejected' => ['Not approved', 'danger']];
    [$label, $tone] = $labels[$row['status']];
    return [
        'id'            => (int) $row['id'],
        'name'          => $row['name'],
        'email'         => $row['email'],
        'campus_name'   => $row['campus_name'],
        'status'        => $row['status'],
        'status_label'  => $label,
        'status_tone'   => $tone,
        'review_note'   => $row['review_note'],
        'signed_up'     => format_date($row['created_at'], 'j M Y, H:i'),
        'reviewed'      => $row['reviewed_at'] ? format_date($row['reviewed_at'], 'j M Y') : null,
        'reviewer_name' => $row['reviewer_name'],
    ];
}

/** GET /api/approvals — sign-ups waiting for a decision, and recent decisions. */
route('GET', '/api/approvals', function (): array {
    [$user, $campusId] = approval_reviewer();
    $signups = array_map('present_signup', get_signups_for_review($campusId));
    return [
        'scope'         => $campusId === null ? 'every campus' : get_campuses()[$campusId],
        'signups'       => $signups,
        'pending_count' => count(array_filter($signups, fn ($s) => $s['status'] === 'pending')),
    ];
});

/**
 * POST /api/approvals/{id}   {"decision": "approve" | "reject", "note": "..."}
 * Approve lets the student sign in. Reject needs a reason, which the student sees when signing in.
 * An approved student can still be rejected later (e.g. a fake account), which ends their sign-in.
 */
route('POST', '/api/approvals/{id}', function (array $params): array {
    [$user, $campusId] = approval_reviewer();

    $student = find_user_by_id($params['id']);
    if ($student === null || $student['role'] !== 'student' || ($campusId !== null && (int) $student['campus_id'] !== $campusId)) {
        abort(404, 'Sign-up not found.');
    }

    $decision = input_text('decision');
    $note = mb_substr(input_text('note'), 0, 300);
    if (!in_array($decision, ['approve', 'reject'], true)) {
        abort(400, 'Invalid decision.');
    }
    if ($decision === 'reject' && mb_strlen($note) < 5) {
        abort(422, 'Please write a short reason (at least 5 characters). The student sees it when they try to sign in.', ['note' => 'Write at least 5 characters.']);
    }

    $status = $decision === 'approve' ? 'active' : 'rejected';
    set_account_status((int) $student['id'], $status, $note !== '' ? $note : null, (int) $user['id']);

    return [
        'status'  => $status,
        'message' => $decision === 'approve'
            ? $student['name'] . ' can now sign in.'
            : $student['name'] . '’s sign-up was not approved. They will see your reason when they try to sign in.',
    ];
});
