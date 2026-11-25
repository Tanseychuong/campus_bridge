/*
 * One focus room: the shared timer, who is studying, your task, and the chat.
 *
 * There is no live connection to the server: the page asks for the room every
 * few seconds (polling). Each answer also tells the server "I am still here",
 * which is how others see you in the room.
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api } from '../api.js';
import { Badge, ErrorView, Loading, Notice } from '../components/ui.jsx';
import { InitialsAvatar } from '../components/icons.jsx';
import { useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';
import { PHASE_LABELS, formatClock, useCountdown } from './timer.jsx';
import './study-rooms.css';

/**
 * Loads the room now and again every few seconds.
 * A short network problem keeps the last answer on screen (offline: true)
 * instead of replacing the whole room with an error page.
 */
function useRoom(id) {
    const [state, setState] = useState({ data: null, error: null, receivedAt: 0, offline: false });
    const alive = useRef(true);

    const refresh = useCallback(async () => {
        try {
            const data = await api.get(`/api/rooms/${id}`);
            if (alive.current) {
                setState({ data, error: null, receivedAt: Date.now(), offline: false });
            }
        } catch (error) {
            if (alive.current) {
                setState((s) => (s.data && error.status === 0 ? { ...s, offline: true } : { ...s, data: null, error }));
            }
        }
    }, [id]);

    useEffect(() => {
        alive.current = true;
        setState({ data: null, error: null, receivedAt: 0, offline: false });
        refresh();
        return () => {
            alive.current = false;
        };
    }, [refresh]);

    // Keep checking in. This keeps running in a background tab, so you stay "in the room" while you study elsewhere.
    const pollSeconds = state.data?.poll_seconds ?? 5;
    useEffect(() => {
        const timer = setInterval(refresh, pollSeconds * 1000);
        return () => clearInterval(timer);
    }, [refresh, pollSeconds]);

    return { ...state, refresh };
}

export function StudyRoom() {
    const { id } = useParams();
    const room = useRoom(id);

    if (room.error) {
        return <ErrorView error={room.error} />;
    }
    if (!room.data) {
        return <Loading />;
    }
    return <RoomView {...room} />;
}

function RoomView({ data, receivedAt, offline, refresh }) {
    const { room, timer, members, messages, me, chat_open: chatOpen } = data;
    const { notify } = useSession();
    const navigate = useNavigate();
    const [busy, setBusy] = useState(false);
    const secondsLeft = useCountdown(timer, receivedAt);

    const clock = secondsLeft === null ? null : formatClock(secondsLeft);
    useTitle(clock ? `${clock} ${PHASE_LABELS[timer.phase]} · ${room.name}` : room.name);

    // When the phase ends, ask the server straight away instead of waiting for the next check-in.
    const phaseEnded = secondsLeft !== null && secondsLeft <= 0;
    useEffect(() => {
        if (phaseEnded) {
            refresh();
        }
    }, [phaseEnded, refresh]);

    /** Sends an action, shows its message and reloads the room. */
    async function act(send, { after, tone = 'success' } = {}) {
        setBusy(true);
        try {
            const answer = await send();
            if (after) {
                notify(answer.message, tone, true);
                after();
                return;
            }
            notify(answer.message, tone);
            await refresh();
        } catch (error) {
            notify(error.message, 'error');
        }
        setBusy(false);
    }

    const join = () => act(() => api.post(`/api/rooms/${room.id}/join`));
    const leave = () => act(() => api.post(`/api/rooms/${room.id}/leave`), { after: () => navigate('/rooms'), tone: 'info' });
    const startTimer = () => act(() => api.post(`/api/rooms/${room.id}/timer`, { action: 'start' }));
    const stopTimer = () => act(() => api.post(`/api/rooms/${room.id}/timer`, { action: 'stop' }), { tone: 'info' });
    const closeRoom = () => {
        if (window.confirm(`Close “${room.name}” for everyone?`)) {
            act(() => api.delete(`/api/rooms/${room.id}`), { after: () => navigate('/rooms'), tone: 'info' });
        }
    };

    return (
        <div className="container">
            <p className="back-link"><Link to="/rooms">← Focus rooms</Link></p>

            <div className="page-header room-header">
                <div>
                    <p className="eyebrow">
                        Hosted by {room.creator_name} · {room.timer_label}
                        {room.visibility === 'private' && <> · <Badge>Private</Badge></>}
                    </p>
                    <h1>{room.name}</h1>
                    {room.topic && <p className="lead">{room.topic}</p>}
                </div>
                <div className="page-actions">
                    {me.is_member ? (
                        <button className="button button-secondary" type="button" disabled={busy} onClick={leave}>Leave room</button>
                    ) : (
                        <button className="button button-primary" type="button" disabled={busy} onClick={join}>Join and study</button>
                    )}
                    {me.can_manage && (
                        <button className="button button-ghost" type="button" disabled={busy} onClick={closeRoom}>Close room</button>
                    )}
                </div>
            </div>

            {offline && (
                <Notice tone="warning" title="Reconnecting…">CampusBridge cannot reach the server right now. The timer keeps running here.</Notice>
            )}

            {room.invite_code && (
                <InviteCode code={room.invite_code} />
            )}

            <div className="room-layout">
                <div className="stack-large">
                    <TimerCard room={room} timer={timer} secondsLeft={secondsLeft} canManage={me.can_manage} busy={busy} onStart={startTimer} onStop={stopTimer} />
                    {me.is_member && <TaskForm roomId={room.id} task={me.task} onSaved={refresh} />}
                    <MemberList members={members} max={room.max_present} />
                </div>
                <Chat roomId={room.id} messages={messages} timer={{ ...timer, chat_open: chatOpen }} secondsLeft={secondsLeft} isMember={me.is_member} onSent={refresh} />
            </div>
        </div>
    );
}

