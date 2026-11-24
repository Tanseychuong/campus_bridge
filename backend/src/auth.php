<?php
/**
 * Accounts: signing in, signing out and checking roles.
 *
 * Roles:
 *   student  - can save items, follow organizations and get recommendations
 *   org_rep    - can also create and edit events for THEIR organization
 *   campus_rep - can also approve or reject students who sign up for THEIR campus
 *   admin      - can also approve or reject new listings, any sign-up, and
 *                add or remove study materials; sees analytics
 *
 * Account status: new students are 'pending' until a campus rep approves them.
 * Only 'active' accounts can sign in (see attempt_login and current_user).
 *
 * Every role check happens here, on the server. Hiding a button in the
 * page is NOT security — the server must refuse the action itself.
 */

/** The signed-in user (an array from the users table) or null for a guest. */
function current_user(): ?array
{
    static $user = false; // false = "not looked up yet"

    if ($user === false) {
        $userId = $_SESSION['user_id'] ?? null;
        $user = $userId ? find_user_by_id((int) $userId) : null;
        // An account that is no longer active (e.g. rejected) loses its session straight away.
        if ($user !== null && $user['status'] !== 'active') {
            $user = null;
        }
    }
    return $user;
}

function is_signed_in(): bool
{
    return current_user() !== null;
}

function has_role(string $role): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === $role;
}

/**
 * Checks an email and password. Returns the user if they match, else null.
 * password_verify() compares the password with the stored bcrypt hash.
 * The caller must still check $user['status'] (see account_status_message).
 */
function attempt_login(string $email, string $password): ?array
{
    $user = find_user_by_email($email);
    if ($user === null || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    return $user;
}

/**
 * Why an account with the right password still cannot sign in, or null if it can.
 * Only shown AFTER the password matched, so it never reveals which emails exist.
 */
function account_status_message(array $user): ?string
{
    if ($user['status'] === 'pending') {
        return 'Your account is waiting for approval by your campus representative. You can sign in as soon as they approve it.';
    }
    if ($user['status'] === 'rejected') {
        $reason = $user['review_note'] ? ' Reason: ' . $user['review_note'] : '';
        return 'Your sign-up was not approved by your campus representative.' . $reason;
    }
    return null;
}

/** Marks the user as signed in for this browser session. */
function log_in(array $user): void
{
    // A new session ID after login stops "session fixation" attacks,
    // where an attacker tricks you into using a session ID they already know.
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
}

function log_out(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/**
 * Stops the request with "401 Unauthorized" for guests.
 * The React app reacts to 401 by showing the sign-in page.
 */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        abort(401, 'Please sign in to continue.');
    }
    return $user;
}

/** Stops the request with "403 Forbidden" unless the user has one of the roles. */
function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        abort(403, 'Your account is not allowed to do this.');
    }
    return $user;
}

/** The parts of a user that the browser may see (never the password hash). */
function present_user(?array $user): ?array
{
    if ($user === null) {
        return null;
    }
    return [
        'id'              => (int) $user['id'],
        'name'            => $user['name'],
        'email'           => $user['email'],
        'role'            => $user['role'],
        'role_label'      => ROLE_LABELS[$user['role']],
        'campus_id'       => $user['campus_id'] === null ? null : (int) $user['campus_id'],
        'organization_id' => $user['organization_id'] === null ? null : (int) $user['organization_id'],
    ];
}
