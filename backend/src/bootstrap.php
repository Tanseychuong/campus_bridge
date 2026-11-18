<?php
/**
 * The first file every request loads. It:
 *   1. loads all our functions,
 *   2. sets the time zone,
 *   3. answers with a friendly JSON error if something goes wrong,
 *   4. starts the session and sends security headers.
 *
 * Loaded by public/index.php (the API's front controller), by the scan
 * worker and by the tests.
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
require_once __DIR__ . '/data/campuses.php';
require_once __DIR__ . '/data/resources.php';
require_once __DIR__ . '/resource_scan.php';
require_once __DIR__ . '/data/organizations.php';
require_once __DIR__ . '/data/opportunities.php';
require_once __DIR__ . '/data/events.php';
require_once __DIR__ . '/data/users.php';
require_once __DIR__ . '/http/responses.php';
require_once __DIR__ . '/http/present.php';

// 2. Time zone for all date calculations.
date_default_timezone_set(app_config()['timezone']);

// Unit tests load this file from the command line (no web request); stop here in that case.
if (PHP_SAPI === 'cli') {
    return;
}

// 3. Friendly errors. Details are only shown when debug mode is on;
//    otherwise they go to the server log, so visitors never see internals.
ini_set('display_errors', '0'); // a PHP warning printed into the JSON would break it
set_exception_handler(function (Throwable $error): void {
    error_log((string) $error);
    $message = $error instanceof PDOException
        ? 'CampusBridge cannot reach its database right now. Please try again in a minute.'
        : 'Something went wrong on our side. Please try again.';
    $body = ['error' => $message];
    if (app_config()['debug']) {
        $body['debug'] = $error->getMessage();
    }
    send_json($body, 500);
});

// 4. Session and headers.
start_secure_session();
send_security_headers();
