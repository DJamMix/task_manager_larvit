<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', env('APP_NAME', 'CrewDev')) }} HelpDesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: rgba(15, 23, 42, 0.08);
            --surface: rgba(255, 255, 255, 0.88);
            --accent: #0f172a;
            --soft: #f1f5f9;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Manrope, system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1200px 600px at 10% -10%, #e2e8f0 0%, transparent 55%),
                radial-gradient(900px 500px at 100% 0%, #cbd5e1 0%, transparent 50%),
                linear-gradient(160deg, #f8fafc 0%, #eef2f7 100%);
            overflow-x: hidden;
        }
        #particles-js {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }
        .particle {
            position: absolute;
            background: rgba(15, 23, 42, 0.08);
            border-radius: 50%;
            pointer-events: none;
            will-change: transform;
        }
        .shell {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }
        .card {
            width: 100%;
            max-width: 920px;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            border-radius: 24px;
            overflow: hidden;
            background: var(--surface);
            border: 1px solid rgba(255, 255, 255, 0.55);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(16px);
            animation: fadeIn .7s ease both;
        }
        @media (max-width: 800px) {
            .card { grid-template-columns: 1fr; }
            .visual { min-height: 240px; }
        }
        .content { padding: 2.5rem 2.75rem; }
        .brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1.5rem;
        }
        .brand svg { width: 2.4rem; height: 2.4rem; filter: drop-shadow(0 4px 8px rgba(15,23,42,.12)); }
        .brand h1 { margin: 0; font-size: 1.35rem; font-weight: 800; letter-spacing: -.02em; }
        h2 {
            margin: 0 0 .75rem;
            font-size: clamp(1.8rem, 3vw, 2.4rem);
            line-height: 1.15;
            letter-spacing: -.03em;
            font-weight: 800;
        }
        .lead { margin: 0 0 1.75rem; color: var(--muted); font-size: 1.05rem; line-height: 1.55; }
        .previews { display: grid; gap: .75rem; margin-bottom: 1.75rem; }
        .preview {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: .9rem 1rem;
            border-left: 4px solid transparent;
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
        }
        .preview:hover {
            transform: translateX(4px);
            border-left-color: #2563eb;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        }
        .preview-top { display: flex; align-items: center; gap: .65rem; font-weight: 600; }
        .dot { width: .7rem; height: .7rem; border-radius: 50%; flex-shrink: 0; }
        .dot-blue { background: #3b82f6; }
        .dot-green { background: #22c55e; }
        .preview-meta { margin: .35rem 0 0; color: var(--muted); font-size: .85rem; }
        .actions { display: flex; flex-wrap: wrap; gap: .75rem; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .85rem 1.25rem;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .btn-primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.18);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(15, 23, 42, 0.22); }
        .btn-ghost {
            background: transparent;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-ghost:hover { background: #f8fafc; }
        .visual {
            position: relative;
            background: linear-gradient(160deg, #f1f5f9, #e2e8f0);
            padding: 2rem;
            overflow: hidden;
            min-height: 420px;
        }
        .float-card {
            position: absolute;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.1);
            border: 1px solid rgba(255,255,255,.8);
            animation: float 6s ease-in-out infinite;
        }
        .float-a { top: 8%; left: 6%; width: 68%; padding: 1rem; animation-delay: 0s; }
        .float-b { top: 34%; right: 4%; width: 58%; padding: 1rem; animation-delay: 1s; }
        .float-c { bottom: 8%; left: 18%; width: 52%; padding: .85rem; animation-delay: 2s; }
        .skeleton { background: #e2e8f0; border-radius: 999px; height: .55rem; }
        .skeleton-lg { height: 5.5rem; border-radius: 12px; background: #f1f5f9; }
        .row { display: flex; gap: .5rem; align-items: center; }
        .avatar { width: 1.7rem; height: 1.7rem; border-radius: 50%; background: #dbeafe; }
        .avatar.g { background: #dcfce7; }
        .avatar.y { background: #fef9c3; }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-14px); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>
    <div id="particles-js" aria-hidden="true"></div>

    <div class="shell">
        <div class="card">
            <div class="content">
                <div class="brand">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 8V16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M8 12H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <h1>CrewDev</h1>
                </div>

                <h2>Менеджер задач</h2>
                <p class="lead">Профессиональное управление проектами для вашей команды и клиентов</p>

                <div class="previews">
                    <div class="preview">
                        <div class="preview-top">
                            <span class="dot dot-blue"></span>
                            <span>Новая функция разработки</span>
                        </div>
                        <p class="preview-meta">В процессе · Приоритет: Высокий</p>
                    </div>
                    <div class="preview">
                        <div class="preview-top">
                            <span class="dot dot-green"></span>
                            <span>Исправление бага</span>
                        </div>
                        <p class="preview-meta">Завершено · Клиент: ООО «Технологии»</p>
                    </div>
                </div>

                <div class="actions">
                    <a href="/admin" class="btn btn-primary">Панель управления</a>
                    <a href="/admin/login" class="btn btn-ghost">Войти</a>
                </div>
            </div>

            <div class="visual" aria-hidden="true">
                <div class="float-card float-a">
                    <div class="row" style="justify-content:space-between;margin-bottom:.75rem;">
                        <div class="skeleton" style="width:5rem;"></div>
                        <div class="avatar"></div>
                    </div>
                    <div class="skeleton" style="width:100%;margin-bottom:.4rem;"></div>
                    <div class="skeleton" style="width:75%;margin-bottom:.4rem;"></div>
                    <div class="skeleton" style="width:50%;"></div>
                    <div class="row" style="margin-top:1rem;">
                        <div class="avatar"></div>
                        <div class="avatar g"></div>
                        <div class="avatar y"></div>
                    </div>
                </div>
                <div class="float-card float-b">
                    <div class="row" style="margin-bottom:.75rem;">
                        <div class="avatar"></div>
                        <div class="skeleton" style="width:4rem;"></div>
                    </div>
                    <div class="skeleton-lg"></div>
                    <div class="row" style="justify-content:space-between;margin-top:.75rem;">
                        <div class="skeleton" style="width:3rem;"></div>
                        <div class="skeleton" style="width:2rem;"></div>
                    </div>
                </div>
                <div class="float-card float-c">
                    <div class="row" style="margin-bottom:.55rem;">
                        <div class="avatar" style="width:1rem;height:1rem;"></div>
                        <div class="skeleton" style="width:3rem;height:.4rem;"></div>
                    </div>
                    <div class="skeleton" style="width:100%;height:.35rem;margin-bottom:.3rem;"></div>
                    <div class="skeleton" style="width:100%;height:.35rem;margin-bottom:.3rem;"></div>
                    <div class="skeleton" style="width:66%;height:.35rem;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const root = document.getElementById('particles-js');
            if (!root) return;
            for (let i = 0; i < 28; i++) {
                const p = document.createElement('div');
                p.className = 'particle';
                const size = Math.random() * 10 + 4;
                p.style.width = size + 'px';
                p.style.height = size + 'px';
                p.style.left = (Math.random() * 100) + '%';
                p.style.top = (Math.random() * 100) + '%';
                p.style.opacity = String(Math.random() * 0.18 + 0.04);
                p.style.animation = 'float ' + (Math.random() * 18 + 10) + 's ease-in-out ' + (Math.random() * 5) + 's infinite';
                root.appendChild(p);
            }
        });
    </script>
</body>
</html>
