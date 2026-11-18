/*
 * Lists with a category filter: campus events (grouped by day) and student organizations.
 */
import { Link, useSearchParams } from 'react-router-dom';
import { EventCard, OrganizationCard } from '../components/cards.jsx';
import { ApiView, EmptyState, PageHeader } from '../components/ui.jsx';
import { useApi, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

/** "All · Academic · Career · ..." links that set ?category= */
function CategoryTabs({ base, label, categories, current }) {
    return (
        <nav className="tabs" aria-label={label}>
            <Link className="tab" to={base} aria-current={current === '' ? 'page' : undefined}>All</Link>
            {categories.map((option) => (
                <Link key={option.value} className="tab" to={`${base}?category=${option.value}`} aria-current={current === option.value ? 'page' : undefined}>
                    {option.label}
                </Link>
            ))}
        </nav>
    );
}

export function Events() {
    useTitle('Campus events');
    const [params] = useSearchParams();
    const category = params.get('category') || '';
    const result = useApi(`/api/events?category=${encodeURIComponent(category)}`);
    const { meta } = useSession();

    return (
        <div className="container">
            <PageHeader
                eyebrow="What’s on"
                title="Campus events"
                description="Upcoming events from student organizations. Mark the ones you’re interested in to build your calendar."
                actions={<Link className="button button-secondary" to="/calendar">My calendar</Link>}
            />
            <ApiView result={result}>
                {(data) => (
                    <>
                        <CategoryTabs base="/events" label="Event categories" categories={meta.event_categories} current={data.category} />
                        {data.days.length === 0 ? (
                            <EmptyState title="No upcoming events in this category" action={<Link className="button button-secondary" to="/events">Show all events</Link>} />
                        ) : (
                            data.days.map((day) => (
                                <section className="day-group" aria-labelledby={`day-${day.key}`} key={day.key}>
                                    <h2 className="day-heading" id={`day-${day.key}`}>{day.heading}</h2>
                                    <div className="grid grid-3">{day.events.map((event) => <EventCard key={event.id} event={event} />)}</div>
                                </section>
                            ))
                        )}
                    </>
                )}
            </ApiView>
        </div>
    );
}

export function Organizations() {
    useTitle('Student organizations');
    const [params] = useSearchParams();
    const category = params.get('category') || '';
    const result = useApi(`/api/organizations?category=${encodeURIComponent(category)}`);
    const { meta } = useSession();

    return (
        <div className="container">
            <PageHeader
                eyebrow="Directory"
                title="Student organizations"
                description="Find clubs and societies that match your interests. Follow them to see their events in your recommendations."
            />
            <ApiView result={result}>
                {(data) => (
                    <>
                        <CategoryTabs base="/organizations" label="Organization categories" categories={meta.organization_categories} current={data.category} />
                        <p className="muted results-heading" aria-live="polite">
                            {data.organizations.length} {data.organizations.length === 1 ? 'organization' : 'organizations'}
                        </p>
                        <ul className="grid grid-3 plain-list">
                            {data.organizations.map((org) => <li key={org.id}><OrganizationCard organization={org} /></li>)}
                        </ul>
                    </>
                )}
            </ApiView>
        </div>
    );
}
