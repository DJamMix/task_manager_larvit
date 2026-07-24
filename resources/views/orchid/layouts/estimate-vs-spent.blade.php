@php
    $estimate = (float) ($task->estimation_hours ?? 0);
    $spent = (float) ($task->hours_spent ?? 0);
    $ratio = $estimate > 0 ? $spent / $estimate : null;
    $overspent = $ratio !== null && $ratio > 1;
@endphp

<div class="estimate-vs-spent mb-4">
    <div class="metric">
        <div class="text-muted small">Оценка (план)</div>
        <div class="value">{{ $estimate > 0 ? number_format($estimate, 2) . ' ч' : 'ещё нет' }}</div>
        <div class="small text-muted mt-1">Согласовывается отдельно, трекинг на неё не влияет</div>
    </div>
    <div class="metric {{ $overspent ? 'overspent' : '' }}">
        <div class="text-muted small">Факт (учтено)</div>
        <div class="value">{{ number_format($spent, 2) }} ч</div>
        <div class="small text-muted mt-1">
            @if($ratio === null)
                Можно трекать сразу после назначения
            @elseif($overspent)
                Факт выше оценки на {{ number_format(($ratio - 1) * 100, 0) }}%
            @else
                Использовано {{ number_format($ratio * 100, 0) }}% от оценки
            @endif
        </div>
    </div>
</div>
