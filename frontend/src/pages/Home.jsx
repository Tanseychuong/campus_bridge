/*
 * Home page, laid out like a social feed:
 *   left   shortcuts (who you are, where to go)
 *   middle a feed of opportunities and events, each shown as a post
 *   right  deadlines closing soon, numbers at a glance, about links
 */
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { ToggleButton } from '../components/cards.jsx';
import { Icon, InitialsAvatar } from '../components/icons.jsx';
import { FooterText, MAIN_TABS, personalLinks } from '../components/Layout.jsx';
import { ApiView } from '../components/ui.jsx';
import { useApi, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

export function Home() {
    useTitle(null);
    const result = useApi('/api/home');
    const { user } = useSession();

    return (
        <ApiView result={result}>
            {(data) => {
                // Mix deadlines and events into one feed: a deadline, an event, a deadline, ...
                const opportunities = [...data.closing_soon, ...data.deadlines_coming_up];
                const feed = [];
                for (let i = 0; i < Math.max(opportunities.length, data.this_week.length); i++) {
                    if (opportunities[i]) feed.push(opportunities[i]);
                    if (data.this_week[i]) feed.push(data.this_week[i]);
                }

                return (
                    <div className="feed-layout">
                        <aside className="feed-left" aria-label="Shortcuts">
                            <Shortcuts user={user} />
                        </aside>

                        <section className="feed-main" aria-labelledby="feed-heading">
                            <h1 id="feed-heading" className="sr-only">Your CampusBridge feed</h1>
                            <Composer user={user} />
                            {feed.map((item) => <Post key={`${item.type}-${item.id}`} item={item} />)}
                            <p className="feed-end">
                                You are all caught up. <Link to="/explore">Explore everything →</Link>
                            </p>
                        </section>

                        <aside className="feed-right" aria-label="At a glance">
                            <div className="side-panel">
                                <div className="row-between">
                                    <h2 className="side-heading">Closing soon</h2>
                                    <Link className="link-arrow small" to="/explore?type=opportunity&sort=soonest">See all</Link>
                                </div>
                                <ul className="side-list">
                                    {data.closing_soon.map((o) => (
                                        <li key={o.id}>
                                            <Link to={`/opportunities/${o.id}`}>
                                                <span className="side-date"><Icon name="events" /></span>
                                                <span>
                                                    <strong>{o.title}</strong>
                                                    <span className="muted small">{o.deadline.label}</span>
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div className="side-panel">
                                <h2 className="side-heading">On CampusBridge</h2>
                                <dl className="side-stats">
                                    {data.stats.map((stat) => <div key={stat.label}><dt>{stat.label}</dt><dd>{stat.value}</dd></div>)}
                                </dl>
                            </div>

                            <div className="side-footer">
                                <FooterText />
                            </div>
                        </aside>
                    </div>
                );
            }}
        </ApiView>
    );
}

/** Left column: the signed-in user, then every place you can go. */
function Shortcuts({ user }) {
    const links = [...MAIN_TABS.slice(1), ...personalLinks(user)];
    return (
        <nav aria-label="Shortcuts">
            <ul className="shortcut-list">
                {user ? (
                    <li>
                        <NavLink to="/dashboard">
                            <InitialsAvatar name={user.name} size="small" />
                            <span><strong>{user.name}</strong></span>
                        </NavLink>
                    </li>
                ) : (
                    <li className="shortcut-signin">
                        <p className="small muted">Sign in to save deadlines, follow organizations and get recommendations.</p>
                        <Link className="button button-primary button-small" to="/register">Create account</Link>
                    </li>
                )}
                {links.map(([path, label, icon]) => (
                    <li key={path}>
                        <NavLink to={path}><span className="shortcut-icon"><Icon name={icon} /></span>{label}</NavLink>
                    </li>
                ))}
                <li><NavLink to="/about"><span className="shortcut-icon"><Icon name="about" /></span>About</NavLink></li>
            </ul>
        </nav>
    );
}

/** The "What's on your mind?" box at the top of the feed, as a search. */
function Composer({ user }) {
    const navigate = useNavigate();
    function search(event) {
        event.preventDefault();
        const q = new FormData(event.target).get('q').trim();
        navigate(q ? `/explore?q=${encodeURIComponent(q)}` : '/explore');
    }
    const firstName = user?.name.split(' ')[0];
    return (
        <div className="card composer">
            <form className="composer-row" role="search" onSubmit={search}>
                {user ? <InitialsAvatar name={user.name} /> : <span className="composer-icon"><Icon name="search" /></span>}
                <label className="sr-only" htmlFor="feed-search">Search opportunities, events and organizations</label>
                <input id="feed-search" type="search" name="q" maxLength={100}
                    placeholder={firstName ? `What are you looking for, ${firstName}?` : 'What are you looking for?'} />
            </form>
            <div className="composer-actions">
                <Link to="/explore?type=opportunity"><Icon name="saved" /> Opportunities</Link>
                <Link to="/events"><Icon name="events" /> Events</Link>
                <Link to="/organizations"><Icon name="organizations" /> Organizations</Link>
            </div>
        </div>
    );
}

/**
 * A small card with an icon and a value, e.g. a clock and "14 Nov 2026".
 * The label ("Posted", "When", ...) is not shown but is read out by screen
 * readers and appears as a tooltip on hover. tone colours deadlines by urgency.
 */
function Fact({ icon, label, tone, children }) {
    return (
        <li className={`fact${tone ? ` fact-${tone}` : ''}`} title={label}>
            <Icon name={icon} />
            <span className="sr-only">{label}: </span>
            <span className="fact-value">{children}</span>
        </li>
    );
}

/** One opportunity or event, shown as a post from the organization behind it. */
function Post({ item }) {
    const isOpportunity = item.type === 'opportunity';
    const from = isOpportunity ? item.provider : item.organization_name;
    const href = isOpportunity ? `/opportunities/${item.id}` : `/events/${item.id}`;
    const authorHref = item.organization_id ? `/organizations/${item.organization_id}` : href;
    return (
        <article className="card post">
            <header className="post-head">
                <Link to={authorHref} tabIndex={-1} aria-hidden="true">
                    <InitialsAvatar name={from} />
                </Link>
                <Link className="post-author" to={authorHref}>{from}</Link>
            </header>

            <div className="post-body">
                <h2 className="post-title"><Link to={href}>{item.title}</Link></h2>
                {/* One small card per fact, each with its own icon, so dates are never mixed up. */}
                <ul className="fact-list">
                    {isOpportunity ? (
                        <>
                            <Fact icon="clock" label="Posted">{item.published}</Fact>
                            <Fact icon="deadline" label="Deadline" tone={item.deadline.tone}>{item.deadline.label}</Fact>
                        </>
                    ) : (
                        <>
                            <Fact icon="events" label="When">{item.when}</Fact>
                            {item.venue && <Fact icon="place" label="Where">{item.venue}</Fact>}
                        </>
                    )}
                    <Fact icon="tag" label="Category">{item.category_label}</Fact>
                    {item.is_campus_only && <Fact icon="campus" label="Open to">{item.campus_name} only</Fact>}
                </ul>
                <p>{item.summary}</p>
            </div>

            <div className="post-actions">
                <ToggleButton kind={item.type} id={item.id} title={item.title} isOn={item.is_saved} />
                <Link className="button button-small" to={href}><Icon name="details" /> Details</Link>
            </div>
        </article>
    );
}
