/*
 * Sign-up approvals: campus reps approve or reject the students who sign up for
 * their campus (admins see every campus). The server checks the role and the
 * campus on every request.
 */
import { useState } from 'react';
import { api } from '../api.js';
import { ApiView, Badge, EmptyState, PageHeader } from '../components/ui.jsx';
import { useApi, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

function SignupItem({ signup: s, showCampus, onDone }) {
    const { notify } = useSession();
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const noteId = `signup-note-${s.id}`;

    async function decide(decision) {
        setBusy(true);
        try {
            const answer = await api.post(`/api/approvals/${s.id}`, { decision, note });
            notify(answer.message, decision === 'approve' ? 'success' : 'info');
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
                    <strong>{s.name}</strong>
                    <span className="muted small">
                        {s.email} · signed up {s.signed_up}{showCampus ? ` · ${s.campus_name}` : ''}
                    </span>
                </div>
                <Badge tone={s.status_tone}>{s.status_label}</Badge>
            </div>

            {s.status !== 'pending' && (
                <p className="muted small">
                    {s.status === 'active' ? 'Approved' : 'Not approved'}{s.reviewer_name ? ` by ${s.reviewer_name}` : ''}{s.reviewed ? ` on ${s.reviewed}` : ''}
                    {s.review_note ? ` — “${s.review_note}”` : ''}
                </p>
            )}

            <div className="review-actions">
                <div className="field">
                    <label className="sr-only" htmlFor={noteId}>Reason (shown to the student)</label>
                    <input id={noteId} maxLength={300} placeholder="Reason (required to reject; the student sees it)" value={note} onChange={(e) => setNote(e.target.value)} />
                </div>
                {s.status !== 'active' && (
                    <button className="button button-primary button-small" type="button" disabled={busy} onClick={() => decide('approve')} aria-label={`Approve ${s.name}`}>Approve</button>
                )}
                {s.status !== 'rejected' && (
                    <button className="button button-danger button-small" type="button" disabled={busy} onClick={() => decide('reject')} aria-label={`Reject ${s.name}`}>
                        {s.status === 'active' ? 'Revoke' : 'Reject'}
                    </button>
                )}
            </div>
        </li>
    );
}

export function Approvals() {
    useTitle('Sign-up approvals');
    const result = useApi('/api/approvals');

    return (
        <ApiView result={result}>
            {({ scope, signups, pending_count: pendingCount }) => {
                const waiting = signups.filter((s) => s.status === 'pending');
                const decided = signups.filter((s) => s.status !== 'pending');
                const showCampus = scope === 'every campus';
                return (
                    <div className="container page-narrow">
                        <PageHeader
                            eyebrow={showCampus ? 'Every campus' : scope}
                            title="Sign-up approvals"
                            description="New students can sign in only after you approve them. Check that the name and email look like a real student of this campus."
                        />

                        <section aria-labelledby="waiting-heading">
                            <h2 id="waiting-heading" className="section-title">Waiting for you ({pendingCount})</h2>
                            {waiting.length === 0 ? (
                                <EmptyState title="All caught up" description="No sign-ups are waiting for approval." />
                            ) : (
                                <ul className="card divided-list padded-list">
                                    {waiting.map((s) => <SignupItem key={`${s.id}-${s.status}`} signup={s} showCampus={showCampus} onDone={result.reload} />)}
                                </ul>
                            )}
                        </section>

                        {decided.length > 0 && (
                            <section className="section" aria-labelledby="decided-heading">
                                <h2 id="decided-heading" className="section-title">Decided in the last 30 days</h2>
                                <ul className="card divided-list padded-list">
                                    {decided.map((s) => <SignupItem key={`${s.id}-${s.status}`} signup={s} showCampus={showCampus} onDone={result.reload} />)}
                                </ul>
                            </section>
                        )}
                    </div>
                );
            }}
        </ApiView>
    );
}
