<div class="yt-workflow" id="yt-workflow"
     data-graph='@json($graph)'
     data-save-url="{{ $save_url }}"
     data-csrf="{{ $csrf }}">
    <div class="yt-workflow__toolbar">
        <button type="button" class="btn btn-sm btn-primary" id="yt-wf-add">+ Статус</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="yt-wf-link" data-mode="off">
            Соединить стрелкой
        </button>
        <button type="button" class="btn btn-sm btn-success" id="yt-wf-save">Сохранить схему</button>
        <span class="yt-muted small ms-2" id="yt-wf-hint">Перетаскивайте статусы. Режим «Соединить» — клик по источнику, затем по цели.</span>
    </div>
    <div class="yt-workflow__canvas-wrap">
        <svg class="yt-workflow__edges" id="yt-wf-edges"></svg>
        <div class="yt-workflow__canvas" id="yt-wf-canvas"></div>
    </div>
</div>

<script>
(function () {
    const root = document.getElementById('yt-workflow');
    if (!root) return;

    const graph = JSON.parse(root.dataset.graph || '{"statuses":[],"transitions":[]}');
    const canvas = document.getElementById('yt-wf-canvas');
    const edgesSvg = document.getElementById('yt-wf-edges');
    const saveUrl = root.dataset.saveUrl;
    const csrf = root.dataset.csrf;

    let linkMode = false;
    let linkFrom = null;
    let uid = 1;

    const COL_W = 168;
    const COL_H = 64;
    const GAP_X = 56;
    const GAP_Y = 48;
    const categories = ['todo', 'in_progress', 'done'];

    function layoutPositions() {
        const byCat = { todo: [], in_progress: [], done: [] };
        graph.statuses.forEach(s => {
            const c = byCat[s.category] ? s.category : 'in_progress';
            byCat[c].push(s);
        });
        categories.forEach((cat, ci) => {
            byCat[cat].forEach((s, ri) => {
                if (s.x == null) s.x = 40 + ci * (COL_W + GAP_X);
                if (s.y == null) s.y = 40 + ri * (COL_H + GAP_Y);
            });
        });
    }

    function renderNodes() {
        canvas.innerHTML = '';
        layoutPositions();
        graph.statuses.forEach(s => {
            const el = document.createElement('div');
            el.className = 'yt-wf-node' + (s.is_final ? ' is-final' : '') + (s.is_initial ? ' is-initial' : '');
            el.dataset.id = s.id;
            el.style.left = s.x + 'px';
            el.style.top = s.y + 'px';
            el.style.setProperty('--c', s.color || '#64748b');
            el.innerHTML = `
                <div class="yt-wf-node__bar"></div>
                <div class="yt-wf-node__name" contenteditable="true" spellcheck="false">${escapeHtml(s.name)}</div>
                <div class="yt-wf-node__meta">
                    <select class="yt-wf-node__cat">
                        <option value="todo"${s.category==='todo'?' selected':''}>To do</option>
                        <option value="in_progress"${s.category==='in_progress'?' selected':''}>In progress</option>
                        <option value="done"${s.category==='done'?' selected':''}>Done</option>
                    </select>
                    <input type="color" class="yt-wf-node__color" value="${s.color || '#64748b'}" title="Цвет">
                    <button type="button" class="yt-wf-node__del" title="Удалить">×</button>
                </div>`;
            canvas.appendChild(el);

            el.querySelector('.yt-wf-node__name').addEventListener('input', (e) => {
                s.name = e.target.textContent.trim() || s.name;
            });
            el.querySelector('.yt-wf-node__cat').addEventListener('change', (e) => {
                s.category = e.target.value;
            });
            el.querySelector('.yt-wf-node__color').addEventListener('input', (e) => {
                s.color = e.target.value;
                el.style.setProperty('--c', s.color);
            });
            el.querySelector('.yt-wf-node__del').addEventListener('click', (e) => {
                e.stopPropagation();
                if (!confirm('Удалить статус «' + s.name + '» из схемы?')) return;
                graph.statuses = graph.statuses.filter(x => String(x.id) !== String(s.id));
                graph.transitions = graph.transitions.filter(t =>
                    String(t.from) !== String(s.id) && String(t.to) !== String(s.id));
                renderAll();
            });

            el.addEventListener('click', (e) => {
                if (!linkMode || e.target.closest('input,select,button,[contenteditable]')) return;
                if (!linkFrom) {
                    linkFrom = s.id;
                    el.classList.add('is-link-from');
                    document.getElementById('yt-wf-hint').textContent = 'Выберите целевой статус';
                    return;
                }
                if (String(linkFrom) === String(s.id)) return;
                const exists = graph.transitions.some(t =>
                    String(t.from) === String(linkFrom) && String(t.to) === String(s.id));
                if (!exists) {
                    graph.transitions.push({ id: null, from: linkFrom, to: s.id, name: null });
                }
                canvas.querySelectorAll('.is-link-from').forEach(n => n.classList.remove('is-link-from'));
                linkFrom = null;
                document.getElementById('yt-wf-hint').textContent = 'Стрелка добавлена. Можно соединять дальше или сохранить.';
                renderEdges();
            });

            makeDraggable(el, s);
        });
        renderEdges();
    }

    function makeDraggable(el, s) {
        let ox = 0, oy = 0, moving = false;
        el.addEventListener('pointerdown', (e) => {
            if (linkMode || e.target.closest('input,select,button,[contenteditable]')) return;
            moving = true;
            ox = e.clientX - s.x;
            oy = e.clientY - s.y;
            el.setPointerCapture(e.pointerId);
        });
        el.addEventListener('pointermove', (e) => {
            if (!moving) return;
            s.x = Math.max(0, e.clientX - ox);
            s.y = Math.max(0, e.clientY - oy);
            el.style.left = s.x + 'px';
            el.style.top = s.y + 'px';
            renderEdges();
        });
        el.addEventListener('pointerup', () => { moving = false; });
    }

    function renderEdges() {
        const wrap = edgesSvg.parentElement;
        const w = Math.max(wrap.clientWidth, 900);
        const h = Math.max(wrap.clientHeight, 600);
        edgesSvg.setAttribute('width', w);
        edgesSvg.setAttribute('height', h);
        edgesSvg.innerHTML = `
            <defs>
                <marker id="yt-arrow" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                    <polygon points="0 0, 10 3.5, 0 7" fill="#64748b"/>
                </marker>
            </defs>`;

        const byId = Object.fromEntries(graph.statuses.map(s => [String(s.id), s]));
        graph.transitions.forEach((t, idx) => {
            const a = byId[String(t.from)];
            const b = byId[String(t.to)];
            if (!a || !b) return;
            const x1 = a.x + COL_W / 2;
            const y1 = a.y + COL_H / 2;
            const x2 = b.x + COL_W / 2;
            const y2 = b.y + COL_H / 2;
            const mx = (x1 + x2) / 2;
            const my = (y1 + y2) / 2 - 20;
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', `M ${x1} ${y1} Q ${mx} ${my} ${x2} ${y2}`);
            path.setAttribute('class', 'yt-wf-edge');
            path.setAttribute('marker-end', 'url(#yt-arrow)');
            path.dataset.idx = idx;
            path.addEventListener('click', () => {
                if (confirm('Удалить переход?')) {
                    graph.transitions.splice(idx, 1);
                    renderEdges();
                }
            });
            edgesSvg.appendChild(path);
        });
    }

    function renderAll() { renderNodes(); }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, m => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        })[m]);
    }

    document.getElementById('yt-wf-add').addEventListener('click', () => {
        const id = 'new_' + (uid++);
        graph.statuses.push({
            id, slug: 's_' + id, name: 'Новый статус', color: '#3b82f6',
            category: 'in_progress', is_initial: false, is_final: false,
            sort_order: graph.statuses.length, x: 80 + Math.random() * 120, y: 80 + Math.random() * 120,
        });
        renderAll();
    });

    const linkBtn = document.getElementById('yt-wf-link');
    linkBtn.addEventListener('click', () => {
        linkMode = !linkMode;
        linkFrom = null;
        linkBtn.classList.toggle('btn-warning', linkMode);
        linkBtn.classList.toggle('btn-outline-secondary', !linkMode);
        linkBtn.dataset.mode = linkMode ? 'on' : 'off';
        canvas.querySelectorAll('.is-link-from').forEach(n => n.classList.remove('is-link-from'));
        document.getElementById('yt-wf-hint').textContent = linkMode
            ? 'Кликните статус-источник, затем статус-цель'
            : 'Перетаскивайте статусы. Режим «Соединить» — клик по источнику, затем по цели.';
        root.classList.toggle('is-link-mode', linkMode);
    });

    document.getElementById('yt-wf-save').addEventListener('click', async () => {
        const payload = {
            statuses: graph.statuses.map((s, i) => ({
                id: String(s.id).startsWith('new_') ? null : s.id,
                client_id: s.id,
                slug: s.slug,
                name: s.name,
                color: s.color,
                category: s.category,
                sort_order: i,
                is_initial: !!s.is_initial,
                is_final: !!s.is_final,
                is_active: true,
            })),
            transitions: graph.transitions.map(t => ({
                from: t.from,
                to: t.to,
                name: t.name,
            })),
        };
        // map client ids for new nodes into payload statuses id field as client string
        payload.statuses = graph.statuses.map((s, i) => ({
            id: s.id,
            slug: s.slug,
            name: s.name,
            color: s.color,
            category: s.category,
            sort_order: i,
            is_initial: !!s.is_initial,
            is_final: !!s.is_final,
            is_active: true,
        }));

        const res = await fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            alert(data.message || 'Ошибка сохранения');
            return;
        }
        const data = await res.json();
        if (data.graph) {
            graph.statuses = data.graph.statuses;
            graph.transitions = data.graph.transitions;
            renderAll();
        }
        alert('Схема сохранена');
    });

    window.addEventListener('resize', renderEdges);
    renderAll();
})();
</script>
