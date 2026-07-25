/**
 * Групповые звонки через LiveKit (SFU).
 * Микрофон/камера, экран, громкость по участникам.
 */
(function () {
    const root = document.querySelector('.bx-messenger');
    if (!root || root.getAttribute('data-calls-enabled') !== '1') return;

    const MIC_KEY = 'bx_chat_mic_device';
    const CAM_KEY = 'bx_call_cam_device';
    const VOL_KEY = 'bx_call_vol_';

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
    const screenBtn = document.getElementById('bx-call-screen');
    const devicesBtn = document.getElementById('bx-call-devices');
    const devicesPanel = document.getElementById('bx-call-devices-panel');
    const micSelect = document.getElementById('bx-call-mic-select');
    const camSelect = document.getElementById('bx-call-cam-select');

    let LK = null;
    let room = null;
    let roomGen = 0;
    let callId = null;
    let isStarter = false;
    let micOn = true;
    let camOn = false;
    let screenOn = false;
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

    const getSaved = (key) => {
        try { return localStorage.getItem(key) || ''; } catch (e) { return ''; }
    };
    const setSaved = (key, val) => {
        try { localStorage.setItem(key, val || ''); } catch (e) {}
    };

    const getVol = (id) => {
        const n = parseInt(getSaved(VOL_KEY + id), 10);
        if (Number.isFinite(n)) return Math.max(0, Math.min(100, n));
        return 100;
    };
    const setVol = (id, pct) => setSaved(VOL_KEY + id, String(pct));

    const ensureLivekit = () => {
        if (window.LivekitClient || window.LiveKit) {
            LK = window.LivekitClient || window.LiveKit;
            return Promise.resolve(LK);
        }
        if (livekitReady) return livekitReady;
        livekitReady = new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/livekit-client@2.9.8/dist/livekit-client.umd.min.js';
            s.onload = () => {
                LK = window.LivekitClient || window.LiveKit;
                resolve(LK);
            };
            s.onerror = () => reject(new Error('Не удалось загрузить LiveKit client'));
            document.head.appendChild(s);
        });
        return livekitReady;
    };

    const isScreenSource = (publication, track) => {
        const src = publication?.source || track?.source;
        const ScreenShare = LK?.Track?.Source?.ScreenShare || 'screen_share';
        const ScreenShareAudio = LK?.Track?.Source?.ScreenShareAudio || 'screen_share_audio';
        return src === ScreenShare || src === ScreenShareAudio || src === 'screen_share' || src === 'screen_share_audio';
    };

    const tileKey = (identity, isScreen) => (isScreen ? String(identity) + ':screen' : String(identity));

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

    const applyAudioVolume = (tile, identity) => {
        const audio = tile?.querySelector('.bx-call-tile__audio');
        if (!audio) return;
        const pct = getVol(identity);
        audio.volume = pct / 100;
        const range = tile.querySelector('.bx-call-tile__vol-range');
        if (range) range.value = String(pct);
        const label = tile.querySelector('.bx-call-tile__vol-val');
        if (label) label.textContent = pct + '%';
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
        if (avatar) avatar.hidden = !!hasVideo;
        if (video) video.classList.toggle('is-on', !!hasVideo);
    };

    const tileEl = (identity, meta, opts = {}) => {
        const isScreen = !!opts.screen;
        const id = String(identity);
        const key = tileKey(id, isScreen);
        let el = grid?.querySelector('[data-id="' + key + '"]');
        const info = { ...(metaById[id] || {}), ...(meta || {}) };
        metaById[id] = info;

        if (!el) {
            el = document.createElement('div');
            el.className = 'bx-call-tile' + (isScreen ? ' is-screen' : '');
            el.setAttribute('data-id', key);
            el.setAttribute('data-user', id);
            el.innerHTML = [
                '<div class="bx-call-tile__avatar"></div>',
                '<video class="bx-call-tile__video" playsinline autoplay muted></video>',
                '<audio class="bx-call-tile__audio" autoplay></audio>',
                '<div class="bx-call-tile__shade"></div>',
                '<div class="bx-call-tile__footer">',
                '  <div class="bx-call-tile__name"></div>',
                '  <label class="bx-call-tile__vol" title="Громкость у вас">',
                '    <span class="bx-call-tile__vol-ico" aria-hidden="true">♪</span>',
                '    <input type="range" class="bx-call-tile__vol-range" min="0" max="100" step="5" value="100">',
                '    <span class="bx-call-tile__vol-val">100%</span>',
                '  </label>',
                '</div>',
                '<div class="bx-call-tile__pulse" aria-hidden="true"></div>',
            ].join('');
            grid?.appendChild(el);

            const range = el.querySelector('.bx-call-tile__vol-range');
            range?.addEventListener('input', () => {
                const pct = parseInt(range.value, 10) || 0;
                setVol(id, pct);
                applyAudioVolume(el, id);
                // если есть экранный тайл того же юзера — та же громкость для screen audio
                const screenTile = grid?.querySelector('[data-id="' + tileKey(id, true) + '"]');
                if (screenTile && screenTile !== el) applyAudioVolume(screenTile, id);
            });
        }

        const isLocal = id === String(room?.localParticipant?.identity);
        const nameEl = el.querySelector('.bx-call-tile__name');
        const volWrap = el.querySelector('.bx-call-tile__vol');
        if (nameEl) {
            nameEl.textContent = isScreen
                ? ((isLocal ? 'Ваш экран' : (info.name || id) + ' · экран'))
                : ((isLocal ? 'Вы · ' : '') + (info.name || id));
        }
        if (volWrap) volWrap.hidden = !!isLocal;

        if (!isScreen) {
            renderAvatar(el.querySelector('.bx-call-tile__avatar'), info);
        } else {
            const av = el.querySelector('.bx-call-tile__avatar');
            if (av) av.innerHTML = '<span class="bx-call-tile__screen-ico">▣</span>';
        }
        applyAudioVolume(el, id);
        syncTileMediaState(el);
        return el;
    };

    const attachTrack = (track, participant, publication) => {
        const id = String(participant.identity);
        const screen = isScreenSource(publication, track);
        const meta = parseMeta(participant);
        const tile = tileEl(id, meta, { screen });
        const video = tile.querySelector('.bx-call-tile__video');
        const audio = tile.querySelector('.bx-call-tile__audio');
        const isLocal = id === String(room?.localParticipant?.identity);

        if (track.kind === 'video' && video) {
            track.attach(video);
            video.muted = true;
            video.playsInline = true;
            video.style.transform = (isLocal && !screen) ? 'scaleX(-1)' : '';
            tile.classList.add('has-video');
            syncTileMediaState(tile);
            video.play?.().catch(() => {});
        }

        if (track.kind === 'audio' && audio) {
            if (isLocal && !screen) {
                try { track.detach(audio); } catch (e) {}
                audio.srcObject = null;
            } else {
                track.attach(audio);
                applyAudioVolume(tile, id);
                audio.play?.().catch(() => {});
            }
        }
        updateCount();
    };

    const detachTrack = (track, participant, publication) => {
        const id = String(participant.identity);
        const screen = isScreenSource(publication, track);
        const tile = grid?.querySelector('[data-id="' + tileKey(id, screen) + '"]');
        if (!tile) return;
        const video = tile.querySelector('.bx-call-tile__video');
        const audio = tile.querySelector('.bx-call-tile__audio');
        if (track.kind === 'video' && video) {
            track.detach(video);
            tile.classList.remove('has-video');
            syncTileMediaState(tile);
            if (screen) tile.remove();
        }
        if (track.kind === 'audio' && audio) {
            track.detach(audio);
        }
    };

    const setSpeaking = (speakers) => {
        const ids = new Set((speakers || []).map((s) => String(s.identity)));
        grid?.querySelectorAll('.bx-call-tile:not(.is-screen)').forEach((tile) => {
            tile.classList.toggle('is-speaking', ids.has(tile.getAttribute('data-user')));
        });
    };

    const fillDeviceSelect = (select, devices, savedId) => {
        if (!select) return;
        const prev = select.value || savedId || '';
        select.innerHTML = '';
        if (!devices.length) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'Нет устройств';
            select.appendChild(opt);
            return;
        }
        devices.forEach((d, i) => {
            const opt = document.createElement('option');
            opt.value = d.deviceId;
            opt.textContent = d.label || (('Устройство ') + (i + 1));
            select.appendChild(opt);
        });
        if (prev && [...select.options].some((o) => o.value === prev)) {
            select.value = prev;
        }
    };

    const refreshDevices = async () => {
        if (!navigator.mediaDevices?.enumerateDevices) return;
        try {
            // labels появляются после разрешения
            try {
                const tmp = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
                tmp.getTracks().forEach((t) => t.stop());
            } catch (e) {
                try {
                    const tmp = await navigator.mediaDevices.getUserMedia({ audio: true });
                    tmp.getTracks().forEach((t) => t.stop());
                } catch (e2) {}
            }
            const all = await navigator.mediaDevices.enumerateDevices();
            fillDeviceSelect(
                micSelect,
                all.filter((d) => d.kind === 'audioinput'),
                getSaved(MIC_KEY)
            );
            fillDeviceSelect(
                camSelect,
                all.filter((d) => d.kind === 'videoinput'),
                getSaved(CAM_KEY)
            );
        } catch (e) {
            console.warn('devices', e);
        }
    };

    const micOptions = () => {
        const id = getSaved(MIC_KEY);
        return id ? { deviceId: id } : {};
    };
    const camOptions = () => {
        const id = getSaved(CAM_KEY);
        const base = { resolution: { width: 960, height: 540, frameRate: 24 } };
        return id ? { ...base, deviceId: id } : base;
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
        if (devicesPanel) devicesPanel.hidden = true;
        document.body.classList.remove('bx-call-open');
        callId = null;
        isStarter = false;
        room = null;
        screenOn = false;
        if (endAllBtn) endAllBtn.hidden = true;
        micBtn?.classList.remove('is-off');
        camBtn?.classList.remove('is-off');
        screenBtn?.classList.remove('is-on');
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
            await room.localParticipant.setMicrophoneEnabled(true, micOptions());
            micOn = true;
            micBtn?.classList.remove('is-off');
        } catch (e) {
            console.warn('mic', e);
            micOn = false;
            micBtn?.classList.add('is-off');
        }

        try {
            await room.localParticipant.setCameraEnabled(!!wantVideo, camOptions());
            camOn = !!wantVideo;
            camBtn?.classList.toggle('is-off', !camOn);
        } catch (e) {
            console.warn('cam', e);
            camOn = false;
            camBtn?.classList.add('is-off');
        }

        tileEl(room.localParticipant.identity, parseMeta(room.localParticipant));
        if (room.localParticipant.videoTrackPublications) {
            room.localParticipant.videoTrackPublications.forEach((pub) => {
                if (pub.track) attachTrack(pub.track, room.localParticipant, pub);
            });
        }
        if (room.localParticipant.audioTrackPublications) {
            room.localParticipant.audioTrackPublications.forEach((pub) => {
                if (pub.track) attachTrack(pub.track, room.localParticipant, pub);
            });
        }
        refreshDevices();
    };

    const switchMic = async (deviceId) => {
        setSaved(MIC_KEY, deviceId);
        if (!room) return;
        try {
            if (typeof room.switchActiveDevice === 'function') {
                await room.switchActiveDevice('audioinput', deviceId);
            } else {
                await room.localParticipant.setMicrophoneEnabled(false);
                await room.localParticipant.setMicrophoneEnabled(micOn, { deviceId });
            }
        } catch (e) {
            alert('Не удалось переключить микрофон');
        }
    };

    const switchCam = async (deviceId) => {
        setSaved(CAM_KEY, deviceId);
        if (!room || !camOn) return;
        try {
            if (typeof room.switchActiveDevice === 'function') {
                await room.switchActiveDevice('videoinput', deviceId);
            } else {
                await room.localParticipant.setCameraEnabled(false);
                await room.localParticipant.setCameraEnabled(true, camOptions());
            }
        } catch (e) {
            alert('Не удалось переключить камеру');
        }
    };

    const toggleScreen = async () => {
        if (!room) return;
        try {
            const next = !screenOn;
            await room.localParticipant.setScreenShareEnabled(next, {
                audio: true,
                selfBrowserSurface: 'include',
            });
            screenOn = next;
            screenBtn?.classList.toggle('is-on', screenOn);
        } catch (e) {
            screenOn = false;
            screenBtn?.classList.remove('is-on');
            if (e?.name !== 'NotAllowedError') {
                alert('Не удалось показать экран. Разрешите доступ в браузере.');
            }
        }
    };

    const connectRoom = async (payload) => {
        if (callId && Number(callId) === Number(payload.call_id) && room) {
            return;
        }

        const client = await ensureLivekit();
        if (!client?.Room) throw new Error('LiveKit client недоступен');
        if (!payload.ws_url) throw new Error('LiveKit URL пустой. Проверьте LIVEKIT_URL в .env');

        rememberRoster(payload);
        await disconnectRoom();

        const gen = ++roomGen;
        callId = payload.call_id;
        isStarter = !!payload.is_starter;
        camOn = !!payload.video;
        micOn = true;
        screenOn = false;
        screenBtn?.classList.remove('is-on');
        if (titleEl) titleEl.textContent = payload.video ? 'Видеозвонок' : 'Аудиозвонок';
        if (stage) stage.hidden = false;
        document.body.classList.add('bx-call-open');
        if (incoming) incoming.hidden = true;
        if (activeBar) activeBar.hidden = true;
        if (endAllBtn) endAllBtn.hidden = !isStarter;
        if (devicesPanel) devicesPanel.hidden = true;
        if (grid) grid.innerHTML = '';
        startTimer();

        const VideoPresets = client.VideoPresets || {};
        room = new client.Room({
            adaptiveStream: true,
            dynacast: true,
            videoCaptureDefaults: {
                resolution: VideoPresets.h540?.resolution || { width: 960, height: 540, frameRate: 24 },
                deviceId: getSaved(CAM_KEY) || undefined,
            },
            audioCaptureDefaults: {
                deviceId: getSaved(MIC_KEY) || undefined,
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true,
            },
            publishDefaults: {
                videoSimulcast: true,
                videoCodec: 'vp8',
                dtx: true,
                red: true,
                screenShareEncoding: { maxBitrate: 3_000_000, maxFramerate: 30 },
            },
        });

        room.on(client.RoomEvent.TrackSubscribed, (track, publication, participant) => {
            if (gen !== roomGen) return;
            attachTrack(track, participant, publication);
        });
        room.on(client.RoomEvent.TrackUnsubscribed, (track, publication, participant) => {
            if (gen !== roomGen) return;
            detachTrack(track, participant, publication);
        });
        room.on(client.RoomEvent.TrackMuted, (publication, participant) => {
            if (gen !== roomGen || publication.kind !== 'video') return;
            const screen = isScreenSource(publication);
            const tile = grid?.querySelector('[data-id="' + tileKey(participant.identity, screen) + '"]');
            tile?.classList.remove('has-video');
            syncTileMediaState(tile);
        });
        room.on(client.RoomEvent.TrackUnmuted, (publication, participant) => {
            if (gen !== roomGen || publication.kind !== 'video') return;
            if (publication.track) attachTrack(publication.track, participant, publication);
        });
        room.on(client.RoomEvent.LocalTrackPublished, (publication, participant) => {
            if (gen !== roomGen) return;
            if (publication.track) attachTrack(publication.track, participant, publication);
            if (isScreenSource(publication)) {
                screenOn = true;
                screenBtn?.classList.add('is-on');
            }
        });
        room.on(client.RoomEvent.LocalTrackUnpublished, (publication) => {
            if (gen !== roomGen) return;
            if (isScreenSource(publication)) {
                screenOn = false;
                screenBtn?.classList.remove('is-on');
                grid?.querySelector('[data-id="' + tileKey(room.localParticipant.identity, true) + '"]')?.remove();
            }
        });
        room.on(client.RoomEvent.ParticipantConnected, (participant) => {
            if (gen !== roomGen) return;
            tileEl(participant.identity, parseMeta(participant));
            updateCount();
        });
        room.on(client.RoomEvent.ParticipantDisconnected, (participant) => {
            if (gen !== roomGen) return;
            grid?.querySelector('[data-id="' + tileKey(participant.identity, false) + '"]')?.remove();
            grid?.querySelector('[data-id="' + tileKey(participant.identity, true) + '"]')?.remove();
            updateCount();
        });
        room.on(client.RoomEvent.ActiveSpeakersChanged, (speakers) => {
            if (gen !== roomGen) return;
            setSpeaking(speakers);
        });
        room.on(client.RoomEvent.Disconnected, () => {
            if (disconnecting) return;
            if (gen !== roomGen) return;
            stopCallUi(gen);
        });

        try {
            await room.connect(payload.ws_url, payload.token);
        } catch (err) {
            stopCallUi(gen);
            throw new Error((err && err.message ? err.message + '\n\n' : '')
                + 'Не удалось подключиться к LiveKit (' + payload.ws_url + ').');
        }

        if (gen !== roomGen) return;

        tileEl(room.localParticipant.identity, {
            ...parseMeta(room.localParticipant),
            ...(payload.me || {}),
            name: (payload.me && payload.me.name) || 'Вы',
        });
        room.remoteParticipants.forEach((p) => {
            tileEl(p.identity, parseMeta(p));
            p.trackPublications.forEach((pub) => {
                if (pub.track) attachTrack(pub.track, p, pub);
            });
        });
        updateCount();

        await publishLocalMedia(!!payload.video);
        updateCount();
    };

    const startCall = async (video) => {
        if (callId && room) {
            try {
                camOn = !!video;
                await room.localParticipant.setCameraEnabled(camOn, camOptions());
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
    screenBtn?.addEventListener('click', () => toggleScreen());
    devicesBtn?.addEventListener('click', async () => {
        if (!devicesPanel) return;
        const open = devicesPanel.hidden;
        devicesPanel.hidden = !open;
        if (open) await refreshDevices();
    });
    micSelect?.addEventListener('change', () => switchMic(micSelect.value));
    camSelect?.addEventListener('change', () => switchCam(camSelect.value));
    navigator.mediaDevices?.addEventListener?.('devicechange', () => {
        if (callId) refreshDevices();
    });

    micBtn?.addEventListener('click', async () => {
        if (!room) return;
        try {
            micOn = !micOn;
            await room.localParticipant.setMicrophoneEnabled(micOn, micOptions());
            micBtn.classList.toggle('is-off', !micOn);
        } catch (e) {
            alert('Микрофон недоступен');
        }
    });
    camBtn?.addEventListener('click', async () => {
        if (!room) return;
        try {
            camOn = !camOn;
            await room.localParticipant.setCameraEnabled(camOn, camOptions());
            camBtn.classList.toggle('is-off', !camOn);
            const tile = grid?.querySelector('[data-id="' + tileKey(room.localParticipant.identity, false) + '"]');
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
            const still = calls.find((c) => Number(c.id) === Number(callId));
            if (!still && incoming) incoming.hidden = true;
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
