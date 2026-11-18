/*
 * Home page: search box, deadlines coming up, and this week's events.
 */
import { Link, useNavigate } from 'react-router-dom';
import { EventCard, OpportunityCard } from '../components/cards.jsx';
import { ApiView, Badge, Notice } from '../components/ui.jsx';
import { useApi, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

export function Home() {
    useTitle(null);
    const result = useApi('/api/home');
    const { user } = useSession();
    const navigate = useNavigate();

    function search(event) {
        event.preventDefault();
        const q = new FormData(event.target).get('q').trim();
        navigate(q ? `/explore?q=${encodeURIComponent(q)}` : '/explore');
    }

    return (
        <ApiView result={result}>
            {(data) => (
                <>
                    <section className="hero">
                        <div className="container hero-grid">
                            <div>
                                <p className="pill">Explainable recommendations · Deadline tracking</p>
                                <h1 className="hero-title">Every campus opportunity, <span className="text-brand">one bridge away.</span></h1>
                                <p className="hero-lead">
                                    Scholarships, internships, events and student organizations — in one place, ranked for
                                    your interests, with the deadlines you can’t afford to miss.
                                </p>
                                <form className="search-form search-form-large" role="search" onSubmit={search}>
                                    <label className="sr-only" htmlFor="hero-search">Search opportunities, events and organizations</label>
                                    <input id="hero-search" type="search" name="q" maxLength={100} placeholder="Try “internship”, “hackathon” or “climate”" />
                                    <button className="button button-primary" type="submit">Search</button>
                                </form>
                                <div className="button-row">
                                    <Link className="button button-gold" to={user ? '/preferences' : '/register'}>Personalise my feed →</Link>
                                    <Link className="button button-secondary" to="/explore?type=opportunity">Browse opportunities</Link>
                                </div>
                            </div>

                            <div className="card hero-card">
                                <div className="row-between">
                                    <h2 className="small-heading">Closing soon</h2>
                                    <Badge tone="gold">Sample data</Badge>
                                </div>
                                <ul className="divided-list">
                                    {data.closing_soon.map((o) => (
                                        <li key={o.id}>
                                            <Link className="list-link" to={`/opportunities/${o.id}`}>{o.title}</Link>
                                            <span className="muted small">{o.provider} · {o.deadline.label}</span>
                                        </li>
                                    ))}
                                </ul>
                                <dl className="stats">
                                    {data.stats.map((stat) => <div key={stat.label}><dt>{stat.label}</dt><dd>{stat.value}</dd></div>)}
                                </dl>
                            </div>
                        </div>
                    </section>

                    <section className="container section" aria-labelledby="why-heading">
                        <h2 id="why-heading" className="section-title">Built for the way students actually find opportunities</h2>
                        <p className="muted">Opportunities are often scattered across email, group chats and posters, so deadlines are easy to miss. CampusBridge brings them together and helps you act on time.</p>
                        <div className="grid grid-3">
                            <div className="card feature"><h3>One search for everything</h3><p className="muted">Search opportunities, events and organizations together, with filters that stay in the address bar so you can share them.</p></div>
                            <div className="card feature"><h3>Recommendations you can understand</h3><p className="muted">Every suggestion explains why: “Matches your interests: Research” or “Deadline in 6 days”. Change your interests and the ranking changes.</p></div>
                            <div className="card feature"><h3>Deadlines and clashes, handled</h3><p className="muted">Saved deadlines are grouped by urgency, your calendar warns about overlapping events, and you can export everything to your own calendar app.</p></div>
                        </div>
                    </section>

                    <section className="container section" aria-labelledby="deadlines-heading">
                        <div className="section-heading">
                            <h2 id="deadlines-heading" className="section-title">Deadlines coming up</h2>
                            <Link className="link-arrow" to="/explore?type=opportunity&sort=soonest">See all →</Link>
                        </div>
                        <div className="grid grid-3">
                            {data.deadlines_coming_up.map((o) => <OpportunityCard key={o.id} opportunity={o} />)}
                        </div>
                    </section>

                    <section className="container section" aria-labelledby="week-heading">
                        <div className="section-heading">
                            <h2 id="week-heading" className="section-title">This week on campus</h2>
                            <Link className="link-arrow" to="/events">All events →</Link>
                        </div>
                        <div className="grid grid-3">
                            {data.this_week.map((e) => <EventCard key={e.id} event={e} />)}
                        </div>
                    </section>

                    <div className="container">
                        <Notice title="An independent student project">
                            CampusBridge was built for the CS341 Web Technologies course. It is not affiliated with or endorsed by any
                            university, and every organization, opportunity and event shown is fictional sample data. <Link to="/about">Learn more</Link>.
                        </Notice>
                    </div>
                </>
            )}
        </ApiView>
    );
}
