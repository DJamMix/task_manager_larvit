/**
 * Глобальный звук новых сообщений в чатах (на любой странице админки).
 * Работает только после первого клика/тапа (ограничение браузеров).
 */
(function () {
    if (window.__bxGlobalChatNotify) return;
    window.__bxGlobalChatNotify = true;

    const pollUrlMeta = document.querySelector('meta[name="chats-poll-url"]');
    const pollUrl = pollUrlMeta?.content
        || (document.querySelector('.bx-messenger')?.getAttribute('data-poll-url') || '');

    const resolvePollUrl = () => {
        if (pollUrl) return pollUrl;
        const prefixMeta = document.querySelector('meta[name="dashboard-prefix"]');
        if (prefixMeta?.content) {
            return prefixMeta.content.replace(/\/$/, '') + '/chats-poll';
        }
        // /admin/tasks → /admin/chats-poll
        const parts = (window.location.pathname || '').split('/').filter(Boolean);
        if (parts.length >= 1) {
            return '/' + parts[0] + '/chats-poll';
        }
        return '/admin/chats-poll';
    };

    const resolvedUrl = resolvePollUrl();
    if (!resolvedUrl) return;

    // На странице мессенджера основной poll уже играет звук — здесь только если мессенджера нет
    const hasMessenger = () => !!document.querySelector('.bx-messenger');

    const storageKey = 'bx_chat_poll_since';
    const beepKey = 'bx_chat_last_beep_id';
    let since = parseInt(localStorage.getItem(storageKey) || '0', 10) || 0;
    let lastBeepMaxId = parseInt(sessionStorage.getItem(beepKey) || String(since), 10) || since;
    let lastBeepAt = 0;
    let busy = false;

    const unlock = () => {
        window.__bxChatSoundUnlocked = true;
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            window.__bxChatAudioCtx = window.__bxChatAudioCtx || new Ctx();
            if (window.__bxChatAudioCtx.state === 'suspended') {
                window.__bxChatAudioCtx.resume();
            }
        } catch (e) {}
    };

    ['click', 'touchstart', 'pointerdown', 'keydown'].forEach((ev) => {
        document.addEventListener(ev, unlock, { once: true, passive: true });
    });

    window.bxPlayChatNotify = function bxPlayChatNotify() {
        if (!window.__bxChatSoundUnlocked) return;
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            const ctx = window.__bxChatAudioCtx = window.__bxChatAudioCtx || new Ctx();
            if (ctx.state === 'suspended') ctx.resume();
            const t = ctx.currentTime;
            const beep = (freq, start, dur, vol) => {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.type = 'sine';
                o.frequency.value = freq;
                g.gain.setValueAtTime(0.0001, t + start);
                g.gain.exponentialRampToValueAtTime(vol, t + start + 0.015);
                g.gain.exponentialRampToValueAtTime(0.0001, t + start + dur);
                o.connect(g);
                g.connect(ctx.destination);
                o.start(t + start);
                o.stop(t + start + dur + 0.02);
            };
            // Два коротких громких сигнала
            beep(1100, 0, 0.12, 0.65);
            beep(1450, 0.14, 0.14, 0.6);
        } catch (e) {}
    };

    const poll = async () => {
        if (busy || hasMessenger()) return;
        busy = true;
        try {
            const params = new URLSearchParams();
            if (since) params.set('since', String(since));
            const url = resolvedUrl + (params.toString() ? ('?' + params) : '');
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (res.status === 403 || res.status === 401) return;
            if (!res.ok) return;
            const data = await res.json();
            const maxId = parseInt(data.max_id || '0', 10) || 0;
            if (data.sound && maxId > lastBeepMaxId) {
                const now = Date.now();
                if (now - lastBeepAt > 1200) {
                    window.bxPlayChatNotify();
                    lastBeepAt = now;
                }
                lastBeepMaxId = maxId;
                sessionStorage.setItem(beepKey, String(lastBeepMaxId));
            }
            if (maxId > since) {
                since = maxId;
                localStorage.setItem(storageKey, String(since));
            }
        } catch (e) {
        } finally {
            busy = false;
        }
    };

    setInterval(poll, 4000);
    setTimeout(poll, 1500);
})();
