/*
 * One study room: a focus room (shared timer, chat and talking in breaks only)
 * or a class (scheduled, the host teaches, students talk when the host lets them).
 * Both have the video call: cameras, microphones and the people in the room.
 *
 * There is no live connection to the server: the page asks for the room every
 * few seconds (polling). Each answer also tells the server "I am still here",
 * which is how others see you in the room. The video itself goes directly
 * between the browsers (call.js).
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api } from '../api.js';
import { Badge, ErrorView, Loading, Notice } from '../components/ui.jsx';
import { InitialsAvatar } from '../components/icons.jsx';
import { useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';
import { MeshCall } from './call.js';
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

/**
 * The video call: connects to everyone else in the room while you are a
 * member and the room is live. Returns the call (or null) and redraws the
 * page whenever a connection or remote stream changes.
 */
function useCall(data) {
    const [call, setCall] = useState(null);
    const [, setVersion] = useState(0);
    const { room, me } = data;
    const active = me.is_member && room.is_live;

    useEffect(() => {
        if (!active || typeof RTCPeerConnection === 'undefined') {
            return undefined;
        }
        const mesh = new MeshCall({
            roomId: room.id,
            myId: me.id,
            iceServers: data.call.ice_servers,
            startCursor: data.call.signal_cursor,
            onChange: () => setVersion((v) => v + 1),
        });
        mesh.start();
        setCall(mesh);
        if (import.meta.env.DEV) {
            window.__studyCall = mesh; // handy in the browser console while developing
        }
        return () => {
            mesh.stop();
            setCall(null);
        };
        // The cursor and servers are read once, when the call starts.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [active, room.id, me.id]);

    // Connect to everyone else who is here; drop people who left.
    const others = data.members.filter((m) => !m.is_me).map((m) => m.id);
    const othersKey = others.join(',');
    useEffect(() => {
        call?.setPeers(others);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [call, othersKey]);

    return call;
}

/** A helpful message for why the browser would not give us the camera or microphone. */
function deviceError(error, device) {
    if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
        return `Your browser blocked the ${device}. Allow it with the icon next to the address bar, then try again.`;
    }
    if (error?.name === 'NotFoundError' || error?.name === 'OverconstrainedError') {
        return `No ${device} was found on this device.`;
    }
    if (error?.name === 'NotReadableError') {
        return `Your ${device} is being used by another app. Close that app and try again.`;
    }
    return `Your ${device} could not be turned on.`;
}

/**
 * Your own camera and microphone. The server is asked first (PUT .../media),
 * so it can hand out the camera spots fairly and refuse a microphone during focus time.
 */
function useLocalMedia(data, call, refresh) {
    const { notify } = useSession();
    const [camera, setCamera] = useState(null); // MediaStreamTrack or null
    const [mic, setMic] = useState(null);
    const [busy, setBusy] = useState(false);
    const tracks = useRef({ camera: null, mic: null });
    tracks.current = { camera, mic };
    const roomId = data.room.id;
    const supported = typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia && window.isSecureContext;

    const tellServer = useCallback(
        (cameraOn, micOn) => api.put(`/api/rooms/${roomId}/media`, { camera: cameraOn, mic: micOn }),
        [roomId],
    );

    // Give the call our tracks whenever they (or the call) change.
    useEffect(() => {
        call?.setTrack('video', camera);
    }, [call, camera]);
    useEffect(() => {
        call?.setTrack('audio', mic);
    }, [call, mic]);

    const stopMic = useCallback(() => {
        tracks.current.mic?.stop();
        setMic(null);
    }, []);
    const stopCamera = useCallback(() => {
        tracks.current.camera?.stop();
        setCamera(null);
    }, []);

    async function toggleCamera() {
        setBusy(true);
        try {
            if (camera) {
                stopCamera();
                await tellServer(false, !!mic);
            } else {
                await tellServer(true, !!mic); // may answer "all camera spots are taken"
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: { width: { ideal: 320 }, height: { ideal: 240 }, frameRate: { ideal: 12, max: 15 } },
                    });
                    setCamera(stream.getVideoTracks()[0]);
                } catch (error) {
                    await tellServer(false, !!mic);
                    notify(deviceError(error, 'camera'), 'error');
                }
            }
            refresh();
        } catch (error) {
            notify(error.message, 'error');
        }
        setBusy(false);
    }

    async function toggleMic() {
        setBusy(true);
        try {
            if (mic) {
                stopMic();
                await tellServer(!!camera, false);
            } else {
                await tellServer(!!camera, true); // refused during focus time, or before the host lets you talk
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
                    setMic(stream.getAudioTracks()[0]);
                } catch (error) {
                    await tellServer(!!camera, false);
                    notify(deviceError(error, 'microphone'), 'error');
                }
            }
            refresh();
        } catch (error) {
            notify(error.message, 'error');
        }
        setBusy(false);
    }

    // The moment you may no longer talk (focus time starts, or the host mutes you), the microphone goes off.
    const mayTalk = data.me.may_talk;
    useEffect(() => {
        if (!mayTalk && tracks.current.mic) {
            stopMic();
            tellServer(!!tracks.current.camera, false).catch(() => {});
        }
    }, [mayTalk, stopMic, tellServer]);

    // Opening the page: if the server still thinks our camera or mic is on (e.g. after a reload), correct it.
    const firstLoad = useRef(true);
    useEffect(() => {
        if (firstLoad.current && data.me.is_member) {
            firstLoad.current = false;
            if (data.me.camera_on || data.me.mic_on) {
                tellServer(false, false).catch(() => {});
            }
        }
    }, [data.me, tellServer]);

    // Leaving the page: switch everything off.
    useEffect(() => () => {
        const { camera: cam, mic: mike } = tracks.current;
        cam?.stop();
        mike?.stop();
        if (cam || mike) {
            tellServer(false, false).catch(() => {});
        }
    }, [tellServer]);

    function stopAll() {
        stopCamera();
        stopMic();
    }

    return { camera, mic, busy, supported, toggleCamera, toggleMic, stopAll };
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
    const { room, timer, members, messages, me, chat_open: chatOpen, cameras } = data;
    const isClass = room.kind === 'class';
    const { notify } = useSession();
    const navigate = useNavigate();
    const [busy, setBusy] = useState(false);
    const secondsLeft = useCountdown(timer, receivedAt);
    const call = useCall(data);
    const media = useLocalMedia(data, call, refresh);

    const clock = secondsLeft === null ? null : formatClock(secondsLeft);
    useTitle(clock ? `${clock} ${PHASE_LABELS[timer.phase]} · ${room.name}` : room.name);

    // When a phase ends (or a class reaches its start time), ask the server straight away.
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
    const leave = () => {
        media.stopAll();
        act(async () => {
            await call?.stop(); // say bye to everyone while we are still in the room
            return api.post(`/api/rooms/${room.id}/leave`);
        }, { after: () => navigate('/rooms'), tone: 'info' });
    };
    const startTimer = () => act(() => api.post(`/api/rooms/${room.id}/timer`, { action: 'start' }));
    const stopTimer = () => act(() => api.post(`/api/rooms/${room.id}/timer`, { action: 'stop' }), { tone: 'info' });
    const openClass = () => act(() => api.post(`/api/rooms/${room.id}/open`));
    const letSpeak = (member, allow) => act(() => api.post(`/api/rooms/${room.id}/members/${member.id}/speak`, { allow }), { tone: allow ? 'success' : 'info' });
    const closeRoom = () => {
        if (window.confirm(isClass ? `End “${room.name}” for everyone?` : `Close “${room.name}” for everyone?`)) {
            media.stopAll();
            act(() => api.delete(`/api/rooms/${room.id}`), { after: () => navigate('/rooms'), tone: 'info' });
        }
    };

    return (
        <div className="container">
            <p className="back-link"><Link to="/rooms">← Study rooms</Link></p>

            <div className="page-header room-header">
                <div>
                    <p className="eyebrow">
                        {isClass ? `Class · ${room.starts_label}` : room.timer_label} · Hosted by {room.creator_name}
                        {room.visibility === 'private' && <> · <Badge>Private</Badge></>}
                    </p>
                    <h1>{room.name}</h1>
                    {room.topic && <p className="lead">{room.topic}</p>}
                </div>
                <div className="page-actions">
                    {/* A class host ends the class instead of leaving it. */}
                    {me.is_member ? (
                        !(isClass && me.is_host) && <button className="button button-secondary" type="button" disabled={busy} onClick={leave}>{isClass ? 'Leave class' : 'Leave room'}</button>
                    ) : (
                        <button className="button button-primary" type="button" disabled={busy} onClick={join}>{isClass ? 'Join class' : 'Join and study'}</button>
                    )}
                    {me.can_manage && (
                        <button className="button button-ghost" type="button" disabled={busy} onClick={closeRoom}>{isClass ? 'End class' : 'Close room'}</button>
                    )}
                </div>
            </div>

            {offline && (
                <Notice tone="warning" title="Reconnecting…">CampusBridge cannot reach the server right now. The timer keeps running here.</Notice>
            )}

            {room.invite_code && <InviteCode code={room.invite_code} />}

            <div className="room-layout">
                <div className="stack-large">
                    {isClass && !room.is_live ? (
                        <ClassWaiting room={room} receivedAt={receivedAt} isHost={me.can_manage} isMember={me.is_member} busy={busy} onOpen={openClass} onStart={refresh} />
                    ) : (
                        <VideoStage data={data} call={call} media={media} />
                    )}
                    {!isClass && (
                        <TimerCard room={room} timer={timer} secondsLeft={secondsLeft} canManage={me.can_manage} busy={busy} onStart={startTimer} onStop={stopTimer} />
                    )}
                    {!isClass && me.is_member && <TaskForm roomId={room.id} task={me.task} onSaved={refresh} />}
                    <MemberList room={room} members={members} me={me} cameras={cameras} busy={busy} onLetSpeak={letSpeak} />
                </div>
                <Chat
                    roomId={room.id}
                    title={isClass ? 'Class chat' : 'Break chat'}
                    messages={messages}
                    open={chatOpen}
                    closedText={isClass ? 'Chat opens when the class starts' : `Chat opens at the break (in ${formatClock(secondsLeft ?? 0)})`}
                    isMember={me.is_member}
                    onSent={refresh}
                />
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

/** Before a class starts: when it starts, and (for the host) a button to open it now. */
function ClassWaiting({ room, receivedAt, isHost, isMember, busy, onOpen, onStart }) {
    const countdown = useCountdown({ phase: 'waiting', seconds_left: room.seconds_to_start }, receivedAt);
    const started = countdown !== null && countdown <= 0;
    useEffect(() => {
        if (started) {
            onStart();
        }
    }, [started, onStart]);

    const s = Math.round(countdown ?? 0);
    const days = Math.floor(s / 86400);
    const hours = Math.floor((s % 86400) / 3600);
    const minutes = Math.floor((s % 3600) / 60);
    const inText = days > 0 ? `${days} d ${hours} h` : hours > 0 ? `${hours} h ${minutes} min` : formatClock(s);

    return (
        <section className="card padded class-waiting" aria-labelledby="waiting-heading">
            <p className="eyebrow">Starts {room.starts_label}</p>
            <h2 id="waiting-heading" className="class-waiting-clock">in {inText}</h2>
            <p className="muted">
                {isHost
                    ? 'Students can join the waiting room now. Open the class when you are ready, or it opens by itself at the start time.'
                    : isMember
                        ? 'You are in the waiting room. Cameras, microphones and chat start when the host opens the class.'
                        : 'Join to be in the waiting room. Cameras, microphones and chat start when the class opens.'}
            </p>
            {isHost && (
                <div className="button-row">
                    <button className="button button-primary" type="button" disabled={busy} onClick={onOpen}>Open class now</button>
                </div>
            )}
        </section>
    );
}

/** One person's video (or their own preview). */
function VideoTile({ stream, track, name, isMe, isHost, micLive, connecting, large }) {
    const ref = useRef(null);
    useEffect(() => {
        const el = ref.current;
        if (!el) {
            return;
        }
        const source = track ? new MediaStream([track]) : stream;
        if (el.srcObject !== source) {
            el.srcObject = source ?? null;
        }
    }, [stream, track]);

    return (
        <figure className={`video-tile${large ? ' is-large' : ''}${isMe ? ' is-me' : ''}`}>
            {/* Always muted: sound is played by the <audio> elements, which follow the talking rules. */}
            <video ref={ref} autoPlay playsInline muted />
            {connecting && <span className="video-tile-status">Connecting…</span>}
            <figcaption>
                <span className={`mic-dot${micLive ? ' is-live' : ''}`} aria-label={micLive ? 'Microphone on' : 'Microphone off'} role="img" />
                {isMe ? 'You' : name}
                {isHost && <Badge tone="brand">Host</Badge>}
            </figcaption>
        </figure>
    );
}

/** Plays someone's sound, but only while the server says they may talk (mic_live). */
function RemoteAudio({ stream, live }) {
    const ref = useRef(null);
    useEffect(() => {
        if (ref.current && stream && ref.current.srcObject !== stream) {
            ref.current.srcObject = stream;
        }
    }, [stream]);
    useEffect(() => {
        if (ref.current) {
            ref.current.muted = !live;
            if (live) {
                ref.current.play().catch(() => {}); // blocked until the page was clicked; the next click fixes it
            }
        }
    }, [live, stream]);
    return <audio ref={ref} autoPlay data-testid="remote-audio" />;
}

/** The cameras, the camera and microphone buttons, and everyone's sound. */
function VideoStage({ data, call, media }) {
    const { room, members, me, cameras } = data;
    const isClass = room.kind === 'class';
    const onCamera = members.filter((m) => m.camera_on);
    // In a class the host is shown first and larger.
    onCamera.sort((a, b) => (isClass ? Number(b.is_host) - Number(a.is_host) : 0));
    const spotsFull = cameras.used >= cameras.max && !media.camera && !(isClass && me.is_host);

    let micHint = null;
    if (!me.may_talk) {
        micHint = isClass ? 'The host can let you talk. Ask in the chat.' : 'Microphones are off while everyone focuses. You can talk at the break.';
    }

    return (
        <section className="card padded video-stage" aria-labelledby="video-heading">
            <div className="row-between wrap">
                <h2 id="video-heading" className="section-title">{isClass ? 'Class' : 'Cameras'}</h2>
                <span className="muted small">{cameras.used} of {cameras.max} camera spots used{isClass ? ' (the host’s camera is extra)' : ''}</span>
            </div>

            {onCamera.length === 0 ? (
                <p className="video-empty muted">No cameras on yet.{me.is_member ? ' Turn yours on to study together.' : ''}</p>
            ) : (
                <div className="video-grid">
                    {onCamera.map((m) => (
                        <VideoTile
                            key={m.id}
                            name={m.name}
                            isMe={m.is_me}
                            isHost={m.is_host}
                            micLive={m.mic_live}
                            large={isClass && m.is_host}
                            track={m.is_me ? media.camera : null}
                            stream={m.is_me ? null : call?.streamOf(m.id)}
                            connecting={!m.is_me && call?.stateOf(m.id) !== 'connected'}
                        />
                    ))}
                </div>
            )}

            {/* Everyone's sound (they are muted unless that person may talk right now). */}
            {members.filter((m) => !m.is_me).map((m) => (
                <RemoteAudio key={m.id} stream={call?.streamOf(m.id)} live={m.mic_live} />
            ))}

            {me.is_member && (
                media.supported ? (
                    <div className="call-controls">
                        <button className={`button ${media.camera ? 'button-secondary' : 'button-primary'}`} type="button" disabled={media.busy || spotsFull} onClick={media.toggleCamera}>
                            {media.camera ? 'Turn camera off' : 'Turn camera on'}
                        </button>
                        <button className="button button-secondary" type="button" disabled={media.busy || (!media.mic && !me.may_talk)} onClick={media.toggleMic}>
                            {media.mic ? 'Mute microphone' : 'Unmute microphone'}
                        </button>
                        <p className="hint">
                            {spotsFull ? 'All camera spots are taken. You can still watch, listen and chat. ' : ''}
                            {micHint}
                        </p>
                    </div>
                ) : (
                    <Notice tone="warning" title="Cameras need a secure connection">
                        Your browser only allows cameras and microphones on https:// addresses (or on localhost). You can still watch and chat.
                    </Notice>
                )
            )}
        </section>
    );
}

/** The big shared clock of a focus room, with a ring that fills up as the phase passes. */
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
                    {timer.phase === 'focus' && 'Heads down. Chat and microphones open again at the break.'}
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

function MemberList({ room, members, me, busy, onLetSpeak }) {
    const isClass = room.kind === 'class';
    const hostControls = isClass && me.can_manage && room.is_live;
    return (
        <section className="card padded" aria-labelledby="members-heading">
            <h2 id="members-heading" className="section-title">{isClass ? 'In class' : 'Studying now'} ({members.length}/{room.max_present})</h2>
            {members.length === 0 ? (
                <p className="muted small">Nobody is here right now. Join to be the first.</p>
            ) : (
                <ul className="room-members">
                    {members.map((m) => (
                        <li key={m.id}>
                            <InitialsAvatar name={m.name} size="small" />
                            <span className="room-member-text">
                                <strong>{m.name}</strong>
                                {m.is_me && <span className="muted"> (you)</span>}
                                {m.is_host && <> <Badge tone="brand">Host</Badge></>}
                                {isClass && !m.is_host && m.can_speak && <> <Badge tone="success">Can talk</Badge></>}
                                <span className="muted small room-member-task">
                                    {[m.camera_on && 'Camera on', m.mic_live && 'Talking', !isClass && (m.task || 'No task shared'), m.campus].filter(Boolean).join(' · ')}
                                </span>
                            </span>
                            {hostControls && !m.is_host && (
                                <button className="button button-secondary button-small" type="button" disabled={busy} onClick={() => onLetSpeak(m, !m.can_speak)} aria-label={m.can_speak ? `Mute ${m.name}` : `Let ${m.name} talk`}>
                                    {m.can_speak ? 'Mute' : 'Let talk'}
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function Chat({ roomId, title, messages, open, closedText, isMember, onSent }) {
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

    return (
        <section className="card room-chat" aria-labelledby="chat-heading">
            <div className="room-chat-head">
                <h2 id="chat-heading" className="section-title">{title}</h2>
                <Badge tone={open ? 'success' : 'neutral'}>{open ? 'Open' : 'Paused'}</Badge>
            </div>

            {!isMember ? (
                <p className="muted small room-chat-empty">Join to see and send messages.</p>
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
                            placeholder={open ? 'Say something to the room' : closedText}
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
