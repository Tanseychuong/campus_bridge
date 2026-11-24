/*
 * Cards and the Save / I'm interested / Follow button.
 *
 * Cards are kept deliberately short: a label, the title, who it is from and
 * one key fact. Everything else lives on the detail page, one click away.
 */
import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import { useSession } from '../session.jsx';
import { Icon } from './icons.jsx';
import { DeadlineBadge, OrgAvatar } from './ui.jsx';

/**
 * The campus an opportunity or event belongs to, in very small text under the
 * organization's name. No campus means an outside provider: open to every campus.
 */
export function CampusLine({ name }) {
    return (
        <p className="card-campus">
            <Icon name="campus" />
            <span>{name ? `${name} Campus` : 'All campuses'}</span>
        </p>
    );
}

const TOGGLE_LABELS = {
    opportunity: ['Save', 'Saved', 'Save '],
    event: ["I'm interested", 'Interested', "I'm interested in "],
    organization: ['Follow', 'Following', 'Follow '],
};

/**
 * Save / "I'm interested" / Follow.
 * kind: 'opportunity', 'event' or 'organization'; isOn: already saved/followed?
 * onChange(isOn) lets a page refresh itself (e.g. the Saved page removes the row).
 */
export function ToggleButton({ kind, id, title, isOn: initiallyOn, size = 'small', onChange }) {
    const [isOn, setIsOn] = useState(initiallyOn);
    const [busy, setBusy] = useState(false);
    useEffect(() => setIsOn(initiallyOn), [initiallyOn]); // fresh data from the server wins
    const { user, notify } = useSession();
    const navigate = useNavigate();
    const location = useLocation();
    const [offText, onText, accessiblePrefix] = TOGGLE_LABELS[kind];

    async function handleClick() {
        if (!user) {
            const back = encodeURIComponent(location.pathname + location.search);
            navigate(`/login?return=${back}`, { state: { notice: 'Please sign in to save items and follow organizations.' } });
            return;
        }
        setBusy(true);
        try {
            const answer = await api.post('/api/toggle', { kind, id });
            setIsOn(answer.is_on);
            notify(answer.message);
            onChange?.(answer.is_on);
        } catch (error) {
            notify(error.message, 'error');
        } finally {
            setBusy(false);
        }
    }

    return (
        <button
            type="button"
            className={`button button-${isOn ? 'primary' : 'secondary'} button-${size}`}
            aria-pressed={isOn}
            aria-label={accessiblePrefix + title}
            disabled={busy}
            onClick={handleClick}
        >
            {isOn ? onText : offText}
        </button>
    );
}

export function OpportunityCard({ opportunity: o }) {
    return (
        <article className="card item-card">
            <p className="card-kicker">{o.category_label}</p>
            <h3 className="card-title"><Link to={`/opportunities/${o.id}`}>{o.title}</Link></h3>
            <p className="card-subtitle">{o.provider}</p>
            <CampusLine name={o.campus_name} />
            <div className="card-footer">
                <DeadlineBadge deadline={o.deadline} />
                <ToggleButton kind="opportunity" id={o.id} title={o.title} isOn={o.is_saved} />
            </div>
        </article>
    );
}

export function EventCard({ event: e }) {
    return (
        <article className="card item-card">
            <p className="card-kicker">
                {e.category_label}
                {e.is_campus_only && <span className="campus-only">{e.campus_name} only</span>}
            </p>
            <h3 className="card-title"><Link to={`/events/${e.id}`}>{e.title}</Link></h3>
            <p className="card-subtitle">{e.organization_name}</p>
            <CampusLine name={e.campus_name} />
            <div className="card-footer">
                <span className="card-when">{e.when}</span>
                <ToggleButton kind="event" id={e.id} title={e.title} isOn={e.is_saved} />
            </div>
        </article>
    );
}

export function OrganizationCard({ organization: org }) {
    return (
        <article className="card item-card">
            <div className="org-heading">
                <OrgAvatar organization={org} />
                <div>
                    <h3 className="card-title"><Link to={`/organizations/${org.id}`}>{org.name}</Link></h3>
                    <p className="card-subtitle">{org.category_label}{org.campus_name ? ` · ${org.campus_name}` : ''}</p>
                </div>
            </div>
            <p className="card-text clamp-2">{org.summary}</p>
            <div className="card-footer card-footer-end">
                <ToggleButton kind="organization" id={org.id} title={org.name} isOn={org.is_following} />
            </div>
        </article>
    );
}

/** Any search result: picks the right card for its type. */
export function ItemCard({ item }) {
    if (item.type === 'opportunity') {
        return <OpportunityCard opportunity={item} />;
    }
    if (item.type === 'event') {
        return <EventCard event={item} />;
    }
    return <OrganizationCard organization={item} />;
}

/** A recommended item. The main reason is shown; the full score breakdown is one click away. */
export function RecommendationCard({ recommendation }) {
    const { item, reasons, score, type } = recommendation;
    const isOpportunity = type === 'opportunity';
    return (
        <article className="card item-card recommendation" data-testid="recommendation">
            <p className="card-kicker">{item.category_label}</p>
            <h3 className="card-title">
                <Link to={isOpportunity ? `/opportunities/${item.id}` : `/events/${item.id}`}>{item.title}</Link>
            </h3>
            <p className="card-subtitle">{isOpportunity ? item.provider : item.organization_name}</p>
            <CampusLine name={item.campus_name} />
            {reasons.length > 0 && (
                <details className="why-box">
                    <summary>{reasons[0].text}</summary>
                    <ul className="why-list">
                        {reasons.map((reason) => (
                            <li key={reason.text}>
                                <span>{reason.text}</span>
                                <strong className={reason.points < 0 ? 'text-danger' : 'text-brand'}>{reason.points > 0 ? `+${reason.points}` : reason.points}</strong>
                            </li>
                        ))}
                        <li className="why-total"><span>Score</span><strong>{score}</strong></li>
                    </ul>
                </details>
            )}
            <div className="card-footer">
                <span className="muted small">{isOpportunity ? item.deadline.label : item.when}</span>
                <ToggleButton kind={type} id={item.id} title={item.title} isOn={false} />
            </div>
        </article>
    );
}

/** A published study resource with its download button. */
export function ResourceCard({ resource: r }) {
    return (
        <article className="card item-card">
            <p className="card-kicker">{r.extension} · {r.size}</p>
            <h3 className="card-title">{r.title}</h3>
            <p className="card-subtitle">{r.uploader_name} · {r.organization_name}</p>
            <p className="card-text clamp-2">{r.description}</p>
            <div className="card-footer">
                <span className="muted small">{r.license}</span>
                {/* A normal link: the browser downloads the file directly from the API. */}
                <a className="button button-secondary button-small" href={`/api/resources/${r.id}/download`} aria-label={`Download ${r.title}`}>Download</a>
            </div>
        </article>
    );
}
