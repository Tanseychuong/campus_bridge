/*
 * Explore: one search across opportunities, events and organizations.
 *
 * All search settings live in the address bar (/explore?q=data&type=event),
 * so a search can be bookmarked or shared, and Back/Refresh keep it. The
 * server checks every value again (read_explore_filters in search.php).
 */
import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ItemCard } from '../components/cards.jsx';
import { ApiView, EmptyState, PageHeader } from '../components/ui.jsx';
import { useApi, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

const TABS = [['all', 'All'], ['opportunity', 'Opportunities'], ['event', 'Events'], ['organization', 'Organizations']];
const SORTS = [['relevance', 'Best match'], ['soonest', 'Soonest deadline / date'], ['newest', 'Newest']];
const WITHIN = [['any', 'Any time'], ['7', 'Within 7 days'], ['30', 'Within 30 days']];

/** The address-bar parameters, as the PHP API expects them (lists are sent as interest[]=a&interest[]=b). */
function apiQuery(params) {
    const query = new URLSearchParams();
    for (const [name, value] of params) {
        query.append(name === 'interest' ? 'interest[]' : name, value);
    }
    return query.toString();
}

export function Explore() {
    useTitle('Explore');
    const [params, setParams] = useSearchParams();
    const result = useApi(`/api/explore?${apiQuery(params)}`);
    const { meta } = useSession();
    const [searchText, setSearchText] = useState(params.get('q') || '');
    const [filtersOpen, setFiltersOpen] = useState(() => window.matchMedia('(min-width: 901px)').matches);

    useEffect(() => setSearchText(params.get('q') || ''), [params]);

    /** Changes some parameters and keeps the rest. An empty value removes the parameter. */
    function update(changes) {
        const next = new URLSearchParams(params);
        for (const [name, value] of Object.entries(changes)) {
            next.delete(name);
            for (const item of [].concat(value)) {
                if (item !== '' && item !== null && item !== undefined) {
                    next.append(name, item);
                }
            }
        }
        setParams(next);
    }

    function tabLink(type) {
        const next = new URLSearchParams(params);
        ['type', 'category', 'mode'].forEach((name) => next.delete(name)); // category and location depend on the tab
        if (type !== 'all') {
            next.set('type', type);
        }
        const query = next.toString();
        return query ? `/explore?${query}` : '/explore';
    }

    return (
        <div className="container">
            <PageHeader eyebrow="Unified discovery" title="Explore" description="One search across opportunities, campus events and student organizations." />

            <form className="search-form" role="search" onSubmit={(event) => { event.preventDefault(); update({ q: searchText.trim() }); }}>
                <label className="sr-only" htmlFor="site-search">Search opportunities, events and organizations</label>
                <input id="site-search" type="search" name="q" value={searchText} onChange={(event) => setSearchText(event.target.value)} maxLength={100} placeholder="Try “internship”, “hackathon” or “climate”" />
                <button className="button button-primary" type="submit">Search</button>
            </form>

            <ApiView result={result}>
                {(data) => {
                    const filters = data.filters; // the values the server actually applied
                    const categoryLists = { opportunity: meta.opportunity_categories, event: meta.event_categories, organization: meta.organization_categories };
                    return (
                        <>
                            <nav className="tabs" aria-label="Result types">
                                {TABS.map(([type, label]) => (
                                    <Link key={type} className="tab" to={tabLink(type)} aria-current={filters.type === type ? 'page' : undefined}>
                                        {label} <span className="tab-count">{data.counts[type]}</span>
                                    </Link>
                                ))}
                            </nav>

                            <div className="explore-layout">
                                <aside>
                                    <details className="filters-toggle" open={filtersOpen || data.active_filter_count > 0} onToggle={(event) => setFiltersOpen(event.target.open)}>
                                        <summary className="button button-secondary">Filters{data.active_filter_count > 0 ? ` (${data.active_filter_count})` : ''}</summary>

                                        {/* Every change is applied straight away. */}
                                        <form className="card filters" onSubmit={(event) => event.preventDefault()}>
                                            <div className="field">
                                                <label htmlFor="sort">Sort by</label>
                                                <select id="sort" value={filters.sort} onChange={(event) => update({ sort: event.target.value === 'relevance' ? '' : event.target.value })}>
                                                    {SORTS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                                </select>
                                            </div>

                                            {filters.type !== 'all' && (
                                                <div className="field">
                                                    <label htmlFor="category">Category</label>
                                                    <select id="category" value={filters.category} onChange={(event) => update({ category: event.target.value })}>
                                                        <option value="">All categories</option>
                                                        {categoryLists[filters.type].map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                                    </select>
                                                </div>
                                            )}

                                            {filters.type === 'opportunity' && (
                                                <div className="field">
                                                    <label htmlFor="mode">Location</label>
                                                    <select id="mode" value={filters.mode} onChange={(event) => update({ mode: event.target.value })}>
                                                        <option value="">Any location</option>
                                                        {meta.location_modes.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                                    </select>
                                                </div>
                                            )}

                                            {filters.type !== 'organization' && (
                                                <fieldset className="field">
                                                    <legend>Deadline or date</legend>
                                                    {WITHIN.map(([value, label]) => (
                                                        <label className="radio" key={value}>
                                                            <input type="radio" name="within" value={value} checked={filters.within === value} onChange={() => update({ within: value === 'any' ? '' : value })} /> {label}
                                                        </label>
                                                    ))}
                                                </fieldset>
                                            )}

                                            <fieldset className="field">
                                                <legend>Interests</legend>
                                                <div className="chip-list">
                                                    {meta.interests.map((interest) => {
                                                        const checked = filters.interests.includes(interest.value);
                                                        const next = checked ? filters.interests.filter((i) => i !== interest.value) : [...filters.interests, interest.value];
                                                        return (
                                                            <label className="chip" key={interest.value}>
                                                                <input type="checkbox" checked={checked} onChange={() => update({ interest: next })} />
                                                                <span>{interest.label}</span>
                                                            </label>
                                                        );
                                                    })}
                                                </div>
                                            </fieldset>

                                            {data.active_filter_count > 0 && (
                                                <div className="button-row">
                                                    <button type="button" className="link" onClick={() => update({ interest: [], category: '', within: '', mode: '' })}>Clear filters</button>
                                                </div>
                                            )}
                                        </form>
                                    </details>
                                </aside>

                                <section aria-labelledby="results-heading">
                                    <h2 id="results-heading" className="results-heading" aria-live="polite">
                                        {data.results.length} {data.results.length === 1 ? 'result' : 'results'}
                                        {filters.q !== '' && <> for “{filters.q}”</>}
                                    </h2>

                                    {data.results.length === 0 ? (
                                        <EmptyState
                                            title="No matches found"
                                            description="Try a shorter search, check the spelling or remove some filters. Closed opportunities and past events are hidden."
                                            action={<Link className="button button-secondary" to="/explore">Reset search</Link>}
                                        />
                                    ) : (
                                        <ul className="grid grid-2 plain-list">
                                            {data.results.map((item) => <li key={`${item.type}-${item.id}`}><ItemCard item={item} /></li>)}
                                        </ul>
                                    )}
                                </section>
                            </div>
                        </>
                    );
                }}
            </ApiView>
        </div>
    );
}
