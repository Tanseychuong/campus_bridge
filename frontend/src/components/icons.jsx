/*
 * Simple line icons (24×24, drawn with the current text colour).
 * They are decorative: every place that uses one also has a text label.
 */
const PATHS = {
    home: 'M3 10.5 12 3l9 7.5M5 9v11h5v-6h4v6h5V9',
    explore: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm3.5-12.5-2 5-5 2 2-5 5-2Z',
    events: 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
    organizations: 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM2.5 20a6.5 6.5 0 0 1 13 0M16 4.3a3.5 3.5 0 0 1 0 6.4M18.5 14a6.5 6.5 0 0 1 3 6',
    resources: 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5ZM4 19a2 2 0 0 1 2-2h13',
    saved: 'M6 3h12v18l-6-4.5L6 21z',
    dashboard: 'M4 4h7v7H4zM13 4h7v4h-7zM13 10h7v10h-7zM4 13h7v7H4z',
    calendar: 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4M8 14h2M14 14h2M8 17h2',
    preferences: 'M4 7h10M18 7h2M4 17h4M12 17h8M16 5v4M10 15v4',
    manage: 'M4 8h16v12H4zM9 8V5h6v3M4 13h16',
    admin: 'M12 3 4 6v6c0 4.5 3.4 8.2 8 9 4.6-.8 8-4.5 8-9V6l-8-3Z',
    approvals: 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM2.5 20a6.5 6.5 0 0 1 11-4.7M15 18l2 2 4-4.5',
    search: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14ZM20 20l-4-4',
    about: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM12 11v5M12 8h.01',
    signout: 'M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10',
    details: 'M5 12h14M13 6l6 6-6 6',
    clock: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM12 7v5l3 2',
    deadline: 'M12 21a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM12 9v4M9 2h6',
    place: 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21ZM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
    tag: 'M3 12V4h8l10 10-8 8L3 12ZM7.5 8.5h.01',
    campus: 'M3 21h18M5 21V10l7-5 7 5v11M9 21v-5h6v5',
};

export function Icon({ name, className = 'icon' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor"
            strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            <path d={PATHS[name]} />
        </svg>
    );
}

/** A round avatar with initials, coloured from the name (always the same colour for the same name). */
export function InitialsAvatar({ name, size = 'medium' }) {
    const words = (name || '?').trim().split(/\s+/);
    const initials = (words[0][0] + (words.length > 1 ? words[words.length - 1][0] : '')).toUpperCase();
    let hash = 0;
    for (const char of name || '') {
        hash = (hash * 31 + char.charCodeAt(0)) % 360;
    }
    return (
        <span className={`initials initials-${size}`} style={{ background: `hsl(${hash} 55% 42%)` }} aria-hidden="true">
            {initials}
        </span>
    );
}
