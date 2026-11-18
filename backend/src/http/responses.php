<?php
/**
 * Sending answers back to the React app.
 *
 * Every API answer is JSON. A successful one is just the data:
 *     {"event": {...}, "more": [...]}
 * A failed one has an "error" message, and form errors per field:
 *     {"error": "Please fix 2 fields below.", "errors": {"title": "Title must be at least 5 characters."}}
 */

/**
 * The exception thrown by abort(). public/index.php catches it and turns it
 * into a JSON error answer with the right HTTP status code.
 */
final class HttpError extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}

/**
 * Stops the current request with an error. Examples:
 *     abort(404, 'That event does not exist.');
 *     abort(422, 'Please fix 2 fields below.', $errors);
 *
 * Status codes used by CampusBridge:
 *   400 bad request       401 not signed in      403 signed in, but not allowed
 *   404 not found         405 wrong method       409 conflict
 *   413 file too large    422 form has errors    429 too many attempts
 */
function abort(int $status, string $message, array $errors = []): never
{
    throw new HttpError($status, $message, $errors);
}

/** Sends data as JSON. Pass $status to change the HTTP status code (201 = created). */
function send_json(array $data, ?int $status = null): void
{
    if (!headers_sent()) {
        if ($status !== null) {
            http_response_code($status);
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store'); // personal data must not be cached
    }
    // JSON_HEX_TAG writes < and > as < and >: even if a browser were
    // tricked into reading this answer as HTML, no tag could ever appear in it.
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
}

/** The error answer for a validation problem: "Please fix 2 fields below." */
function abort_with_form_errors(array $errors): never
{
    abort(422, 'Please fix ' . plural(count($errors), 'field') . ' below.', $errors);
}
