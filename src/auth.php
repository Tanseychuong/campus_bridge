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

/** Sends guests to the sign-in page, then back here afterwards. */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('Please sign in to continue.', 'info');
        redirect(url('login.php', ['return' => current_path()]));
    }
    return $user;
}

/** Stops the page with "403 Forbidden" unless the user has one of the roles. */
function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $pageTitle = 'Not allowed';
        require __DIR__ . '/../templates/forbidden.php';
        exit;
    }
    return $user;
}
