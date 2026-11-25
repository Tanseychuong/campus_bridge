/*
 * The shared focus timer on the page.
 *
 * The server says which phase the room is in and how many seconds are left
 * (backend/src/study-rooms/data.php, room_timer). Between two answers the page
 * counts down by itself, so the clock ticks every second without asking the
 * server every second.
 */
import { useEffect, useState } from 'react';
import { Badge } from '../components/ui.jsx';

export const PHASE_LABELS = { idle: 'Not started', focus: 'Focus', break: 'Break' };

/** 1500 -> "25:00" */
export function formatClock(seconds) {
    const s = Math.max(0, Math.round(seconds));
    return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
}

/**
 * Seconds left in the current phase, ticking down locally.
 * `receivedAt` is when the server's answer arrived (Date.now()).
 */
export function useCountdown(timer, receivedAt) {
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        setNow(Date.now()); // a fresh answer: count from now, not from the last tick
        if (timer.phase === 'idle') {
            return undefined;
        }
        const id = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(id);
    }, [timer.phase, receivedAt]);

    if (timer.seconds_left === null) {
        return null;
    }
    // Never count "backwards in time": `now` can be a moment older than the answer.
    const elapsed = Math.max(0, (now - receivedAt) / 1000);
    return Math.max(0, timer.seconds_left - elapsed);
}

/** "Focus · 12 min left" / "Break" / "Not started" for the room list. */
export function PhaseBadge({ timer }) {
    if (timer.phase === 'idle') {
        return <Badge>Not started</Badge>;
    }
    const minutes = Math.ceil(timer.seconds_left / 60);
    return (
        <Badge tone={timer.phase === 'focus' ? 'brand' : 'success'}>
            {PHASE_LABELS[timer.phase]} · {minutes} min left
        </Badge>
    );
}
