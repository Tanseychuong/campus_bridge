<?php
/**
 * Loaded once before the tests run.
 * It loads Composer's autoloader (for PHPUnit) and all CampusBridge functions.
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';   // stops early on the command line: functions only, no session
require __DIR__ . '/../src/http/router.php'; // for the router unit tests (the routes themselves are tested through the API)
require __DIR__ . '/Support/factories.php';
require __DIR__ . '/Support/WebTestCase.php';
