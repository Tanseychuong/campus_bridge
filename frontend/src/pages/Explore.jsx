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
const SORTS = [['relevance', 'Best match'], ['soonest', 'Soonest'], ['newest', 'Newest']];
const WITHIN = [['any', 'Any time'], ['7', 'Within 7 days'], ['30', 'Within 30 days']];
const WITHIN_SHORT = [['any', 'Any time'], ['7', '7 days'], ['30', '30 days']]; // the filter buttons are narrow

/** The filters in use, as [{key, label, remove: parameters that switch it off}], for the chips above the results. */
function activeFilters(filters, categoryLists, meta) {
    const labelOf = (list, value) => list?.find((option) => option.value === value)?.label ?? value;
    const active = [];
    if (filters.category && filters.type !== 'all') {
        active.push({ key: 'category', label: labelOf(categoryLists[filters.type], filters.category), remove: { category: '' } });
    }
    if (filters.mode) {
        active.push({ key: 'mode', label: labelOf(meta.location_modes, filters.mode), remove: { mode: '' } });
    }
    if (filters.within && filters.within !== 'any') {
        active.push({ key: 'within', label: WITHIN.find(([value]) => value === filters.within)?.[1] ?? '', remove: { within: '' } });
    }
    for (const interest of filters.interests) {
        active.push({
            key: `interest-${interest}`,
            label: labelOf(meta.interests, interest),
            remove: { interest: filters.interests.filter((i) => i !== interest) },
        });
    }
    return active;
}

/** One titled section of the filters card. */
function FilterGroup({ title, id, children }) {
    return (
        <fieldset className="filter-group" aria-labelledby={id}>
            <legend className="filter-group-title" id={id}>{title}</legend>
            {children}
        </fieldset>
    );
}

/** Radio buttons shown as pills: one choice out of a few. */
function OptionPills({ name, options, value, onChange }) {
    return (
        <div className="option-pills">
            {options.map(([optionValue, label]) => (
                <label className="option-pill" key={optionValue}>
                    <input type="radio" name={name} value={optionValue} checked={value === optionValue} onChange={() => onChange(optionValue)} />
                    <span>{label}</span>
                </label>
            ))}
        </div>
    );
}

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

    function clearFilters() {
        update({ interest: [], category: '', within: '', mode: '' });
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
                                            <div className="filters-head">
                                                <h2 className="filters-title">
                                                    Filters{data.active_filter_count > 0 && <span className="filters-count">{data.active_filter_count}</span>}
                                                </h2>
                                                {data.active_filter_count > 0 && (
                                                    <button type="button" className="link" onClick={clearFilters}>Clear all</button>
                                                )}
                                            </div>

                                            <FilterGroup title="Sort by" id="sort-title">
                                                <OptionPills name="sort" options={SORTS} value={filters.sort}
                                                    onChange={(value) => update({ sort: value === 'relevance' ? '' : value })} />
                                            </FilterGroup>

                                            {filters.type !== 'all' && (
                                                <FilterGroup title="Category" id="category-title">
                                                    <label className="sr-only" htmlFor="category">Category</label>
                                                    <select id="category" value={filters.category} onChange={(event) => update({ category: event.target.value })}>
                                                        <option value="">All categories</option>
                                                        {categoryLists[filters.type].map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                                    </select>
                                                </FilterGroup>
                                            )}

                                            {filters.type === 'opportunity' && (
                                                <FilterGroup title="Location" id="mode-title">
                                                    <label className="sr-only" htmlFor="mode">Location</label>
                                                    <select id="mode" value={filters.mode} onChange={(event) => update({ mode: event.target.value })}>
                                                        <option value="">Any location</option>
                                                        {meta.location_modes.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                                    </select>
                                                </FilterGroup>
                                            )}

                                            {filters.type !== 'organization' && (
                                                <FilterGroup title="Deadline or date" id="within-title">
                                                    <OptionPills name="within" options={WITHIN_SHORT} value={filters.within}
                                                        onChange={(value) => update({ within: value === 'any' ? '' : value })} />
                                                </FilterGroup>
                                            )}

                                            <FilterGroup title="Interests" id="interests-title">
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
                                            </FilterGroup>
                                        </form>
                                    </details>
                                </aside>

                                <section aria-labelledby="results-heading">
                                    <h2 id="results-heading" className="results-heading" aria-live="polite">
                                        {data.results.length} {data.results.length === 1 ? 'result' : 'results'}
                                        {filters.q !== '' && <> for “{filters.q}”</>}
                                    </h2>

                                    {/* The filters in use, each removable with one click. */}
                                    {(() => {
                                        const active = activeFilters(filters, categoryLists, meta);
                                        return active.length > 0 && (
                                            <ul className="active-filters" aria-label="Filters in use">
                                                {active.map(({ key, label, remove }) => (
                                                    <li key={key}>
                                                        <button type="button" className="active-filter" onClick={() => update(remove)} aria-label={`Remove filter: ${label}`}>
                                                            {label} <span aria-hidden="true">×</span>
                                                        </button>
                                                    </li>
                                                ))}
                                            </ul>
                                        );
                                    })()}

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
