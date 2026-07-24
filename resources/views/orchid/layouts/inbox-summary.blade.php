@php
    $stats = $inbox_stats ?? ['new' => 0, 'estimation' => 0, 'comments' => 0];
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="bg-white rounded shadow-sm h-100 p-3 border-start border-4 border-info">
            <div class="text-muted small text-uppercase mb-1" style="letter-spacing: .04em;">Взять в работу</div>
            <div class="d-flex align-items-end justify-content-between">
                <div class="fs-2 fw-semibold lh-1">{{ $stats['new'] }}</div>
                <span class="badge text-bg-info">Новые</span>
            </div>
            <div class="small text-muted mt-2">Задачи в статусе «Новая»</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bg-white rounded shadow-sm h-100 p-3 border-start border-4 border-warning">
            <div class="text-muted small text-uppercase mb-1" style="letter-spacing: .04em;">Нужна оценка</div>
            <div class="d-flex align-items-end justify-content-between">
                <div class="fs-2 fw-semibold lh-1">{{ $stats['estimation'] }}</div>
                <span class="badge text-bg-warning">Оценка</span>
            </div>
            <div class="small text-muted mt-2">Клиент ждёт вашу оценку часов</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bg-white rounded shadow-sm h-100 p-3 border-start border-4 border-secondary">
            <div class="text-muted small text-uppercase mb-1" style="letter-spacing: .04em;">Комментарии</div>
            <div class="d-flex align-items-end justify-content-between">
                <div class="fs-2 fw-semibold lh-1">{{ $stats['comments'] }}</div>
                <span class="badge text-bg-secondary">Новые</span>
            </div>
            <div class="small text-muted mt-2">Сообщения по вашим задачам</div>
        </div>
    </div>
</div>
