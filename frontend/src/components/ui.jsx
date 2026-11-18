/*
 * Small reusable pieces of the page ("components").
 *
 * React escapes every value written as {text}, so text typed by a user can
 * never become HTML or JavaScript (no Cross-Site Scripting). We never use
 * dangerouslySetInnerHTML anywhere in CampusBridge.
 */
import { Link, Navigate, useLocation } from 'react-router-dom';

/** The CampusBridge logo: an arch (a bridge) joining two pillars. */
export function LogoMark() {
    return (
        <svg className="logo-mark" viewBox="0 0 32 32" aria-hidden="true">
            <rect width="32" height="32" rx="9" fill="#2747bd" />
            <path d="M6 22c2.5-7 6-10.5 10-10.5S23.5 15 26 22" fill="none" stroke="#efb23c" strokeWidth="2.6" strokeLinecap="round" />
            <path d="M6 22h20" stroke="#fff" strokeWidth="2.2" strokeLinecap="round" />
            <path d="M11 16.5V22M16 11.5V22M21 16.5V22" stroke="#fff" strokeWidth="1.6" strokeLinecap="round" opacity=".85" />
        </svg>
    );
}

/** A small coloured label. tone: neutral, brand, gold, success, warning, danger */
export function Badge({ tone = 'neutral', children }) {
    return <span className={`badge badge-${tone}`}>{children}</span>;
}

/** "5 days left", coloured by urgency (the server decides the text and colour). */
export function DeadlineBadge({ deadline }) {
    return <Badge tone={deadline.tone}>{deadline.label}</Badge>;
}

/** The interest tags of an item. */
export function InterestBadges({ interests }) {
    return interests.map((interest) => <Badge key={interest.slug}>{interest.label}</Badge>);
}

/** Title block at the top of most pages. */
export function PageHeader({ eyebrow, title, description, actions }) {
    return (
        <div className="page-header">
            <div>
                {eyebrow && <p className="eyebrow">{eyebrow}</p>}
                <h1>{title}</h1>
                {description && <p className="lead">{description}</p>}
            </div>
            {actions && <div className="page-actions">{actions}</div>}
        </div>
    );
}

/** A friendly message when there is nothing to show. */
export function EmptyState({ title, description, action }) {
    return (
        <div className="card empty-state">
            <h2 className="empty-title">{title}</h2>
            {description && <p className="muted">{description}</p>}
            {action && <div className="empty-action">{action}</div>}
        </div>
    );
}

/** An information or warning box. */
export function Notice({ title, tone = 'info', children }) {
    return (
        <div className={`notice notice-${tone}`}>
            {title && <p className="notice-title">{title}</p>}
            <div>{children}</div>
        </div>
    );
}

/** An organization's coloured monogram (we never show real club logos). */
export function OrgAvatar({ organization, size = 'medium' }) {
    return (
        <svg className={`avatar avatar-${size}`} viewBox="0 0 48 48" aria-hidden="true">
            <rect width="48" height="48" rx="12" fill={organization.color} />
            <text x="24" y="29" textAnchor="middle" fontSize="13" fontWeight="700" fill="#fff">{organization.short_name}</text>
        </svg>
    );
}

/* ------------------------------------------------------------------
 * Forms
 * ------------------------------------------------------------------ */

/** A field's error message, with an id the input points to. */
export function FieldError({ errors, field }) {
    if (!errors?.[field]) {
        return null;
    }
    return <p className="field-error" id={`${field}-error`}>{errors[field]}</p>;
}

/** Extra attributes for an input that has an error, so screen readers announce it. */
export function errorProps(errors, field) {
    return errors?.[field] ? { 'aria-invalid': true, 'aria-describedby': `${field}-error` } : {};
}

/** "Please fix 2 fields below." */
export function FormAlert({ message }) {
    if (!message) {
        return null;
    }
    return <p className="form-alert" role="alert">{message}</p>;
}

/** The interest checkboxes used by several forms. */
export function InterestChips({ interests, selected, onChange, name = 'interests' }) {
    const toggle = (value) => {
        onChange(selected.includes(value) ? selected.filter((v) => v !== value) : [...selected, value]);
    };
    return (
        <div className="chip-list">
            {interests.map((interest) => (
                <label className="chip" key={interest.value}>
                    <input type="checkbox" name={name} value={interest.value} checked={selected.includes(interest.value)} onChange={() => toggle(interest.value)} />
                    <span>{interest.label}</span>
                </label>
            ))}
        </div>
    );
}

/* ------------------------------------------------------------------
 * Loading and errors
 * ------------------------------------------------------------------ */

export function Loading() {
    return (
        <div className="container">
            <p className="muted" role="status">Loading…</p>
        </div>
    );
}

/** What to show when the API answered with an error. */
export function ErrorView({ error }) {
    const location = useLocation();

    if (error.status === 401) {
        // Not signed in: go to the sign-in page, then come back here afterwards.
        const back = encodeURIComponent(location.pathname + location.search);
        return <Navigate to={`/login?return=${back}`} replace state={{ notice: error.message }} />;
    }
    if (error.status === 404) {
        return <NotFound />;
    }
    const title = error.status === 403 ? 'Not allowed' : 'Something went wrong';
    return (
        <div className="container page-narrow">
            <EmptyState title={title} description={error.message} action={<Link className="button button-secondary" to="/">Go to the home page</Link>} />
        </div>
    );
}

export function NotFound() {
    return (
        <div className="container page-narrow">
            <EmptyState
                title="Page not found"
                description="This page does not exist, or the item is no longer published."
                action={<Link className="button button-primary" to="/explore">Explore CampusBridge</Link>}
            />
        </div>
    );
}

/**
 * Shows loading / error / content for data from useApi():
 *   <ApiView result={result}>{(data) => <p>{data.title}</p>}</ApiView>
 */
export function ApiView({ result, children }) {
    if (result.error) {
        return <ErrorView error={result.error} />;
    }
    if (!result.data) {
        return <Loading />;
    }
    return children(result.data);
}
