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
            --card: #111827;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #22c55e;
            --danger: #dc2626;
            --line: rgba(148,163,184,.22);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            color: var(--text);
            background:
                radial-gradient(900px 480px at 10% -10%, rgba(34,197,94,.16), transparent 55%),
                radial-gradient(700px 420px at 100% 0%, rgba(56,189,248,.12), transparent 50%),
                var(--bg);
        }
        .wrap {
            max-width: 440px;
            margin: 0 auto;
            padding: 2rem 1.1rem 3rem;
        }
        .brand { font-size: .85rem; color: var(--muted); letter-spacing: .04em; text-transform: uppercase; }
        h1 { margin: .45rem 0 .35rem; font-size: 1.55rem; line-height: 1.25; }
        .sub { color: var(--muted); margin: 0 0 1.4rem; }
        .card {
            background: rgba(17,24,39,.88);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 1.1rem;
            backdrop-filter: blur(10px);
        }
        label { display: block; font-size: .82rem; color: var(--muted); margin-bottom: .35rem; }
        input[type=text] {
            width: 100%;
            border: 1px solid var(--line);
            background: #0f172a;
            color: var(--text);
            border-radius: 12px;
            padding: .75rem .9rem;
            font-size: 1rem;
            margin-bottom: 1rem;
        }
        .row { display: flex; gap: .6rem; flex-wrap: wrap; }
        button {
            border: 0;
            border-radius: 999px;
            padding: .75rem 1.15rem;
            font-size: .92rem;
            cursor: pointer;
            color: #fff;
            background: #1e293b;
        }
        button.primary { background: var(--accent); color: #052e16; font-weight: 700; }
        button:disabled { opacity: .55; cursor: not-allowed; }
        .err { color: #fca5a5; font-size: .86rem; margin: .5rem 0 0; min-height: 1.2em; }
        #stage {
            position: fixed; inset: 0; z-index: 20;
            display: none; flex-direction: column;
            background: var(--bg);
        }
        #stage.is-on { display: flex; }
        #grid {
            flex: 1; min-height: 0; overflow: auto;
            display: grid; gap: .65rem; padding: .75rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            align-content: center;
        }
        .tile {
            position: relative; border-radius: 14px; overflow: hidden;
            min-height: 160px; aspect-ratio: 16/10;
            background: linear-gradient(160deg,#1e293b,#0f172a);
            border: 2px solid transparent;
        }
        .tile.is-speaking { border-color: #22c55e; }
        .tile video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; }
        .tile.has-video video { opacity: 1; }
        .tile .av {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        }
        .tile .av span {
            width: 72px; height: 72px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: #fff;
        }
        .tile .nm {
            position: absolute; left: .5rem; bottom: .5rem; z-index: 2;
            background: rgba(15,23,42,.75); padding: .2rem .45rem; border-radius: 8px; font-size: .78rem;
        }
        .bar {
            display: flex; justify-content: center; flex-wrap: wrap; gap: .5rem;
            padding: .85rem 1rem calc(.85rem + env(safe-area-inset-bottom,0px));
            border-top: 1px solid var(--line);
        }
        .bar button.off { background: #7f1d1d; }
        .bar button.danger { background: var(--danger); }
        .top {
            display: flex; justify-content: space-between; align-items: center;
            padding: .75rem 1rem; border-bottom: 1px solid var(--line);
        }
        .top small { color: var(--muted); }
    </style>
</head>
<body>
<div class="wrap" id="lobby">
    <div class="brand">Гостевой доступ</div>
    <h1>{{ $chat_title }}</h1>
    <p class="sub">Войдите по ссылке и участвуйте в разговоре без аккаунта.</p>
    <div class="card">
        <label for="guest-name">Ваше имя</label>
        <input id="guest-name" type="text" maxlength="60" placeholder="Как вас представить" autocomplete="name">
        <div class="row">
            <button type="button" class="primary" id="join-audio">Войти с микрофоном</button>
            <button type="button" id="join-video">С видео</button>
        </div>
        <div class="err" id="err"></div>
    </div>
</div>

<div id="stage">
    <div class="top">
        <div>
            <strong id="stage-title">Звонок</strong><br>
            <small id="timer">00:00</small>
        </div>
        <small>Гость</small>
    </div>
    <div id="grid"></div>
    <div class="bar">
        <button type="button" id="btn-mic">Микрофон</button>
        <button type="button" id="btn-cam" class="off">Камера</button>
        <button type="button" class="danger" id="btn-leave">Выйти</button>
    </div>
</div>

<script>
window.BX_GUEST_CALL = {
    joinUrl: @json($join_url),
    csrf: @json(csrf_token()),
    defaultVideo: @json((bool) $video),
};
</script>
<script src="{{ asset('js/call-guest.js') }}?v=20260725k"></script>
</body>
</html>
