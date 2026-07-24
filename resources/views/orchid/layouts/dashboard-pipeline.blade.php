@php
    $pipeline = $pipeline ?? [];
    $max = max(1, collect($pipeline)->max('count') ?: 1);
@endphp

<div class="bg-white rounded shadow-sm p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="h5 mb-0 text-body-emphasis">Пайплайн задач</h3>
            <div class="small text-muted">Где сейчас находятся задачи по этапам</div>
        </div>
    </div>

    <div class="row g-2">
        @foreach($pipeline as $step)
            @php $pct = (int) round(($step['count'] / $max) * 100); @endphp
            <div class="col">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted text-truncate" title="{{ $step['label'] }}">{{ $step['label'] }}</div>
                    <div class="fs-4 fw-semibold my-1">{{ $step['count'] }}</div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
