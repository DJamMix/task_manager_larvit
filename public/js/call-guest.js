/**
 * Гостевой вход в групповой звонок (публичная ссылка).
 */
(function () {
    const cfg = window.BX_GUEST_CALL || {};
    const lobby = document.getElementById('lobby');
    const stage = document.getElementById('stage');
    const grid = document.getElementById('grid');
    const errEl = document.getElementById('err');
    const nameInput = document.getElementById('guest-name');
    const timerEl = document.getElementById('timer');
    let room = null;
    let micOn = true;
    let camOn = false;
    let timerIv = null;
    let sec = 0;

    const postJson = async (url, body) => {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': cfg.csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body || {}),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Ошибка');
        return data;
    };

    const ensureLivekit = () => new Promise((resolve, reject) => {
        if (window.LivekitClient || window.LiveKit) {
            return resolve(window.LivekitClient || window.LiveKit);
        }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/livekit-client@2.9.8/dist/livekit-client.umd.min.js';
        s.onload = () => resolve(window.LivekitClient || window.LiveKit);
        s.onerror = () => reject(new Error('LiveKit недоступен'));
        document.head.appendChild(s);
    });

    const tile = (id, name, color, initials) => {
        let el = grid.querySelector('[data-id="' + id + '"]');
        if (!el) {
            el = document.createElement('div');
            el.className = 'tile';
            el.setAttribute('data-id', id);
            el.innerHTML = '<div class="av"><span></span></div><video playsinline autoplay muted></video><audio autoplay></audio><div class="nm"></div>';
            grid.appendChild(el);
        }
        el.querySelector('.nm').textContent = name || id;
        const sp = el.querySelector('.av span');
        sp.textContent = initials || '?';
        sp.style.background = color || '#64748b';
        return el;
    };

    const attach = (track, participant) => {
        const meta = (() => {
            try { return JSON.parse(participant.metadata || '{}'); } catch (e) { return {}; }
        })();
        const el = tile(
            participant.identity,
            participant.name || meta.name,
            meta.color,
            meta.initials
        );
        const video = el.querySelector('video');
        const audio = el.querySelector('audio');
        const local = participant === room.localParticipant;
        if (track.kind === 'video') {
            track.attach(video);
            video.muted = true;
            if (local) video.style.transform = 'scaleX(-1)';
            el.classList.add('has-video');
        }
        if (track.kind === 'audio' && !local) {
            track.attach(audio);
            audio.play?.().catch(() => {});
        }
    };

    const startTimer = () => {
        sec = 0;
        clearInterval(timerIv);
        timerIv = setInterval(() => {
            sec += 1;
            const m = String(Math.floor(sec / 60)).padStart(2, '0');
            const s = String(sec % 60).padStart(2, '0');
            if (timerEl) timerEl.textContent = m + ':' + s;
        }, 1000);
    };

    const leave = async () => {
        try { await room?.disconnect(); } catch (e) {}
        room = null;
        clearInterval(timerIv);
        location.reload();
    };

    const join = async (video) => {
        errEl.textContent = '';
        const name = (nameInput?.value || '').trim();
        if (name.length < 2) {
            errEl.textContent = 'Укажите имя (минимум 2 символа)';
            return;
        }
        document.getElementById('join-audio').disabled = true;
        document.getElementById('join-video').disabled = true;
        try {
            const payload = await postJson(cfg.joinUrl, { name, video: !!video });
            const LK = await ensureLivekit();
            room = new LK.Room({ adaptiveStream: true, dynacast: true });
            room.on(LK.RoomEvent.TrackSubscribed, (track, pub, p) => attach(track, p));
            room.on(LK.RoomEvent.ParticipantDisconnected, (p) => {
                grid.querySelector('[data-id="' + p.identity + '"]')?.remove();
            });
            room.on(LK.RoomEvent.ActiveSpeakersChanged, (speakers) => {
                const ids = new Set(speakers.map((s) => s.identity));
                grid.querySelectorAll('.tile').forEach((t) => {
                    t.classList.toggle('is-speaking', ids.has(t.getAttribute('data-id')));
                });
            });
        room.on(LK.RoomEvent.DataReceived, (payload) => {
            try {
                const msg = JSON.parse(new TextDecoder().decode(payload));
                if (msg && msg.type === 'call_ended') leave();
            } catch (e) {}
        });
        room.on(LK.RoomEvent.Disconnected, () => leave());

            await room.connect(payload.ws_url, payload.token);
            lobby.style.display = 'none';
            stage.classList.add('is-on');
            startTimer();

            tile(room.localParticipant.identity, payload.me?.name || 'Вы', payload.me?.color, payload.me?.initials);
            await room.localParticipant.setMicrophoneEnabled(true);
            await room.localParticipant.setCameraEnabled(!!video);
            camOn = !!video;
            document.getElementById('btn-cam').classList.toggle('off', !camOn);

            room.localParticipant.videoTrackPublications?.forEach((pub) => {
                if (pub.track) attach(pub.track, room.localParticipant);
            });
            room.remoteParticipants.forEach((p) => {
                tile(p.identity, p.name, (() => { try { return JSON.parse(p.metadata || '{}').color; } catch (e) { return '#64748b'; } })());
                p.trackPublications.forEach((pub) => {
                    if (pub.track) attach(pub.track, p);
                });
            });
        } catch (e) {
            errEl.textContent = e.message || 'Не удалось войти';
            document.getElementById('join-audio').disabled = false;
            document.getElementById('join-video').disabled = false;
        }
    };

    document.getElementById('join-audio')?.addEventListener('click', () => join(false));
    document.getElementById('join-video')?.addEventListener('click', () => join(true));
    document.getElementById('btn-leave')?.addEventListener('click', () => leave());
    document.getElementById('btn-mic')?.addEventListener('click', async () => {
        if (!room) return;
        micOn = !micOn;
        await room.localParticipant.setMicrophoneEnabled(micOn);
        document.getElementById('btn-mic').classList.toggle('off', !micOn);
    });
    document.getElementById('btn-cam')?.addEventListener('click', async () => {
        if (!room) return;
        camOn = !camOn;
        await room.localParticipant.setCameraEnabled(camOn);
        document.getElementById('btn-cam').classList.toggle('off', !camOn);
    });
})();
