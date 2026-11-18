<?php
/**
 * Application settings.
 *
 * Where values come from (later steps override earlier ones):
 *   1. The defaults below, which match a fresh AMPPS installation
 *      (MySQL on localhost, user "root", password "mysql").
 *   2. src/config.local.php — your personal settings (NOT committed to Git).
 *      Copy src/config.local.example.php to create it, e.g. to set your
 *      AMPPS MySQL password.
 *   3. Environment variables — Docker and the automated tests set these,
 *      and they always win, so tests never touch your own database.
 *
 * Never put real production passwords in a file that is committed to Git.
 */

// 1. Defaults
$settings = [
    'app_name' => 'CampusBridge',
    'timezone' => 'Africa/Accra', // Ghana uses GMT (UTC+0) all year, with no daylight saving
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => '3306',
        'name'     => 'campus_bridge',
        'user'     => 'root',
        'password' => 'mysql',
    ],
    'debug' => false, // show detailed error messages? Only while developing.
    // Uploaded resources live OUTSIDE public/, so they can only be downloaded through resource-download.php.
    'upload_dir' => dirname(__DIR__) . '/storage/uploads',
];

// 2. Personal settings file, if it exists
$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $settings = array_replace_recursive($settings, require $localFile);
}

// 3. Environment variables, if they are set
$environment = [
    'DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_NAME' => 'name', 'DB_USER' => 'user', 'DB_PASSWORD' => 'password',
];
foreach ($environment as $variable => $key) {
    $value = getenv($variable);
    if ($value !== false) {
        $settings['db'][$key] = $value;
    }
}
if (getenv('UPLOAD_DIR') !== false) {
    $settings['upload_dir'] = getenv('UPLOAD_DIR');
}
if (getenv('APP_DEBUG') !== false) {
    $settings['debug'] = getenv('APP_DEBUG') === '1';
}

return $settings;
