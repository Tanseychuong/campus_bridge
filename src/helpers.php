<?php
/**
 * Small helper functions used by every page.
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

/**
 * Escapes text before putting it into HTML.
 *
 * ALWAYS use this when printing data: <?= e($event['title']) ?>
 * It turns characters like < and > into &lt; and &gt;, so text typed by a user
 * can never become HTML or JavaScript. This prevents Cross-Site Scripting (XSS).
 */
function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Builds a link to one of our pages, e.g. url('event.php', ['id' => 3]).
 * It works whether the site lives at http://localhost/ (Docker) or at
 * http://localhost/campus_bridge/public/ (AMPPS).
 */
function url(string $page = '', array $query = []): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $link = $base . '/' . ltrim($page, '/');
    if ($query !== []) {
        $link .= '?' . http_build_query($query);
    }
    return $link;
}

/** Sends the browser to another page and stops the script. */
function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

/** True when the form was submitted with method="post". */
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Reads a text value from the query string (?name=value), trimmed. */
function query_text(string $name, string $default = ''): string
{
    $value = $_GET[$name] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Reads a whole-number value from the query string, or null if missing/invalid. */
function query_int(string $name): ?int
{
    $value = filter_input(INPUT_GET, $name, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return is_int($value) ? $value : null;
}

/** Reads a text value from a submitted form, trimmed. */
function post_text(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Reads a list of checkbox values from a submitted form (name="interests[]"). */
function post_list(string $name): array
{
    $value = $_POST[$name] ?? [];
    return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
}

/**
 * Only allow redirects to pages on THIS site (a path like "/event.php?id=3").
 * Without this check, an attacker could send people a login link that
 * forwards them to a fake website afterwards ("open redirect").
 */
function safe_return_path(?string $path, string $fallback): string
{
    if (is_string($path) && preg_match('#^/(?!/)[^\s\\\\]*$#', $path) === 1) {
        return $path;
    }
    return $fallback;
}

/** The current page's path and query, e.g. "/explore.php?q=data". */
function current_path(): string
{
    return $_SERVER['REQUEST_URI'] ?? url('index.php');
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

/* ------------------------------------------------------------------
 * Flash messages: a one-time message shown on the next page,
 * e.g. "Event saved." after a form is submitted.
 * ------------------------------------------------------------------ */

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
