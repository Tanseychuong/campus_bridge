<?php
/**
 * Small helper functions used by every API endpoint.
 */

/** Returns the settings array from config.php (loaded only once). */
function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

/* ------------------------------------------------------------------
 * Reading the request
 *
 * GET requests carry their values in the query string (?q=data&type=event).
 * Other requests send a JSON body ({"email": "...", "password": "..."}),
 * except file uploads, which are sent as a normal multipart form ($_POST + $_FILES).
 * ------------------------------------------------------------------ */

/** The submitted values as an array, whichever way they were sent. */
function request_input(): array
{
    static $input = null;
    if ($input === null) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_starts_with($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $input = is_array($decoded) ? $decoded : [];
        } else {
            $input = $_POST;
        }
    }
    return $input;
}

/** A submitted text value, trimmed. Numbers are accepted and turned into text. */
function input_text(string $name): string
{
    $value = request_input()[$name] ?? '';
    if (is_int($value)) {
        return (string) $value;
    }
    return is_string($value) ? trim($value) : '';
}

/** A submitted value exactly as typed (passwords must not be trimmed). */
function input_raw(string $name): string
{
    $value = request_input()[$name] ?? '';
    return is_string($value) ? $value : '';
}

/** A submitted list of text values, e.g. {"interests": ["research", "arts"]}. */
function input_list(string $name): array
{
    $value = request_input()[$name] ?? [];
    return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
}

/** Reads a text value from the query string (?name=value), trimmed. */
function query_text(string $name, string $default = ''): string
{
    $value = $_GET[$name] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Splits a text block into non-empty lines (used for eligibility lists etc.). */
function lines(string $text): array
{
    return array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($line) => $line !== ''));
}

/** "1 event" / "3 events". */
function plural(int $count, string $singular, ?string $pluralForm = null): string
{
    return $count . ' ' . ($count === 1 ? $singular : ($pluralForm ?? $singular . 's'));
}
