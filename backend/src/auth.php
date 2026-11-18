<?php
/**
 * Accounts: signing in, signing out and checking roles.
 *
 * Roles:
 *   student  - can save items, follow organizations and get recommendations
 *   org_rep  - can also create and edit events for THEIR organization
 *   admin    - can also approve or reject new listings and see analytics
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
 */
function attempt_login(string $email, string $password): ?array
{
    $user = find_user_by_email($email);
    if ($user === null || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    return $user;
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
