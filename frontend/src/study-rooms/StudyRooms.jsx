/*
 * Focus rooms: the list of open rooms, "create a room" and "join with a code".
 * The room itself is StudyRoom.jsx (same folder). Every rule is checked again
 * by the server (backend/src/study-rooms/).
 */
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import { ApiView, Badge, EmptyState, FieldError, FormAlert, PageHeader, errorProps } from '../components/ui.jsx';
import { Icon } from '../components/icons.jsx';
import { useApi, useFocusFirstError, useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';
import { PhaseBadge } from './timer.jsx';
import './study-rooms.css';

function RoomCard({ room }) {
    return (
        <li className="card item-card room-card">
            <p className="card-kicker">
                <PhaseBadge timer={room.timer} />
                {room.visibility === 'private' && <Badge>Private</Badge>}
                {room.is_member && <Badge tone="brand">Joined</Badge>}
            </p>
            <h3 className="card-title"><Link to={`/rooms/${room.id}`}>{room.name}</Link></h3>
            {room.topic && <p className="card-text">{room.topic}</p>}
            <p className="muted small room-card-meta">
                <span><Icon name="clock" /> {room.timer_label}</span>
                <span>Hosted by {room.creator_name}</span>
            </p>
            <div className="card-footer">
                <span className="room-present">
                    <span className={`presence-dot${room.present_count > 0 ? ' is-live' : ''}`} aria-hidden="true" />
                    {room.present_count === 1 ? '1 person studying' : `${room.present_count} people studying`}
                </span>
                <Link className="button button-secondary button-small" to={`/rooms/${room.id}`}>{room.is_member ? 'Open' : 'View'}</Link>
            </div>
        </li>
    );
}

function CreateRoomForm({ timers, visibility, canCreate, maxOpen }) {
    const navigate = useNavigate();
    const { notify } = useSession();
    const [values, setValues] = useState({ kind: 'focus', name: '', topic: '', timer: timers[0].value, visibility: 'public' });
    const [failure, setFailure] = useState({ message: '', errors: {} });
    const [busy, setBusy] = useState(false);
    useFocusFirstError(failure.errors);
    const set = (field) => (event) => setValues({ ...values, [field]: event.target.value });
    const errors = failure.errors;

    async function submit(event) {
        event.preventDefault();
        setBusy(true);
        try {
            const answer = await api.post('/api/rooms', values);
            notify(answer.message, 'success', true);
            navigate(`/rooms/${answer.room_id}`);
        } catch (error) {
            setFailure({ message: error.message, errors: error.errors });
            setBusy(false);
        }
    }

    if (!canCreate) {
        return <p className="muted small">You already have {maxOpen} open rooms. Close one of them to create another.</p>;
    }

    return (
        <form className="stack" onSubmit={submit} noValidate>
            <FormAlert message={failure.message} />
            <div className="field">
                <label htmlFor="room-name">Room name <span className="required">*</span></label>
                <input id="room-name" maxLength={60} placeholder="e.g. Exam prep: Data Structures" value={values.name} onChange={set('name')} {...errorProps(errors, 'name')} />
                <FieldError errors={errors} field="name" />
            </div>
            <div className="field">
                <label htmlFor="room-topic">Topic (optional)</label>
                <input id="room-topic" maxLength={160} placeholder="What will people work on?" value={values.topic} onChange={set('topic')} {...errorProps(errors, 'topic')} />
                <FieldError errors={errors} field="topic" />
            </div>
            <div className="field">
                <label htmlFor="room-timer">Timer</label>
                <select id="room-timer" value={values.timer} onChange={set('timer')} {...errorProps(errors, 'timer')}>
                    {timers.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
                <FieldError errors={errors} field="timer" />
            </div>
            <div className="field" role="radiogroup" aria-labelledby="room-visibility-label">
                <p className="field-label" id="room-visibility-label"><strong>Who can join?</strong></p>
                {visibility.map((option) => (
                    <label className="radio" key={option.value}>
                        <input type="radio" name="visibility" value={option.value} checked={values.visibility === option.value} onChange={set('visibility')} /> {option.label}
                    </label>
                ))}
                <FieldError errors={errors} field="visibility" />
            </div>
            <div>
                <button className="button button-primary" type="submit" disabled={busy}>Create room</button>
            </div>
        </form>
    );
}

function JoinByCodeForm() {
    const navigate = useNavigate();
    const { notify } = useSession();
    const [code, setCode] = useState('');
    const [failure, setFailure] = useState({ message: '', errors: {} });
    const [busy, setBusy] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setBusy(true);
        try {
            const answer = await api.post('/api/rooms/join', { code });
            notify(answer.message, 'success', true);
            navigate(`/rooms/${answer.room_id}`);
        } catch (error) {
            setFailure({ message: error.message, errors: error.errors });
            setBusy(false);
        }
    }

    return (
        <form className="stack" onSubmit={submit} noValidate>
            <div className="field">
                <label htmlFor="room-code">Invite code</label>
                <div className="room-code-row">
                    <input id="room-code" className="room-code-input" maxLength={8} autoComplete="off" spellCheck={false} placeholder="K7QX3M" value={code} onChange={(e) => setCode(e.target.value)} {...errorProps(failure.errors, 'code')} />
                    <button className="button button-secondary" type="submit" disabled={busy || code.trim() === ''}>Join</button>
                </div>
                {failure.message && <p className="field-error" id="code-error">{failure.message}</p>}
            </div>
        </form>
    );
}

export function StudyRooms() {
    useTitle('Focus rooms');
    const result = useApi('/api/rooms');

    return (
        <ApiView result={result}>
            {({ rooms, can_create: canCreate, max_open: maxOpen, timers, visibility }) => (
                <div className="container">
                    <PageHeader
                        eyebrow="Study together"
                        title="Focus rooms"
                        description="Join other students online and study to the same timer. Everyone focuses together, then takes a break together. Chat opens only during breaks."
                    />
                    <div className="rooms-layout">
                        <section aria-labelledby="open-rooms-heading">
                            <h2 id="open-rooms-heading" className="section-title">Open rooms</h2>
                            {rooms.length === 0 ? (
                                <EmptyState title="No rooms open right now" description="Create the first one. Others can join while you study." />
                            ) : (
                                <ul className="grid grid-2 room-grid">
                                    {rooms.map((room) => <RoomCard key={room.id} room={room} />)}
                                </ul>
                            )}
                        </section>

                        <aside className="stack-large">
                            <section className="card padded" aria-labelledby="create-room-heading">
                                <h2 id="create-room-heading" className="section-title">Create a room</h2>
                                <CreateRoomForm timers={timers} visibility={visibility} canCreate={canCreate} maxOpen={maxOpen} />
                            </section>
                            <section className="card padded" aria-labelledby="join-code-heading">
                                <h2 id="join-code-heading" className="section-title">Got an invite code?</h2>
                                <JoinByCodeForm />
                            </section>
                        </aside>
                    </div>
                </div>
            )}
        </ApiView>
    );
}
