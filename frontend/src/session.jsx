/*
 * App-wide state, shared through React "context":
 *   user   - the signed-in user (or null for a guest)
 *   meta   - the fixed lists from the server: interests, categories, campuses...
 *   flash  - a one-time message such as "Saved: Volta STEM Scholarship"
 */
import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { api, setCsrfToken } from './api.js';

const SessionContext = createContext(null);

export function SessionProvider({ children }) {
    const [user, setUser] = useState(null);
    const [meta, setMeta] = useState(null);
    const [loadError, setLoadError] = useState(null);
    const [flash, setFlash] = useState(null);
    const location = useLocation();

    // On start: who is signed in, the CSRF token, and the fixed lists.
    useEffect(() => {
        Promise.all([api.get('/api/session'), api.get('/api/meta')])
            .then(([session, metaData]) => {
                setCsrfToken(session.csrf_token);
                setUser(session.user);
                setMeta(metaData);
            })
            .catch(setLoadError);
    }, []);

    /** After signing in, signing out or registering: store the new user and token. */
    const applySession = useCallback((answer) => {
        setCsrfToken(answer.csrf_token);
        setUser(answer.user);
    }, []);

    /**
     * Shows a message at the top of the page.
     * keepForNextPage: true when the code navigates to another page right after
     * (e.g. "Event saved" and then back to the list), so the message survives that move.
     */
    const notify = useCallback((message, type = 'success', keepForNextPage = false) => {
        setFlash({ message, type, keep: keepForNextPage });
    }, []);

    // When the page changes, a message is removed, unless it was meant for this new page.
    useEffect(() => {
        setFlash((current) => (current && current.keep ? { ...current, keep: false } : null));
    }, [location.pathname]);

    const value = { user, meta, loadError, applySession, flash, notify };
    return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession() {
    return useContext(SessionContext);
}
