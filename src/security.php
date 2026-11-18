<?php
/**
 * Security basics: sessions, CSRF protection and HTTP security headers.
 */

/**
 * Starts the PHP session with safe cookie settings.
 *
 * A session lets the server remember who you are between page loads. PHP
 * stores the data on the server and gives your browser a random session ID
 * in a cookie.
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
        'httponly' => true,                    // JavaScript cannot read the cookie
        'samesite' => 'Lax',                   // not sent with forms posted from other sites
        'secure'   => !empty($_SERVER['HTTPS']), // only over HTTPS when HTTPS is used
    ]);
    session_start();
}

/** Asks the browser to apply extra protections to every page. */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');             // do not guess file types
    header('X-Frame-Options: DENY');                       // our pages cannot be put inside an <iframe>
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // Only load scripts, styles and images from our own site.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
}

/* ------------------------------------------------------------------
 * CSRF protection (Cross-Site Request Forgery)
 *
 * Problem: another website could secretly submit a form to our site while
 * you are signed in (for example "delete this event").
 * Solution: every form we show contains a secret random token that only our
 * pages know. When a form is submitted we check the token matches.
 * ------------------------------------------------------------------ */

/** Returns this session's CSRF token, creating it if needed. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** The hidden <input> to put inside every <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Stops the request if the submitted token is missing or wrong. */
function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    // hash_equals compares in constant time, so the token cannot be guessed by timing.
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        exit('Your form expired or was not sent from CampusBridge. Please go back, refresh the page and try again.');
    }
}

/** Use at the top of every page that only accepts POST forms. */
function require_post_with_csrf(): void
{
    if (!is_post()) {
        http_response_code(405);
        header('Allow: POST');
        exit('This address only accepts form submissions.');
    }
    verify_csrf();
}
