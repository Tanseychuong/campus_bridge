/*
 * The video call of a study room: every browser connects DIRECTLY to every
 * other browser in the room (a "mesh"), using WebRTC, which is built into
 * browsers. Camera and microphone never pass through our server.
 *
 * To connect, two browsers must swap a short description of themselves
 * (an "offer" and an "answer", in a format called SDP). We pass those through
 * the PHP API: POST /api/rooms/{id}/signals sends one, and this file asks
 * GET /api/rooms/{id}/signals every second or two for new ones.
 *
 * Who starts? For every pair, the person with the LOWER user id sends the
 * offer. The other one sends a "hello" when it opens the page, which asks the
 * lower id for a fresh offer (needed when someone reloads the page).
 *
 * Each connection is set up once with one audio and one video channel. Turning
 * a camera or microphone on or off later only swaps the track on that channel
 * (replaceTrack), so nothing has to be negotiated again.
 */
import { api } from '../api.js';

/** Waits until the browser has found its network addresses (or 2.5 s), so one offer carries them all. */
function gatheringDone(pc, timeout = 2500) {
    if (pc.iceGatheringState === 'complete') {
        return Promise.resolve();
    }
    return new Promise((resolve) => {
        const timer = setTimeout(resolve, timeout);
        pc.addEventListener('icegatheringstatechange', () => {
            if (pc.iceGatheringState === 'complete') {
                clearTimeout(timer);
                resolve();
            }
        });
    });
}

export class MeshCall {
    /**
     * roomId, myId     - the room and the signed-in user
     * iceServers       - STUN/TURN servers from the API (config.php)
     * startCursor      - skip connection messages that were meant for an earlier page
     * onChange()       - called when a connection or a remote stream changes, to redraw the page
     */
    constructor({ roomId, myId, iceServers, startCursor, onChange }) {
        this.roomId = roomId;
        this.myId = myId;
        this.iceServers = iceServers;
        this.cursor = startCursor;
        this.onChange = onChange;
        this.peers = new Map(); // user id -> { pc, stream, generation }
        this.tracks = { audio: null, video: null };
        this.running = false;
        this.generation = 0;
    }

    start() {
        this.running = true;
        this.pollSignals();
    }

    /**
     * Leaves the call: tells everyone ("bye") and closes all connections.
     * Resolves once the byes were sent, so the caller can leave the room
     * AFTER them (the server only passes on messages from people in the room).
     */
    stop() {
        this.running = false;
        clearTimeout(this.pollTimer);
        const byes = [...this.peers.keys()].map((id) => this.send(id, 'bye'));
        for (const id of [...this.peers.keys()]) {
            this.closePeer(id);
        }
        return Promise.allSettled(byes);
    }

    /** The people to be connected with (everyone else in the room). */
    setPeers(ids) {
        if (!this.running) {
            return;
        }
        for (const id of ids) {
            if (!this.peers.has(id)) {
                this.connect(id);
            }
        }
        for (const id of [...this.peers.keys()]) {
            if (!ids.includes(id)) {
                this.closePeer(id);
            }
        }
    }

    /** Starts or stops sending our camera ('video') or microphone ('audio'). Pass null to stop. */
    setTrack(kind, track) {
        this.tracks[kind] = track;
        for (const peer of this.peers.values()) {
            const transceiver = peer.pc.getTransceivers().find((t) => t.receiver.track.kind === kind);
            transceiver?.sender.replaceTrack(track).catch(() => {});
        }
        this.limitVideoBitrate();
    }

    /** The remote camera/microphone stream of a user, or null. */
    streamOf(id) {
        return this.peers.get(id)?.stream ?? null;
    }

    /** 'new', 'connecting', 'connected', 'failed'... for a user, or null. */
    stateOf(id) {
        return this.peers.get(id)?.pc.connectionState ?? null;
    }

    /* ---------------- connections ---------------- */

    connect(id) {
        if (this.myId < id) {
            this.sendOffer(id);
        } else {
            this.createPeer(id); // wait for their offer...
            this.send(id, 'hello'); // ...and ask for a fresh one
        }
    }

    createPeer(id) {
        this.closePeer(id);
        const pc = new RTCPeerConnection({ iceServers: this.iceServers });
        const peer = { pc, stream: new MediaStream(), generation: 0 };
        pc.ontrack = (event) => {
            if (!peer.stream.getTracks().includes(event.track)) {
                peer.stream.addTrack(event.track);
            }
            this.onChange();
        };
        pc.onconnectionstatechange = () => {
            this.onChange();
            if (pc.connectionState === 'connected') {
                this.limitVideoBitrate();
            }
            if (pc.connectionState === 'failed') {
                // Try again in a moment, if they are still in the room.
                setTimeout(() => {
                    if (this.running && this.peers.get(id)?.pc === pc) {
                        this.myId < id ? this.sendOffer(id) : this.send(id, 'hello');
                    }
                }, 3000);
            }
        };
        this.peers.set(id, peer);
        this.onChange();
        return peer;
    }

