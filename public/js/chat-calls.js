/**
 * Групповые звонки через LiveKit (SFU).
 * Шифрование медиа: DTLS-SRTP (кроссплатформенно). Сигналинг: WSS.
 */
(function () {
    const root = document.querySelector('.bx-messenger');
    if (!root || root.getAttribute('data-calls-enabled') !== '1') return;

    const csrf = root.getAttribute('data-csrf')
        || document.querySelector('meta[name="csrf_token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
        || '';

    const startUrl = root.getAttribute('data-calls-start-url') || '';
    const joinTpl = root.getAttribute('data-call-join-tpl') || '';
    const activeChatId = String(root.getAttribute('data-active-chat') || '');

    const stage = document.getElementById('bx-call-stage');
    const grid = document.getElementById('bx-call-grid');
    const titleEl = document.getElementById('bx-call-title');
    const timerEl = document.getElementById('bx-call-timer');
    const incoming = document.getElementById('bx-incoming-call');
    const incomingTitle = document.getElementById('bx-incoming-title');
    const incomingSub = document.getElementById('bx-incoming-sub');
    const activeBar = document.getElementById('bx-active-call-bar');
    const activeBarText = document.getElementById('bx-active-call-text');
    const endAllBtn = document.getElementById('bx-call-end-all');

    let room = null;
    let callId = null;
    let isStarter = false;
    let micOn = true;
    let camOn = false;
    let timerSec = 0;
    let timerIv = null;
    let ringingCallId = null;
    let joinableCallId = null;
    let livekitReady = null;

    const postJson = async (url, body) => {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body || {}),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Ошибка звонка');
        return data;
    };

    const callUrl = (id, action) => joinTpl.replace('__ID__', String(id)).replace('/join', '/' + action);

    const ensureLivekit = () => {
        if (window.LivekitClient || window.LiveKit) {
            return Promise.resolve(window.LivekitClient || window.LiveKit);
        }
        if (livekitReady) return livekitReady;
        livekitReady = new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/livekit-client@2.9.8/dist/livekit-client.umd.min.js';
            s.onload = () => resolve(window.LivekitClient || window.LiveKit);
            s.onerror = () => reject(new Error('Не удалось загрузить LiveKit client'));
            document.head.appendChild(s);
        });
        return livekitReady;
    };

    const tileEl = (identity, name) => {
        let el = grid?.querySelector('[data-id="' + identity + '"]');
        if (el) return el;
        el = document.createElement('div');
        el.className = 'bx-call-tile';
        el.setAttribute('data-id', identity);
        el.innerHTML = '<video playsinline autoplay></video><div class="bx-call-tile__name"></div>';
        el.querySelector('.bx-call-tile__name').textContent = name || identity;
        grid?.appendChild(el);
        return el;
    };

    const attachTrack = (track, identity, name) => {
        const tile = tileEl(identity, name);
        const video = tile.querySelector('video');
        if (!video) return;
        if (track.kind === 'video' || track.kind === 'audio') {
            track.attach(video);
        }
        // Свой звук не воспроизводим (эхо), чужой — да
        if (track.kind === 'audio') {
            video.muted = identity === String(room?.localParticipant?.identity);
        }
        if (identity === String(room?.localParticipant?.identity) && track.kind === 'video') {
            video.muted = true;
            video.style.transform = 'scaleX(-1)';
        }
    };

    const detachTrack = (track, identity) => {
        const tile = grid?.querySelector('[data-id="' + identity + '"]');
        const video = tile?.querySelector('video');
        if (video) track.detach(video);
    };

    const startTimer = () => {
        timerSec = 0;
        clearInterval(timerIv);
        timerIv = setInterval(() => {
            timerSec += 1;
            const m = String(Math.floor(timerSec / 60)).padStart(2, '0');
            const s = String(timerSec % 60).padStart(2, '0');
            if (timerEl) timerEl.textContent = m + ':' + s;
        }, 1000);
    };

    const stopCallUi = () => {
        clearInterval(timerIv);
        if (stage) stage.hidden = true;
        if (grid) grid.innerHTML = '';
        document.body.classList.remove('bx-call-open');
        callId = null;
        isStarter = false;
        room = null;
        if (endAllBtn) endAllBtn.hidden = true;
    };

    const hangup = async () => {
        const id = callId;
        try {
            if (room) await room.disconnect();
        } catch (e) {}
        stopCallUi();
        if (id) {
            try { await postJson(callUrl(id, 'leave'), {}); } catch (e) {}
        }
    };

    const endForAll = async () => {
        const id = callId;
        try {
            if (room) await room.disconnect();
        } catch (e) {}
        stopCallUi();
        if (id) {
            try { await postJson(callUrl(id, 'end'), {}); } catch (e) {}
        }
    };

    const connectRoom = async (payload) => {
        const LK = await ensureLivekit();
        if (!LK?.Room) throw new Error('LiveKit client недоступен');

        if (room) {
            try { await room.disconnect(); } catch (e) {}
        }

        callId = payload.call_id;
        isStarter = !!payload.is_starter;
        camOn = !!payload.video;
        micOn = true;
        if (titleEl) titleEl.textContent = payload.video ? 'Видеозвонок' : 'Аудиозвонок';
        if (stage) stage.hidden = false;
        document.body.classList.add('bx-call-open');
        if (incoming) incoming.hidden = true;
        if (activeBar) activeBar.hidden = true;
        if (endAllBtn) endAllBtn.hidden = !isStarter;
        startTimer();

        room = new LK.Room({ adaptiveStream: true, dynacast: true });
        room.on(LK.RoomEvent.TrackSubscribed, (track, publication, participant) => {
            attachTrack(track, participant.identity, participant.name || participant.identity);
        });
        room.on(LK.RoomEvent.TrackUnsubscribed, (track, publication, participant) => {
            detachTrack(track, participant.identity);
        });
        room.on(LK.RoomEvent.ParticipantDisconnected, (participant) => {
            grid?.querySelector('[data-id="' + participant.identity + '"]')?.remove();
        });
        room.on(LK.RoomEvent.Disconnected, () => stopCallUi());

        if (!payload.ws_url) {
            throw new Error('LiveKit URL пустой. Проверьте LIVEKIT_URL в .env');
        }
        try {
            await room.connect(payload.ws_url, payload.token);
        } catch (err) {
            const hint = [
                'Не удалось подключиться к сигналингу LiveKit (' + payload.ws_url + ').',
                'Проверьте: 1) LiveKit запущен (порт 7880)',
                '2) LIVEKIT_URL доступен из браузера (не localhost сервера, если вы на другом ПК)',
                '3) На HTTPS нужен wss://, не ws://',
            ].join('\n');
            throw new Error((err && err.message ? err.message + '\n\n' : '') + hint);
        }
        await room.localParticipant.setMicrophoneEnabled(true);
        await room.localParticipant.setCameraEnabled(!!payload.video);

        room.localParticipant.videoTrackPublications.forEach((pub) => {
            if (pub.track) attachTrack(pub.track, room.localParticipant.identity, 'Вы');
        });
        room.localParticipant.audioTrackPublications.forEach((pub) => {
            if (pub.track) attachTrack(pub.track, room.localParticipant.identity, 'Вы');
        });

        room.remoteParticipants.forEach((p) => {
            p.trackPublications.forEach((pub) => {
                if (pub.track) attachTrack(pub.track, p.identity, p.name || p.identity);
            });
        });
    };

    const startCall = async (video) => {
        if (!startUrl) {
            alert('Звонки недоступны в этом чате');
            return;
        }
        try {
            const data = await postJson(startUrl, { video: !!video });
            await connectRoom(data);
        } catch (e) {
            alert(e.message || 'Не удалось начать звонок');
        }
    };

    const joinById = async (id) => {
        if (!id || !joinTpl) return;
        try {
            const data = await postJson(callUrl(id, 'join'), {});
            ringingCallId = null;
            joinableCallId = null;
            await connectRoom(data);
        } catch (e) {
            alert(e.message || 'Не удалось подключиться');
        }
    };

    const declineIncoming = async () => {
        if (!ringingCallId || !joinTpl) return;
        try { await postJson(callUrl(ringingCallId, 'decline'), {}); } catch (e) {}
        ringingCallId = null;
        if (incoming) incoming.hidden = true;
    };

    document.getElementById('bx-call-audio')?.addEventListener('click', () => startCall(false));
    document.getElementById('bx-call-video')?.addEventListener('click', () => startCall(true));
    document.getElementById('bx-call-hang')?.addEventListener('click', () => hangup());
    endAllBtn?.addEventListener('click', () => endForAll());
    document.getElementById('bx-call-mic')?.addEventListener('click', async () => {
        if (!room) return;
        micOn = !micOn;
        await room.localParticipant.setMicrophoneEnabled(micOn);
        document.getElementById('bx-call-mic')?.classList.toggle('is-off', !micOn);
    });
    document.getElementById('bx-call-cam')?.addEventListener('click', async () => {
        if (!room) return;
        camOn = !camOn;
        await room.localParticipant.setCameraEnabled(camOn);
        document.getElementById('bx-call-cam')?.classList.toggle('is-off', !camOn);
    });
    document.getElementById('bx-incoming-accept')?.addEventListener('click', () => joinById(ringingCallId));
    document.getElementById('bx-incoming-decline')?.addEventListener('click', () => declineIncoming());
    document.getElementById('bx-active-call-join')?.addEventListener('click', () => joinById(joinableCallId));

    window.bxHandleCallsPoll = function (calls) {
        if (!Array.isArray(calls)) return;

        // Входящий (ещё не в звонке)
        if (!callId) {
            const invite = calls.find((c) => !c.is_mine && c.my_status === 'invited');
            if (invite) {
                if (ringingCallId !== invite.id) {
                    ringingCallId = invite.id;
                    if (incomingTitle) {
                        incomingTitle.textContent = invite.video ? 'Входящий видеозвонок' : 'Входящий звонок';
                    }
                    if (incomingSub) {
                        incomingSub.textContent = (invite.starter_name || 'Участник')
                            + ' · ' + (invite.chat_title || '');
                    }
                    if (incoming) incoming.hidden = false;
                    if (typeof window.bxPlayChatNotify === 'function') window.bxPlayChatNotify();
                }
            } else {
                ringingCallId = null;
                if (incoming) incoming.hidden = true;
            }

            // Баннер «присоединиться» в текущем чате
            const inChat = calls.find((c) => String(c.chat_id) === activeChatId && c.my_status !== 'joined');
            if (inChat && inChat.my_status !== 'declined') {
                joinableCallId = inChat.id;
                if (activeBarText) {
                    activeBarText.textContent = (inChat.video ? 'Видеозвонок' : 'Звонок')
                        + ' · ' + (inChat.participants || 0) + ' в эфире';
                }
                if (activeBar) activeBar.hidden = false;
            } else {
                joinableCallId = null;
                if (activeBar) activeBar.hidden = true;
            }
        }
    };
})();
