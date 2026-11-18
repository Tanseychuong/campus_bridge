<?php
/**
 * Security basics: sessions, CSRF protection and HTTP security headers.
 */

/**
 * Starts the PHP session with safe cookie settings.
 *
 * A session lets the server remember who you are between requests. PHP
 * stores the data on the server and gives your browser a random session ID
 * in a cookie. The React app never sees this cookie: the browser simply
 * sends it along with every request to /api/...
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('campusbridge_session');
    session_set_cookie_params([
        'lifetime' => 0,                       // cookie ends when the browser closes
        'path'     => '/',
        'httponly' => true,                    // JavaScript (including React) cannot read the cookie
        'samesite' => 'Lax',                   // not sent with requests started by other sites
        'secure'   => !empty($_SERVER['HTTPS']), // only over HTTPS when HTTPS is used
    ]);
    session_start();
}

/** Asks the browser to apply extra protections to every response. */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');             // do not guess file types (JSON is never run as HTML)
    header('X-Frame-Options: DENY');                       // our responses cannot be put inside an <iframe>
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // The API only returns data, never pages, so it needs no scripts, styles or images at all.
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
}

/* ------------------------------------------------------------------
 * CSRF protection (Cross-Site Request Forgery)
 *
 * Problem: another website could secretly send a request to our API while
 * you are signed in (for example "delete this event").
 * Solution: the React app asks GET /api/session for a secret random token
 * and sends it back in an "X-CSRF-Token" header with every request that
 * changes something. Another website cannot read that token, and browsers do
 * not let it add custom headers to a request sent to our site.
 * ------------------------------------------------------------------ */

/** Returns this session's CSRF token, creating it if needed. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Stops the request if the token in the X-CSRF-Token header is missing or wrong. */
function verify_csrf(): void
{
    $submitted = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    // hash_equals compares in constant time, so the token cannot be guessed by timing.
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        abort(400, 'Your session expired or the request did not come from CampusBridge. Refresh the page and try again.');
    }
}
