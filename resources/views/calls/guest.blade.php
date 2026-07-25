<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Гостевой звонок — {{ $chat_title }}</title>
    <style>
        :root {
            --bg: #0b1220;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #22c55e;
            --danger: #dc2626;
            --line: rgba(148,163,184,.22);
            --panel: rgba(17,24,39,.92);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            color: var(--text);
            background:
                radial-gradient(1200px 600px at 20% -10%, rgba(56,189,248,.12), transparent 55%),
                radial-gradient(900px 500px at 100% 0%, rgba(34,197,94,.1), transparent 50%),
                var(--bg);
        }
        button { font: inherit; }
        .lobby {
            max-width: 520px;
            margin: 0 auto;
            padding: 2rem 1.1rem 3rem;
        }
        .brand {
            font-size: .78rem;
            color: var(--muted);
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .lobby h1 { margin: .4rem 0 .35rem; font-size: 1.55rem; line-height: 1.25; }
        .lobby .sub { color: var(--muted); margin: 0 0 1.25rem; }
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 1.1rem;
            backdrop-filter: blur(12px);
        }
        .field { margin-bottom: .9rem; }
        .field label {
            display: block;
            font-size: .8rem;
            color: var(--muted);
            margin-bottom: .35rem;
        }
        .field input[type=text],
        .field select {
            width: 100%;
            border: 1px solid var(--line);
            background: #0f172a;
            color: var(--text);
            border-radius: 12px;
            padding: .7rem .85rem;
            font-size: .95rem;
        }
        .device-row {
            display: grid;
            grid-template-columns: 36px 1fr;
            gap: .55rem;
            align-items: center;
            margin-bottom: .75rem;
        }
        .device-ico {
            width: 36px; height: 36px; border-radius: 10px;
            background: rgba(148,163,184,.12);
            display: inline-flex; align-items: center; justify-content: center;
            color: #e2e8f0;
        }
        .actions { display: flex; gap: .55rem; flex-wrap: wrap; margin-top: .35rem; }
        .btn {
            border: 0;
            border-radius: 999px;
            padding: .75rem 1.15rem;
            font-size: .9rem;
            cursor: pointer;
            color: #fff;
            background: #1e293b;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
        }
        .btn:hover { background: #334155; }
        .btn-primary { background: var(--accent); color: #052e16; font-weight: 700; }
        .btn-primary:hover { background: #16a34a; }
        .btn:disabled { opacity: .55; cursor: not-allowed; }
        .err { color: #fca5a5; font-size: .86rem; margin: .65rem 0 0; min-height: 1.2em; }
        .secure {
            display: inline-flex; align-items: center; gap: .35rem;
            margin-top: .85rem; font-size: .75rem; color: #86efac;
        }

        .stage {
            position: fixed; inset: 0; z-index: 30;
            display: none; flex-direction: column;
            background:
                radial-gradient(1200px 600px at 20% -10%, rgba(56,189,248,.12), transparent 55%),
                radial-gradient(900px 500px at 100% 0%, rgba(34,197,94,.08), transparent 50%),
                var(--bg);
            color: var(--text);
        }
        .stage.is-on { display: flex; }
        .stage-top {
            display: flex; justify-content: space-between; align-items: center;
            padding: .85rem 1rem;
            border-bottom: 1px solid var(--line);
        }
        .stage-title { font-weight: 700; }
        .stage-meta { display: flex; gap: .75rem; color: var(--muted); font-variant-numeric: tabular-nums; }
        .stage-count {
            padding: .15rem .5rem; border-radius: 999px;
            background: rgba(148,163,184,.15); font-size: .8rem;
        }
        .stage-body { flex: 1 1 auto; min-height: 0; position: relative; display: flex; flex-direction: column; }
        .focus { display: none; flex: 1 1 auto; min-height: 0; padding: .65rem; }
        .stage.has-screen .focus { display: block; }
        .pip-layer { position: absolute; inset: 0; pointer-events: none; z-index: 6; }
        .grid {
            flex: 1 1 auto; min-height: 0; overflow: auto;
            display: grid; gap: .75rem; padding: .85rem;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            align-content: center;
        }
        .stage.has-screen .grid { display: none; }
        .strip-wrap {
            border-top: 1px solid var(--line);
            background: rgba(2,6,23,.55);
            padding: .45rem .65rem .55rem;
        }
        .strip-wrap[hidden] { display: none !important; }
        .strip-wrap.is-collapsed .strip { display: none; }
        .strip-toggle {
            display: inline-flex; align-items: center; gap: .4rem;
            border: 0; background: rgba(148,163,184,.12); color: #e2e8f0;
            border-radius: 999px; padding: .35rem .75rem; font-size: .78rem; cursor: pointer;
            margin-bottom: .4rem;
        }
        .strip { display: flex; gap: .55rem; overflow-x: auto; }
        .strip .tile { flex: 0 0 auto; width: 150px; min-height: 96px; aspect-ratio: 16/10; }

        .tile {
            position: relative;
            background: linear-gradient(160deg, #1e293b, #0f172a);
            border-radius: 16px;
            overflow: hidden;
            min-height: 160px;
            aspect-ratio: 16/10;
            border: 2px solid transparent;
            box-shadow: 0 8px 24px rgba(0,0,0,.25);
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .tile.is-speaking {
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34,197,94,.25);
        }
        .tile.is-screen { width: 100%; height: 100%; min-height: 220px; aspect-ratio: auto; }
        .tile.is-pip {
            position: absolute; width: min(220px, 42vw); aspect-ratio: 16/10; min-height: 0;
            pointer-events: auto; cursor: grab; z-index: 7;
            box-shadow: 0 12px 28px rgba(0,0,0,.45);
            border: 2px solid rgba(226,232,240,.35);
        }
        .tile.is-pip.is-dragging { cursor: grabbing; }
        .tile .av {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 1;
        }
        .tile.has-video .av { opacity: 0; pointer-events: none; }
        .tile .av span, .tile .av img {
            width: 78px; height: 78px; border-radius: 50%;
            object-fit: cover; display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: #fff; font-size: 1.35rem;
            box-shadow: 0 8px 20px rgba(0,0,0,.35);
        }
        .tile video {
            position: absolute; inset: 0; width: 100%; height: 100%;
            object-fit: cover; background: #020617; opacity: 0; z-index: 2;
        }
        .tile.has-video video { opacity: 1; }
        .tile audio { display: none; }
        .tile .shade {
            position: absolute; left: 0; right: 0; bottom: 0; height: 42%;
            background: linear-gradient(transparent, rgba(2,6,23,.75));
            z-index: 3; pointer-events: none;
        }
        .tile .footer {
            position: absolute; left: .5rem; right: .5rem; bottom: .45rem; z-index: 4;
            display: flex; align-items: center; justify-content: space-between; gap: .5rem;
        }
        .tile .nm {
            background: rgba(15,23,42,.72); padding: .25rem .55rem; border-radius: 8px;
            font-size: .8rem; max-width: 55%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .tile .vol {
            display: inline-flex; align-items: center; gap: .3rem;
            background: rgba(15,23,42,.78); border-radius: 999px;
            padding: .15rem .45rem .15rem .35rem; margin: 0; cursor: pointer;
        }
        .tile .vol[hidden] { display: none !important; }
        .tile .vol input { width: 64px; accent-color: #22c55e; }
        .tile .vol span { font-size: .68rem; color: #cbd5e1; min-width: 2.1rem; text-align: right; }

        .devices {
            display: grid; gap: .75rem;
            padding: .85rem 1rem 1rem;
            border-top: 1px solid var(--line);
            background: rgba(2,6,23,.72);
            backdrop-filter: blur(12px);
        }
        .devices[hidden] { display: none !important; }
        .devices-head { display: flex; justify-content: space-between; align-items: center; }
        .devices-close {
            border: 0; background: transparent; color: var(--muted);
            width: 36px; height: 36px; border-radius: 10px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .devices-row {
            display: grid; grid-template-columns: 36px 88px minmax(0,1fr);
            gap: .55rem; align-items: center; margin: 0; font-size: .84rem; color: #cbd5e1;
        }
        .devices-row select {
            width: 100%; border: 1px solid var(--line); background: #0f172a; color: var(--text);
            border-radius: 12px; padding: .55rem .7rem;
        }

        .bar {
            display: flex; justify-content: center; flex-wrap: wrap; gap: .65rem;
            padding: .95rem 1rem calc(.95rem + env(safe-area-inset-bottom, 0px));
            border-top: 1px solid var(--line);
            background: rgba(2,6,23,.45);
            backdrop-filter: blur(10px);
        }
        .ctrl {
            border: 0; border-radius: 999px; width: 52px; height: 52px; padding: 0;
            background: #1e293b; color: #e2e8f0; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            position: relative;
        }
        .ctrl:hover { background: #334155; }
        .ctrl.is-off { background: #7f1d1d; }
        .ctrl.is-on { background: #15803d; box-shadow: 0 0 0 3px rgba(34,197,94,.2); }
        .ctrl.danger { background: var(--danger); width: auto; min-width: 52px; padding: 0 1rem; }
        .ctrl__ico { display: none; line-height: 0; }
        .ctrl__ico--on { display: inline-flex; }
        .ctrl.is-off .ctrl__ico--on { display: none; }
        .ctrl.is-off .ctrl__ico--off { display: inline-flex; }

        @media (max-width: 700px) {
            .devices-row { grid-template-columns: 36px 1fr; }
            .devices-row .lbl { grid-column: 2; }
            .devices-row select { grid-column: 1 / -1; }
            .ctrl { width: 48px; height: 48px; }
        }
    </style>
</head>
<body>
<div class="lobby" id="lobby">
    <div class="brand">Гостевой доступ</div>
    <h1>{{ $chat_title }}</h1>
    <p class="sub">Подключитесь без аккаунта. Можно говорить и включать камеру.</p>

    <div class="card">
        <div class="field">
            <label for="guest-name">Ваше имя</label>
            <input id="guest-name" type="text" maxlength="60" placeholder="Как вас представить" autocomplete="name">
        </div>

        <div class="device-row">
            <span class="device-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg>
            </span>
            <div class="field" style="margin:0">
                <label for="lobby-mic">Микрофон</label>
                <select id="lobby-mic"></select>
            </div>
        </div>
        <div class="device-row">
            <span class="device-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
            </span>
            <div class="field" style="margin:0">
                <label for="lobby-cam">Камера</label>
                <select id="lobby-cam"></select>
            </div>
        </div>

        <div class="actions">
            <button type="button" class="btn btn-primary" id="join-audio">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/></svg>
                Войти с микрофоном
            </button>
            <button type="button" class="btn" id="join-video">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                С видео
            </button>
        </div>
        <div class="err" id="err"></div>
        <div class="secure">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            Защищённый звонок · DTLS-SRTP
        </div>
    </div>
</div>

<div class="stage" id="stage">
    <div class="stage-top">
        <div>
            <div class="stage-title" id="stage-title">Гостевой звонок</div>
            <div class="secure" style="margin-top:.2rem">Гость</div>
        </div>
        <div class="stage-meta">
            <span class="stage-count" id="count"></span>
            <span id="timer">00:00</span>
        </div>
    </div>

    <div class="stage-body">
        <div class="focus" id="focus"></div>
        <div class="pip-layer" id="pip-layer"></div>
        <div class="grid" id="grid"></div>
    </div>

    <div class="strip-wrap" id="strip-wrap" hidden>
        <button type="button" class="strip-toggle" id="strip-toggle">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
            <span id="strip-label">Участники</span>
        </button>
        <div class="strip" id="strip"></div>
    </div>

    <div class="devices" id="devices" hidden>
        <div class="devices-head">
            <strong>Устройства</strong>
            <button type="button" class="devices-close" id="devices-close" title="Закрыть" aria-label="Закрыть">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <label class="devices-row">
            <span class="device-ico">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg>
            </span>
            <span class="lbl">Микрофон</span>
            <select id="mic-select"></select>
        </label>
        <label class="devices-row">
            <span class="device-ico">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
            </span>
            <span class="lbl">Камера</span>
            <select id="cam-select"></select>
        </label>
    </div>

    <div class="bar">
        <button type="button" class="ctrl" id="btn-mic" title="Микрофон">
            <span class="ctrl__ico ctrl__ico--on">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg>
            </span>
            <span class="ctrl__ico ctrl__ico--off">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 9v3a3 3 0 005.12 2.12M15 9.34V4a3 3 0 00-5.94-.6"/><path d="M17 16.95A7 7 0 015 12v-2m14 0v2a7 7 0 01-.11 1.23M12 19v4M8 23h8M1 1l22 22"/></svg>
            </span>
        </button>
        <button type="button" class="ctrl is-off" id="btn-cam" title="Камера">
            <span class="ctrl__ico ctrl__ico--on">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
            </span>
            <span class="ctrl__ico ctrl__ico--off">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 16v1a2 2 0 01-2 2H3a2 2 0 01-2-2V7a2 2 0 012-2h2m5.66 0H14a2 2 0 012 2v3.34l1 1L23 7v10"/><path d="M1 1l22 22"/></svg>
            </span>
        </button>
        <button type="button" class="ctrl" id="btn-screen" title="Демонстрация экрана">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        </button>
        <button type="button" class="ctrl" id="btn-devices" title="Устройства">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
        </button>
        <button type="button" class="ctrl danger" id="btn-leave" title="Выйти">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10.68 13.31a16 16 0 003.41 2.6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7 2 2 0 011.72 2v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.42 19.42 0 01-3.33-2.67m-2.67-3.34a19.79 19.79 0 01-3.07-8.63A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91"/><path d="M22 2L2 22"/></svg>
        </button>
    </div>
</div>

<script>
window.BX_GUEST_CALL = {
    joinUrl: @json($join_url),
    csrf: @json(csrf_token()),
    chatTitle: @json($chat_title),
    defaultVideo: @json((bool) $video),
};
</script>
<script src="{{ asset('js/call-guest.js') }}?v=20260725n"></script>
</body>
</html>
