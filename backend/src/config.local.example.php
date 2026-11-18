<?php
/**
 * Personal settings for YOUR computer.
 *
 * 1. Copy this file and name the copy  config.local.php  (same folder).
 * 2. Change the values you need. Leave out anything you do not need to change.
 *
 * config.local.php is listed in .gitignore, so your password is never
 * committed or pushed to GitHub.
 */
return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => '3306',
        'name'     => 'campus_bridge',
        'user'     => 'root',
        'password' => 'your-mysql-password-here',
    ],
    'debug' => true, // show error details while developing
];
