<?php
/**
 * A tiny router: it connects an HTTP method + path to a PHP function.
 *
 *     route('GET', '/api/events/{id}', function (array $params): array {
 *         return ['event' => ...];          // sent to the browser as JSON
 *     });
 *
 * {id} only matches a positive whole number, so /api/events/abc or
 * /api/events/-5 never reach the handler: they are simply "404 Not found".
 *
 * Frameworks such as Laravel have a router like this; ours is about 50 lines
 * so you can read exactly what it does.
 */

/** The list of all routes, filled by the files in src/routes/. */
function &route_table(): array
{
    static $routes = [];
    return $routes;
}

/** Registers a route. $handler receives the {id} values and returns the JSON data (or null if it sent a file itself). */
function route(string $method, string $pattern, callable $handler): void
{
    $routes = &route_table();
    $routes[] = [$method, $pattern, $handler];
}

/** Turns '/api/events/{id}' into the regular expression '#^/api/events/(?P<id>[1-9][0-9]{0,9})$#'. */
function route_regex(string $pattern): string
{
    $quoted = preg_quote($pattern, '#'); // "." in "calendar.ics" must match a real dot
    return '#^' . preg_replace('#\\\\\{(\w+)\\\\\}#', '(?P<$1>[1-9][0-9]{0,9})', $quoted) . '$#';
}

/**
 * The path of this request, starting at "/api".
 * Works for http://localhost:8000/api/events (Docker) and for
 * http://localhost/campus_bridge/backend/public/api/events (AMPPS).
 */
function request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $apiStart = strpos($path, '/api/');
    if ($apiStart === false) {
        return $path;
    }
    return rtrim(substr($path, $apiStart), '/');
}

/** Finds the route for this request and runs it. */
function dispatch(string $method, string $path): void
{
    $allowedMethods = [];

    foreach (route_table() as [$routeMethod, $pattern, $handler]) {
        if (preg_match(route_regex($pattern), $path, $matches) !== 1) {
            continue;
        }
        if ($routeMethod !== $method) {
            $allowedMethods[] = $routeMethod; // right address, wrong method
            continue;
        }

        // Every request that can change data must carry the CSRF token.
        if ($method !== 'GET') {
            verify_csrf();
        }

        // Keep only the named parts, e.g. ['id' => 5].
        $params = array_map('intval', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
        $data = $handler($params);
        if ($data !== null) {
            send_json($data);
        }
        return;
    }

    if ($allowedMethods !== []) {
        header('Allow: ' . implode(', ', array_unique($allowedMethods)));
        abort(405, "This address does not accept $method requests.");
    }
    abort(404, 'Not found.');
}
