<?php
/**
 * Accounts: who is signed in, signing in and out, creating an account,
 * and the fixed lists (interests, categories, ...) the forms need.
 */

/**
 * GET /api/session
 * The React app calls this when it starts: who is signed in, and the CSRF
 * token it must send with every request that changes data.
 */
route('GET', '/api/session', function (): array {
    return ['user' => present_user(current_user()), 'csrf_token' => csrf_token()];
});

/**
 * POST /api/login   {"email": "...", "password": "..."}
 */
route('POST', '/api/login', function (): array {
    // Slow down password guessing: after 5 failures, wait 30 seconds.
    $failures = $_SESSION['login_failures'] ?? 0;
    $lockedUntil = $_SESSION['login_locked_until'] ?? 0;
    if (time() < $lockedUntil) {
        abort(429, 'Too many attempts. Please wait 30 seconds and try again.');
    }

    $user = attempt_login(input_text('email'), input_raw('password'));
    if ($user === null) {
        $_SESSION['login_failures'] = $failures + 1;
        if ($_SESSION['login_failures'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 30;
            $_SESSION['login_failures'] = 0;
        }
        // Same message whether the email or the password is wrong,
        // so attackers cannot find out which emails have accounts.
        abort(422, 'That email and password do not match an account.');
    }

    unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
    $blocked = account_status_message($user);
    if ($blocked !== null) {
        abort(403, $blocked);
    }
    log_in($user);
    return [
        'user'       => present_user($user),
        'csrf_token' => csrf_token(),
        'message'    => 'Welcome back, ' . $user['name'] . '!',
    ];
});

/**
 * POST /api/logout
 * A POST with the CSRF token, so another website cannot sign you out.
 */
route('POST', '/api/logout', function (): array {
    log_out();
    return ['user' => null, 'csrf_token' => csrf_token(), 'message' => 'You have signed out.'];
});

/**
 * POST /api/register   {"name", "email", "campus_id", "password", "password_confirm"}
 * New accounts are always "student" and start 'pending': the student can sign in
 * once a campus rep of their campus approves them (see routes/approvals.php).
 * Admins, org reps and campus reps are set up in the database.
 */
route('POST', '/api/register', function (): array {
    if (is_signed_in()) {
        abort(409, 'You are already signed in.');
    }

    $input = [
        'name'             => input_text('name'),
        'email'            => input_text('email'),
        'campus_id'        => input_text('campus_id'),
        'password'         => input_raw('password'),
        'password_confirm' => input_raw('password_confirm'),
    ];
    $errors = validate_registration($input, array_keys(get_campuses()));
    if (!isset($errors['email']) && find_user_by_email($input['email']) !== null) {
        $errors['email'] = 'An account with this email already exists. Try signing in.';
    }
    if ($errors !== []) {
        abort_with_form_errors($errors);
    }

    $campusId = (int) $input['campus_id'];
    create_student_account($input['name'], $input['email'], $input['password'], $campusId);
    http_response_code(201); // 201 Created
    return [
        'user'       => null, // not signed in until approved
        'pending'    => true,
        'csrf_token' => csrf_token(),
        'message'    => 'Thanks for signing up! A campus representative for ' . get_campuses()[$campusId]
            . ' will check your account. You can sign in as soon as it is approved.',
    ];
});

/**
 * GET /api/meta
 * The fixed lists from taxonomy.php and friends, so the React forms and
 * filters always offer exactly the values the server accepts.
 */
route('GET', '/api/meta', function (): array {
    $options = fn (array $labels) => array_map(fn ($value, $label) => ['value' => (string) $value, 'label' => $label], array_keys($labels), $labels);
    $points = RECOMMENDATION_POINTS;

    return [
        'interests'               => $options(INTEREST_LABELS),
        'opportunity_categories'  => $options(OPPORTUNITY_CATEGORIES),
        'event_categories'        => $options(EVENT_CATEGORIES),
        'organization_categories' => $options(ORGANIZATION_CATEGORIES),
        'location_modes'          => $options(LOCATION_MODES),
        'campuses'                => $options(get_campuses()),
        'ip_ownership'            => $options(IP_OWNERSHIP),
        'licenses'                => $options(RESOURCE_LICENSES),
        'file_extensions'         => array_map(fn ($extension) => '.' . $extension, array_keys(RESOURCE_FILE_TYPES)),
        'max_upload_bytes'        => RESOURCE_MAX_BYTES,
        // The scoring rules, shown on the About page.
        'recommendation_rules'    => [
            ["Each interest the item shares with you (up to {$points['max_matching_interests']})", "+{$points['per_matching_interest']} each"],
            ['Posted by an organization you follow', "+{$points['followed_organization']}"],
            ['Same category as items you already saved', "+{$points['similar_to_saved']}"],
            ['Deadline 3–14 days away (best time to apply)', "+{$points['deadline_soon']}"],
            ['Deadline 0–2 or 15–30 days away', "+{$points['deadline_near']}"],
            ['Event within the next 7 days', "+{$points['event_this_week']}"],
            ['Event in 8–14 days', "+{$points['event_next_week']}"],
            ['Eligibility information missing', (string) $points['missing_information']],
        ],
    ];
});
