/*
 * Detail pages: one opportunity, one event, one organization.
 */
import { Link, useParams } from 'react-router-dom';
import { EventCard, OpportunityCard, ToggleButton } from '../components/cards.jsx';
import { ApiView, Badge, DeadlineBadge, EmptyState, InterestBadges, Notice, OrgAvatar } from '../components/ui.jsx';
import { useApi, useTitle } from '../hooks.js';

/** Text with its line breaks kept (see .prose-lines in app.css). */
function Prose({ text }) {
    return <p className="prose prose-lines">{text}</p>;
}

export function OpportunityPage() {
    const { id } = useParams();
    const result = useApi(`/api/opportunities/${encodeURIComponent(id)}`);
    useTitle(result.data?.opportunity.title);

    return (
        <ApiView result={result}>
            {({ opportunity: o, similar }) => (
                <div className="container">
                    <p className="back-link"><Link to="/explore?type=opportunity">← All opportunities</Link></p>

                    <div className="detail-layout">
                        <article>
                            <div className="badge-row">
                                <Badge tone="brand">{o.category_label}</Badge>
                                <DeadlineBadge deadline={o.deadline} />
                                <Badge tone="gold">Sample data</Badge>
                            </div>
                            <h1 className="detail-title">{o.title}</h1>
                            <p className="detail-subtitle">
                                {o.organization_id ? <Link to={`/organizations/${o.organization_id}`}>{o.provider}</Link> : o.provider}
                            </p>

                            {o.is_closed && (
                                <Notice title="This opportunity has closed" tone="warning">The deadline has passed. It is kept here for reference only.</Notice>
                            )}

                            <section className="detail-section">
                                <h2>About this opportunity</h2>
                                <Prose text={o.description} />
                            </section>

                            <section className="detail-section">
                                <h2>Eligibility</h2>
                                {o.eligibility.length === 0 ? (
                                    <p className="muted">The provider has not published eligibility details. Check the official source before applying.</p>
                                ) : (
                                    <ul className="check-list">{o.eligibility.map((line) => <li key={line}>{line}</li>)}</ul>
                                )}
                            </section>
                        </article>

                        <aside>
                            <div className="card side-card">
                                <dl className="details">
                                    <div><dt>Deadline</dt><dd>{o.deadline.full}</dd></div>
                                    <div><dt>Location</dt><dd>{o.location}</dd></div>
                                    <div><dt>Interests</dt><dd className="badge-row"><InterestBadges interests={o.interests} /></dd></div>
                                </dl>
                                <div className="stack">
                                    {!o.is_closed && o.source.url && (
                                        // rel="noopener noreferrer" stops the other website from controlling this tab.
                                        <a className="button button-primary" href={o.source.url} target="_blank" rel="noopener noreferrer">
                                            Apply on official site <span className="sr-only">(opens {o.source.host} in a new tab)</span>
                                        </a>
                                    )}
                                    <ToggleButton kind="opportunity" id={o.id} title={o.title} isOn={o.is_saved} size="medium" />
                                </div>
                            </div>
                            <p className="muted small side-note">
                                Source: <strong>{o.source.name}</strong> ({o.source.host}). Published {o.published}. This is fictional
                                sample data; the link points to a reserved example domain.
                            </p>
                        </aside>
                    </div>

                    {similar.length > 0 && (
                        <section className="section" aria-labelledby="similar-heading">
                            <h2 id="similar-heading" className="section-title">Similar opportunities</h2>
                            <div className="grid grid-3">{similar.map((other) => <OpportunityCard key={other.id} opportunity={other} />)}</div>
                        </section>
                    )}
                </div>
            )}
        </ApiView>
    );
}