function InviteCode({ code }) {
    const [copied, setCopied] = useState(false);
    async function copy() {
        try {
            await navigator.clipboard.writeText(code);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard not allowed (e.g. not https): the code is still on screen to copy by hand.
        }
    }
    return (
        <div className="card room-invite">
            <span className="muted small">Invite code. Share it with the people you want to study with.</span>
            <code className="room-invite-code">{code}</code>
            <button className="button button-secondary button-small" type="button" onClick={copy}>{copied ? 'Copied' : 'Copy'}</button>
        </div>
    );
}

/** The big shared clock with a ring that fills up as the phase passes. */
function TimerCard({ room, timer, secondsLeft, canManage, busy, onStart, onStop }) {
    const idle = timer.phase === 'idle';
    const total = (timer.phase === 'break' ? room.break_minutes : room.focus_minutes) * 60;
    const progress = idle ? 0 : 1 - secondsLeft / total;
    const radius = 54;
    const circumference = 2 * Math.PI * radius;

    return (
        <section className={`card padded room-timer phase-${timer.phase}`} aria-labelledby="timer-heading">
            <h2 id="timer-heading" className="sr-only">Shared timer</h2>
            <div className="room-timer-ring">
                <svg viewBox="0 0 120 120" aria-hidden="true">
                    <circle className="ring-track" cx="60" cy="60" r={radius} />
                    {/* Not drawn at 0%: the round line end would still show as a dot. */}
                    {progress > 0 && (
                        <circle className="ring-fill" cx="60" cy="60" r={radius} strokeDasharray={circumference} strokeDashoffset={circumference * (1 - progress)} />
                    )}
                </svg>
                <div className="room-timer-text">
                    <span className="room-timer-phase">{PHASE_LABELS[timer.phase]}</span>
                    <span className="room-timer-clock" role="timer" aria-live="off">{idle ? formatClock(room.focus_minutes * 60) : formatClock(secondsLeft)}</span>
                    {!idle && <span className="muted small">Round {timer.round}</span>}
                </div>
            </div>
            {/* Screen readers hear the phase changes, not every second. */}
            <p className="sr-only" aria-live="polite">{idle ? 'The timer has not started.' : `${PHASE_LABELS[timer.phase]} time, round ${timer.round}.`}</p>

            <div className="room-timer-side">
                <p className="room-timer-hint">
                    {idle && (canManage ? 'Start the timer when everyone is ready. Everyone in the room follows the same clock.' : 'Waiting for the host to start the timer. Say hi in the chat meanwhile.')}
                    {timer.phase === 'focus' && 'Heads down. Chat opens again at the break.'}
                    {timer.phase === 'break' && 'Break time: stretch, drink some water, check in with the others.'}
                </p>
                {canManage && (
                    <div className="button-row">
                        <button className="button button-primary" type="button" disabled={busy} onClick={onStart}>{idle ? 'Start timer' : 'Restart from focus'}</button>
                        {!idle && <button className="button button-secondary" type="button" disabled={busy} onClick={onStop}>Stop</button>}
                    </div>
                )}
            </div>
        </section>
    );
}

