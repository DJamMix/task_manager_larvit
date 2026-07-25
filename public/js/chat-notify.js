/**
 * Глобальный звук новых сообщений в чатах (на любой странице админки).
 * Громкость: localStorage bx_chat_notify_volume (0–100).
 * Работает только после первого клика/тапа (ограничение браузеров).
 */
(function () {
    if (window.__bxGlobalChatNotify) return;
    window.__bxGlobalChatNotify = true;

    const VOL_KEY = 'bx_chat_notify_volume';
    const getVol = () => {
        const v = parseInt(localStorage.getItem(VOL_KEY) || '70', 10);
        return Number.isFinite(v) ? Math.max(0, Math.min(100, v)) : 70;
    };
    window.__bxChatNotifyVolume = getVol();

    const pollUrlMeta = document.querySelector('meta[name="chats-poll-url"]');
    const pollUrl = pollUrlMeta?.content
        || (document.querySelector('.bx-messenger')?.getAttribute('data-poll-url') || '');

    const resolvePollUrl = () => {
        if (pollUrl) return pollUrl;
        const prefixMeta = document.querySelector('meta[name="dashboard-prefix"]');
        if (prefixMeta?.content) {
            return prefixMeta.content.replace(/\/$/, '') + '/chats-poll';
        }
        const parts = (window.location.pathname || '').split('/').filter(Boolean);
        if (parts.length >= 1) {
            return '/' + parts[0] + '/chats-poll';
        }
        return '/admin/chats-poll';
    };

    const resolvedUrl = resolvePollUrl();
    if (!resolvedUrl) return;

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
        const volScale = (typeof window.__bxChatNotifyVolume === 'number'
            ? window.__bxChatNotifyVolume
            : getVol()) / 100;
        if (volScale <= 0) return;
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
                const peak = Math.max(0.0001, vol * volScale);
                g.gain.setValueAtTime(0.0001, t + start);
                g.gain.exponentialRampToValueAtTime(peak, t + start + 0.015);
                g.gain.exponentialRampToValueAtTime(0.0001, t + start + dur);
                o.connect(g);
                g.connect(ctx.destination);
                o.start(t + start);
                o.stop(t + start + dur + 0.02);
            };
            beep(1100, 0, 0.12, 0.65);
            beep(1450, 0.14, 0.14, 0.6);
        } catch (e) {}
    };

    // Компактный регулятор громкости на всех страницах (если нет мессенджера)
    const mountVolControl = () => {
        if (document.getElementById('bx-global-notify-vol')) return;
        if (hasMessenger()) return;
        const wrap = document.createElement('div');
        wrap.id = 'bx-global-notify-vol';
        wrap.innerHTML = '<label title="Громкость уведомлений чата">'
            + '<span>Звук</span>'
            + '<input type="range" min="0" max="100" step="5" value="' + getVol() + '">'
            + '<span data-lbl>' + getVol() + '%</span></label>';
        wrap.style.cssText = 'position:fixed;right:12px;bottom:12px;z-index:1500;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:6px 10px;box-shadow:0 4px 16px rgba(15,23,42,.12);font-size:12px;color:#334155;';
        const label = wrap.querySelector('label');
        label.style.cssText = 'display:flex;align-items:center;gap:8px;margin:0;cursor:pointer;';
        const input = wrap.querySelector('input');
        input.style.width = '90px';
        const lbl = wrap.querySelector('[data-lbl]');
        input.addEventListener('input', () => {
            unlock();
            const n = Math.max(0, Math.min(100, parseInt(input.value, 10) || 0));
            localStorage.setItem(VOL_KEY, String(n));
            window.__bxChatNotifyVolume = n;
            lbl.textContent = n + '%';
            window.bxPlayChatNotify();
        });
        document.body.appendChild(wrap);
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountVolControl);
    } else {
        setTimeout(mountVolControl, 400);
    }

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
