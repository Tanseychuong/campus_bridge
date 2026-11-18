/*
 * Shared study resources: the public list, and the upload form with its
 * intellectual-property declaration.
 */
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import { ResourceCard } from '../components/cards.jsx';
import { ApiView, Badge, EmptyState, FieldError, FormAlert, Notice, PageHeader, errorProps } from '../components/ui.jsx';
import { useApi, useFocusFirstError, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

export function Resources() {
    useTitle('Resources');
    const result = useApi('/api/resources');

    return (
        <ApiView result={result}>
            {({ resources, my_uploads: myUploads, can_share: canShare }) => (
                <div className="container">
                    <PageHeader
                        eyebrow="Shared by students"
                        title="Resources"
                        description="Notes, slides and templates from students. Each one is reviewed by an organization rep before it appears here."
                        actions={canShare && <Link className="button button-primary" to="/resources/new">Share a resource</Link>}
                    />

                    {myUploads.length > 0 && (
                        <section className="section" aria-labelledby="mine-heading">
                            <h2 id="mine-heading" className="section-title">Your uploads</h2>
                            <ul className="card divided-list padded-list">
                                {myUploads.map((r) => (
                                    <li className="row-between wrap" key={r.id}>
                                        <div>
                                            <strong>{r.title}</strong>
                                            <span className="muted small">{r.organization_name}</span>
                                            {r.review_note && <span className="small">Rep’s note: “{r.review_note}”</span>}
                                        </div>
                                        <Badge tone={r.status_tone}>{r.status_label}</Badge>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <section className="section" aria-labelledby="all-heading">
                        <h2 id="all-heading" className="section-title">Public resources</h2>
                        {resources.length === 0 ? (
                            <EmptyState title="No public resources yet" description="Share your notes or slides — a rep reviews them before they go live." />
                        ) : (
                            <div className="grid grid-3">{resources.map((r) => <ResourceCard key={r.id} resource={r} />)}</div>
                        )}
                    </section>
                </div>
            )}
        </ApiView>
    );
}

const CONFIRMATIONS = [
    ['confirm_rights', 'This is my own work, or I have the right to share every part of it, and it does not infringe anyone’s copyright.'],
    ['confirm_no_confidential', 'It contains no exam papers, answer keys, confidential material or other people’s personal data.'],
    ['confirm_takedown', 'I understand the rep may refuse it or take it down at any time, and that I keep ownership of my work.'],
];

export function ResourceUpload() {
    useTitle('Share a resource');
    const result = useApi('/api/resources/options');
    return <ApiView result={result}>{(data) => <UploadForm groups={data.groups} />}</ApiView>;
}

function UploadForm({ groups }) {
    const { meta, notify } = useSession();
    const navigate = useNavigate();
    const [values, setValues] = useState({
        title: '', description: '', organization_id: '', ip_ownership: 'sole', ip_credits: '', license: 'cc_by', declared_name: '',
        confirm_rights: false, confirm_no_confidential: false, confirm_takedown: false,
    });
    const [file, setFile] = useState(null);
    const [failure, setFailure] = useState({ message: '', errors: {} });
    const [busy, setBusy] = useState(false);
    useFocusFirstError(failure.errors);

    const set = (field) => (event) => setValues({ ...values, [field]: event.target.type === 'checkbox' ? event.target.checked : event.target.value });
    const errors = failure.errors;

    async function submit(event) {
        event.preventDefault();
        // A file is sent as a multipart form, like a normal HTML upload form.
        const form = new FormData();
        for (const [name, value] of Object.entries(values)) {
            form.append(name, typeof value === 'boolean' ? (value ? '1' : '') : value);
        }
        if (file) {
            form.append('file', file);
        }
        setBusy(true);
        try {
            const answer = await api.post('/api/resources', form);
            notify(answer.message, 'success', true);
            navigate('/resources');
        } catch (error) {
            setFailure({ message: error.message, errors: error.errors });
            setBusy(false);
        }
    }

    return (
        <div className="container page-form-wide">
            <p className="back-link"><Link to="/resources">← Resources</Link></p>
            <div className="card form-card">
                <h1>Share a resource</h1>
                <p className="muted">An automatic check looks for personal data and copyrighted material, then an organization rep reviews it before anyone else can see it.</p>
                <FormAlert message={failure.message} />

                {groups.length === 0 ? (
                    <Notice title="No reviewers yet" tone="warning">No organization has a rep who can review resources at the moment.</Notice>
                ) : (
                    <form className="stack-large" onSubmit={submit} noValidate>
                        <div className="field">
                            <label htmlFor="title">Title <span className="required">*</span></label>
                            <input id="title" maxLength={120} value={values.title} onChange={set('title')} {...errorProps(errors, 'title')} />
                            <FieldError errors={errors} field="title" />
                        </div>

                        <div className="field">
                            <label htmlFor="description">Short description <span className="required">*</span></label>
                            <input id="description" maxLength={300} value={values.description} onChange={set('description')} {...errorProps(errors, 'description')} />
                            <p className="hint">One or two sentences, e.g. “My summary of week 1–6 lectures with worked examples.”</p>
                            <FieldError errors={errors} field="description" />
                        </div>

                        <div className="grid grid-2">
                            <div className="field">
                                <label htmlFor="organization_id">Reviewed by <span className="required">*</span></label>
                                <select id="organization_id" value={values.organization_id} onChange={set('organization_id')} {...errorProps(errors, 'organization_id')}>
                                    <option value="">Choose an organization…</option>
                                    {groups.map((group) => (
                                        <optgroup label={group.campus} key={group.campus}>
                                            {group.organizations.map((org) => <option key={org.id} value={org.id}>{org.name}</option>)}
                                        </optgroup>
                                    ))}
                                </select>
                                <FieldError errors={errors} field="organization_id" />
                            </div>
                            <div className="field">
                                <label htmlFor="file">File <span className="required">*</span></label>
                                <input id="file" type="file" accept={meta.file_extensions.join(',')} onChange={(event) => setFile(event.target.files[0] || null)} {...errorProps(errors, 'file')} />
                                <p className="hint">PDF, Word, PowerPoint, Excel, text, image or ZIP. Max 10 MB.</p>
                                <FieldError errors={errors} field="file" />
                            </div>
                        </div>

                        <fieldset className="ip-box stack">
                            <legend>Intellectual property declaration</legend>

                            <div className="field" role="radiogroup" aria-labelledby="ownership-label">
                                <p className="field-label" id="ownership-label"><strong>Who created this work?</strong> <span className="required">*</span></p>
                                {meta.ip_ownership.map((option) => (
                                    <label className="radio" key={option.value}>
                                        <input type="radio" name="ip_ownership" value={option.value} checked={values.ip_ownership === option.value} onChange={set('ip_ownership')} /> {option.label}
                                    </label>
                                ))}
                                <FieldError errors={errors} field="ip_ownership" />
                            </div>

                            <div className="field">
                                <label htmlFor="ip_credits">Co-authors or sources</label>
                                <textarea id="ip_credits" rows={2} maxLength={400} value={values.ip_credits} onChange={set('ip_credits')} {...errorProps(errors, 'ip_credits')} />
                                <p className="hint">Required if others helped create it or if it includes their material (with permission).</p>
                                <FieldError errors={errors} field="ip_credits" />
                            </div>

                            <div className="field">
                                <label htmlFor="license">How may others use it? <span className="required">*</span></label>
                                <select id="license" value={values.license} onChange={set('license')} {...errorProps(errors, 'license')}>
                                    {meta.licenses.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                </select>
                                <FieldError errors={errors} field="license" />
                            </div>

                            <div className="field" aria-describedby={errors.declaration ? 'declaration-error' : undefined}>
                                <p className="field-label"><strong>I confirm that:</strong> <span className="required">*</span></p>
                                {CONFIRMATIONS.map(([name, text]) => (
                                    <label className="check" key={name}>
                                        <input type="checkbox" checked={values[name]} onChange={set(name)} /> {text}
                                    </label>
                                ))}
                                <FieldError errors={errors} field="declaration" />
                            </div>

                            <div className="field">
                                <label htmlFor="declared_name">Type your full name to sign <span className="required">*</span></label>
                                <input id="declared_name" maxLength={80} autoComplete="name" value={values.declared_name} onChange={set('declared_name')} {...errorProps(errors, 'declared_name')} />
                                <FieldError errors={errors} field="declared_name" />
                            </div>
                        </fieldset>

                        <div className="button-row">
                            <button className="button button-primary" type="submit" disabled={busy}>Submit for review</button>
                            <Link className="button button-secondary" to="/resources">Cancel</Link>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}
