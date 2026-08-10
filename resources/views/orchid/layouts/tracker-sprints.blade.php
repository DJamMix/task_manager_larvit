<div class="yt-sprints" id="yt-sprints"
     data-assign-url="{{ $assign_url }}"
     data-csrf="{{ $csrf }}">

    <div class="yt-sprints__grid">
        <section class="yt-sprint-panel yt-sprint-panel--backlog">
            <header class="yt-sprint-panel__head">
                <div>
                    <h3>Бэклог</h3>
                    <p class="yt-muted">Задачи без спринта · {{ $backlog_tasks->count() }}</p>
                </div>
            </header>
            <div class="yt-sprint-panel__list" data-sprint-id="" data-dropzone="backlog">
                @forelse($backlog_tasks as $task)
                    @include('orchid.layouts.partials.yt-sprint-task', ['task' => $task])
                @empty
                    <div class="yt-empty-sm">Бэклог пуст</div>
                @endforelse
            </div>
        </section>

        <section class="yt-sprint-panel yt-sprint-panel--active">
            <header class="yt-sprint-panel__head">
                @if($active_sprint)
                    <div>
                        <div class="yt-sprint-badge">Активный</div>
                        <h3>{{ $active_sprint->name }}</h3>
                        <p class="yt-muted">
                            @if($active_sprint->goal) {{ $active_sprint->goal }} · @endif
                            {{ $active_tasks->count() }} задач
                            @if($active_sprint->start_date || $active_sprint->end_date)
                                · {{ optional($active_sprint->start_date)->format('d.m') }}
                                — {{ optional($active_sprint->end_date)->format('d.m.Y') }}
                            @endif
                        </p>
                    </div>
                    <form method="post" action="{{ url()->current() }}/completeSprint">
                        @csrf
                        <input type="hidden" name="sprint_id" value="{{ $active_sprint->id }}">
                        <input type="hidden" name="move_to_backlog" value="1">
                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                onclick="return confirm('Завершить спринт? Незавершённые уйдут в бэклог.')">
                            Завершить
                        </button>
                    </form>
                @else
                    <div>
                        <h3>Нет активного спринта</h3>
                        <p class="yt-muted">Запустите запланированный спринт справа</p>
                    </div>
                @endif
            </header>
            <div class="yt-sprint-panel__list"
                 data-sprint-id="{{ $active_sprint?->id }}"
                 data-dropzone="active">
                @forelse($active_tasks as $task)
                    @include('orchid.layouts.partials.yt-sprint-task', ['task' => $task])
                @empty
                    <div class="yt-empty-sm">Перетащите задачи из бэклога</div>
                @endforelse
            </div>
        </section>

        <aside class="yt-sprint-side">
            <h4>Запланированные</h4>
            @forelse($planned_sprints as $sp)
                <div class="yt-sprint-card">
                    <div class="yt-sprint-card__name">{{ $sp->name }}</div>
                    <div class="yt-muted small">{{ $sp->tasks_count }} задач
                        @if($sp->start_date) · {{ $sp->start_date->format('d.m') }}–{{ optional($sp->end_date)->format('d.m') }} @endif
                    </div>
                    @if($sp->goal)
                        <div class="small mt-1">{{ \Illuminate\Support\Str::limit($sp->goal, 80) }}</div>
                    @endif
                    <form method="post" action="{{ url()->current() }}/startSprint" class="mt-2">
                        @csrf
                        <input type="hidden" name="sprint_id" value="{{ $sp->id }}">
                        <button type="submit" class="btn btn-sm btn-primary">Запустить</button>
                    </form>
                </div>
            @empty
                <div class="yt-empty-sm">Нет запланированных</div>
            @endforelse

            @if($closed_sprints->isNotEmpty())
                <h4 class="mt-4">Закрытые</h4>
                @foreach($closed_sprints->take(8) as $sp)
                    <div class="yt-sprint-card yt-sprint-card--closed">
                        <div class="yt-sprint-card__name">{{ $sp->name }}</div>
                        <div class="yt-muted small">{{ $sp->tasks_count }} задач</div>
                    </div>
                @endforeach
            @endif
        </aside>
    </div>
</div>

<script>
(function () {
    const root = document.getElementById('yt-sprints');
    if (!root) return;
    const url = root.dataset.assignUrl;
    const csrf = root.dataset.csrf;
    let dragEl = null;

    root.querySelectorAll('.yt-sprint-task').forEach(el => {
        el.addEventListener('dragstart', (e) => {
            dragEl = el;
            el.classList.add('is-dragging');
            e.dataTransfer.setData('text/plain', el.dataset.taskId);
        });
        el.addEventListener('dragend', () => {
            el.classList.remove('is-dragging');
            dragEl = null;
            root.querySelectorAll('[data-dropzone]').forEach(z => z.classList.remove('is-over'));
        });
    });

    root.querySelectorAll('[data-dropzone]').forEach(zone => {
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('is-over');
        });
        zone.addEventListener('dragleave', () => zone.classList.remove('is-over'));
        zone.addEventListener('drop', async (e) => {
            e.preventDefault();
            zone.classList.remove('is-over');
            if (!dragEl) return;
            const sprintId = zone.dataset.sprintId || null;
            if (zone.dataset.dropzone === 'active' && !sprintId) {
                alert('Сначала запустите спринт');
                return;
            }
            zone.appendChild(dragEl);
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        task_id: Number(dragEl.dataset.taskId),
                        sprint_id: sprintId ? Number(sprintId) : null,
                    }),
                });
                if (!res.ok) {
                    alert('Не удалось переместить задачу');
                    location.reload();
                }
            } catch (err) {
                location.reload();
            }
        });
    });
})();
</script>
