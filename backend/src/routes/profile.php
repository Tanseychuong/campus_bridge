<?php
/**
 * The signed-in user's profile: every detail CampusBridge holds about the
 * account, a summary of their activity, and forms to change their name and
 * password. Works for every role; each role also gets its own shortcuts.
 */

/** GET /api/profile */
route('GET', '/api/profile', function (): array {
    $user = require_login();
    $userId = (int) $user['id'];
    $account = get_account_details($userId);
    $profile = get_student_profile($userId);
    $uploads = get_resources_by_uploader($userId);

    $followed = array_values(array_filter(get_organizations(), fn ($org) => in_array((int) $org['id'], $profile['followed'], true)));

    // What this role looks after, with a count of what is waiting.
    $role = match ($user['role']) {
        'org_rep' => $user['organization_id'] ? [
            'title'   => 'You manage ' . $account['organization_name'],
            'link'    => '/manage',
            'action'  => 'Open Manage',
            'waiting' => (int) fetch_one(
                "SELECT COUNT(*) AS total FROM resources WHERE organization_id = ? AND status = 'pending'",
                [(int) $user['organization_id']]
            )['total'],
            'waiting_label' => 'waiting for your review',
        ] : null,
        'campus_rep' => [
            'title'   => 'You approve sign-ups for ' . $account['campus_name'],
            'link'    => '/approvals',
            'action'  => 'Open Approvals',
            'waiting' => (int) fetch_one(
                "SELECT COUNT(*) AS total FROM users WHERE role = 'student' AND status = 'pending' AND campus_id = ?",
                [(int) $user['campus_id']]
            )['total'],
            'waiting_label' => 'waiting for approval',
        ],
        'admin' => [
            'title'   => 'You administer every campus',
            'link'    => '/admin',
            'action'  => 'Open the admin dashboard',
            'waiting' => count(get_opportunities('pending')) + count(get_events('pending')),
            'waiting_label' => 'waiting for review',
        ],
        default => null,
    };
    if ($role !== null) {
        // "1 resource waiting…" / "3 resources waiting…"
        [$one, $many] = ['org_rep' => ['resource', 'resources'], 'campus_rep' => ['sign-up', 'sign-ups'], 'admin' => ['listing', 'listings']][$user['role']];
        $role['waiting_label'] = ($role['waiting'] === 1 ? $one : $many) . ' ' . $role['waiting_label'];
    }

    return [
        'account' => [
            'name'              => $account['name'],
            'email'             => $account['email'],
            'role'              => $account['role'],
            'role_label'        => ROLE_LABELS[$account['role']],
            'campus_name'       => $account['campus_name'] ?? 'Every campus',
            'organization_name' => $account['organization_name'],
            'organization_id'   => $account['organization_id'] === null ? null : (int) $account['organization_id'],
            'member_since'      => format_date($account['created_at'], 'j F Y'),
            'status_label'      => 'Active',
            // Who approved the sign-up, for students who went through approval.
            'approved'          => $account['reviewed_at']
                ? 'Approved ' . format_date($account['reviewed_at'], 'j M Y') . ($account['reviewer_name'] ? ' by ' . $account['reviewer_name'] : '')
                : null,
        ],
        'stats' => [
            ['label' => 'Saved opportunities', 'value' => count($profile['saved_opportunity_ids']), 'link' => '/saved'],
            ['label' => 'Events you’re interested in', 'value' => count($profile['saved_event_ids']), 'link' => '/saved'],
            ['label' => 'Organizations followed', 'value' => count($profile['followed']), 'link' => '/organizations'],
            ['label' => 'Resources shared', 'value' => count($uploads), 'link' => '/resources'],
        ],
        'interests' => array_map(fn ($slug) => ['value' => $slug, 'label' => INTEREST_LABELS[$slug] ?? $slug], $profile['interests']),
        'followed'  => array_map(fn ($org) => present_organization($org, $profile), $followed),
        'uploads'   => array_map('present_resource', $uploads),
        'role'      => $role,
    ];
});

/** PUT /api/profile   {"name": "..."} — the only detail you can change yourself (email and role are set by the university). */
route('PUT', '/api/profile', function (): array {
    $user = require_login();
    $name = input_text('name');
    if (($error = name_error($name)) !== null) {
        abort_with_form_errors(['name' => $error]);
    }
    update_user_name((int) $user['id'], $name);
    return [
        'user'       => present_user(find_user_by_id((int) $user['id'])),
        'csrf_token' => csrf_token(),
        'message'    => 'Your name was updated.',
    ];
});

/** PUT /api/profile/password   {"current_password", "password", "password_confirm"} */
route('PUT', '/api/profile/password', function (): array {
    $user = require_login();
    $errors = new_password_errors(input_raw('password'), input_raw('password_confirm'));
    // The current password proves it is really you (e.g. not someone at an unlocked computer).
    if (!password_verify(input_raw('current_password'), $user['password_hash'])) {
        $errors = ['current_password' => 'That is not your current password.'] + $errors;
    }
    if ($errors !== []) {
        abort_with_form_errors($errors);
    }
    update_user_password((int) $user['id'], input_raw('password'));
    session_regenerate_id(true); // a fresh session id after a credential change
    return ['csrf_token' => csrf_token(), 'message' => 'Your password was changed.'];
});

/*
 * Profile and cover pictures (only the owner sees them):
 *   GET    /api/profile/avatar   /api/profile/cover   the picture itself
 *   POST   /api/profile/avatar   /api/profile/cover   upload a new one (multipart form, field "image")
 *   DELETE /api/profile/avatar   /api/profile/cover   remove it
 */
foreach (PROFILE_IMAGE_COLUMNS as $kind => $column) {
    route('GET', "/api/profile/$kind", function () use ($column): ?array {
        $user = require_login();
        $path = $user[$column] ? profile_image_directory() . '/' . basename($user[$column]) : null;
        if ($path === null || !is_file($path)) {
            abort(404, 'No picture yet.');
        }
        $mime = array_search(pathinfo($path, PATHINFO_EXTENSION), PROFILE_IMAGE_TYPES, true);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff'); // the browser must treat it as this image type, nothing else
        header('Cache-Control: private, max-age=31536000, immutable'); // the address changes with every new picture
        readfile($path);
        return null; // we sent the file ourselves, not JSON
    });

    route('POST', "/api/profile/$kind", function () use ($kind): array {
        $user = require_login();
        // A file bigger than PHP's post_max_size empties the whole form.
        if ($_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            abort(413, 'The picture is too large (maximum 5 MB).');
        }
        [$error, $extension] = check_profile_image($_FILES['image'] ?? null);
        if ($error !== null) {
            abort(422, $error, ['image' => $error]);
        }
        save_profile_image($user, $kind, $_FILES['image'], $extension);
        return [
            'user'       => present_user(find_user_by_id((int) $user['id'])),
            'csrf_token' => csrf_token(),
            'message'    => $kind === 'avatar' ? 'Your profile picture was updated.' : 'Your cover picture was updated.',
        ];
    });

    route('DELETE', "/api/profile/$kind", function () use ($kind): array {
        $user = require_login();
        remove_profile_image($user, $kind);
        return [
            'user'       => present_user(find_user_by_id((int) $user['id'])),
            'csrf_token' => csrf_token(),
            'message'    => $kind === 'avatar' ? 'Your profile picture was removed.' : 'Your cover picture was removed.',
        ];
    });
}
