/**
 * Гостевой звонок — современный UI (устройства, экран, PIP, громкость).
 */
(function () {
    const cfg = window.BX_GUEST_CALL || {};
    const MIC_KEY = 'bx_guest_mic_device';
    const CAM_KEY = 'bx_guest_cam_device';
    const VOL_KEY = 'bx_guest_vol_';

    const lobby = document.getElementById('lobby');
    const stage = document.getElementById('stage');
    const grid = document.getElementById('grid');
    const focusEl = document.getElementById('focus');
    const pipLayer = document.getElementById('pip-layer');
    const stripWrap = document.getElementById('strip-wrap');
    const stripEl = document.getElementById('strip');
    const stripToggle = document.getElementById('strip-toggle');
    const stripLabel = document.getElementById('strip-label');
    const devicesPanel = document.getElementById('devices');
    const errEl = document.getElementById('err');
    const nameInput = document.getElementById('guest-name');
    const timerEl = document.getElementById('timer');
    const countEl = document.getElementById('count');
    const stageTitle = document.getElementById('stage-title');
    const lobbyMic = document.getElementById('lobby-mic');
    const lobbyCam = document.getElementById('lobby-cam');
    const micSelect = document.getElementById('mic-select');
    const camSelect = document.getElementById('cam-select');
    const micBtn = document.getElementById('btn-mic');
    const camBtn = document.getElementById('btn-cam');
    const screenBtn = document.getElementById('btn-screen');
    const devicesBtn = document.getElementById('btn-devices');

    let LK = null;
    let room = null;
    let micOn = true;
    let camOn = false;
    let screenOn = false;
    let stripHidden = false;
    let timerIv = null;
    let sec = 0;
    const decoder = new TextDecoder();

    const getSaved = (key) => { try { return localStorage.getItem(key) || ''; } catch (e) { return ''; } };
    const setSaved = (key, val) => { try { localStorage.setItem(key, val || ''); } catch (e) {} };
    const getVol = (id) => {
        const n = parseInt(getSaved(VOL_KEY + id), 10);
        return Number.isFinite(n) ? Math.max(0, Math.min(100, n)) : 100;
    };
    const setVol = (id, pct) => setSaved(VOL_KEY + id, String(pct));

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
            LK = window.LivekitClient || window.LiveKit;
            return resolve(LK);
        }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/livekit-client@2.9.8/dist/livekit-client.umd.min.js';
        s.onload = () => {
            LK = window.LivekitClient || window.LiveKit;
            resolve(LK);
        };
        s.onerror = () => reject(new Error('LiveKit недоступен'));
        document.head.appendChild(s);
    });

    const isScreenSource = (publication, track) => {
        const src = publication?.source || track?.source;
        const ScreenShare = LK?.Track?.Source?.ScreenShare || 'screen_share';
        const ScreenShareAudio = LK?.Track?.Source?.ScreenShareAudio || 'screen_share_audio';
        return src === ScreenShare || src === ScreenShareAudio || src === 'screen_share' || src === 'screen_share_audio';
    };

    const tileKey = (id, screen) => (screen ? String(id) + ':screen' : String(id));

    const findTile = (key) =>
        focusEl?.querySelector('[data-id="' + key + '"]')
        || stripEl?.querySelector('[data-id="' + key + '"]')
        || pipLayer?.querySelector('[data-id="' + key + '"]')
        || grid?.querySelector('[data-id="' + key + '"]');

    const allTiles = () => [
        ...(focusEl ? [...focusEl.querySelectorAll('.tile')] : []),
        ...(stripEl ? [...stripEl.querySelectorAll('.tile')] : []),
        ...(pipLayer ? [...pipLayer.querySelectorAll('.tile')] : []),
        ...(grid ? [...grid.querySelectorAll('.tile')] : []),
    ];

    const parseMeta = (participant) => {
        try { return JSON.parse(participant.metadata || '{}'); } catch (e) { return {}; }
    };

    const fillSelect = (select, devices, saved) => {
        if (!select) return;
        const prev = select.value || saved || '';
        select.innerHTML = '';
        if (!devices.length) {
            const o = document.createElement('option');
            o.value = '';
            o.textContent = 'Нет устройств';
            select.appendChild(o);
            return;
        }
        devices.forEach((d, i) => {
            const o = document.createElement('option');
            o.value = d.deviceId;
            o.textContent = d.label || ('Устройство ' + (i + 1));
            select.appendChild(o);
        });
        if (prev && [...select.options].some((o) => o.value === prev)) select.value = prev;
    };

    const refreshDevices = async () => {
        if (!navigator.mediaDevices?.enumerateDevices) return;
        try {
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
            const mics = all.filter((d) => d.kind === 'audioinput');
            const cams = all.filter((d) => d.kind === 'videoinput');
            fillSelect(lobbyMic, mics, getSaved(MIC_KEY));
            fillSelect(lobbyCam, cams, getSaved(CAM_KEY));
            fillSelect(micSelect, mics, getSaved(MIC_KEY));
            fillSelect(camSelect, cams, getSaved(CAM_KEY));
        } catch (e) {}
    };

    const micOpts = () => {
        const id = getSaved(MIC_KEY);
        return id ? { deviceId: id } : {};
    };
    const camOpts = () => {
        const id = getSaved(CAM_KEY);
        const base = { resolution: { width: 960, height: 540, frameRate: 24 } };
        return id ? { ...base, deviceId: id } : base;
    };

    const applyVol = (tile, id) => {
        const audio = tile?.querySelector('audio');
        if (!audio) return;
        const pct = getVol(id);
        audio.volume = pct / 100;
        const range = tile.querySelector('.vol input');
        const val = tile.querySelector('.vol .val');
        if (range) range.value = String(pct);
        if (val) val.textContent = pct + '%';
    };

    const makeDraggable = (el) => {
        if (!el || el._dragBound) return;
        el._dragBound = true;
        let ox = 0, oy = 0, dragging = false;
        const onDown = (e) => {
            if (e.target.closest('input,label,button')) return;
            dragging = true;
            const p = e.touches ? e.touches[0] : e;
            const r = el.getBoundingClientRect();
            ox = p.clientX - r.left;
            oy = p.clientY - r.top;
            el.classList.add('is-dragging');
            e.preventDefault();
        };
        const onMove = (e) => {
            if (!dragging) return;
            const p = e.touches ? e.touches[0] : e;
            const parent = pipLayer.getBoundingClientRect();
            let left = p.clientX - parent.left - ox;
            let top = p.clientY - parent.top - oy;
            left = Math.max(8, Math.min(parent.width - el.offsetWidth - 8, left));
            top = Math.max(8, Math.min(parent.height - el.offsetHeight - 8, top));
            el.style.left = left + 'px';
            el.style.top = top + 'px';
            el.style.right = 'auto';
            el.style.bottom = 'auto';
        };
        const onUp = () => { dragging = false; el.classList.remove('is-dragging'); };
        el.addEventListener('mousedown', onDown);
        el.addEventListener('touchstart', onDown, { passive: false });
        window.addEventListener('mousemove', onMove);
        window.addEventListener('touchmove', onMove, { passive: false });
        window.addEventListener('mouseup', onUp);
        window.addEventListener('touchend', onUp);
    };

    const refreshLayout = () => {
        const screens = allTiles().filter((t) => t.classList.contains('is-screen'));
        const hasScreen = screens.length > 0;
        stage.classList.toggle('has-screen', hasScreen);
        if (stripWrap) stripWrap.hidden = true;

        if (!hasScreen) {
            allTiles().forEach((t) => {
                t.classList.remove('is-pip');
                t.style.left = t.style.top = t.style.right = t.style.bottom = '';
                grid.appendChild(t);
            });
            return;
        }

        const sharers = new Set(screens.map((s) => s.getAttribute('data-user')));
        allTiles().forEach((t) => {
            if (t.classList.contains('is-screen')) {
                t.classList.remove('is-pip');
                t.style.left = t.style.top = t.style.right = t.style.bottom = '';
                grid.appendChild(t);
                return;
            }
            const uid = t.getAttribute('data-user');
            if (sharers.has(uid) && t.classList.contains('has-video')) {
                t.classList.add('is-pip');
                if (!t.style.left && !t.style.top) {
                    t.style.right = '16px';
                    t.style.bottom = '16px';
                    t.style.left = 'auto';
                    t.style.top = 'auto';
                }
                pipLayer.appendChild(t);
                makeDraggable(t);
            } else {
                t.classList.remove('is-pip');
                t.style.left = t.style.top = t.style.right = t.style.bottom = '';
                grid.appendChild(t);
            }
        });
    };

    const updateCount = () => {
        if (!countEl || !room) return;
        countEl.textContent = (1 + (room.remoteParticipants?.size || 0)) + ' уч.';
    };

    const tileEl = (identity, meta, opts = {}) => {
        const screen = !!opts.screen;
        const id = String(identity);
        const key = tileKey(id, screen);
        let el = findTile(key);
        const name = meta.name || id;
        const color = meta.color || '#64748b';
        const initials = meta.initials || '?';
        const isLocal = id === String(room?.localParticipant?.identity);

        if (!el) {
            el = document.createElement('div');
            el.className = 'tile' + (screen ? ' is-screen' : '');
            el.setAttribute('data-id', key);
            el.setAttribute('data-user', id);
            el.innerHTML = [
                '<div class="av"><span></span></div>',
                '<video playsinline autoplay muted></video>',
                '<audio autoplay></audio>',
                '<div class="shade"></div>',
                '<button type="button" class="fs" title="На весь экран" hidden>⛶</button>',
                '<div class="footer">',
                '  <div class="nm"></div>',
                '  <label class="vol" title="Громкость у вас"><span>♪</span><input type="range" min="0" max="100" step="5" value="100"><span class="val">100%</span></label>',
                '</div>',
            ].join('');
            grid.appendChild(el);
            el.querySelector('.vol input')?.addEventListener('input', (ev) => {
                const pct = parseInt(ev.target.value, 10) || 0;
                setVol(id, pct);
                applyVol(el, id);
                const screenTile = findTile(tileKey(id, true));
                if (screenTile && screenTile !== el) applyVol(screenTile, id);
            });
            el.querySelector('.fs')?.addEventListener('click', (ev) => {
                ev.stopPropagation();
                const video = el.querySelector('video') || el;
                try {
                    if (document.fullscreenElement) document.exitFullscreen();
                    else if (video.requestFullscreen) video.requestFullscreen();
                    else if (video.webkitRequestFullscreen) video.webkitRequestFullscreen();
                } catch (e) {}
            });
        }

        el.querySelector('.nm').textContent = screen
            ? (isLocal ? 'Ваш экран' : name + ' · экран')
            : (isLocal ? 'Вы · ' + name : name);
        const av = el.querySelector('.av span');
        if (av) {
            av.textContent = screen ? '▣' : initials;
            av.style.background = screen ? '#1e293b' : color;
        }
        const vol = el.querySelector('.vol');
        if (vol) vol.hidden = !!isLocal;
        const fs = el.querySelector('.fs');
        if (fs) fs.hidden = !screen;
        applyVol(el, id);
        refreshLayout();
        return el;
    };

    const attach = (track, participant, publication) => {
        const meta = parseMeta(participant);
        meta.name = participant.name || meta.name;
        const screen = isScreenSource(publication, track);
        const id = String(participant.identity);
        const el = tileEl(id, meta, { screen });
        const video = el.querySelector('video');
        const audio = el.querySelector('audio');
        const local = participant === room.localParticipant;

        if (track.kind === 'video' && video) {
            track.attach(video);
            video.muted = true;
            video.style.transform = (local && !screen) ? 'scaleX(-1)' : '';
            el.classList.add('has-video');
            video.play?.().catch(() => {});
        }
        if (track.kind === 'audio' && audio) {
            if (local && !screen) {
                try { track.detach(audio); } catch (e) {}
            } else {
                track.attach(audio);
                applyVol(el, id);
                audio.play?.().catch(() => {});
            }
        }
        updateCount();
        refreshLayout();
    };

    const detach = (track, participant, publication) => {
        const screen = isScreenSource(publication, track);
        const el = findTile(tileKey(participant.identity, screen));
        if (!el) return;
        if (track.kind === 'video') {
            const video = el.querySelector('video');
            if (video) track.detach(video);
            el.classList.remove('has-video');
            if (screen) el.remove();
        }
        if (track.kind === 'audio') {
            const audio = el.querySelector('audio');
            if (audio) track.detach(audio);
        }
        refreshLayout();
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

    const switchMic = async (deviceId) => {
        setSaved(MIC_KEY, deviceId);
        if (lobbyMic) lobbyMic.value = deviceId;
        if (micSelect) micSelect.value = deviceId;
        if (!room) return;
        try {
            if (typeof room.switchActiveDevice === 'function') {
                await room.switchActiveDevice('audioinput', deviceId);
            } else if (micOn) {
                await room.localParticipant.setMicrophoneEnabled(false);
                await room.localParticipant.setMicrophoneEnabled(true, { deviceId });
            }
        } catch (e) {
            alert('Не удалось переключить микрофон');
        }
    };

    const switchCam = async (deviceId) => {
        setSaved(CAM_KEY, deviceId);
        if (lobbyCam) lobbyCam.value = deviceId;
        if (camSelect) camSelect.value = deviceId;
        if (!room || !camOn) return;
        try {
            if (typeof room.switchActiveDevice === 'function') {
                await room.switchActiveDevice('videoinput', deviceId);
            } else {
                await room.localParticipant.setCameraEnabled(false);
                await room.localParticipant.setCameraEnabled(true, camOpts());
            }
        } catch (e) {
            alert('Не удалось переключить камеру');
        }
    };

    const join = async (video) => {
        errEl.textContent = '';
        const name = (nameInput?.value || '').trim();
        if (name.length < 2) {
            errEl.textContent = 'Укажите имя (минимум 2 символа)';
            return;
        }
        if (lobbyMic?.value) setSaved(MIC_KEY, lobbyMic.value);
        if (lobbyCam?.value) setSaved(CAM_KEY, lobbyCam.value);

        document.getElementById('join-audio').disabled = true;
        document.getElementById('join-video').disabled = true;

        try {
            const payload = await postJson(cfg.joinUrl, { name, video: !!video });
            const client = await ensureLivekit();
            room = new client.Room({
                adaptiveStream: true,
                dynacast: true,
                audioCaptureDefaults: {
                    deviceId: getSaved(MIC_KEY) || undefined,
                    echoCancellation: true,
                    noiseSuppression: true,
                },
                videoCaptureDefaults: {
                    deviceId: getSaved(CAM_KEY) || undefined,
                    resolution: { width: 960, height: 540, frameRate: 24 },
                },
                publishDefaults: {
                    videoSimulcast: true,
                    videoCodec: 'vp8',
                    screenShareEncoding: { maxBitrate: 3_000_000, maxFramerate: 30 },
                },
            });

            room.on(client.RoomEvent.TrackSubscribed, (track, pub, p) => attach(track, p, pub));
            room.on(client.RoomEvent.TrackUnsubscribed, (track, pub, p) => detach(track, p, pub));
            room.on(client.RoomEvent.LocalTrackPublished, (pub, p) => {
                if (pub.track) attach(pub.track, p, pub);
                if (isScreenSource(pub)) {
                    screenOn = true;
                    screenBtn?.classList.add('is-on');
                }
            });
            room.on(client.RoomEvent.LocalTrackUnpublished, (pub) => {
                if (isScreenSource(pub)) {
                    screenOn = false;
                    screenBtn?.classList.remove('is-on');
                    findTile(tileKey(room.localParticipant.identity, true))?.remove();
                    refreshLayout();
                }
            });
            room.on(client.RoomEvent.ParticipantConnected, (p) => {
                const meta = parseMeta(p);
                tileEl(p.identity, { name: p.name || meta.name, color: meta.color, initials: meta.initials });
                updateCount();
            });
            room.on(client.RoomEvent.ParticipantDisconnected, (p) => {
                findTile(tileKey(p.identity, false))?.remove();
                findTile(tileKey(p.identity, true))?.remove();
                updateCount();
                refreshLayout();
            });
            room.on(client.RoomEvent.ActiveSpeakersChanged, (speakers) => {
                const ids = new Set(speakers.map((s) => s.identity));
                allTiles().filter((t) => !t.classList.contains('is-screen')).forEach((t) => {
                    t.classList.toggle('is-speaking', ids.has(t.getAttribute('data-user')));
                });
            });
            room.on(client.RoomEvent.DataReceived, (payload) => {
                try {
                    const msg = JSON.parse(decoder.decode(payload));
                    if (msg && msg.type === 'call_ended') leave();
                } catch (e) {}
            });
            room.on(client.RoomEvent.Disconnected, () => leave());

            await room.connect(payload.ws_url, payload.token);
            lobby.style.display = 'none';
            stage.classList.add('is-on');
            if (stageTitle) stageTitle.textContent = cfg.chatTitle || 'Гостевой звонок';
            startTimer();

            const me = payload.me || {};
            tileEl(room.localParticipant.identity, {
                name: me.name || name,
                color: me.color,
                initials: me.initials,
            });

            await room.localParticipant.setMicrophoneEnabled(true, micOpts());
            micOn = true;
            micBtn?.classList.remove('is-off');

            await room.localParticipant.setCameraEnabled(!!video, camOpts());
            camOn = !!video;
            camBtn?.classList.toggle('is-off', !camOn);

            room.localParticipant.videoTrackPublications?.forEach((pub) => {
                if (pub.track) attach(pub.track, room.localParticipant, pub);
            });
            room.remoteParticipants.forEach((p) => {
                const meta = parseMeta(p);
                tileEl(p.identity, { name: p.name || meta.name, color: meta.color, initials: meta.initials });
                p.trackPublications.forEach((pub) => {
                    if (pub.track) attach(pub.track, p, pub);
                });
            });
            updateCount();
            refreshDevices();
        } catch (e) {
            errEl.textContent = e.message || 'Не удалось войти';
            document.getElementById('join-audio').disabled = false;
            document.getElementById('join-video').disabled = false;
        }
    };

    document.getElementById('join-audio')?.addEventListener('click', () => join(false));
    document.getElementById('join-video')?.addEventListener('click', () => join(true));
    document.getElementById('btn-leave')?.addEventListener('click', () => leave());
    document.getElementById('devices-close')?.addEventListener('click', () => {
        if (devicesPanel) devicesPanel.hidden = true;
    });
    devicesBtn?.addEventListener('click', async () => {
        if (!devicesPanel) return;
        devicesPanel.hidden = !devicesPanel.hidden;
        if (!devicesPanel.hidden) await refreshDevices();
    });
    stripToggle?.addEventListener('click', () => {
        stripHidden = !stripHidden;
        refreshLayout();
    });
    lobbyMic?.addEventListener('change', () => switchMic(lobbyMic.value));
    lobbyCam?.addEventListener('change', () => switchCam(lobbyCam.value));
    micSelect?.addEventListener('change', () => switchMic(micSelect.value));
    camSelect?.addEventListener('change', () => switchCam(camSelect.value));

    micBtn?.addEventListener('click', async () => {
        if (!room) return;
        try {
            micOn = !micOn;
            await room.localParticipant.setMicrophoneEnabled(micOn, micOpts());
            micBtn.classList.toggle('is-off', !micOn);
        } catch (e) {
            alert('Микрофон недоступен');
        }
    });
    camBtn?.addEventListener('click', async () => {
        if (!room) return;
        try {
            camOn = !camOn;
            await room.localParticipant.setCameraEnabled(camOn, camOpts());
            camBtn.classList.toggle('is-off', !camOn);
            const tile = findTile(tileKey(room.localParticipant.identity, false));
            if (!camOn) tile?.classList.remove('has-video');
            refreshLayout();
        } catch (e) {
            camOn = false;
            camBtn.classList.add('is-off');
            alert('Камера недоступна');
        }
    });
    screenBtn?.addEventListener('click', async () => {
        if (!room) return;
        try {
            const next = !screenOn;
            await room.localParticipant.setScreenShareEnabled(next, { audio: true });
            screenOn = next;
            screenBtn.classList.toggle('is-on', screenOn);
        } catch (e) {
            screenOn = false;
            screenBtn.classList.remove('is-on');
        }
    });

    refreshDevices();
})();
