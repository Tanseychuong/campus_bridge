/*
 * The signed-in user's profile: every detail of the account, what they do on
 * CampusBridge, and forms to change their name and password.
 */
import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { Icon, InitialsAvatar } from '../components/icons.jsx';
import { ApiView, Badge, FieldError, FormAlert, OrgAvatar, errorProps } from '../components/ui.jsx';
import { useApi, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

/** One line of the "About" card: icon, label and value. */
function Detail({ icon, label, children }) {
    return (
        <li className="about-row">
            <span className="about-icon"><Icon name={icon} /></span>
            <span>
                <span className="about-label">{label}</span>
                <span className="about-value">{children}</span>
            </span>
        </li>
    );
}

/**
 * Change or remove the profile picture ("avatar") or the cover picture.
 * With no picture yet, the button opens the file picker straight away;
 * otherwise it opens a small menu: Upload a new one / Remove.
 */
function PictureMenu({ kind, hasPicture, className, label, children }) {
    const { applySession, notify } = useSession();
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const input = useRef(null);
    const menu = useRef(null);
    const what = kind === 'avatar' ? 'profile picture' : 'cover picture';

    // Close the menu with a click outside or Escape.
    useEffect(() => {
        if (!open) {
            return undefined;
        }
        const onClick = (event) => menu.current && !menu.current.contains(event.target) && setOpen(false);
        const onKey = (event) => event.key === 'Escape' && setOpen(false);
        document.addEventListener('mousedown', onClick);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onClick);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    async function send(request) {
        setOpen(false);
        setBusy(true);
        try {
            const answer = await request();
            applySession(answer); // the new picture shows everywhere at once (top bar, menu, home)
            notify(answer.message);
        } catch (error) {
            notify(error.message, 'error');
        } finally {
            setBusy(false);
        }
    }

    function upload(event) {
        const file = event.target.files[0];
        event.target.value = ''; // choosing the same file again should still work
        if (!file) {
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            notify('The picture is too large (maximum 5 MB).', 'error');
            return;
        }
        const form = new FormData();
        form.append('image', file);
        send(() => api.post(`/api/profile/${kind}`, form));
    }

    return (
        <div className={`picture-menu picture-menu-${kind}`} ref={menu}>
            <button type="button" className={className} disabled={busy} aria-label={label} aria-haspopup={hasPicture ? 'menu' : undefined}
                aria-expanded={hasPicture ? open : undefined} onClick={() => (hasPicture ? setOpen(!open) : input.current.click())}>
                {busy ? <span className="picture-busy" aria-hidden="true" /> : children}
            </button>
            <input ref={input} type="file" accept="image/png,image/jpeg,image/webp" hidden onChange={upload} />
            {open && (
                <ul className="card picture-options" role="menu">
                    <li role="none">
                        <button type="button" role="menuitem" onClick={() => { setOpen(false); input.current.click(); }}>
                            <Icon name="camera" /> Upload a new {what}
                        </button>
                    </li>
                    <li role="none">
                        <button type="button" role="menuitem" className="text-danger" onClick={() => send(() => api.delete(`/api/profile/${kind}`))}>
                            <Icon name="signout" /> Remove {what}
                        </button>
                    </li>
                </ul>
            )}
        </div>
    );
}

function NameForm({ currentName, onSaved }) {
    const { applySession, notify } = useSession();
    const [name, setName] = useState(currentName);
    const [failure, setFailure] = useState({ message: '', errors: {} });
    const [busy, setBusy] = useState(false);

    async function save(event) {
        event.preventDefault();
        setBusy(true);
        try {
            const answer = await api.put('/api/profile', { name });
            applySession(answer); // the new name appears in the top bar straight away
            notify(answer.message);
            setFailure({ message: '', errors: {} });
            onSaved();
        } catch (error) {
            setFailure({ message: error.message, errors: error.errors });
        } finally {
            setBusy(false);
        }
    }

    return (
        <form className="stack" onSubmit={save} noValidate>
            <div className="field">
                <label className="sr-only" htmlFor="profile-name">Name</label>
                <input id="profile-name" maxLength={80} value={name} onChange={(e) => setName(e.target.value)} {...errorProps(failure.errors, 'name')} />
                <FieldError errors={failure.errors} field="name" />
            </div>
            <div>
                <button className="button button-primary button-small" type="submit" disabled={busy || name.trim() === currentName}>Save name</button>
            </div>
        </form>
    );
}

function PasswordForm() {
    const { notify } = useSession();
    const empty = { current_password: '', password: '', password_confirm: '' };
    const [values, setValues] = useState(empty);
    const [failure, setFailure] = useState({ message: '', errors: {} });
    const [busy, setBusy] = useState(false);
    const set = (field) => (event) => setValues({ ...values, [field]: event.target.value });
    const errors = failure.errors;

    async function save(event) {
        event.preventDefault();
        setBusy(true);
        try {
            const answer = await api.put('/api/profile/password', values);
            notify(answer.message);
            setValues(empty);
            setFailure({ message: '', errors: {} });
        } catch (error) {
            setFailure({ message: error.message, errors: error.errors });
        } finally {
            setBusy(false);
        }
    }

    return (
        <form className="stack" onSubmit={save} noValidate>
            <FormAlert message={failure.message} />
            <div className="field">
                <label htmlFor="current_password">Current password</label>
                <input id="current_password" type="password" autoComplete="current-password" value={values.current_password} onChange={set('current_password')} {...errorProps(errors, 'current_password')} />
                <FieldError errors={errors} field="current_password" />
            </div>
            <div className="grid grid-2">
                <div className="field">
                    <label htmlFor="password">New password</label>
                    <input id="password" type="password" autoComplete="new-password" value={values.password} onChange={set('password')} {...errorProps(errors, 'password')} />
                    <p className="hint">At least 10 characters.</p>
                    <FieldError errors={errors} field="password" />
                </div>
                <div className="field">
                    <label htmlFor="password_confirm">Repeat new password</label>
                    <input id="password_confirm" type="password" autoComplete="new-password" value={values.password_confirm} onChange={set('password_confirm')} {...errorProps(errors, 'password_confirm')} />
                    <FieldError errors={errors} field="password_confirm" />
                </div>
            </div>
            <div>
                <button className="button button-primary button-small" type="submit" disabled={busy || !values.current_password || !values.password}>Change password</button>
            </div>
        </form>
    );
}

export function Profile() {
    useTitle('Your profile');
    const result = useApi('/api/profile');
    const { user } = useSession(); // the pictures come from the session, so a change shows everywhere at once

    return (
        <ApiView result={result}>
            {({ account, stats, interests, followed, uploads, role }) => (
                <div className="container profile-page">
                    {/* Header: avatar, name, role */}
                    <section className="card profile-hero" aria-labelledby="profile-name-heading">
                        <div className="profile-cover" style={user?.cover_url ? { backgroundImage: `url("${user.cover_url}")` } : undefined}>
                            <PictureMenu kind="cover" hasPicture={Boolean(user?.cover_url)} className="cover-button" label="Change cover picture">
                                <Icon name="camera" /> <span>{user?.cover_url ? 'Change cover' : 'Add cover photo'}</span>
                            </PictureMenu>
                        </div>
                        <div className="profile-identity">
                            <div className="profile-avatar">
                                <InitialsAvatar name={account.name} size="large" src={user?.avatar_url} />
                                <PictureMenu kind="avatar" hasPicture={Boolean(user?.avatar_url)} className="avatar-camera" label="Change profile picture">
                                    <Icon name="camera" />
                                </PictureMenu>
                            </div>
                            <div className="profile-names">
                                <h1 id="profile-name-heading">{account.name}</h1>
                                <p className="muted">{account.email}</p>
                                <div className="badge-row">
                                    <Badge tone="brand">{account.role_label}</Badge>
                                    <Badge>{account.campus_name}</Badge>
                                    <Badge tone="success">{account.status_label}</Badge>
                                </div>
                            </div>
                            <div className="profile-actions">
                                <Link className="button button-secondary button-small" to="/preferences">Edit interests</Link>
                                <a className="button button-secondary button-small" href="#settings">Account settings</a>
                            </div>
                        </div>
                    </section>

                    <div className="profile-layout">
                        {/* Side column: every detail of the account */}
                        <aside className="stack">
                            <section className="card padded" aria-labelledby="about-heading">
                                <h2 id="about-heading" className="small-heading">About</h2>
                                <ul className="about-list">
                                    <Detail icon="search" label="Email">{account.email}</Detail>
                                    <Detail icon="admin" label="Role">{account.role_label}</Detail>
                                    <Detail icon="campus" label="Campus">{account.campus_name}</Detail>
                                    {account.organization_name && (
                                        <Detail icon="organizations" label="Organization">
                                            <Link to={`/organizations/${account.organization_id}`}>{account.organization_name}</Link>
                                        </Detail>
                                    )}
                                    <Detail icon="clock" label="Member since">{account.member_since}</Detail>
                                    <Detail icon="approvals" label="Account status">
                                        {account.status_label}{account.approved && <span className="muted small"> · {account.approved}</span>}
                                    </Detail>
                                </ul>
                            </section>

                            {role && (
                                <section className="card padded role-card" aria-labelledby="role-heading">
                                    <h2 id="role-heading" className="small-heading">{role.title}</h2>
                                    <p className="role-waiting">
                                        <strong>{role.waiting}</strong> <span className="muted">{role.waiting_label}</span>
                                    </p>
                                    <Link className="button button-primary button-small" to={role.link}>{role.action}</Link>
                                </section>
                            )}

                            <section className="card padded" aria-labelledby="following-heading">
                                <div className="row-between">
                                    <h2 id="following-heading" className="small-heading">Following</h2>
                                    <Link className="link-arrow small" to="/organizations">Browse</Link>
                                </div>
                                {followed.length === 0 ? (
                                    <p className="muted small">You don’t follow any organizations yet.</p>
                                ) : (
                                    <ul className="plain-list following-list">
                                        {followed.map((org) => (
                                            <li key={org.id}>
                                                <Link to={`/organizations/${org.id}`}><OrgAvatar organization={org} size="small" /> {org.name}</Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>
                        </aside>

                        {/* Main column: activity, then settings */}
                        <div className="stack-large">
                            <section aria-label="Your activity">
                                <ul className="profile-stats">
                                    {stats.map((stat) => (
                                        <li key={stat.label}>
                                            <Link className="card profile-stat" to={stat.link}>
                                                <span className="profile-stat-value">{stat.value}</span>
                                                <span className="profile-stat-label">{stat.label}</span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </section>

                            <section className="card padded" aria-labelledby="interests-heading">
                                <div className="row-between">
                                    <h2 id="interests-heading" className="small-heading">Interests</h2>
                                    <Link className="link-arrow small" to="/preferences">Edit</Link>
                                </div>
                                {interests.length === 0 ? (
                                    <p className="muted small">No interests yet. <Link to="/preferences">Choose a few</Link> to get recommendations.</p>
                                ) : (
                                    <div className="badge-row profile-interests">
                                        {interests.map((interest) => (
                                            <Link key={interest.value} className="badge badge-brand" to={`/explore?interest=${interest.value}`}>{interest.label}</Link>
                                        ))}
                                    </div>
                                )}
                            </section>

                            <section className="card padded" aria-labelledby="uploads-heading">
                                <div className="row-between">
                                    <h2 id="uploads-heading" className="small-heading">Resources you shared</h2>
                                    <Link className="link-arrow small" to="/resources/new">Share one</Link>
                                </div>
                                {uploads.length === 0 ? (
                                    <p className="muted small">You haven’t shared any notes or slides yet.</p>
                                ) : (
                                    <ul className="divided-list">
                                        {uploads.map((r) => (
                                            <li key={r.id} className="row-between">
                                                <div>
                                                    <strong>{r.title}</strong>
                                                    <span className="muted small">{r.organization_name} · {r.extension}, {r.size}</span>
                                                    {r.review_note && <span className="small">Rep’s note: “{r.review_note}”</span>}
                                                </div>
                                                <Badge tone={r.status_tone}>{r.status_label}</Badge>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>

                            <section className="card padded" id="settings" aria-labelledby="settings-heading">
                                <h2 id="settings-heading" className="small-heading">Account settings</h2>
                                <p className="muted small">Your email, role and campus are set when your account is created; ask an administrator to change them.</p>
                                <div className="settings-block">
                                    <h3 className="settings-title">Your name</h3>
                                    <NameForm currentName={account.name} onSaved={result.reload} />
                                </div>
                                <div className="settings-block">
                                    <h3 className="settings-title">Password</h3>
                                    <PasswordForm />
                                </div>
                                <div className="settings-block">
                                    <h3 className="settings-title">Your data</h3>
                                    <p className="muted small">To delete your interests, saved items and follows, use <Link to="/preferences#data">Preferences → Your data</Link>.</p>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            )}
        </ApiView>
    );
}
