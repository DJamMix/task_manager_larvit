/**
 * Групповые звонки через LiveKit (SFU).
 * Шифрование медиа: DTLS-SRTP. Сигналинг: WSS.
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
    const countEl = document.getElementById('bx-call-count');
    const incoming = document.getElementById('bx-incoming-call');
    const incomingTitle = document.getElementById('bx-incoming-title');
    const incomingSub = document.getElementById('bx-incoming-sub');
    const incomingAvatar = document.getElementById('bx-incoming-avatar');
    const activeBar = document.getElementById('bx-active-call-bar');
    const activeBarText = document.getElementById('bx-active-call-text');
    const endAllBtn = document.getElementById('bx-call-end-all');
    const micBtn = document.getElementById('bx-call-mic');
    const camBtn = document.getElementById('bx-call-cam');

    let room = null;
    let roomGen = 0;
    let callId = null;
    let isStarter = false;
    let micOn = true;
    let camOn = false;
    let timerSec = 0;
    let timerIv = null;
    let ringingCallId = null;
    let joinableCallId = null;
    let livekitReady = null;
    let disconnecting = false;
    const metaById = {};

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

    const parseMeta = (participant) => {
        const id = String(participant?.identity || '');
        let meta = metaById[id] || {};
        try {
            if (participant?.metadata) {
                meta = { ...meta, ...JSON.parse(participant.metadata) };
            }
        } catch (e) {}
        if (participant?.name) meta.name = meta.name || participant.name;
        metaById[id] = meta;
        return meta;
    };

    const rememberRoster = (payload) => {
        (payload.roster || []).forEach((p) => {
            metaById[String(p.id)] = {
                name: p.name,
                avatar: p.avatar,
                initials: p.initials,
                color: p.color,
            };
        });
        if (payload.me) {
            metaById[String(payload.me.id)] = {
                name: payload.me.name,
                avatar: payload.me.avatar,
                initials: payload.me.initials,
                color: payload.me.color,
            };
        }
    };

    const renderAvatar = (wrap, meta) => {
        if (!wrap) return;
        const avatar = meta.avatar || '';
        const initials = meta.initials || '?';
        const color = meta.color || '#64748b';
        if (avatar) {
            wrap.innerHTML = '<img src="' + avatar.replace(/"/g, '') + '" alt="" loading="lazy">';
        } else {
            wrap.innerHTML = '<span style="background:' + color + '">' + initials + '</span>';
        }
    };

    const updateCount = () => {
        if (!countEl || !room) return;
        const n = 1 + (room.remoteParticipants?.size || 0);
        countEl.textContent = n + ' уч.';
    };

    const syncTileMediaState = (tile) => {
        if (!tile) return;
        const hasVideo = tile.classList.contains('has-video');
        const avatar = tile.querySelector('.bx-call-tile__avatar');
        const video = tile.querySelector('.bx-call-tile__video');
        if (avatar) avatar.hidden = hasVideo;
        if (video) video.classList.toggle('is-on', hasVideo);
    };

    const tileEl = (identity, meta) => {
        const id = String(identity);
        let el = grid?.querySelector('[data-id="' + id + '"]');
        const info = { ...(metaById[id] || {}), ...(meta || {}) };
        metaById[id] = info;

        if (!el) {
            el = document.createElement('div');
            el.className = 'bx-call-tile';
            el.setAttribute('data-id', id);
            el.innerHTML = [
                '<div class="bx-call-tile__avatar"></div>',
                '<video class="bx-call-tile__video" playsinline autoplay muted></video>',
                '<audio class="bx-call-tile__audio" autoplay></audio>',
                '<div class="bx-call-tile__shade"></div>',
                '<div class="bx-call-tile__name"></div>',
                '<div class="bx-call-tile__pulse" aria-hidden="true"></div>',
            ].join('');
            grid?.appendChild(el);
        }

        const isLocal = id === String(room?.localParticipant?.identity);
        const label = (isLocal ? 'Вы · ' : '') + (info.name || id);
        const nameEl = el.querySelector('.bx-call-tile__name');
        if (nameEl) nameEl.textContent = label;
        renderAvatar(el.querySelector('.bx-call-tile__avatar'), info);
        syncTileMediaState(el);
        return el;
    };

    const attachTrack = (track, participant) => {
        const id = String(participant.identity);
        const meta = parseMeta(participant);
        const tile = tileEl(id, meta);
        const video = tile.querySelector('.bx-call-tile__video');
        const audio = tile.querySelector('.bx-call-tile__audio');
        const isLocal = id === String(room?.localParticipant?.identity);

        if (track.kind === 'video' && video) {
            track.attach(video);
            video.muted = true;
            video.playsInline = true;
            if (isLocal) video.style.transform = 'scaleX(-1)';
            else video.style.transform = '';
            tile.classList.add('has-video');
            syncTileMediaState(tile);
            video.play?.().catch(() => {});
        }

        if (track.kind === 'audio' && audio) {
            if (isLocal) {
                // свой звук не играем
                try { track.detach(audio); } catch (e) {}
                audio.srcObject = null;
            } else {
                track.attach(audio);
                audio.play?.().catch(() => {});
            }
        }
        updateCount();
    };

    const detachTrack = (track, participant) => {
        const id = String(participant.identity);
        const tile = grid?.querySelector('[data-id="' + id + '"]');
        if (!tile) return;
        const video = tile.querySelector('.bx-call-tile__video');
        const audio = tile.querySelector('.bx-call-tile__audio');
        if (track.kind === 'video' && video) {
            track.detach(video);
            tile.classList.remove('has-video');
            syncTileMediaState(tile);
        }
        if (track.kind === 'audio' && audio) {
            track.detach(audio);
        }
    };

    const setSpeaking = (speakers) => {
        const ids = new Set((speakers || []).map((s) => String(s.identity)));
        grid?.querySelectorAll('.bx-call-tile').forEach((tile) => {
            tile.classList.toggle('is-speaking', ids.has(tile.getAttribute('data-id')));
        });
    };

    const startTimer = () => {
        timerSec = 0;
        clearInterval(timerIv);
        if (timerEl) timerEl.textContent = '00:00';
        timerIv = setInterval(() => {
            timerSec += 1;
            const m = String(Math.floor(timerSec / 60)).padStart(2, '0');
            const s = String(timerSec % 60).padStart(2, '0');
            if (timerEl) timerEl.textContent = m + ':' + s;
        }, 1000);
    };

    const stopCallUi = (gen) => {
        if (typeof gen === 'number' && gen !== roomGen) return;
        clearInterval(timerIv);
        if (stage) stage.hidden = true;
        if (grid) grid.innerHTML = '';
        document.body.classList.remove('bx-call-open');
        callId = null;
        isStarter = false;
        room = null;
        if (endAllBtn) endAllBtn.hidden = true;
        micBtn?.classList.remove('is-off');
        camBtn?.classList.remove('is-off');
        if (countEl) countEl.textContent = '';
    };

    const disconnectRoom = async () => {
        if (!room) return;
        disconnecting = true;
        const current = room;
        try {
            await current.disconnect(true);
        } catch (e) {}
        if (room === current) room = null;
        disconnecting = false;
    };

    const hangup = async () => {
        const id = callId;
        const gen = roomGen;
        await disconnectRoom();
        stopCallUi(gen);
        if (id) {
            try { await postJson(callUrl(id, 'leave'), {}); } catch (e) {}
        }
    };

    const endForAll = async () => {
        const id = callId;
        const gen = roomGen;
        await disconnectRoom();
        stopCallUi(gen);
        if (id) {
            try { await postJson(callUrl(id, 'end'), {}); } catch (e) {}
        }
    };

    const publishLocalMedia = async (wantVideo) => {
        if (!room) return;
        try {
            await room.localParticipant.setMicrophoneEnabled(true);
            micOn = true;
            micBtn?.classList.remove('is-off');
        } catch (e) {
            console.warn('mic', e);
            micOn = false;
            micBtn?.classList.add('is-off');
        }

        try {
            await room.localParticipant.setCameraEnabled(!!wantVideo, {
                resolution: { width: 960, height: 540, frameRate: 24 },
            });
            camOn = !!wantVideo;
            camBtn?.classList.toggle('is-off', !camOn);
        } catch (e) {
            console.warn('cam', e);
            camOn = false;
            camBtn?.classList.add('is-off');
        }

        // локальный тайл сразу
        tileEl(room.localParticipant.identity, parseMeta(room.localParticipant));
        room.localParticipant.trackPublications?.forEach?.((pub) => {
            if (pub.track) attachTrack(pub.track, room.localParticipant);
        });
        // Map API в LK 2.x
        if (room.localParticipant.videoTrackPublications) {
            room.localParticipant.videoTrackPublications.forEach((pub) => {
                if (pub.track) attachTrack(pub.track, room.localParticipant);
            });
        }
        if (room.localParticipant.audioTrackPublications) {
            room.localParticipant.audioTrackPublications.forEach((pub) => {
                if (pub.track) attachTrack(pub.track, room.localParticipant);
            });
        }
    };

    const connectRoom = async (payload) => {
        // уже в этом же звонке — не переподключаемся (иначе сбрасывает)
        if (callId && Number(callId) === Number(payload.call_id) && room) {
            return;
        }

        const LK = await ensureLivekit();
        if (!LK?.Room) throw new Error('LiveKit client недоступен');
        if (!payload.ws_url) throw new Error('LiveKit URL пустой. Проверьте LIVEKIT_URL в .env');

        rememberRoster(payload);
        await disconnectRoom();

        const gen = ++roomGen;
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
        if (grid) grid.innerHTML = '';
        startTimer();

        const VideoPresets = LK.VideoPresets || {};
        room = new LK.Room({
            adaptiveStream: true,
            dynacast: true,
            videoCaptureDefaults: {
                resolution: VideoPresets.h540?.resolution || { width: 960, height: 540, frameRate: 24 },
            },
            publishDefaults: {
                videoSimulcast: true,
                videoCodec: 'vp8',
                dtx: true,
                red: true,
            },
        });

        room.on(LK.RoomEvent.TrackSubscribed, (track, publication, participant) => {
            if (gen !== roomGen) return;
            attachTrack(track, participant);
        });
        room.on(LK.RoomEvent.TrackUnsubscribed, (track, publication, participant) => {
            if (gen !== roomGen) return;
            detachTrack(track, participant);
        });
        room.on(LK.RoomEvent.TrackMuted, (publication, participant) => {
            if (gen !== roomGen || publication.kind !== 'video') return;
            const tile = grid?.querySelector('[data-id="' + participant.identity + '"]');
            tile?.classList.remove('has-video');
            syncTileMediaState(tile);
        });
        room.on(LK.RoomEvent.TrackUnmuted, (publication, participant) => {
            if (gen !== roomGen || publication.kind !== 'video') return;
            if (publication.track) attachTrack(publication.track, participant);
        });
        room.on(LK.RoomEvent.LocalTrackPublished, (publication, participant) => {
            if (gen !== roomGen) return;
            if (publication.track) attachTrack(publication.track, participant);
        });
        room.on(LK.RoomEvent.ParticipantConnected, (participant) => {
            if (gen !== roomGen) return;
            tileEl(participant.identity, parseMeta(participant));
            updateCount();
        });
        room.on(LK.RoomEvent.ParticipantDisconnected, (participant) => {
            if (gen !== roomGen) return;
            grid?.querySelector('[data-id="' + participant.identity + '"]')?.remove();
            updateCount();
        });
        room.on(LK.RoomEvent.ActiveSpeakersChanged, (speakers) => {
            if (gen !== roomGen) return;
            setSpeaking(speakers);
        });
        room.on(LK.RoomEvent.Disconnected, () => {
            if (disconnecting) return;
            if (gen !== roomGen) return;
            stopCallUi(gen);
        });

        try {
            await room.connect(payload.ws_url, payload.token);
        } catch (err) {
            stopCallUi(gen);
            const hint = [
                'Не удалось подключиться к сигналингу LiveKit (' + payload.ws_url + ').',
                'Проверьте LiveKit, LIVEKIT_URL (wss:// на HTTPS) и ключи API.',
            ].join('\n');
            throw new Error((err && err.message ? err.message + '\n\n' : '') + hint);
        }

        if (gen !== roomGen) return;

        // локальный участник + уже в комнате
        tileEl(room.localParticipant.identity, {
            ...parseMeta(room.localParticipant),
            ...(payload.me || {}),
            name: (payload.me && payload.me.name) || 'Вы',
        });
        room.remoteParticipants.forEach((p) => {
            tileEl(p.identity, parseMeta(p));
            p.trackPublications.forEach((pub) => {
                if (pub.track) attachTrack(pub.track, p);
            });
        });
        updateCount();

        await publishLocalMedia(!!payload.video);
        updateCount();
    };

    const startCall = async (video) => {
        if (callId && room) {
            // уже в звонке — только переключить камеру, не создавать новый
            try {
                camOn = !!video;
                await room.localParticipant.setCameraEnabled(camOn, {
                    resolution: { width: 960, height: 540, frameRate: 24 },
                });
                camBtn?.classList.toggle('is-off', !camOn);
            } catch (e) {
                alert(e.message || 'Не удалось переключить камеру');
            }
            return;
        }
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
        if (callId && Number(callId) === Number(id) && room) return;
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

    const setIncomingAvatar = (call) => {
        if (!incomingAvatar) return;
        renderAvatar(incomingAvatar, {
            avatar: call.starter_avatar || '',
            initials: call.starter_initials || '?',
            color: call.starter_color || '#64748b',
        });
    };

    document.getElementById('bx-call-audio')?.addEventListener('click', () => startCall(false));
    document.getElementById('bx-call-video')?.addEventListener('click', () => startCall(true));
    document.getElementById('bx-call-hang')?.addEventListener('click', () => hangup());
    endAllBtn?.addEventListener('click', () => endForAll());
    micBtn?.addEventListener('click', async () => {
        if (!room) return;
        try {
            micOn = !micOn;
            await room.localParticipant.setMicrophoneEnabled(micOn);
            micBtn.classList.toggle('is-off', !micOn);
        } catch (e) {
            alert('Микрофон недоступен');
        }
    });
    camBtn?.addEventListener('click', async () => {
        if (!room) return;
        try {
            camOn = !camOn;
            await room.localParticipant.setCameraEnabled(camOn, {
                resolution: { width: 960, height: 540, frameRate: 24 },
            });
            camBtn.classList.toggle('is-off', !camOn);
            const tile = grid?.querySelector('[data-id="' + room.localParticipant.identity + '"]');
            if (!camOn) {
                tile?.classList.remove('has-video');
                syncTileMediaState(tile);
            }
        } catch (e) {
            camOn = false;
            camBtn.classList.add('is-off');
            alert('Камера недоступна. Разрешите доступ в браузере.');
        }
    });
    document.getElementById('bx-incoming-accept')?.addEventListener('click', () => joinById(ringingCallId));
    document.getElementById('bx-incoming-decline')?.addEventListener('click', () => declineIncoming());
    document.getElementById('bx-active-call-join')?.addEventListener('click', () => joinById(joinableCallId));

    window.bxHandleCallsPoll = function (calls) {
        if (!Array.isArray(calls)) return;

        if (callId) {
            // если звонок завершили для всех — закрыть UI
            const still = calls.find((c) => Number(c.id) === Number(callId));
            if (!still) {
                // не рвём мгновенно: LiveKit сам пришлёт Disconnected; только спрячем входящие
                if (incoming) incoming.hidden = true;
            }
            return;
        }

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
                setIncomingAvatar(invite);
                if (incoming) incoming.hidden = false;
                if (typeof window.bxPlayChatNotify === 'function') window.bxPlayChatNotify();
            }
        } else {
            ringingCallId = null;
            if (incoming) incoming.hidden = true;
        }

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
    };
})();