function TaskForm({ roomId, task, onSaved }) {
    const { notify } = useSession();
    const [value, setValue] = useState(task ?? '');
    const [busy, setBusy] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setBusy(true);
        try {
            const answer = await api.put(`/api/rooms/${roomId}/task`, { task: value });
            notify(answer.message);
            onSaved();
        } catch (error) {
            notify(error.message, 'error');
        }
        setBusy(false);
    }

    return (
        <form className="card padded room-task" onSubmit={submit}>
            <label htmlFor="room-task">What are you working on?</label>
            <div className="room-code-row">
                <input id="room-task" maxLength={120} placeholder="e.g. Calculus problem set 4" value={value} onChange={(e) => setValue(e.target.value)} />
                <button className="button button-secondary" type="submit" disabled={busy || value === (task ?? '')}>Save</button>
            </div>
            <p className="hint">Others in the room see this next to your name.</p>
        </form>
    );
}

function MemberList({ members, max }) {
    return (
        <section className="card padded" aria-labelledby="members-heading">
            <h2 id="members-heading" className="section-title">Studying now ({members.length}/{max})</h2>
            {members.length === 0 ? (
                <p className="muted small">Nobody is here right now. Join to be the first.</p>
            ) : (
                <ul className="room-members">
                    {members.map((m) => (
                        <li key={m.id}>
                            <InitialsAvatar name={m.name} size="small" />
                            <span>
                                <strong>{m.name}</strong>
                                {m.is_me && <span className="muted"> (you)</span>}
                                {m.is_host && <> <Badge tone="brand">Host</Badge></>}
                                <span className="muted small room-member-task">{m.task || 'No task shared'}{m.campus ? ` · ${m.campus}` : ''}</span>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function Chat({ roomId, messages, timer, secondsLeft, isMember, onSent }) {
    const { notify } = useSession();
    const [body, setBody] = useState('');
    const [busy, setBusy] = useState(false);
    const listRef = useRef(null);
    const lastId = messages.length ? messages[messages.length - 1].id : 0;

    // Scroll to the newest message when one arrives.
    useEffect(() => {
        if (listRef.current) {
            listRef.current.scrollTop = listRef.current.scrollHeight;
        }
    }, [lastId]);

    async function send(event) {
        event.preventDefault();
        if (body.trim() === '') {
            return;
        }
        setBusy(true);
        try {
            await api.post(`/api/rooms/${roomId}/messages`, { body });
            setBody('');
            await onSent();
        } catch (error) {
            notify(error.message, 'error');
        }
        setBusy(false);
    }

    const open = timer.chat_open;
    return (
        <section className="card room-chat" aria-labelledby="chat-heading">
            <div className="room-chat-head">
                <h2 id="chat-heading" className="section-title">Break chat</h2>
                <Badge tone={open ? 'success' : 'neutral'}>{open ? 'Open' : 'Paused'}</Badge>
            </div>

            {!isMember ? (
                <p className="muted small room-chat-empty">Join the room to see and send messages.</p>
            ) : (
                <>
                    <ol className="room-messages" ref={listRef} aria-live="polite">
                        {messages.length === 0 && <li className="muted small room-chat-empty">No messages yet.</li>}
                        {messages.map((m) => (
                            <li key={m.id} className={m.is_me ? 'is-me' : undefined}>
                                <span className="room-message-meta"><strong>{m.is_me ? 'You' : m.name}</strong> <span className="muted">{m.time}</span></span>
                                <span className="room-message-body">{m.body}</span>
                            </li>
                        ))}
                    </ol>
                    <form className="room-chat-form" onSubmit={send}>
                        <label className="sr-only" htmlFor="room-message">Message</label>
                        <input
                            id="room-message"
                            maxLength={300}
                            autoComplete="off"
                            disabled={!open}
                            placeholder={open ? 'Say something to the room' : `Chat opens at the break (in ${formatClock(secondsLeft ?? 0)})`}
                            value={body}
                            onChange={(e) => setBody(e.target.value)}
                        />
                        <button className="button button-primary" type="submit" disabled={!open || busy || body.trim() === ''}>Send</button>
                    </form>
                </>
            )}
        </section>
    );
}
