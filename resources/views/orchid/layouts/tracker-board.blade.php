@php
    $filters = $filters ?? [];
    $canManage = (bool) ($can_manage ?? false);
    $assignee = $filters['assignee'] ?? ($canManage ? 'all' : 'me');
    $quickFilters = $quick_filters ?? [];
    $activeQuickId = $active_quick_id ?? null;
    $defaultAssignee = $canManage ? 'all' : 'me';

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

    $boardUrl = function (array $override = [], array $remove = []) use ($baseParams) {
        $params = array_merge($baseParams, $override);
        foreach ($remove as $key) {
            unset($params[$key]);
        }
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');

        return route('platform.systems.boards', $params);
    };

    $assigneeLabel = match (true) {
        $assignee === 'me' => 'Я',
        $assignee === 'all' => 'Все',
        $assignee === 'unassigned' => 'Не назначен',
        default => collect($filter_assignees ?? [])->firstWhere('id', (int) $assignee)['name'] ?? ('#'.$assignee),
    };

    $projectLabel = ($filters['project'] ?? null)
        ? (collect($filter_projects ?? [])->firstWhere('id', (int) $filters['project'])?->name ?? 'Проект')
        : null;
    $queueLabel = ($filters['queue'] ?? null)
        ? (collect($filter_queues ?? [])->firstWhere('id', (int) $filters['queue'])?->key ?? 'Очередь')
        : null;
    $priorityLabel = ($filters['priority'] ?? null)
        ? (($filter_priorities[$filters['priority']] ?? null) ?: $filters['priority'])
        : null;
    $typeLabel = ($filters['type'] ?? null)
        ? (($filter_types[$filters['type']] ?? null) ?: $filters['type'])
        : null;
    $sprintLabel = ($filters['sprint'] ?? null)
        ? (collect($sprints ?? [])->firstWhere('id', (int) $filters['sprint'])?->name ?? 'Спринт')
        : null;

    $hasAdvanced = filled($filters['q'] ?? null)
        || filled($filters['project'] ?? null)
        || filled($filters['priority'] ?? null)
        || filled($filters['queue'] ?? null)
        || filled($filters['type'] ?? null)
        || filled($filters['sprint'] ?? null)
        || ($assignee !== $defaultAssignee && $assignee !== 'me');
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

    <form class="yt-filters" method="get" action="{{ route('platform.systems.boards') }}" id="yt-filters">
        @if($board)
            <input type="hidden" name="board" value="{{ $board->id }}">
        @endif

        {{-- Строка 1: поиск + тулбар --}}
        <div class="yt-filters__toolbar">
            <label class="yt-filters__search">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.5 1a6.5 6.5 0 0 1 5.097 10.535l2.934 2.935-1.06 1.06-2.935-2.934A6.5 6.5 0 1 1 7.5 1m0 1.5a5 5 0 1 0 0 10 5 5 0 0 0 0-10" clip-rule="evenodd"/>
                </svg>
                <input type="search"
                       name="q"
                       value="{{ $filters['q'] ?? '' }}"
                       placeholder="Поиск по названию или ключу"
                       autocomplete="off">
            </label>

            <button type="button"
                    class="yt-icon-btn {{ $hasAdvanced ? 'is-active' : '' }}"
                    id="yt-toggle-params"
                    aria-expanded="true"
                    aria-controls="yt-params"
                    title="Фильтры">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M1 3.25a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H1.75A.75.75 0 0 1 1 3.25M3 8a.75.75 0 0 1 .75-.75h8.5a.75.75 0 0 1 0 1.5h-8.5A.75.75 0 0 1 3 8m3.75 4a.75.75 0 0 0 0 1.5h2.5a.75.75 0 0 0 0-1.5z" clip-rule="evenodd"/>
                </svg>
            </button>

            <button type="submit" class="yt-btn yt-btn--primary yt-btn--sm">Применить</button>
            <a href="{{ route('platform.systems.boards', array_filter(['board' => $board?->id, 'assignee' => $defaultAssignee])) }}"
               class="yt-btn yt-btn--ghost yt-btn--sm"
               title="Сбросить фильтры">Сбросить</a>
        </div>

        {{-- Строка 2: чипы полей (как в Трекере) --}}
        <div class="yt-filters__params" id="yt-params">
            <div class="yt-fchip {{ filled($filters['queue'] ?? null) ? 'has-value' : '' }}">
                <span class="yt-fchip__name">Очередь</span>
                <select name="queue" class="yt-fchip__select" aria-label="Очередь">
                    <option value="">Все</option>
                    @foreach($filter_queues as $queue)
                        <option value="{{ $queue->id }}" @selected((int)($filters['queue'] ?? 0) === (int)$queue->id)>
                            {{ $queue->key }}{{ $queue->name ? ' · '.$queue->name : '' }}
                        </option>
                    @endforeach
                </select>
                @if(filled($filters['queue'] ?? null))
                    <a class="yt-fchip__clear" href="{{ $boardUrl([], ['queue']) }}" title="Убрать" aria-label="Убрать очередь">×</a>
                @endif
            </div>

            <div class="yt-fchip {{ filled($filters['type'] ?? null) ? 'has-value' : '' }}">
                <span class="yt-fchip__name">Тип</span>
                <select name="type" class="yt-fchip__select" aria-label="Тип">
                    <option value="">Все</option>
                    @foreach($filter_types as $val => $label)
                        <option value="{{ $val }}" @selected(($filters['type'] ?? '') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                @if(filled($filters['type'] ?? null))
                    <a class="yt-fchip__clear" href="{{ $boardUrl([], ['type']) }}" title="Убрать" aria-label="Убрать тип">×</a>
                @endif
            </div>

            <div class="yt-fchip has-value">
                <span class="yt-fchip__name">Исполнитель</span>
                <select name="assignee" class="yt-fchip__select" aria-label="Исполнитель">
                    <option value="me" @selected($assignee === 'me')>Я</option>
                    <option value="all" @selected($assignee === 'all')>Все</option>
                    <option value="unassigned" @selected($assignee === 'unassigned')>Не назначен</option>
                    @foreach($filter_assignees as $u)
                        <option value="{{ $u['id'] }}" @selected((string)$assignee === (string)$u['id'])>{{ $u['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="yt-fchip {{ filled($filters['project'] ?? null) ? 'has-value' : '' }}">
                <span class="yt-fchip__name">Проект</span>
                <select name="project" class="yt-fchip__select" aria-label="Проект">
                    <option value="">Все</option>
                    @foreach($filter_projects as $p)
                        <option value="{{ $p->id }}" @selected((int)($filters['project'] ?? 0) === (int)$p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
                @if(filled($filters['project'] ?? null))
                    <a class="yt-fchip__clear" href="{{ $boardUrl([], ['project']) }}" title="Убрать" aria-label="Убрать проект">×</a>
                @endif
            </div>

            <div class="yt-fchip {{ filled($filters['priority'] ?? null) ? 'has-value' : '' }}">
                <span class="yt-fchip__name">Приоритет</span>
                <select name="priority" class="yt-fchip__select" aria-label="Приоритет">
                    <option value="">Любой</option>
                    @foreach($filter_priorities as $val => $label)
                        <option value="{{ $val }}" @selected(($filters['priority'] ?? '') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                @if(filled($filters['priority'] ?? null))
                    <a class="yt-fchip__clear" href="{{ $boardUrl([], ['priority']) }}" title="Убрать" aria-label="Убрать приоритет">×</a>
                @endif
            </div>

            <button type="button"
                    class="yt-btn yt-btn--flat yt-btn--sm"
                    id="yt-open-save-filter"
                    title="Сохранить выбранные фильтры">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 11.5A1.5 1.5 0 0 0 4.5 13v-2.5a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2V13a1.5 1.5 0 0 0 1.5-1.5V6.036a1 1 0 0 0-.293-.708l-2.035-2.035A1 1 0 0 0 9.964 3H6v1a.5.5 0 0 0 .5.5h3a.75.75 0 0 1 0 1.5h-3a2 2 0 0 1-2-2V3A1.5 1.5 0 0 0 3 4.5zm-1.5 0a3 3 0 0 0 3 3h7a3 3 0 0 0 3-3V6.036a2.5 2.5 0 0 0-.732-1.768l-2.036-2.036A2.5 2.5 0 0 0 9.964 1.5H4.5a3 3 0 0 0-3 3zm8.5-1V13H6v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5" clip-rule="evenodd"/>
                </svg>
                Сохранить
            </button>
        </div>

        {{-- Строка 3: спринт + быстрые фильтры --}}
        <div class="yt-filters__bottom">
            <label class="yt-sprint-select">
                <select name="sprint" aria-label="Спринт">
                    <option value="">Все спринты</option>
                    @foreach($sprints as $sp)
                        <option value="{{ $sp->id }}" @selected((int)($filters['sprint'] ?? 0) === (int)$sp->id)>
                            {{ $sp->name }} ({{ $sp->statusLabel() }})
                        </option>
                    @endforeach
                </select>
            </label>

            <div class="yt-filters__bottom-divider" aria-hidden="true"></div>

            <div class="yt-quick-filters" role="group" aria-label="Быстрые фильтры">
                <a href="{{ $boardUrl(['assignee' => 'me'], ['q', 'project', 'priority', 'queue', 'type', 'sprint']) }}"
                   class="yt-qf {{ $assignee === 'me' && ! $activeQuickId && ! $hasAdvanced ? 'is-active' : '' }}">
                    Мои задачи
                </a>
                <a href="{{ $boardUrl(['assignee' => 'all'], ['q', 'project', 'priority', 'queue', 'type', 'sprint']) }}"
                   class="yt-qf {{ $assignee === 'all' && ! $activeQuickId && ! filled($filters['q'] ?? null) && ! filled($filters['project'] ?? null) && ! filled($filters['priority'] ?? null) && ! filled($filters['queue'] ?? null) && ! filled($filters['type'] ?? null) && ! filled($filters['sprint'] ?? null) ? 'is-active' : '' }}">
                    Все задачи
                </a>
                <a href="{{ $boardUrl(['assignee' => 'unassigned'], ['q', 'project', 'priority', 'queue', 'type', 'sprint']) }}"
                   class="yt-qf {{ $assignee === 'unassigned' && ! $activeQuickId ? 'is-active' : '' }}">
                    Без исполнителя
                </a>

                @foreach($quickFilters as $qf)
                    @php
                        $qfParams = array_filter(array_merge(
                            ['board' => $board?->id],
                            $qf['params'] ?? []
                        ), fn ($v) => $v !== null && $v !== '');
                    @endphp
                    <a href="{{ route('platform.systems.boards', $qfParams) }}"
                       class="yt-qf {{ (string) $activeQuickId === (string) ($qf['id'] ?? '') ? 'is-active' : '' }}"
                       title="{{ $qf['name'] ?? 'Фильтр' }}">
                        {{ $qf['name'] ?? 'Фильтр' }}
                    </a>
                @endforeach

                <button type="button"
                        class="yt-icon-btn yt-icon-btn--sm"
                        id="yt-open-manage-filters"
                        title="Управление фильтрами"
                        aria-label="Управление фильтрами">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.199 2H8.8a.2.2 0 0 1 .2.2c0 1.808 1.958 2.939 3.524 2.034a.2.2 0 0 1 .271.073l.802 1.388a.2.2 0 0 1-.073.272c-1.566.904-1.566 3.164 0 4.069a.2.2 0 0 1 .073.271l-.802 1.388a.2.2 0 0 1-.271.073C10.958 10.863 9 11.993 9 13.8a.2.2 0 0 1-.199.2H7.2a.2.2 0 0 1-.2-.2c0-1.808-1.958-2.938-3.524-2.034a.2.2 0 0 1-.272-.073l-.8-1.388a.2.2 0 0 1 .072-.271c1.566-.905 1.566-3.165 0-4.07a.2.2 0 0 1-.073-.27l.801-1.389a.2.2 0 0 1 .272-.072C5.042 5.138 7 4.007 7 2.199c0-.11.089-.199.199-.199M5.5 2.2c0-.94.76-1.7 1.699-1.7H8.8c.94 0 1.7.76 1.7 1.7a.85.85 0 0 0 1.274.735 1.7 1.7 0 0 1 2.32.622l.802 1.388c.469.813.19 1.851-.622 2.32a.85.85 0 0 0 0 1.472 1.7 1.7 0 0 1 .622 2.32l-.802 1.388a1.7 1.7 0 0 1-2.32.622.85.85 0 0 0-1.274.735c0 .939-.76 1.7-1.699 1.7H7.2a1.7 1.7 0 0 1-1.699-1.7.85.85 0 0 0-1.274-.735 1.7 1.7 0 0 1-2.32-.622l-.802-1.388a1.7 1.7 0 0 1 .622-2.32.85.85 0 0 0 0-1.471 1.7 1.7 0 0 1-.622-2.32l.801-1.389a1.7 1.7 0 0 1 2.32-.622A.85.85 0 0 0 5.5 2.2m4 5.8a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M11 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </div>
    </form>

    <div class="yt-board__gap" aria-hidden="true"></div>

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

{{-- Модалка: сохранить фильтр --}}
<div class="modal fade" id="yt-save-filter-modal" tabindex="-1" aria-labelledby="yt-save-filter-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="{{ url()->current() }}/saveQuickFilter">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="yt-save-filter-title">Сохранить фильтр</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="board_id" value="{{ $board?->id }}">
                <input type="hidden" name="params[assignee]" value="{{ $assignee }}">
                <input type="hidden" name="params[project]" value="{{ $filters['project'] ?? '' }}">
                <input type="hidden" name="params[sprint]" value="{{ $filters['sprint'] ?? '' }}">
                <input type="hidden" name="params[priority]" value="{{ $filters['priority'] ?? '' }}">
                <input type="hidden" name="params[queue]" value="{{ $filters['queue'] ?? '' }}">
                <input type="hidden" name="params[type]" value="{{ $filters['type'] ?? '' }}">
                <input type="hidden" name="params[q]" value="{{ $filters['q'] ?? '' }}">
                <label class="form-label" for="yt-filter-name">Название</label>
                <input type="text"
                       class="form-control"
                       id="yt-filter-name"
                       name="name"
                       required
                       maxlength="80"
                       placeholder="Например, Мои задачи (Для меня)"
                       value="">
                <p class="form-text mb-0 mt-2">Сохранится текущий набор параметров доски.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

{{-- Модалка: управление фильтрами --}}
<div class="modal fade" id="yt-manage-filters-modal" tabindex="-1" aria-labelledby="yt-manage-filters-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="yt-manage-filters-title">Управление фильтрами</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                @forelse($quickFilters as $qf)
                    <div class="yt-manage-filter-row">
                        <div>
                            <div class="yt-manage-filter-row__name">{{ $qf['name'] ?? 'Фильтр' }}</div>
                            <div class="yt-manage-filter-row__meta text-muted small">
                                @php
                                    $bits = [];
                                    $p = $qf['params'] ?? [];
                                    if (!empty($p['assignee'])) $bits[] = 'исп: '.$p['assignee'];
                                    if (!empty($p['project'])) $bits[] = 'проект';
                                    if (!empty($p['queue'])) $bits[] = 'очередь';
                                    if (!empty($p['type'])) $bits[] = 'тип';
                                    if (!empty($p['priority'])) $bits[] = 'приоритет';
                                    if (!empty($p['sprint'])) $bits[] = 'спринт';
                                    if (!empty($p['q'])) $bits[] = 'поиск';
                                @endphp
                                {{ $bits ? implode(' · ', $bits) : 'без параметров' }}
                            </div>
                        </div>
                        <form method="post" action="{{ url()->current() }}/deleteQuickFilter" onsubmit="return confirm('Удалить фильтр?');">
                            @csrf
                            <input type="hidden" name="id" value="{{ $qf['id'] }}">
                            <input type="hidden" name="board_id" value="{{ $board?->id }}">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted mb-0">Пока нет сохранённых фильтров. Настройте параметры и нажмите «Сохранить».</p>
                @endforelse
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const root = document.getElementById('yt-board');
    if (!root) return;
    const canManage = root.dataset.canManage === '1';
    const moveUrl = root.dataset.moveUrl;
    const csrf = root.dataset.csrf;
    let dragCard = null;

    document.querySelectorAll('#yt-filters select').forEach((el) => {
        el.addEventListener('change', () => el.form?.requestSubmit());
    });

    const params = document.getElementById('yt-params');
    const toggle = document.getElementById('yt-toggle-params');
    if (params && toggle) {
        const key = 'yt-board-params-open';
        const saved = localStorage.getItem(key);
        const open = saved === null ? true : saved === '1';
        params.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.classList.toggle('is-active', open);
        toggle.addEventListener('click', () => {
            const next = params.hidden;
            params.hidden = !next;
            toggle.setAttribute('aria-expanded', next ? 'true' : 'false');
            toggle.classList.toggle('is-active', next);
            localStorage.setItem(key, next ? '1' : '0');
        });
    }

    const openModal = (id) => {
        const el = document.getElementById(id);
        if (!el || typeof bootstrap === 'undefined') return;
        bootstrap.Modal.getOrCreateInstance(el).show();
    };
    document.getElementById('yt-open-save-filter')?.addEventListener('click', () => openModal('yt-save-filter-modal'));
    document.getElementById('yt-open-manage-filters')?.addEventListener('click', () => openModal('yt-manage-filters-modal'));

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
