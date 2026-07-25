@php
    $header = $header ?? [];
    $tasks = $tasks ?? [];
    $projects = $projects ?? collect();
    $project = $project ?? null;
    $actExists = (bool) ($act_exists ?? false);
    $selectedCount = (int) ($selected_count ?? 0);
    $selectedHours = (float) ($selected_hours ?? 0);
@endphp

<div class="act-composer" id="act-composer">
    <div class="act-composer__header card">
        <div class="act-composer__header-top">
            <div>
                <h3 class="act-composer__title">{{ $actExists ? 'Редактирование акта' : 'Составление акта' }}</h3>
                <p class="act-composer__hint text-muted mb-0">
                    Отметьте задачи, поправьте часы при необходимости. Итог пересчитывается сразу.
                </p>
            </div>
            <div class="act-composer__totals" id="act-totals">
                <div class="act-composer__total-item">
                    <span class="act-composer__total-label">Задач</span>
                    <strong id="act-total-tasks">{{ $selectedCount }}</strong>
                </div>
                <div class="act-composer__total-item">
                    <span class="act-composer__total-label">Часов</span>
                    <strong id="act-total-hours">{{ number_format($selectedHours, 2, ',', ' ') }}</strong>
                </div>
            </div>
        </div>

        <div class="act-composer__fields">
            <label class="act-field">
                <span>Проект</span>
                @php
                    $projectSwitchUrl = $actExists
                        ? route('platform.systems.acts.edit', $act)
                        : route('platform.systems.acts.create');
                @endphp
                <select name="project_id"
                        id="act-project"
                        class="form-select"
                        form="post-form"
                        required
                        data-base-url="{{ $projectSwitchUrl }}"
                        onchange="(function(el){var u=el.getAttribute('data-base-url'); window.location=el.value?(u+(u.indexOf('?')>=0?'&':'?')+'project_id='+el.value):u;})(this)">
                    <option value="">Выберите проект…</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" @selected((int)($header['project_id'] ?? 0) === (int)$p->id)>
                            {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="act-field">
                <span>Номер</span>
                <input type="text"
                       name="act[number]"
                       class="form-control"
                       form="post-form"
                       value="{{ $header['number'] ?? '' }}"
                       required>
            </label>

            <label class="act-field">
                <span>Дата</span>
                <input type="date"
                       name="act[date]"
                       class="form-control"
                       form="post-form"
                       value="{{ $header['date'] ?? '' }}"
                       required>
            </label>

            <label class="act-field">
                <span>Заказчик (полное наименование)</span>
                <input type="text"
                       name="act[customer]"
                       class="form-control"
                       form="post-form"
                       value="{{ $header['customer'] ?? '' }}"
                       required
                       placeholder="ООО «Название» / ИП ФИО">
            </label>

            <label class="act-field">
                <span>Исполнитель (полное наименование / ФИО)</span>
                <input type="text"
                       name="act[executor]"
                       class="form-control"
                       form="post-form"
                       value="{{ $header['executor'] ?? '' }}"
                       required
                       placeholder="ФИО / ИП / самозанятый">
            </label>

            <label class="act-field act-field--wide">
                <span>Основание (договор) или примечание</span>
                <input type="text"
                       name="act[info]"
                       class="form-control"
                       form="post-form"
                       value="{{ $header['info'] ?? '' }}"
                       placeholder="Напр.: договора № 12 от 01.02.2026">
            </label>
        </div>
    </div>

    @if(!$project)
        <div class="act-composer__empty card">
            <strong>Выберите проект</strong>
            <p class="text-muted mb-0">После выбора появится список задач для включения в акт.</p>
        </div>
    @else
        <div class="act-composer__toolbar card">
            <div class="act-composer__toolbar-left">
                <input type="search"
                       id="act-task-search"
                       class="form-control"
                       placeholder="Поиск по названию или №…"
                       autocomplete="off">
                <label class="act-composer__check">
                    <input type="checkbox" id="act-hide-dupes">
                    <span>Скрыть уже в других актах</span>
                </label>
            </div>
            <div class="act-composer__toolbar-right">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="act-use-spent">Часы = факт</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="act-use-estimate">Часы = оценка</button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="act-select-all">Выбрать все</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="act-clear-all">Снять все</button>
            </div>
        </div>

        <div class="act-composer__table-wrap card">
            <table class="act-composer__table" id="act-tasks-table">
                <thead>
                <tr>
                    <th class="act-col-check"></th>
                    <th>Задача</th>
                    <th>Статус</th>
                    <th>Исполнитель</th>
                    <th class="text-end">Факт</th>
                    <th class="text-end">Оценка</th>
                    <th class="text-end">В акт, ч</th>
                </tr>
                </thead>
                <tbody>
                @forelse($tasks as $task)
                    @php
                        $tid = (int) $task['id'];
                        $checked = !empty($task['selected']);
                    @endphp
                    <tr class="act-row {{ $checked ? 'is-selected' : '' }} {{ !empty($task['has_duplicates']) ? 'has-dupe' : '' }}"
                        data-title="{{ mb_strtolower($task['title'] . ' #' . $tid) }}"
                        data-dupe="{{ !empty($task['has_duplicates']) ? '1' : '0' }}">
                        <td class="act-col-check">
                            <input type="checkbox"
                                   class="form-check-input act-row-check"
                                   name="lines[{{ $tid }}][selected]"
                                   form="post-form"
                                   value="1"
                                   @checked($checked)>
                        </td>
                        <td>
                            <div class="act-task-title">
                                <span class="act-task-id">#{{ $tid }}</span>
                                {{ $task['title'] }}
                            </div>
                            @if(!empty($task['used_in_acts']))
                                <div class="act-task-dupe">
                                    Уже в:
                                    @foreach($task['used_in_acts'] as $a)
                                        <a href="{{ route('platform.systems.acts.edit', $a['id']) }}">{{ $a['number'] }}</a>@if(!$loop->last), @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="act-status">{{ $task['status_label'] }}</span>
                        </td>
                        <td>{{ $task['executor'] }}</td>
                        <td class="text-end act-num" data-spent="{{ $task['hours_spent'] }}">
                            {{ number_format($task['hours_spent'], 2, ',', ' ') }}
                        </td>
                        <td class="text-end act-num" data-estimate="{{ $task['estimation_hours'] }}">
                            {{ number_format($task['estimation_hours'], 2, ',', ' ') }}
                        </td>
                        <td class="text-end">
                            <input type="number"
                                   class="form-control form-control-sm act-hours-input"
                                   name="lines[{{ $tid }}][hours]"
                                   form="post-form"
                                   min="0"
                                   step="0.01"
                                   inputmode="decimal"
                                   value="{{ number_format((float)$task['hours'], 2, '.', '') }}"
                                   data-spent="{{ number_format((float)$task['hours_spent'], 2, '.', '') }}"
                                   data-estimate="{{ number_format((float)$task['estimation_hours'], 2, '.', '') }}">
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">В проекте нет задач</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>

<script>
(() => {
    const root = document.getElementById('act-composer');
    if (!root) return;

    const totalTasks = document.getElementById('act-total-tasks');
    const totalHours = document.getElementById('act-total-hours');
    const search = document.getElementById('act-task-search');
    const hideDupes = document.getElementById('act-hide-dupes');

    const fmt = (n) => {
        const v = Math.round((Number(n) || 0) * 100) / 100;
        return v.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const rows = () => [...root.querySelectorAll('.act-row')];

    const recalc = () => {
        let count = 0;
        let hours = 0;
        rows().forEach((row) => {
            const check = row.querySelector('.act-row-check');
            const input = row.querySelector('.act-hours-input');
            const on = !!check?.checked;
            row.classList.toggle('is-selected', on);
            if (on) {
                count += 1;
                hours += parseFloat(input?.value || '0') || 0;
            }
        });
        if (totalTasks) totalTasks.textContent = String(count);
        if (totalHours) totalHours.textContent = fmt(hours);
    };

    const filterRows = () => {
        const q = (search?.value || '').trim().toLowerCase();
        const hide = !!hideDupes?.checked;
        rows().forEach((row) => {
            const title = row.getAttribute('data-title') || '';
            const dupe = row.getAttribute('data-dupe') === '1';
            let show = !q || title.includes(q);
            if (hide && dupe) show = false;
            row.style.display = show ? '' : 'none';
        });
    };

    const normalizeHours = (input) => {
        if (!input) return;
        let raw = String(input.value || '').replace(',', '.').trim();
        let v = parseFloat(raw);
        if (!isFinite(v) || v < 0) v = 0;
        input.value = (Math.round(v * 100) / 100).toFixed(2);
    };

    root.addEventListener('change', (e) => {
        if (e.target.matches('.act-hours-input')) normalizeHours(e.target);
        if (e.target.matches('.act-row-check, .act-hours-input')) recalc();
    });
    root.addEventListener('input', (e) => {
        if (e.target.matches('.act-hours-input')) recalc();
    });

    search?.addEventListener('input', filterRows);
    hideDupes?.addEventListener('change', filterRows);

    document.getElementById('act-select-all')?.addEventListener('click', () => {
        rows().forEach((row) => {
            if (row.style.display === 'none') return;
            const check = row.querySelector('.act-row-check');
            if (check) check.checked = true;
        });
        recalc();
    });

    document.getElementById('act-clear-all')?.addEventListener('click', () => {
        rows().forEach((row) => {
            const check = row.querySelector('.act-row-check');
            if (check) check.checked = false;
        });
        recalc();
    });

    const applyHours = (attr) => {
        rows().forEach((row) => {
            const input = row.querySelector('.act-hours-input');
            if (!input) return;
            let v = parseFloat(String(input.getAttribute(attr) || '0').replace(',', '.')) || 0;
            if (v < 0) v = 0;
            input.value = (Math.round(v * 100) / 100).toFixed(2);
        });
        recalc();
    };

    document.getElementById('act-use-spent')?.addEventListener('click', () => applyHours('data-spent'));
    document.getElementById('act-use-estimate')?.addEventListener('click', () => applyHours('data-estimate'));

    recalc();
})();
</script>
