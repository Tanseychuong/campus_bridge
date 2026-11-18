<?php
/**
 * The CampusBridge API: every request to /api/... arrives here
 * (the "front controller" pattern; see .htaccess).
 *
 *   1. bootstrap.php loads our functions, starts the session and sends security headers.
 *   2. The files in src/routes/ register which function answers which address.
 *   3. dispatch() runs the matching function and sends its answer as JSON.
 *
 * The React app in frontend/ is the only thing that talks to this file.
 */
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/http/router.php';

require __DIR__ . '/../src/routes/account.php';
require __DIR__ . '/../src/routes/catalogue.php';
require __DIR__ . '/../src/routes/student.php';
require __DIR__ . '/../src/routes/resources.php';
require __DIR__ . '/../src/routes/manage.php';
require __DIR__ . '/../src/routes/admin.php';

try {
    dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', request_path());
} catch (HttpError $error) {
    // abort() was called: answer with the status code and a message the page can show.
    $body = ['error' => $error->getMessage()];
    if ($error->errors !== []) {
        $body['errors'] = $error->errors;
    }
    send_json($body, $error->status);
}
