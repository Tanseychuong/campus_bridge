/*
 * A signed-in student's pages: dashboard, saved items, calendar and preferences.
 * Guests are sent to the sign-in page (the API answers 401; see ErrorView).
 */
import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../api.js';
import { RecommendationCard, ToggleButton } from '../components/cards.jsx';
import { ApiView, DeadlineBadge, EmptyState, InterestChips, Notice, OrgAvatar, PageHeader } from '../components/ui.jsx';
import { useApi, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

/** The interest picker, used on the dashboard (first visit) and on Preferences. */
function InterestsForm({ initial, buttonLabel, onSaved }) {
    const { meta, notify } = useSession();
    const [selected, setSelected] = useState(initial);
    const [busy, setBusy] = useState(false);

    async function save(event) {
        event.preventDefault();
        setBusy(true);
        try {
            const answer = await api.put('/api/preferences/interests', { interests: selected });
            notify(answer.message);
            onSaved?.();
        } catch (error) {
            notify(error.message, 'error');
        } finally {
            setBusy(false);
        }
    }

    return (
        <form onSubmit={save}>
            <InterestChips interests={meta.interests} selected={selected} onChange={setSelected} />
            <button className="button button-primary" type="submit" disabled={busy}>{buttonLabel}</button>
        </form>
    );
}

export function Dashboard() {
    useTitle('My dashboard');
    const result = useApi('/api/dashboard');

    return (
        <ApiView result={result}>
            {(data) => (
                <div className="container">
                    <PageHeader eyebrow="My campus" title="Your dashboard" description="Recommendations, deadlines and events that matter to you." />

                    {!data.has_interests && (
                        <section className="card highlight-card" aria-labelledby="start-heading">
                            <h2 id="start-heading" className="small-heading">Get started: what are you interested in?</h2>
                            <p className="muted">Pick a few interests and press Save. Your recommendations will appear below.</p>
                            <InterestsForm initial={[]} buttonLabel="Save my interests" onSaved={result.reload} />
                        </section>
                    )}

                    <ul className="summary-grid plain-list" aria-label="Summary">
                        {data.summary.map((item) => (
                            <li key={item.label}>
                                <Link className={`card summary-card${item.is_alert ? ' summary-alert' : ''}`} to={item.link}>
                                    <span className="summary-value">{item.value}</span>
                                    <span className="muted small">{item.label}</span>
                                </Link>
                            </li>
                        ))}
                    </ul>

                    <section className="section" aria-labelledby="rec-heading">
                        <div className="section-heading">
                            <div>
                                <h2 id="rec-heading" className="section-title">Recommended for you</h2>
                                <p className="muted small">Ranked by a transparent rule-based score. Each card shows exactly why it was chosen.</p>
                            </div>
                            <Link className="link-arrow" to="/preferences">Tune recommendations →</Link>
                        </div>
                        {data.recommendations.length === 0 ? (
                            <EmptyState
                                title={data.has_interests ? 'Nothing new matches right now' : 'No interests selected yet'}
                                description={data.has_interests
                                    ? 'You may have saved everything that matches. Try adding more interests or following organizations.'
                                    : 'Choose interests above or follow organizations to get personalised recommendations.'}
                            />
                        ) : (
                            <div className="grid grid-3">
                                {data.recommendations.map((r) => <RecommendationCard key={`${r.type}-${r.item.id}`} recommendation={r} />)}
                            </div>
                        )}
                    </section>

                    <div className="grid grid-2 section">
                        <section aria-labelledby="deadlines-heading">
                            <div className="section-heading">
                                <h2 id="deadlines-heading" className="section-title">Your upcoming deadlines</h2>
                                <Link className="link" to="/saved">All saved</Link>
                            </div>
                            {data.upcoming_deadlines.length === 0 ? (
                                <EmptyState title="No saved deadlines" description="Save opportunities and their deadlines will be tracked here." />
                            ) : (
                                <ul className="card divided-list padded-list">
                                    {data.upcoming_deadlines.map((o) => (
                                        <li className="row-between" key={o.id}>
                                            <Link className="list-link" to={`/opportunities/${o.id}`}>{o.title}</Link>
                                            <DeadlineBadge deadline={o.deadline} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section aria-labelledby="events-heading">
                            <div className="section-heading">
                                <h2 id="events-heading" className="section-title">Coming up in your calendar</h2>
                                <Link className="link" to="/calendar">Open calendar</Link>
                            </div>
                            {data.my_events.length === 0 ? (
                                <EmptyState title="No events in your calendar" description="Mark events as “I’m interested” to plan your week." />
                            ) : (
                                <ul className="card divided-list padded-list">
                                    {data.my_events.map((event) => (
                                        <li key={event.id}>
                                            <Link className="list-link" to={`/events/${event.id}`}>{event.title}</Link>
                                            <span className="muted small">{event.when}</span>
                                            {event.clashes && <span className="clash-warning">⚠ Clashes with another event in your calendar</span>}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>

                    <section className="section" aria-labelledby="following-heading">
                        <h2 id="following-heading" className="section-title">Organizations you follow</h2>
                        {data.followed.length === 0 ? (
                            <EmptyState
                                title="You don’t follow any organizations yet"
                                description="Following an organization boosts its events and opportunities in your recommendations."
                                action={<Link className="button button-secondary" to="/organizations">Browse organizations</Link>}
                            />
                        ) : (
                            <ul className="follow-list plain-list">
                                {data.followed.map((org) => (
                                    <li key={org.id}><Link className="card follow-chip" to={`/organizations/${org.id}`}><OrgAvatar organization={org} size="small" /> {org.name}</Link></li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            )}
        </ApiView>
    );
}

export function Saved() {
    useTitle('Saved & deadlines');
    const result = useApi('/api/saved');

    return (
        <div className="container">
            <PageHeader
                eyebrow="My campus"
                title="Saved & deadlines"
                description="Everything you have saved, with deadlines grouped by urgency."
                actions={<a className="button button-secondary" href="/api/calendar.ics">Export to calendar (.ics)</a>}
            />
            <ApiView result={result}>
                {(data) => (
                    <>
                        <section className="section" aria-labelledby="saved-opps-heading">
                            <h2 id="saved-opps-heading" className="section-title">Opportunities by deadline</h2>
                            {data.deadline_groups.length === 0 ? (
                                <EmptyState
                                    title="No saved opportunities"
                                    description="Use the Save button on any opportunity to track its deadline here."
                                    action={<Link className="button button-primary" to="/explore?type=opportunity">Find opportunities</Link>}
                                />
                            ) : (
                                data.deadline_groups.map((group) => (
                                    <div key={group.bucket}>
                                        <h3 className={`bucket-heading${group.is_urgent ? ' text-danger' : ''}`}>{group.label} ({group.opportunities.length})</h3>
                                        <ul className="card divided-list padded-list">
                                            {group.opportunities.map((o) => (
                                                <li className="row-between wrap" key={o.id}>
                                                    <div>
                                                        <Link className="list-link" to={`/opportunities/${o.id}`}>{o.title}</Link>
                                                        <span className="muted small">{o.provider} · {o.category_label}</span>
                                                    </div>
                                                    <div className="row-gap">
                                                        <DeadlineBadge deadline={o.deadline} />
                                                        <ToggleButton kind="opportunity" id={o.id} title={o.title} isOn onChange={result.reload} />
                                                    </div>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ))
                            )}
                        </section>

                        <section className="section" aria-labelledby="saved-events-heading">
                            <div className="section-heading">
                                <h2 id="saved-events-heading" className="section-title">Events you’re interested in</h2>
                                <Link className="link" to="/calendar">View as calendar</Link>
                            </div>
                            {data.events.length === 0 ? (
                                <EmptyState
                                    title="No events yet"
                                    description="Mark events as “I’m interested” to add them here and to your calendar."
                                    action={<Link className="button button-primary" to="/events">Browse events</Link>}
                                />
                            ) : (
                                <ul className="card divided-list padded-list">
                                    {data.events.map((event) => (
                                        <li className="row-between wrap" key={event.id}>
                                            <div>
                                                <Link className="list-link" to={`/events/${event.id}`}>{event.title}</Link>
                                                <span className="muted small">{event.when} · {event.venue}</span>
                                            </div>
                                            <ToggleButton kind="event" id={event.id} title={event.title} isOn onChange={result.reload} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </>
                )}
            </ApiView>
        </div>
    );
}

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export function Calendar() {
    useTitle('My calendar');
    const [params] = useSearchParams();
    const month = params.get('month') || '';
    const result = useApi(`/api/calendar?month=${encodeURIComponent(month)}`);

    return (
        <div className="container">
            <PageHeader
                eyebrow="My campus"
                title="My calendar"
                description="Your saved events and deadlines in one view, with automatic clash detection. Times are in Accra time (GMT)."
            />
            <ApiView result={result}>
                {(data) => !data.has_anything ? (
                    <EmptyState
                        title="Your calendar is empty"
                        description="Mark events as “I’m interested” and save opportunities — their dates and deadlines will appear here."
                        action={<Link className="button button-primary" to="/events">Browse events</Link>}
                    />
                ) : (
                    <>
                        {data.clashes.length > 0 && (
                            <Notice tone="warning" title={`${data.clashes.length} ${data.clashes.length === 1 ? 'schedule clash' : 'schedule clashes'} detected`}>
                                <ul>
                                    {data.clashes.map((clash) => (
                                        <li key={`${clash.first.title}-${clash.second.title}`}>
                                            <strong>{clash.first.title}</strong> ({clash.first.time}) overlaps <strong>{clash.second.title}</strong> ({clash.second.time}) on {clash.day}.
                                        </li>
                                    ))}
                                </ul>
                            </Notice>
                        )}

                        <div className="row-between wrap calendar-toolbar">
                            <div className="row-gap">
                                <Link className="button button-secondary button-small" to={`/calendar?month=${data.previous}`} aria-label="Previous month">←</Link>
                                <h2 className="month-title">{data.title}</h2>
                                <Link className="button button-secondary button-small" to={`/calendar?month=${data.next}`} aria-label="Next month">→</Link>
                            </div>
                            <a className="button button-secondary" href="/api/calendar.ics">Export to calendar (.ics)</a>
                        </div>

                        {/* The grid is a visual overview. The agenda list below has the same information for screen readers. */}
                        <div className="card month-grid" aria-hidden="true">
                            {WEEKDAYS.map((weekday) => <div className="weekday" key={weekday}>{weekday}</div>)}
                            {data.weeks.flat().map((day) => (
                                <div className={`day${day.is_outside ? ' day-outside' : ''}`} key={day.key}>
                                    <span className={`day-number${day.is_today ? ' day-today' : ''}`}>{day.number}</span>
                                    {day.entries.slice(0, 3).map((entry) => (
                                        <span key={entry.link} className={`day-entry entry-${entry.kind === 'deadline' ? 'deadline' : entry.clash ? 'clash' : 'event'}`}>{entry.title}</span>
                                    ))}
                                    {day.entries.length > 3 && <span className="muted small">+{day.entries.length - 3} more</span>}
                                </div>
                            ))}
                        </div>

                        <section className="section" aria-labelledby="agenda-heading">
                            <h2 id="agenda-heading" className="section-title">Agenda for {data.title}</h2>
                            {data.agenda.length === 0 ? (
                                <p className="muted">Nothing saved in this month.</p>
                            ) : (
                                <ol className="plain-list agenda">
                                    {data.agenda.map((day) => (
                                        <li key={day.key}>
                                            <h3 className="day-heading">{day.heading}</h3>
                                            <ul className="card divided-list padded-list">
                                                {day.entries.map((entry) => (
                                                    <li key={entry.link}>
                                                        <Link className="list-link" to={entry.link}>{entry.title}</Link>
                                                        <span className="muted small">{entry.detail}</span>
                                                        {entry.clash && <span className="clash-warning">⚠ Clashes with another saved event</span>}
                                                    </li>
                                                ))}
                                            </ul>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </section>
                    </>
                )}
            </ApiView>
        </div>
    );
}

export function Preferences() {
    useTitle('Preferences');
    const result = useApi('/api/preferences');
    const { notify } = useSession();

    async function resetData() {
        if (!window.confirm('Delete all your interests, saved items and follows? This cannot be undone.')) {
            return;
        }
        try {
            const answer = await api.post('/api/preferences/reset');
            notify(answer.message, 'info');
            result.reload();
        } catch (error) {
            notify(error.message, 'error');
        }
    }

    return (
        <ApiView result={result}>
            {(data) => (
                <div className="container">
                    <PageHeader eyebrow="My campus" title="Preferences" description="Control what CampusBridge recommends to you." />

                    <div className="detail-layout">
                        <div className="stack-large">
                            <section className="card padded" aria-labelledby="interests-heading">
                                <h2 id="interests-heading" className="small-heading">Your interests</h2>
                                <p className="muted small">Recommendations earn points for every interest they match.</p>
                                {/* "key" rebuilds the form with fresh values after a reset. */}
                                <InterestsForm key={data.interests.join(',')} initial={data.interests} buttonLabel="Save interests" onSaved={result.reload} />
                            </section>

                            <section className="card padded" aria-labelledby="followed-heading">
                                <h2 id="followed-heading" className="small-heading">Organizations you follow</h2>
                                <p className="muted small">Items from followed organizations get a recommendation boost.</p>
                                {data.followed.length === 0 ? (
                                    <p className="muted">Not following anyone yet. <Link to="/organizations">Browse organizations</Link>.</p>
                                ) : (
                                    <ul className="divided-list">
                                        {data.followed.map((org) => (
                                            <li className="row-between" key={org.id}>
                                                <Link className="list-link row-gap" to={`/organizations/${org.id}`}><OrgAvatar organization={org} size="small" /> {org.name}</Link>
                                                <ToggleButton kind="organization" id={org.id} title={org.name} isOn onChange={result.reload} />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>

                            <section className="card padded" id="data" aria-labelledby="data-heading">
                                <h2 id="data-heading" className="small-heading">Your data</h2>
                                <p className="muted small">
                                    Your account stores your name, email, a hashed password, and the interests, saved items and follows you choose.
                                    Resetting removes the interests, saved items and follows; your account stays.
                                </p>
                                <button className="button button-danger button-small" type="button" onClick={resetData}>Reset my data</button>
                            </section>
                        </div>

                        <aside className="card side-card" aria-labelledby="preview-heading">
                            <h2 id="preview-heading" className="small-heading">Your top recommendations</h2>
                            <p className="muted small">Based on your saved settings.</p>
                            {data.preview.length === 0 ? (
                                <p className="muted">Select interests and save to see recommendations.</p>
                            ) : (
                                <ol className="preview-list">
                                    {data.preview.map((r, position) => (
                                        <li key={`${r.type}-${r.item.id}`}>
                                            <span className="small text-brand">#{position + 1} · score {r.score}</span>
                                            <strong>{r.item.title}</strong>
                                            <span className="muted small">{r.reasons[0].text}</span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                            <Link className="link" to="/dashboard">Open my dashboard →</Link>
                        </aside>
                    </div>
                </div>
            )}
        </ApiView>
    );
}
