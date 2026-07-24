@php
    $health = $project_health ?? collect();
    $bottlenecks = $bottlenecks ?? [];
    $accuracy = $estimate_accuracy ?? ['sample' => 0, 'avg_ratio' => 0, 'under' => 0, 'over' => 0, 'on_track' => 0];
    $executors = $top_executors ?? collect();
    $acts = $acts_summary ?? ['visible' => false];
@endphp

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="bg-white rounded shadow-sm p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h3 class="h5 mb-0">Здоровье проектов</h3>
                    <div class="small text-muted">Прогресс, просрочки и часы</div>
                </div>
            </div>

            @if($health->isEmpty())
                <div class="text-muted">Нет проектов для отображения</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Проект</th>
                            <th class="text-center">Прогресс</th>
                            <th class="text-center">Активные</th>
                            <th class="text-center">Просрочено</th>
                            <th class="text-end">Факт / оценка</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($health as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['name'] }}</td>
                                <td style="min-width:140px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:8px;">
                                            <div class="progress-bar {{ $row['percent'] >= 70 ? 'bg-success' : ($row['percent'] >= 40 ? 'bg-warning' : 'bg-secondary') }}"
                                                 style="width: {{ $row['percent'] }}%"></div>
                                        </div>
                                        <span class="small text-muted" style="width:36px;">{{ $row['percent'] }}%</span>
                                    </div>
                                </td>
                                <td class="text-center">{{ $row['active'] }} / {{ $row['total'] }}</td>
                                <td class="text-center">
                                    @if($row['overdue'] > 0)
                                        <span class="badge text-bg-danger">{{ $row['overdue'] }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($row['spent'], 1) }} / {{ $row['estimated'] > 0 ? number_format($row['estimated'], 1) : '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="bg-white rounded shadow-sm p-4 mb-3">
            <h3 class="h5 mb-1">Точность оценок</h3>
            <div class="small text-muted mb-3">Сравнение факта и плана (задачи с обеими цифрами)</div>

            @if(($accuracy['sample'] ?? 0) === 0)
                <div class="text-muted">Пока мало данных для сравнения</div>
            @else
                <div class="display-6 fw-semibold mb-2">{{ $accuracy['avg_ratio'] }}%</div>
                <div class="small text-muted mb-3">средний факт к оценке · выборка {{ $accuracy['sample'] }}</div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge text-bg-success">В плане: {{ $accuracy['on_track'] }}</span>
                    <span class="badge text-bg-info">Меньше плана: {{ $accuracy['under'] }}</span>
                    <span class="badge text-bg-danger">Больше плана: {{ $accuracy['over'] }}</span>
                </div>
            @endif
        </div>

        @if(!empty($acts['visible']))
            <div class="bg-white rounded shadow-sm p-4">
                <h3 class="h5 mb-1">Акты</h3>
                <div class="small text-muted mb-3">Документы выполненных работ</div>
                <div class="row g-2">
                    <div class="col-4">
                        <div class="border rounded p-2 text-center">
                            <div class="fw-semibold fs-5">{{ $acts['total'] }}</div>
                            <div class="small text-muted">всего</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-2 text-center">
                            <div class="fw-semibold fs-5">{{ $acts['month_count'] }}</div>
                            <div class="small text-muted">за месяц</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-2 text-center">
                            <div class="fw-semibold fs-5">{{ $acts['month_hours'] }}</div>
                            <div class="small text-muted">часов</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="bg-white rounded shadow-sm p-4 h-100">
            <h3 class="h5 mb-1">Узкие места</h3>
            <div class="small text-muted mb-3">Статусы, где задачи «зависают»</div>

            @if(empty($bottlenecks))
                <div class="text-muted">Узких мест не видно — хороший знак</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Статус</th>
                            <th class="text-center">Задач</th>
                            <th class="text-end">Средний возраст</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($bottlenecks as $item)
                            <tr>
                                <td>{{ $item['label'] }}</td>
                                <td class="text-center"><span class="badge text-bg-secondary">{{ $item['count'] }}</span></td>
                                <td class="text-end">{{ $item['avg_days'] }} дн.</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-6">
        <div class="bg-white rounded shadow-sm p-4 h-100">
            <h3 class="h5 mb-1">Топ исполнителей по часам</h3>
            <div class="small text-muted mb-3">За последние 30 дней</div>

            @if(collect($executors)->isEmpty())
                <div class="text-muted">Нет данных трекинга за период</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Сотрудник</th>
                            <th class="text-center">Задач</th>
                            <th class="text-end">Часы</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($executors as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['name'] }}</td>
                                <td class="text-center">{{ $row['tasks'] }}</td>
                                <td class="text-end">{{ number_format($row['hours'], 1) }} ч</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
