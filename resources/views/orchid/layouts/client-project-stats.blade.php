@php $stats = $project_stats ?? []; @endphp
<div class="row mb-4">
    <div class="col-md-3 mb-2">
        <div class="p-3 border rounded bg-white h-100">
            <div class="text-muted small">Всего задач</div>
            <div class="fs-4 fw-semibold">{{ $stats['total'] ?? 0 }}</div>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="p-3 border rounded bg-white h-100">
            <div class="text-muted small">В работе</div>
            <div class="fs-4 fw-semibold">{{ $stats['active'] ?? 0 }}</div>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="p-3 border rounded bg-white h-100">
            <div class="text-muted small">Готово</div>
            <div class="fs-4 fw-semibold">{{ $stats['done'] ?? 0 }}</div>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="p-3 border rounded bg-white h-100">
            <div class="text-muted small">Прогресс</div>
            <div class="fs-4 fw-semibold">{{ $stats['percent'] ?? 0 }}%</div>
            <div class="progress mt-2" style="height: 6px;">
                <div class="progress-bar" style="width: {{ $stats['percent'] ?? 0 }}%"></div>
            </div>
        </div>
    </div>
</div>
