<div class="row mb-3">
    <div class="col-md-8">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">С</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">По</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit">Показать</button>
            </div>
        </form>
    </div>
    <div class="col-md-4 text-md-end">
        <div class="metric d-inline-block text-start p-3 bg-light rounded border">
            <div class="text-muted small">Всего за период</div>
            <div class="fs-4 fw-semibold">{{ number_format($total_hours ?? 0, 2) }} ч</div>
        </div>
    </div>
</div>
