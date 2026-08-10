@php
    $filters = $filters ?? [];
    $canManage = (bool) ($can_manage ?? false);
    $assignee = $filters['assignee'] ?? ($canManage ? 'all' : 'me');
    $baseParams = array_filter([
        'board' => $board?->id,
        'sprint' => $filters['sprint'] ?? null,
        'project' => $filters['project'] ?? null,
        'priority' => $filters['priority'] ?? null,
        'queue' => $filters['queue'] ?? null,
        'type' => $filters['type'] ?? null,
        'q' => $filters['q'] ?? null,
        'assignee' => $assignee !== '' ? $assignee : null,
    ], fn ($v) => $v !== null && $v !== '');

    $boardUrl = function (array $override = []) use ($baseParams) {
        $params = array_filter(array_merge($baseParams, $override), fn ($v) => $v !== null && $v !== '');
        return route('platform.systems.boards', $params);
    };
@endphp

<div class="yt-board {{ $canManage ? 'yt-board--manage' : 'yt-board--readonly' }}"
     id="yt-board"
     data-move-url="{{ $move_url }}"
     data-csrf="{{ $csrf }}"
     data-can-manage="{{ $canManage ? '1' : '0' }}">

    <div class="yt-board__top">
        <div class="yt-board__heading">
            @if(($boards ?? collect())->count() > 1)
                <div class="yt-board__board-switch" role="tablist" aria-label="Доски">
                    @foreach($boards as $b)
                        <a href="{{ $boardUrl(['board' => $b->id]) }}"
                           class="yt-chip {{ ($board && (int)$board->id === (int)$b->id) ? 'is-active' : '' }}"
                           role="tab"
                           aria-selected="{{ ($board && (int)$board->id === (int)$b->id) ? 'true' : 'false' }}">
                            {{ $b->name }}
                        </a>
                    @endforeach
                </div>
            @else
                <h2 class="yt-board__title">{{ $board?->name ?? 'Доска' }}</h2>
            @endif
        </div>
        <div class="yt-board__meta">
            <span class="yt-board__count">{{ $tasks_total ?? 0 }} задач</span>
            @unless($canManage)
                <span class="yt-board__hint">Просмотр · перетаскивание недоступно</span>
            @endunless
        </div>
    </div>

    {{-- Панель фильтров как в Яндекс Трекере --}}
    <form class="yt-filters" method="get" action="{{ route('platform.systems.boards') }}" id="yt-filters">
        @if($board)
            <input type="hidden" name="board" value="{{ $board->id }}">
        @endif

        <div class="yt-filters__quick">
            <a href="{{ $boardUrl(['assignee' => 'me']) }}"
               class="yt-filter-chip {{ $assignee === 'me' ? 'is-active' : '' }}">
                Мои
            </a>
            <a href="{{ $boardUrl(['assignee' => 'all']) }}"
               class="yt-filter-chip {{ $assignee === 'all' ? 'is-active' : '' }}">
                Все
            </a>
            <a href="{{ $boardUrl(['assignee' => 'unassigned']) }}"
               class="yt-filter-chip {{ $assignee === 'unassigned' ? 'is-active' : '' }}">
                Без исполнителя
            </a>
        </div>

        <div class="yt-filters__fields">
            <label class="yt-field yt-field--search">
                <span class="yt-field__label">Поиск</span>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ключ или название…" autocomplete="off">
            </label>

            <label class="yt-field">
                <span class="yt-field__label">Исполнитель</span>
                <select name="assignee">
                    <option value="me" @selected($assignee === 'me')>Я</option>
                    <option value="all" @selected($assignee === 'all')>Все</option>
                    <option value="unassigned" @selected($assignee === 'unassigned')>Не назначен</option>
                    @foreach($filter_assignees as $u)
                        <option value="{{ $u['id'] }}" @selected((string)$assignee === (string)$u['id'])>{{ $u['name'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="yt-field">
                <span class="yt-field__label">Проект</span>
                <select name="project">
                    <option value="">Все проекты</option>
                    @foreach($filter_projects as $p)
                        <option value="{{ $p->id }}" @selected((int)($filters['project'] ?? 0) === (int)$p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="yt-field">
                <span class="yt-field__label">Спринт</span>
                <select name="sprint">
                    <option value="">Все спринты</option>
                    @foreach($sprints as $sp)
                        <option value="{{ $sp->id }}" @selected((int)($filters['sprint'] ?? 0) === (int)$sp->id)>
                            {{ $sp->name }} ({{ $sp->statusLabel() }})
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="yt-field">
                <span class="yt-field__label">Приоритет</span>
                <select name="priority">
                    <option value="">Любой</option>
                    @foreach($filter_priorities as $val => $label)
                        <option value="{{ $val }}" @selected(($filters['priority'] ?? '') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="yt-field">
                <span class="yt-field__label">Очередь</span>
                <select name="queue">
                    <option value="">Все</option>
                    @foreach($filter_queues as $queue)
                        <option value="{{ $queue->id }}" @selected((int)($filters['queue'] ?? 0) === (int)$queue->id)>
                            {{ $queue->key }}{{ $queue->name ? ' · '.$queue->name : '' }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="yt-field">
                <span class="yt-field__label">Тип</span>
                <select name="type">
                    <option value="">Все типы</option>
                    @foreach($filter_types as $val => $label)
                        <option value="{{ $val }}" @selected(($filters['type'] ?? '') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="yt-filters__actions">
                <button type="submit" class="yt-btn yt-btn--primary">Применить</button>
                <a href="{{ route('platform.systems.boards', array_filter(['board' => $board?->id, 'assignee' => $canManage ? 'all' : 'me'])) }}"
                   class="yt-btn yt-btn--ghost">Сбросить</a>
            </div>
        </div>
    </form>

    @if(!$board)
        <div class="yt-empty">Создайте доску через кнопку «Создать доску».</div>
    @else
        <div class="yt-board__viewport" id="yt-board-viewport">
            <div class="yt-board__cols" id="yt-cols">
            @foreach($columns as $col)
                <section class="yt-col" data-status-id="{{ $col['status_id'] }}" data-column-id="{{ $col['id'] }}">
                    <header class="yt-col__head">
                        <span class="yt-col__dot" style="background:{{ $col['color'] }}"></span>
                        <span class="yt-col__title">{{ $col['name'] }}</span>
                        <span class="yt-col__count">{{ count($col['tasks']) }}</span>
                        @if($col['wip_limit'])
                            <span class="yt-col__wip" title="WIP limit">/ {{ $col['wip_limit'] }}</span>
                        @endif
                    </header>
                    <div class="yt-col__list" data-dropzone="1">
                        @foreach($col['tasks'] as $card)
                            <article class="yt-card"
                                     @if($canManage) draggable="true" @endif
                                     data-task-id="{{ $card['id'] }}"
                                     data-status-id="{{ $card['status_id'] }}">
                                <div class="yt-card__top">
                                    <a class="yt-card__key" href="{{ $card['url'] }}">{{ $card['key'] }}</a>
                                    @if($card['type'])
                                        <span class="yt-card__type yt-card__type--{{ $card['type'] }}">{{ $card['type'] }}</span>
                                    @endif
                                </div>
                                <a class="yt-card__title" href="{{ $card['url'] }}">{{ $card['name'] }}</a>
                                <div class="yt-card__foot">
                                    @if($card['priority'])
                                        <span class="yt-card__prio yt-card__prio--{{ $card['priority'] }}">{{ $card['priority'] }}</span>
                                    @else
                                        <span></span>
                                    @endif
                                    @if($card['executor'])
                                        <span class="yt-card__assignee bx-avatar bx-avatar--xs bx-avatar--round"
                                              style="--bx-avatar-bg: {{ $card['executor_color'] ?? '#64748b' }}"
                                              title="{{ $card['executor'] }}">
                                            <span class="bx-avatar__initials">{{ $card['executor_initials'] ?? '?' }}</span>
                                            @if(!empty($card['executor_avatar']))
                                                <img class="bx-avatar__img"
                                                     src="{{ $card['executor_avatar'] }}"
                                                     alt=""
                                                     loading="lazy"
                                                     decoding="async"
                                                     onerror="this.remove()">
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
            </div>
        </div>
    @endif
</div>

<script>
(function () {
    const root = document.getElementById('yt-board');
    if (!root) return;
    const canManage = root.dataset.canManage === '1';
    const moveUrl = root.dataset.moveUrl;
    const csrf = root.dataset.csrf;
    let dragCard = null;

    // Автосабмит селектов фильтров
    document.querySelectorAll('#yt-filters select').forEach((el) => {
        el.addEventListener('change', () => el.form?.requestSubmit());
    });

    // Горизонтальный скролл колесиком (как на доске Трекера)
    const viewport = document.getElementById('yt-board-viewport');
    if (viewport) {
        viewport.addEventListener('wheel', (e) => {
            if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
            if (viewport.scrollWidth <= viewport.clientWidth) return;
            e.preventDefault();
            viewport.scrollLeft += e.deltaY;
        }, { passive: false });
    }

    if (!canManage) return;

    root.querySelectorAll('.yt-card').forEach(card => {
        card.addEventListener('dragstart', (e) => {
            dragCard = card;
            card.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.dataset.taskId);
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
            root.querySelectorAll('.yt-col__list').forEach(z => z.classList.remove('is-over'));
            dragCard = null;
        });
    });

    root.querySelectorAll('.yt-col__list').forEach(zone => {
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('is-over');
            e.dataTransfer.dropEffect = 'move';
        });
        zone.addEventListener('dragleave', () => zone.classList.remove('is-over'));
        zone.addEventListener('drop', async (e) => {
            e.preventDefault();
            zone.classList.remove('is-over');
            if (!dragCard) return;
            const col = zone.closest('.yt-col');
            const statusId = col.dataset.statusId;
            const taskId = dragCard.dataset.taskId;
            const siblings = [...zone.querySelectorAll('.yt-card')];
            const after = siblings.find(el => {
                const box = el.getBoundingClientRect();
                return e.clientY < box.top + box.height / 2;
            });
            if (after) zone.insertBefore(dragCard, after);
            else zone.appendChild(dragCard);

            const order = [...zone.querySelectorAll('.yt-card')].map(el => el.dataset.taskId);
            try {
                const res = await fetch(moveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        task_id: Number(taskId),
                        status_id: Number(statusId),
                        order: order.map(Number),
                    }),
                });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || (data.errors && Object.values(data.errors).flat().join('\n')) || 'Не удалось переместить');
                    location.reload();
                } else {
                    dragCard.dataset.statusId = statusId;
                    root.querySelectorAll('.yt-col').forEach(c => {
                        c.querySelector('.yt-col__count').textContent = c.querySelectorAll('.yt-card').length;
                    });
                }
            } catch (err) {
                alert('Ошибка сети');
                location.reload();
            }
        });
    });
})();
</script>
