<?php
/**
 * The first file every page loads. It:
 *   1. loads all our functions,
 *   2. sets the time zone,
 *   3. shows a friendly page if something goes wrong,
 *   4. starts the session and sends security headers.
 *
 * Usage at the top of a page:  require __DIR__ . '/../src/bootstrap.php';
 */

declare(strict_types=1);

// 1. Load our code. Order does not matter: PHP only runs a function when it is called.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/taxonomy.php';
require_once __DIR__ . '/dates.php';
require_once __DIR__ . '/deadlines.php';
require_once __DIR__ . '/schedule.php';
require_once __DIR__ . '/recommend.php';
require_once __DIR__ . '/search.php';
require_once __DIR__ . '/ics.php';
require_once __DIR__ . '/moderation.php';
require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/data/interests.php';
require_once __DIR__ . '/data/organizations.php';
require_once __DIR__ . '/data/opportunities.php';
require_once __DIR__ . '/data/events.php';
require_once __DIR__ . '/data/users.php';
require_once __DIR__ . '/../templates/components.php';

// 2. Time zone for all date calculations.
date_default_timezone_set(app_config()['timezone']);

// Unit tests load this file from the command line (no web request); stop here in that case.
if (PHP_SAPI === 'cli') {
    return;
}

// 3. Friendly error pages. Details are only shown when debug mode is on;
//    otherwise they go to the server log, so visitors never see internals.
ini_set('display_errors', app_config()['debug'] ? '1' : '0');
set_exception_handler(function (Throwable $error): void {
    error_log((string) $error);
    define('SHOWING_ERROR_PAGE', true);
    if (!headers_sent()) {
        http_response_code(500);
    }
    $databaseProblem = $error instanceof PDOException;
    $debugMessage = app_config()['debug'] ? $error->getMessage() : null;
    $pageTitle = 'Something went wrong';
    require __DIR__ . '/../templates/error.php';
});

// 4. Session and headers.
start_secure_session();
send_security_headers();
