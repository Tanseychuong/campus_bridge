/*
 * Administrator area: approve or reject new listings, and see content analytics.
 */
import { useState } from 'react';
import { api } from '../api.js';
import { BarChart } from '../components/BarChart.jsx';
import { ApiView, Badge, EmptyState, PageHeader } from '../components/ui.jsx';
import { useApi, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

function QueueItem({ item, onDone }) {
    const { notify } = useSession();
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const noteId = `note-${item.type}${item.id}`;

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
        <li className="card padded">
            <div className="badge-row">
                {item.type === 'opportunity' ? <Badge tone="brand">Opportunity</Badge> : <Badge tone="gold">Event</Badge>}
            </div>
            <h3 className="card-title">{item.title}</h3>
            <p className="muted small">{item.by} · {item.when}</p>
            <p>{item.summary}</p>

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

            <div className="review-form">
                <div className="field">
                    <label htmlFor={noteId}>Note to the organizer <span className="muted">(required when rejecting)</span></label>
                    <input id={noteId} maxLength={300} value={note} onChange={(event) => setNote(event.target.value)} />
                </div>
                <div className="button-row">
                    <button className="button button-primary button-small" type="button" disabled={busy} onClick={() => decide('approve')} aria-label={`Approve ${item.title}`}>Approve</button>
                    <button className="button button-danger button-small" type="button" disabled={busy} onClick={() => decide('reject')} aria-label={`Reject ${item.title}`}>Reject</button>
                </div>
            </div>
        </li>
    );
}

export function Admin() {
    useTitle('Review & analytics');
    const result = useApi('/api/admin');

    return (
        <ApiView result={result}>
            {({ queue, charts, accounts }) => (
                <div className="container">
                    <PageHeader eyebrow="Administration" title="Review & analytics" description="Approve or reject new listings, and understand the content on the platform." />

                    <section aria-labelledby="queue-heading">
                        <h2 id="queue-heading" className="section-title">Review queue ({queue.length})</h2>
                        <p className="muted small">New listings wait here until an administrator approves them. Automated hints point out common problems; the decision is always yours.</p>
                        {queue.length === 0 ? (
                            <EmptyState title="All caught up" description="There is nothing waiting for review." />
                        ) : (
                            <ul className="plain-list stack">
                                {queue.map((item) => <QueueItem key={`${item.type}-${item.id}`} item={item} onDone={result.reload} />)}
                            </ul>
                        )}
                    </section>

                    <section className="section" aria-labelledby="analytics-heading">
                        <h2 id="analytics-heading" className="section-title">Content analytics</h2>
                        <p className="muted small">Calculated from the content in the database. With the sample data these numbers describe fictional content, not real university usage.</p>
                        <div className="grid grid-2">
                            <BarChart {...charts[0]} />
                            <BarChart {...charts[1]} />
                        </div>
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
                    </section>
                </div>
            )}
        </ApiView>
    );
}
