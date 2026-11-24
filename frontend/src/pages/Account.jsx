/*
 * Sign in and create an account.
 */
import { useState } from 'react';
import { Link, Navigate, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { api, safeReturnPath } from '../api.js';
import { FieldError, FormAlert, Notice, errorProps } from '../components/ui.jsx';
import { useFocusFirstError, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

export function Login() {
    useTitle('Sign in');
    const { user, applySession, notify } = useSession();
    const [params] = useSearchParams();
    const location = useLocation();
    const navigate = useNavigate();
    const [form, setForm] = useState({ email: '', password: '' });
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    // Only ever return to a page on this site (see safeReturnPath in api.js).
    const returnTo = safeReturnPath(params.get('return'));

    if (user && !busy) {
        return <Navigate to={returnTo} replace />;
    }

    async function submit(event) {
        event.preventDefault();
        setBusy(true);
        setError('');
        try {
            const answer = await api.post('/api/login', form);
            applySession(answer);
            notify(answer.message, 'success', true);
            navigate(returnTo);
        } catch (failure) {
            setError(failure.message);
            setBusy(false);
        }
    }

    const set = (field) => (event) => setForm({ ...form, [field]: event.target.value });

    return (
        <div className="container page-form">
            <div className="card form-card">
                <h1>Sign in</h1>
                <p className="muted">Sign in to save opportunities, follow organizations and get recommendations.</p>

                {location.state?.notice && !error && <p className="flash flash-info" role="status">{location.state.notice}</p>}
                <FormAlert message={error} />

                <form className="stack" onSubmit={submit}>
                    <div className="field">
                        <label htmlFor="email">Email</label>
                        <input id="email" type="email" autoComplete="email" required value={form.email} onChange={set('email')} />
                    </div>
                    <div className="field">
                        <label htmlFor="password">Password</label>
                        <input id="password" type="password" autoComplete="current-password" required value={form.password} onChange={set('password')} />
                    </div>
                    <button className="button button-primary" type="submit" disabled={busy}>Sign in</button>
                </form>

                <p className="muted small">No account yet? <Link to="/register">Create one</Link>.</p>
                <Notice title="Demo accounts (sample data only)">
                    student@example.org, rep@example.org, campus@example.org (campus rep) or admin@example.org — password <code>CampusBridge2026!</code>. These exist
                    only in the sample database; change or delete them before any real use.
                </Notice>
            </div>
        </div>
    );
}

export function Register() {
    useTitle('Create account');
    const { user, meta, applySession } = useSession();
    const navigate = useNavigate();
    const [form, setForm] = useState({ name: '', email: '', campus_id: '', password: '', password_confirm: '' });
    const [failure, setFailure] = useState({ message: '', errors: {} });
    const [busy, setBusy] = useState(false);
    useFocusFirstError(failure.errors);

    if (user && !busy) {
        return <Navigate to="/dashboard" replace />;
    }

    async function submit(event) {
        event.preventDefault();
        setBusy(true);
        try {
            const answer = await api.post('/api/register', form);
            applySession(answer); // still signed out: a campus rep must approve the account first
            navigate('/login', { state: { notice: answer.message } });
        } catch (error) {
            setFailure({ message: error.message, errors: error.errors });
            setBusy(false);
        }
    }

    const set = (field) => (event) => setForm({ ...form, [field]: event.target.value });
    const errors = failure.errors;

    return (
        <div className="container page-form">
            <div className="card form-card">
                <h1>Create your account</h1>
                <p className="muted">Free for students. We only store your name, email and the choices you make in CampusBridge.</p>
                <p className="muted small">A campus representative checks every new account. You can sign in once yours is approved.</p>

                <FormAlert message={failure.message} />

                {/* noValidate: the server checks everything and explains each problem. */}
                <form className="stack" onSubmit={submit} noValidate>
                    <div className="field">
                        <label htmlFor="name">Name</label>
                        <input id="name" autoComplete="name" maxLength={80} value={form.name} onChange={set('name')} {...errorProps(errors, 'name')} />
                        <FieldError errors={errors} field="name" />
                    </div>
                    <div className="field">
                        <label htmlFor="email">Email</label>
                        <input id="email" type="email" autoComplete="email" value={form.email} onChange={set('email')} {...errorProps(errors, 'email')} />
                        <FieldError errors={errors} field="email" />
                    </div>
                    <div className="field">
                        <label htmlFor="campus_id">Your campus</label>
                        <select id="campus_id" value={form.campus_id} onChange={set('campus_id')} {...errorProps(errors, 'campus_id')}>
                            <option value="">Choose…</option>
                            {meta.campuses.map((campus) => <option key={campus.value} value={campus.value}>{campus.label}</option>)}
                        </select>
                        <p className="hint">You will also see campus-only events from this campus.</p>
                        <FieldError errors={errors} field="campus_id" />
                    </div>
                    <div className="field">
                        <label htmlFor="password">Password</label>
                        <input id="password" type="password" autoComplete="new-password" value={form.password} onChange={set('password')} {...errorProps(errors, 'password')} />
                        <p className="hint">At least 10 characters.</p>
                        <FieldError errors={errors} field="password" />
                    </div>
                    <div className="field">
                        <label htmlFor="password_confirm">Repeat password</label>
                        <input id="password_confirm" type="password" autoComplete="new-password" value={form.password_confirm} onChange={set('password_confirm')} {...errorProps(errors, 'password_confirm')} />
                        <FieldError errors={errors} field="password_confirm" />
                    </div>
                    <button className="button button-primary" type="submit" disabled={busy}>Create account</button>
                </form>
                <p className="muted small">Already have an account? <Link to="/login">Sign in</Link>.</p>
            </div>
        </div>
    );
}
