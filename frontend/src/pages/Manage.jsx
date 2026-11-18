/*
 * Organization rep area: your organization's events and the resources
 * students send you, plus the create / edit event form.
 * The server checks the role and the organization on every request.
 */
import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api } from '../api.js';
import { ApiView, Badge, EmptyState, FieldError, FormAlert, InterestChips, OrgAvatar, PageHeader, errorProps } from '../components/ui.jsx';
import { useApi, useFocusFirstError, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

/** One resource waiting for (or after) the rep's decision. */
function ResourceReview({ resource: r, onDone }) {
    const { notify } = useSession();
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const scan = r.scan;

    async function decide(decision) {
        if (decision === 'publish' && scan.critical_count > 0
            && !window.confirm(`The automatic check found ${scan.critical_count} serious issue(s). Publish anyway?`)) {
            return;
        }
        setBusy(true);
        try {
            const answer = await api.post(`/api/manage/resources/${r.id}`, { decision, note });
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
                    <strong>{r.title}</strong>
                    <span className="muted small">{r.uploader_name} · {r.extension}, {r.size}</span>
                </div>
                <Badge tone={r.status_tone}>{r.status_label}</Badge>
            </div>

            <div className="scan-result" data-testid="scan-result">
                {scan.state === 'pending' && <p className="flag flag-pending">⏳ Automatic privacy &amp; copyright check in progress…</p>}
                {scan.state === 'clean' && <p className="flag flag-ok">✓ Automatic check found no privacy or copyright issues</p>}
                {scan.state === 'flagged' && (
                    <>
                        <p className="scan-heading">Automatic check: {scan.flags.length} {scan.flags.length === 1 ? 'thing' : 'things'} to look at</p>
                        <ul className="plain-list" aria-label="Automatic privacy and copyright check">
                            {scan.flags.map((flag) => (
                                <li className={`flag flag-${flag.severity}`} key={flag.message}>
                                    {flag.severity === 'critical' ? <>⛔ <span className="sr-only">Critical:</span></> : <>⚠ <span className="sr-only">Warning:</span></>}
                                    <span className="flag-kind">{flag.kind_label}</span> {flag.message}
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>

            <details className="review-details">
                <summary>File &amp; IP declaration</summary>
                <dl>
                    <dt>Description</dt><dd>{r.description}</dd>
                    <dt>Created by</dt><dd>{r.declaration.ownership}</dd>
                    {r.declaration.credits && <><dt>Credits</dt><dd>{r.declaration.credits}</dd></>}
                    <dt>Licence</dt><dd>{r.declaration.license}</dd>
                    <dt>Signed</dt><dd>{r.declaration.declared_name}, {r.declaration.declared_at} — confirmed all three statements</dd>
                    {r.review_note && <><dt>Your note</dt><dd>{r.review_note}</dd></>}
                </dl>
                <p><a className="link" href={`/api/resources/${r.id}/download`}>Download to check ({r.original_name})</a></p>
            </details>

            {r.status !== 'rejected' && (
                <div className="review-actions">
                    {r.status === 'pending' && (
                        <>
                            <div className="field">
                                <label className="sr-only" htmlFor={`note-${r.id}`}>Note to the student</label>
                                <input id={`note-${r.id}`} maxLength={300} placeholder="Note to the student (required to reject)" value={note} onChange={(e) => setNote(e.target.value)} />
                            </div>
                            <button className="button button-primary button-small" type="button" disabled={busy} onClick={() => decide('publish')} aria-label={`Publish ${r.title}`}>Publish</button>
                            <button className="button button-danger button-small" type="button" disabled={busy} onClick={() => decide('reject')} aria-label={`Reject ${r.title}`}>Reject</button>
                        </>
                    )}
                    {r.status === 'published' && (
                        <button className="button button-danger button-small" type="button" disabled={busy} onClick={() => decide('hide')} aria-label={`Take down ${r.title}`}>Take down</button>
                    )}
                    {r.status === 'hidden' && (
                        <button className="button button-secondary button-small" type="button" disabled={busy} onClick={() => decide('publish')} aria-label={`Publish ${r.title} again`}>Publish again</button>
                    )}
                </div>
            )}
        </li>
    );
}

export function Manage() {
    useTitle('Manage your organization');
    const result = useApi('/api/manage');
    const { notify } = useSession();

    async function deleteEvent(event) {
        if (!window.confirm(`Delete “${event.title}”? This cannot be undone.`)) {
            return;
        }
        try {
            const answer = await api.delete(`/api/manage/events/${event.id}`);
            notify(answer.message, 'info');
            result.reload();
        } catch (error) {
            notify(error.message, 'error');
        }
    }

    return (
        <ApiView result={result}>
            {({ organization, events, resources, pending_count: pendingCount }) => (
                <div className="container">
                    <PageHeader
                        eyebrow="Organization area"
                        title="Manage your organization"
                        description="Create events for everyone or just your campus, and review resources students send you."
                        actions={<Link className="button button-primary" to="/manage/events/new">Create event</Link>}
                    />

                    <div className="card org-header">
                        <OrgAvatar organization={organization} size="large" />
                        <div className="grow">
                            <p className="eyebrow">You manage</p>
                            <h2 className="small-heading">{organization.name}</h2>
                            <Link className="link" to={`/organizations/${organization.id}`}>View public profile</Link>
                        </div>
                    </div>

                    <section className="section" aria-labelledby="events-heading">
                        <h2 id="events-heading" className="section-title">Your events</h2>
                        {events.length === 0 ? (
                            <EmptyState title="No events yet" description="Create an event to see how the review workflow works." />
                        ) : (
                            <ul className="card divided-list padded-list">
                                {events.map((event) => (
                                    <li className="row-between wrap" key={event.id}>
                                        <div>
                                            <strong>{event.title}</strong>
                                            <span className="muted small">{event.when}</span>
                                            <span><Badge tone={event.status_tone}>{event.status_label}</Badge></span>
                                            {event.status === 'rejected' && event.review_note && <span className="small">Reviewer’s note: “{event.review_note}”</span>}
                                        </div>
                                        <div className="row-gap">
                                            <Link className="button button-secondary button-small" to={`/manage/events/${event.id}`} aria-label={`Edit ${event.title}`}>Edit</Link>
                                            <button className="button button-danger button-small" type="button" onClick={() => deleteEvent(event)} aria-label={`Delete ${event.title}`}>Delete</button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="section" aria-labelledby="resources-heading">
                        <h2 id="resources-heading" className="section-title">Student resources{pendingCount > 0 ? ` · ${pendingCount} waiting` : ''}</h2>
                        <p className="muted small">Check the file and the IP declaration, then publish it. You can take a public resource down, or bring it back, at any time.</p>
                        {resources.length === 0 ? (
                            <EmptyState title="No resources yet" description={`When students send resources to ${organization.name}, they appear here.`} />
                        ) : (
                            <ul className="card divided-list padded-list">
                                {/* "key" includes the status so a row starts fresh after a decision. */}
                                {resources.map((r) => <ResourceReview key={`${r.id}-${r.status}`} resource={r} onDone={result.reload} />)}
                            </ul>
                        )}
                    </section>
                </div>
            )}
        </ApiView>
    );
}

/** Create (/manage/events/new) or edit (/manage/events/4) an event. */
export function ManageEvent() {
    const { id } = useParams();
    const isEdit = id !== undefined;
    useTitle(isEdit ? 'Edit event' : 'Create event');
    const result = useApi(isEdit ? `/api/manage/events/${encodeURIComponent(id)}` : '/api/manage/events/new');

    return (
        <ApiView result={result}>
            {(data) => <EventForm key={id || 'new'} id={id} organization={data.organization} initialValues={data.values} />}
        </ApiView>
    );
}

function EventForm({ id, organization, initialValues }) {
    const isEdit = id !== undefined;
    const { meta, notify } = useSession();
    const navigate = useNavigate();
    const [values, setValues] = useState(initialValues);
    const [failure, setFailure] = useState({ message: '', errors: {} });
    const [busy, setBusy] = useState(false);
    useFocusFirstError(failure.errors);
    useEffect(() => setValues(initialValues), [initialValues]);

    const set = (field) => (event) => setValues({ ...values, [field]: event.target.value });
    const errors = failure.errors;
    const title = isEdit ? 'Edit event' : 'Create event';

    async function submit(event) {
        event.preventDefault();
        setBusy(true);
        try {
            const answer = isEdit ? await api.put(`/api/manage/events/${id}`, values) : await api.post('/api/manage/events', values);
            notify(answer.message, 'success', true);
            navigate('/manage');
        } catch (error) {
            setFailure({ message: error.message, errors: error.errors });
            setBusy(false);
        }
    }

    return (
        <div className="container page-form-wide">
            <p className="back-link"><Link to="/manage">← Back to your events</Link></p>
            <div className="card form-card">
                <h1>{title}</h1>
                <p className="muted">For {organization.name}. Times are in Accra time (GMT).</p>
                <FormAlert message={failure.message} />

                <form className="stack" onSubmit={submit} noValidate>
                    <div className="field">
                        <label htmlFor="title">Title <span className="required">*</span></label>
                        <input id="title" maxLength={120} value={values.title} onChange={set('title')} {...errorProps(errors, 'title')} />
                        <FieldError errors={errors} field="title" />
                    </div>

                    <div className="grid grid-2">
                        <div className="field">
                            <label htmlFor="category">Category <span className="required">*</span></label>
                            <select id="category" value={values.category} onChange={set('category')} {...errorProps(errors, 'category')}>
                                {meta.event_categories.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                            </select>
                            <FieldError errors={errors} field="category" />
                        </div>
                        <div className="field">
                            <label htmlFor="venue">Venue <span className="required">*</span></label>
                            <input id="venue" maxLength={120} value={values.venue} onChange={set('venue')} {...errorProps(errors, 'venue')} />
                            <FieldError errors={errors} field="venue" />
                        </div>
                    </div>

                    <div className="field">
                        <label htmlFor="summary">Short summary <span className="required">*</span></label>
                        <input id="summary" maxLength={220} value={values.summary} onChange={set('summary')} {...errorProps(errors, 'summary')} />
                        <p className="hint">One sentence shown on event cards (10–220 characters).</p>
                        <FieldError errors={errors} field="summary" />
                    </div>

                    <div className="field">
                        <label htmlFor="description">Description <span className="required">*</span></label>
                        <textarea id="description" rows={5} maxLength={3000} value={values.description} onChange={set('description')} {...errorProps(errors, 'description')} />
                        <FieldError errors={errors} field="description" />
                    </div>

                    <div className="grid grid-3">
                        <div className="field">
                            <label htmlFor="date">Date <span className="required">*</span></label>
                            <input id="date" type="date" value={values.date} onChange={set('date')} {...errorProps(errors, 'date')} />
                            <FieldError errors={errors} field="date" />
                        </div>
                        <div className="field">
                            <label htmlFor="start_time">Start time <span className="required">*</span></label>
                            <input id="start_time" type="time" value={values.start_time} onChange={set('start_time')} {...errorProps(errors, 'start_time')} />
                            <FieldError errors={errors} field="start_time" />
                        </div>
                        <div className="field">
                            <label htmlFor="end_time">End time <span className="required">*</span></label>
                            <input id="end_time" type="time" value={values.end_time} onChange={set('end_time')} {...errorProps(errors, 'end_time')} />
                            <FieldError errors={errors} field="end_time" />
                        </div>
                    </div>

                    <div className="grid grid-2">
                        <div className="field">
                            <label htmlFor="capacity">Capacity</label>
                            <input id="capacity" inputMode="numeric" value={values.capacity} onChange={set('capacity')} {...errorProps(errors, 'capacity')} />
                            <p className="hint">Leave empty for no limit.</p>
                            <FieldError errors={errors} field="capacity" />
                        </div>
                        <div className="field">
                            <label htmlFor="registration">Registration <span className="required">*</span></label>
                            <select id="registration" value={values.registration} onChange={set('registration')}>
                                <option value="open">Open entry</option>
                                <option value="rsvp">RSVP required</option>
                            </select>
                        </div>
                    </div>

                    <fieldset className="field" aria-describedby={errors.visibility ? 'visibility-error' : undefined}>
                        <legend>Who can see this event? <span className="required">*</span></legend>
                        {[['public', 'Everyone (all campuses)'], ['campus', `Only ${organization.campus_name} students`]].map(([value, label]) => (
                            <label className="radio" key={value}>
                                <input type="radio" name="visibility" value={value} checked={values.visibility === value} onChange={set('visibility')} /> {label}
                            </label>
                        ))}
                        <p className="hint">Campus-only events are hidden from students of other campuses and from visitors who are not signed in.</p>
                        <FieldError errors={errors} field="visibility" />
                    </fieldset>

                    <fieldset className="field" aria-describedby={errors.interests ? 'interests-error' : undefined}>
                        <legend>Interest tags <span className="required">*</span></legend>
                        <InterestChips interests={meta.interests} selected={values.interests} onChange={(interests) => setValues({ ...values, interests })} />
                        <FieldError errors={errors} field="interests" />
                    </fieldset>

                    <div className="button-row">
                        <button className="button button-primary" type="submit" disabled={busy}>{isEdit ? 'Save changes' : 'Submit for review'}</button>
                        <Link className="button button-secondary" to="/manage">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    );
}
