/*
 * Study rooms: focus rooms and classes that are live now, upcoming classes,
 * "create a focus room or a class" and "join with a code".
 * One room is StudyRoom.jsx (same folder). Every rule is checked again by the
 * server (backend/src/study-rooms/).
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
    const isClass = room.kind === 'class';
    return (
        <li className="card item-card room-card">
            <p className="card-kicker">
                {isClass ? (
                    <Badge tone={room.is_live ? 'success' : 'neutral'}>{room.is_live ? 'Live class' : 'Class'}</Badge>
                ) : (
                    <PhaseBadge timer={room.timer} />
                )}
                {room.visibility === 'private' && <Badge>Private</Badge>}
                {room.is_member && <Badge tone="brand">Joined</Badge>}
            </p>
            <h3 className="card-title"><Link to={`/rooms/${room.id}`}>{room.name}</Link></h3>
            {room.topic && <p className="card-text">{room.topic}</p>}
            <p className="muted small room-card-meta">
                <span><Icon name="clock" /> {isClass ? room.starts_label : room.timer_label}</span>
                <span>{isClass ? 'Taught' : 'Hosted'} by {room.creator_name}</span>
            </p>
            <div className="card-footer">
                <span className="room-present">
                    <span className={`presence-dot${room.present_count > 0 ? ' is-live' : ''}`} aria-hidden="true" />
                    {room.present_count === 1 ? '1 person here' : `${room.present_count} people here`}
                </span>
                <Link className="button button-secondary button-small" to={`/rooms/${room.id}`}>{room.is_member ? 'Open' : 'View'}</Link>
            </div>
        </li>
    );
}

/** The next full hour, as "HH:MM" (a sensible default start time for a class). */
function nextFullHour() {
    const hour = (new Date().getHours() + 1) % 24;
    return `${String(hour).padStart(2, '0')}:00`;
}

function CreateRoomForm({ options, canCreate, maxOpen }) {
    const navigate = useNavigate();
    const { notify } = useSession();
    const [values, setValues] = useState({
        kind: 'focus', name: '', topic: '', timer: options.timers[0].value, visibility: 'public', date: options.today, time: nextFullHour(),
    });
    const [failure, setFailure] = useState({ message: '', errors: {} });
    const [busy, setBusy] = useState(false);
    useFocusFirstError(failure.errors);
    const set = (field) => (event) => setValues({ ...values, [field]: event.target.value });
    const errors = failure.errors;
    const isClass = values.kind === 'class';

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
        return <p className="muted small">You already have {maxOpen} open rooms or upcoming classes. Close one of them to create another.</p>;
    }

    return (
        <form className="stack" onSubmit={submit} noValidate>
            <FormAlert message={failure.message} />
            <div className="field" role="radiogroup" aria-labelledby="room-kind-label">
                <p className="field-label" id="room-kind-label"><strong>What are you creating?</strong></p>
                {options.kinds.map((option) => (
                    <label className="radio" key={option.value}>
                        <input type="radio" name="kind" value={option.value} checked={values.kind === option.value} onChange={set('kind')} /> {option.label}
                    </label>
                ))}
                <FieldError errors={errors} field="kind" />
            </div>
            <div className="field">
                <label htmlFor="room-name">{isClass ? 'Class name' : 'Room name'} <span className="required">*</span></label>
                <input id="room-name" maxLength={60} placeholder={isClass ? 'e.g. Intro to SQL joins' : 'e.g. Exam prep: Data Structures'} value={values.name} onChange={set('name')} {...errorProps(errors, 'name')} />
                <FieldError errors={errors} field="name" />
            </div>
            <div className="field">
                <label htmlFor="room-topic">{isClass ? 'What will you teach? (optional)' : 'Topic (optional)'}</label>
                <input id="room-topic" maxLength={160} placeholder={isClass ? 'e.g. Bring a laptop with MySQL installed' : 'What will people work on?'} value={values.topic} onChange={set('topic')} {...errorProps(errors, 'topic')} />
                <FieldError errors={errors} field="topic" />
            </div>
            {isClass ? (
                <fieldset className="field">
                    <legend>Starts <span className="required">*</span></legend>
                    <div className="room-code-row">
                        <label className="sr-only" htmlFor="room-date">Date</label>
                        <input id="room-date" type="date" min={options.today} max={options.max_date} value={values.date} onChange={set('date')} {...errorProps(errors, 'starts_at')} />
                        <label className="sr-only" htmlFor="room-time">Time</label>
                        <input id="room-time" type="time" value={values.time} onChange={set('time')} {...errorProps(errors, 'starts_at')} />
                    </div>
                    <p className="hint">Students can join from this time. You can open it earlier.</p>
                    <FieldError errors={errors} field="starts_at" />
                </fieldset>
            ) : (
                <div className="field">
                    <label htmlFor="room-timer">Timer</label>
                    <select id="room-timer" value={values.timer} onChange={set('timer')} {...errorProps(errors, 'timer')}>
                        {options.timers.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                    <FieldError errors={errors} field="timer" />
                </div>
            )}
            <div className="field" role="radiogroup" aria-labelledby="room-visibility-label">
                <p className="field-label" id="room-visibility-label"><strong>Who can join?</strong></p>
                {options.visibility.map((option) => (
                    <label className="radio" key={option.value}>
                        <input type="radio" name="visibility" value={option.value} checked={values.visibility === option.value} onChange={set('visibility')} /> {option.label}
                    </label>
                ))}
                <FieldError errors={errors} field="visibility" />
            </div>
            <div>
                <button className="button button-primary" type="submit" disabled={busy}>{isClass ? 'Schedule class' : 'Create room'}</button>
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
    useTitle('Study rooms');
    const result = useApi('/api/rooms');

    return (
        <ApiView result={result}>
            {(data) => {
                const live = data.rooms.filter((r) => r.is_live);
                const upcoming = data.rooms.filter((r) => !r.is_live);
                return (
                    <div className="container">
                        <PageHeader
                            eyebrow="Study together"
                            title="Study rooms"
                            description="Join a focus room to study on camera with others to a shared timer, with chat and talking only in the breaks. Or join a class, where a host teaches and lets students speak."
                        />
                        <div className="rooms-layout">
                            <div className="stack-large">
                                <section aria-labelledby="live-heading">
                                    <h2 id="live-heading" className="section-title">Live now</h2>
                                    {live.length === 0 ? (
                                        <EmptyState title="Nothing live right now" description="Create a focus room. Others can join while you study." />
                                    ) : (
                                        <ul className="grid grid-2 room-grid">
                                            {live.map((room) => <RoomCard key={room.id} room={room} />)}
                                        </ul>
                                    )}
                                </section>
                                {upcoming.length > 0 && (
                                    <section aria-labelledby="upcoming-heading">
                                        <h2 id="upcoming-heading" className="section-title">Upcoming classes</h2>
                                        <ul className="grid grid-2 room-grid">
                                            {upcoming.map((room) => <RoomCard key={room.id} room={room} />)}
                                        </ul>
                                    </section>
                                )}
                            </div>

                            <aside className="stack-large">
                                <section className="card padded" aria-labelledby="create-room-heading">
                                    <h2 id="create-room-heading" className="section-title">Create</h2>
                                    <CreateRoomForm options={data} canCreate={data.can_create} maxOpen={data.max_open} />
                                </section>
                                <section className="card padded" aria-labelledby="join-code-heading">
                                    <h2 id="join-code-heading" className="section-title">Got an invite code?</h2>
                                    <JoinByCodeForm />
                                </section>
                            </aside>
                        </div>
                    </div>
                );
            }}
        </ApiView>
    );
}