export function EventPage() {
    const { id } = useParams();
    const result = useApi(`/api/events/${encodeURIComponent(id)}`);
    useTitle(result.data?.event.title);

    return (
        <ApiView result={result}>
            {({ event: e, organization, more }) => (
                <div className="container">
                    <p className="back-link"><Link to="/events">← All events</Link></p>

                    <div className="detail-layout">
                        <article>
                            <div className="badge-row">
                                <Badge tone="gold">{e.category_label}</Badge>
                                <InterestBadges interests={e.interests} />
                                <Badge tone="gold">Sample data</Badge>
                            </div>
                            <h1 className="detail-title">{e.title}</h1>
                            {organization && (
                                <p className="org-link">
                                    <Link to={`/organizations/${organization.id}`}><OrgAvatar organization={organization} size="small" /> {organization.name}</Link>
                                </p>
                            )}

                            {e.has_ended && <Notice title="This event has ended" tone="warning">It is kept here for reference only.</Notice>}

                            <section className="detail-section">
                                <h2>About this event</h2>
                                <Prose text={e.description} />
                            </section>
                        </article>

                        <aside>
                            <div className="card side-card">
                                <dl className="details">
                                    <div><dt>When</dt><dd>{e.when} (Accra time)</dd></div>
                                    <div><dt>Venue</dt><dd>{e.venue}</dd></div>
                                    <div><dt>Capacity</dt><dd>{e.capacity}</dd></div>
                                    <div><dt>Open to</dt><dd>{e.open_to}</dd></div>
                                    <div><dt>Registration</dt><dd>{e.registration === 'rsvp' ? 'RSVP required' : 'Open entry — just turn up'}</dd></div>
                                    <div><dt>Contact</dt><dd className="break-all">{e.contact}</dd></div>
                                </dl>
                                {!e.has_ended && <ToggleButton kind="event" id={e.id} title={e.title} isOn={e.is_saved} size="medium" />}
                            </div>
                            <Notice>
                                “I’m interested” adds the event to <Link to="/calendar">your calendar</Link>. It is <strong>not</strong> a reservation —{' '}
                                {e.registration === 'rsvp' ? 'RSVP with the organizer to secure a place.' : 'no registration is needed.'}
                            </Notice>
                        </aside>
                    </div>

                    {more.length > 0 && organization && (
                        <section className="section" aria-labelledby="more-heading">
                            <h2 id="more-heading" className="section-title">More from {organization.name}</h2>
                            <div className="grid grid-3">{more.map((other) => <EventCard key={other.id} event={other} />)}</div>
                        </section>
                    )}
                </div>
            )}
        </ApiView>
    );
}

export function OrganizationPage() {
    const { id } = useParams();
    const result = useApi(`/api/organizations/${encodeURIComponent(id)}`);
    useTitle(result.data?.organization.name);

    return (
        <ApiView result={result}>
            {({ organization: org, events, opportunities }) => (
                <div className="container">
                    <p className="back-link"><Link to="/organizations">← All organizations</Link></p>

                    <header className="card org-header">
                        <OrgAvatar organization={org} size="large" />
                        <div className="grow">
                            <div className="badge-row">
                                <Badge tone="brand">{org.category_label}</Badge> <Badge>{org.campus_name} Campus</Badge> <Badge tone="gold">Sample data</Badge>
                            </div>
                            <h1 className="detail-title">{org.name}</h1>
                            <p className="muted">{org.summary}</p>
                        </div>
                        <ToggleButton kind="organization" id={org.id} title={org.name} isOn={org.is_following} size="medium" />
                    </header>

                    <div className="detail-layout">
                        <div>
                            <section className="detail-section">
                                <h2>About</h2>
                                <Prose text={org.description} />
                                <h3>What we do</h3>
                                <ul className="tile-list">{org.activities.map((activity) => <li key={activity}>{activity}</li>)}</ul>
                            </section>

                            <section className="detail-section" aria-labelledby="events-heading">
                                <h2 id="events-heading">Upcoming events</h2>
                                {events.length === 0 ? (
                                    <EmptyState title="No upcoming events" description="Follow this organization so its future events appear in your recommendations." />
                                ) : (
                                    <div className="grid grid-2">{events.map((event) => <EventCard key={event.id} event={event} />)}</div>
                                )}
                            </section>

                            {opportunities.length > 0 && (
                                <section className="detail-section" aria-labelledby="opps-heading">
                                    <h2 id="opps-heading">Opportunities from {org.short_name}</h2>
                                    <div className="grid grid-2">{opportunities.map((o) => <OpportunityCard key={o.id} opportunity={o} />)}</div>
                                </section>
                            )}
                        </div>

                        <aside className="card side-card">
                            <dl className="details">
                                <div><dt>How to join</dt><dd>{org.membership}</dd></div>
                                <div><dt>Meetings</dt><dd>{org.meeting_info}</dd></div>
                                <div><dt>Community</dt><dd>{org.member_count} members (sample figure) · founded {org.founded_year}</dd></div>
                                <div><dt>Contact</dt><dd className="break-all">{org.contact_email}</dd></div>
                                <div><dt>Interests</dt><dd className="badge-row"><InterestBadges interests={org.interests} /></dd></div>
                            </dl>
                        </aside>
                    </div>
                </div>
            )}
        </ApiView>
    );
}
