<?php
/**
 * Sends a resource file: resource-download.php?id=3
 *
 * Public resources can be downloaded by anyone. Pending, hidden and rejected
 * ones only by the student who uploaded them, the reviewing rep and admins.
 * The file is always sent as a download ("attachment"), never shown inside
 * our site, so an uploaded HTML or SVG file can never run as part of CampusBridge.
 */
require __DIR__ . '/../src/bootstrap.php';

$id = query_int('id');
$resource = $id ? find_resource($id) : null;
if ($resource === null || !can_download_resource($resource, current_user())) {
    require __DIR__ . '/../templates/not-found.php';
    exit;
}

$path = upload_directory() . '/' . basename($resource['stored_name']);
if (!is_file($path)) {
    require __DIR__ . '/../templates/not-found.php';
    exit;
}

$asciiName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $resource['original_name']);
header('Content-Type: ' . $resource['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($resource['original_name']));
header('Cache-Control: private, no-store');
readfile($path);
