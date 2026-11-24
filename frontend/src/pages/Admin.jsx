/*
 * Administrator dashboard: what needs attention at a glance, then three tabs:
 *   Review queue    approve or reject new listings
 *   Study materials add and remove materials, with the automatic check's warnings
 *   Analytics       charts about the content on the platform
 * The open tab is kept in the address (#queue, #materials, #analytics).
 */
import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import { BarChart } from '../components/BarChart.jsx';
import { Icon } from '../components/icons.jsx';
import { BreachWarning, ScanResult } from '../components/scan.jsx';
import { ApiView, Badge, EmptyState, PageHeader } from '../components/ui.jsx';
import { useApi, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

const TABS = [['queue', 'Review queue'], ['materials', 'Study materials'], ['analytics', 'Analytics']];

const MATERIAL_FILTERS = [
    ['all', 'All', () => true],
    ['attention', 'Needs attention', (r) => Boolean(r.scan.breach_warning)],
    ['pending', 'Waiting for review', (r) => r.status === 'pending'],
    ['published', 'Public', (r) => r.status === 'published'],
    ['hidden', 'Taken down', (r) => r.status === 'hidden' || r.status === 'rejected'],
];

/** A new opportunity or event waiting for an admin's decision. */
function QueueItem({ item, onDone }) {
    const { notify } = useSession();
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const noteId = `note-${item.type}${item.id}`;
    const critical = item.flags.filter((flag) => flag.severity === 'critical').length;

    async function decide(decision) {
        setBusy(true);
        try {
            const answer = await api.post('/api/admin/review', { type: item.type, id: item.id, decision, note });
            notify(answer.message);
            onDone();
        } catch (error) {
            notify(error.message, 'error');
            setBusy(false);
        }
    }

    return (
        <li className="review-item">
            <div className="row-between wrap">
                <div>
                    <strong>{item.title}</strong>
                    <span className="muted small">{item.by} · {item.when}</span>
                </div>
                <div className="badge-row">
                    {critical > 0 && <Badge tone="danger">{critical} serious {critical === 1 ? 'issue' : 'issues'}</Badge>}
                    {item.type === 'opportunity' ? <Badge tone="brand">Opportunity</Badge> : <Badge tone="gold">Event</Badge>}
                </div>
            </div>
            <p className="queue-summary">{item.summary}</p>

            {item.flags.length === 0 ? (
                <p className="flag flag-ok">✓ No automated issues found</p>
            ) : (
                <ul className="plain-list" aria-label="Automated review hints">
                    {item.flags.map((flag) => (
                        <li className={`flag flag-${flag.severity}`} key={flag.message}>
                            {flag.severity === 'critical' ? <>⛔ <span className="sr-only">Critical:</span></> : <>⚠ <span className="sr-only">Warning:</span></>} {flag.message}
                        </li>
                    ))}
                </ul>
            )}

            <div className="review-actions">
                <div className="field">
                    <label className="sr-only" htmlFor={noteId}>Note to the organizer</label>
                    <input id={noteId} maxLength={300} placeholder="Note to the organizer (required to reject)" value={note} onChange={(event) => setNote(event.target.value)} />
                </div>
                <button className="button button-primary button-small" type="button" disabled={busy} onClick={() => decide('approve')} aria-label={`Approve ${item.title}`}>Approve</button>
                <button className="button button-danger button-small" type="button" disabled={busy} onClick={() => decide('reject')} aria-label={`Reject ${item.title}`}>Reject</button>
            </div>
        </li>
    );
}

/** One study material, with its scan result and a Remove button. */
function MaterialItem({ resource: r, onDone }) {
    const { notify } = useSession();
    const [busy, setBusy] = useState(false);

    async function remove() {
        if (!window.confirm(`Remove “${r.title}” for good? The file is deleted and cannot be brought back.`)) {
            return;
        }
        setBusy(true);
        try {
            const answer = await api.delete(`/api/admin/resources/${r.id}`);
            notify(answer.message, 'info');
            onDone();
        } catch (error) {
            notify(error.message, 'error');
            setBusy(false);
        }
    }

    return (
        <li className="review-item">
            <div className="row-between wrap">
                <div>
                    <strong>{r.title}</strong>
                    <span className="muted small">{r.uploader_name} · {r.organization_name} · {r.extension}, {r.size}</span>
                </div>
                <Badge tone={r.status_tone}>{r.status_label}</Badge>
            </div>
            <BreachWarning scan={r.scan} />
            <ScanResult scan={r.scan} />
            <div className="review-actions review-actions-end">
                <a className="button button-secondary button-small" href={`/api/resources/${r.id}/download`}>Download</a>
                <button className="button button-danger button-small" type="button" disabled={busy} onClick={remove} aria-label={`Remove ${r.title}`}>Remove</button>
            </div>
        </li>
    );
}

/** A number at the top of the dashboard. "alert" colours it when something is waiting. */
function StatTile({ icon, label, value, hint, alert, onClick, to }) {
    const content = (
        <>
            <span className="stat-icon"><Icon name={icon} /></span>
            <span className="stat-text">
                <span className="stat-value">{value}</span>
                <span className="stat-label">{label}</span>
                {hint && <span className="stat-hint">{hint}</span>}
            </span>
        </>
    );
    const className = `card stat-tile${alert ? ' stat-alert' : ''}`;
    return to
        ? <Link className={className} to={to}>{content}</Link>
        : <button className={className} type="button" onClick={onClick}>{content}</button>;
}

function MaterialsPanel({ resources, onDone }) {
    const [filter, setFilter] = useState(() => (resources.some((r) => r.scan.breach_warning) ? 'attention' : 'all'));
    const [, , matches] = MATERIAL_FILTERS.find(([key]) => key === filter);
    const shown = resources.filter(matches);

    return (
        <>
            <div className="panel-toolbar">
                <div className="filter-pills" role="group" aria-label="Show materials">
                    {MATERIAL_FILTERS.map(([key, label, test]) => (
                        <button key={key} type="button" className="filter-pill" aria-pressed={filter === key} onClick={() => setFilter(key)}>
                            {label} <span className="filter-count">{resources.filter(test).length}</span>
                        </button>
                    ))}
                </div>
                <Link className="button button-primary button-small" to="/resources/new">Add material</Link>
            </div>
            <p className="muted small">
                Material you add is public straight away and still goes through the automatic privacy &amp; copyright check.
                Removing deletes the file for good; to hide something for a while, the organization's rep can take it down instead.
            </p>
            {shown.length === 0 ? (
                <EmptyState title="Nothing here" description={filter === 'attention' ? 'No material needs attention.' : 'No materials match this filter.'} />
            ) : (
                <ul className="card divided-list padded-list">
                    {shown.map((r) => <MaterialItem key={r.id} resource={r} onDone={onDone} />)}
                </ul>
            )}
        </>
    );
}

export function Admin() {
    useTitle('Admin dashboard');
    const result = useApi('/api/admin');
    const location = useLocation();
    const navigate = useNavigate();
    const hashTab = location.hash.replace('#', '');
    const tab = TABS.some(([key]) => key === hashTab) ? hashTab : 'queue';
    const openTab = (key) => navigate({ hash: key }, { replace: true });

    return (
        <ApiView result={result}>
            {({ queue, charts, accounts, resources, pending_signups: pendingSignups }) => {
                const needsAttention = resources.filter((r) => r.scan.breach_warning).length;
                const totalAccounts = accounts.reduce((sum, row) => sum + row.total, 0);
                const counts = { queue: queue.length, materials: resources.length };

                return (
                    <div className="container">
                        <PageHeader
                            eyebrow="Administration"
                            title="Admin dashboard"
                            description="What needs your attention, the materials on CampusBridge, and how the content is doing."
                        />

                        <div className="stat-grid">
                            <StatTile icon="events" label="Listings to review" value={queue.length} alert={queue.length > 0}
                                hint={queue.length > 0 ? 'Opportunities & events' : 'All caught up'} onClick={() => openTab('queue')} />
                            <StatTile icon="approvals" label="Sign-ups waiting" value={pendingSignups} alert={pendingSignups > 0}
                                hint="All campuses" to="/approvals" />
                            <StatTile icon="admin" label="Materials need attention" value={needsAttention} alert={needsAttention > 0}
                                hint={`${resources.length} ${resources.length === 1 ? 'material' : 'materials'} in total`} onClick={() => openTab('materials')} />
                            <StatTile icon="organizations" label="Accounts" value={totalAccounts}
                                hint={accounts.map((row) => `${row.total} ${row.role.toLowerCase()}`).join(' · ')} onClick={() => openTab('analytics')} />
                        </div>

                        <div className="tabs" role="tablist" aria-label="Dashboard sections">
                            {TABS.map(([key, label]) => (
                                <button key={key} type="button" role="tab" id={`tab-${key}`} className="tab" aria-selected={tab === key}
                                    aria-controls={`panel-${key}`} onClick={() => openTab(key)}>
                                    {label}{counts[key] !== undefined && <span className="tab-count">{counts[key]}</span>}
                                </button>
                            ))}
                        </div>

                        <section className="tab-panel" role="tabpanel" id={`panel-${tab}`} aria-labelledby={`tab-${tab}`}>
                            {tab === 'queue' && (
                                <>
                                    <p className="muted small">New listings wait here until an administrator approves them. Automated hints point out common problems; the decision is always yours.</p>
                                    {queue.length === 0 ? (
                                        <EmptyState title="All caught up" description="There is nothing waiting for review." />
                                    ) : (
                                        <ul className="card divided-list padded-list">
                                            {queue.map((item) => <QueueItem key={`${item.type}-${item.id}`} item={item} onDone={result.reload} />)}
                                        </ul>
                                    )}
                                </>
                            )}

                            {tab === 'materials' && <MaterialsPanel resources={resources} onDone={result.reload} />}

                            {tab === 'analytics' && (
                                <>
                                    <p className="muted small">Calculated from the content in the database. With the sample data these numbers describe fictional content, not real university usage.</p>
                                    <div className="grid grid-2">
                                        <BarChart {...charts[0]} />
                                        <BarChart {...charts[1]} />
                                    </div>
                                    <div className="grid grid-2">
                                        <BarChart {...charts[2]} />
                                        <div className="card padded">
                                            <h3 className="chart-title">Accounts by role</h3>
                                            <table className="simple-table">
                                                <thead><tr><th scope="col">Role</th><th scope="col">Accounts</th></tr></thead>
                                                <tbody>
                                                    {accounts.map((row) => <tr key={row.role}><td>{row.role}</td><td>{row.total}</td></tr>)}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </>
                            )}
                        </section>
                    </div>
                );
            }}
        </ApiView>
    );
}
