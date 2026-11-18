/*
 * Small reusable React hooks.
 */
import { useCallback, useEffect, useState } from 'react';
import { api } from './api.js';

/**
 * Loads data from the API whenever `path` changes.
 *   const { data, error, reload } = useApi('/api/events?category=tech');
 * While it reloads, the previous data stays on screen (no flicker).
 */
export function useApi(path) {
    const [state, setState] = useState({ data: null, error: null });
    const [version, setVersion] = useState(0);

    useEffect(() => {
        let cancelled = false; // ignore a slow answer that arrives after the page moved on
        api.get(path).then(
            (data) => !cancelled && setState({ data, error: null }),
            (error) => !cancelled && setState({ data: null, error }),
        );
        return () => {
            cancelled = true;
        };
    }, [path, version]);

    const reload = useCallback(() => setVersion((v) => v + 1), []);
    return { ...state, reload };
}

/** Sets the text in the browser tab. */
export function useTitle(title) {
    useEffect(() => {
        document.title = title ? `${title} · CampusBridge` : 'CampusBridge — Discover opportunities, events and student organizations';
    }, [title]);
}

/** Moves keyboard focus to the first invalid field, so screen-reader users find the problem. */
export function useFocusFirstError(errors) {
    useEffect(() => {
        if (Object.keys(errors || {}).length > 0) {
            document.querySelector('[aria-invalid="true"]')?.focus();
        }
    }, [errors]);
}