    closePeer(id) {
        const peer = this.peers.get(id);
        if (peer) {
            peer.pc.close();
            this.peers.delete(id);
            this.onChange();
        }
    }

    /** Puts our current camera and microphone tracks on a connection's channels. */
    attachTracks(pc) {
        for (const transceiver of pc.getTransceivers()) {
            const track = this.tracks[transceiver.receiver.track.kind] ?? null;
            transceiver.sender.replaceTrack(track).catch(() => {});
        }
    }

    async sendOffer(id) {
        const peer = this.createPeer(id);
        const { pc } = peer;
        peer.generation = ++this.generation; // an answer must match the offer it answers
        pc.addTransceiver('audio', { direction: 'sendrecv' });
        pc.addTransceiver('video', { direction: 'sendrecv' });
        this.attachTracks(pc);
        try {
            await pc.setLocalDescription(await pc.createOffer());
            await gatheringDone(pc);
            if (this.peers.get(id) === peer) {
                this.send(id, 'offer', { description: pc.localDescription, generation: peer.generation });
            }
        } catch {
            // The connection was closed while we were preparing; a new one takes over.
        }
    }

    async receiveOffer(id, { description, generation }) {
        const peer = this.createPeer(id); // an offer always starts a fresh connection
        const { pc } = peer;
        try {
            await pc.setRemoteDescription(description);
            for (const transceiver of pc.getTransceivers()) {
                transceiver.direction = 'sendrecv'; // we send too, even before a camera is on
            }
            this.attachTracks(pc);
            await pc.setLocalDescription(await pc.createAnswer());
            await gatheringDone(pc);
            if (this.peers.get(id) === peer) {
                this.send(id, 'answer', { description: pc.localDescription, generation });
            }
        } catch {
            // Replaced by a newer offer in the meantime.
        }
    }

    async receiveAnswer(id, { description, generation }) {
        const peer = this.peers.get(id);
        if (peer && peer.generation === generation && peer.pc.signalingState === 'have-local-offer') {
            await peer.pc.setRemoteDescription(description).catch(() => {});
        }
    }

    /**
     * Video quality goes down as more people join: every browser sends its
     * camera to every other one, so the upload is shared between them.
     */
    limitVideoBitrate() {
        const perPeer = Math.max(120_000, Math.min(500_000, Math.floor(2_500_000 / Math.max(1, this.peers.size))));
        for (const peer of this.peers.values()) {
            const sender = peer.pc.getSenders().find((s) => s.track?.kind === 'video');
            if (!sender) {
                continue;
            }
            const parameters = sender.getParameters();
            if (!parameters.encodings?.length) {
                continue; // not negotiated yet; we try again once connected
            }
            parameters.encodings[0].maxBitrate = perPeer;
            sender.setParameters(parameters).catch(() => {});
        }
    }

    /* ---------------- talking to the API ---------------- */

    send(to, kind, payload) {
        const body = { to, kind };
        if (payload) {
            body.payload = JSON.stringify(payload);
        }
        return api.post(`/api/rooms/${this.roomId}/signals`, body).catch(() => {
            // They may have just left; their connection will be cleaned up.
        });
    }

    async pollSignals() {
        if (!this.running) {
            return;
        }
        try {
            const answer = await api.get(`/api/rooms/${this.roomId}/signals?after=${this.cursor}`);
            this.cursor = answer.cursor;
            for (const signal of answer.signals) {
                await this.handleSignal(signal);
            }
        } catch {
            // A network hiccup: try again on the next round.
        }
        // Check often while someone is still connecting, less often once everyone is connected.
        const settling = [...this.peers.values()].some((p) => p.pc.connectionState !== 'connected');
        if (this.running) {
            this.pollTimer = setTimeout(() => this.pollSignals(), settling ? 1000 : 2500);
        }
    }

    async handleSignal({ from, kind, payload }) {
        const data = payload ? JSON.parse(payload) : null;
        if (kind === 'offer') {
            await this.receiveOffer(from, data);
        } else if (kind === 'answer') {
            await this.receiveAnswer(from, data);
        } else if (kind === 'hello' && this.myId < from) {
            await this.sendOffer(from);
        } else if (kind === 'bye') {
            this.closePeer(from);
        }
    }
}
