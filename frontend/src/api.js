/*
 * Talking to the PHP API.
 *
 * Every address starts with /api. In development Vite forwards those requests
 * to the PHP server (see vite.config.js), so the browser only ever talks to
 * one website and sends the session cookie automatically.
 *
 * Requests that change data (POST, PUT, DELETE) carry the CSRF token in an
 * X-CSRF-Token header. The token comes from GET /api/session.
 */

let csrfToken = '';

/** Remembers the CSRF token from /api/session, /api/login, /api/logout... */
export function setCsrfToken(token) {
    csrfToken = token || '';
}

/** An error answer from the API: status 404, message "This event does not exist…", field errors. */
export class ApiError extends Error {
    constructor(status, message, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

async function request(method, path, body) {
    const options = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } };

    if (method !== 'GET') {
        options.headers['X-CSRF-Token'] = csrfToken;
        if (body instanceof FormData) {
            options.body = body; // file upload: the browser sets the multipart Content-Type itself
        } else if (body !== undefined) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
    }

    let response;
    try {
        response = await fetch(path, options);
    } catch {
        throw new ApiError(0, 'CampusBridge cannot reach its server. Check your connection and try again.');
    }

    const data = await response.json().catch(() => null);
    if (!response.ok) {
        throw new ApiError(response.status, data?.error || 'Something went wrong. Please try again.', data?.errors || {});
    }
    return data;
}

export const api = {
    get: (path) => request('GET', path),
    post: (path, body) => request('POST', path, body),
    put: (path, body) => request('PUT', path, body),
    delete: (path) => request('DELETE', path),
};

/**
 * Only allow returning to a page on THIS site after signing in (a path like
 * "/events/3"). Without this check, an attacker could send people a login
 * link that forwards them to a fake website afterwards ("open redirect").
 */
export function safeReturnPath(path, fallback = '/dashboard') {
    if (typeof path === 'string' && /^\/(?!\/)[^\s\\]*$/.test(path)) {
        return path;
    }
    return fallback;
}
