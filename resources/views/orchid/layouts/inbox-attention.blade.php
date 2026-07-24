<div class="row">
    <div class="col-md-6 mb-4">
        <h5 class="mb-3">Новые задачи (взять в работу)</h5>
        @forelse($new_tasks ?? [] as $task)
            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div>
                    <a href="{{ route('platform.systems.my_tasks.view', $task) }}">{{ $task->name }}</a>
                    <div class="small text-muted">{{ $task->project?->name }}</div>
                </div>
                <span class="badge text-bg-info">Новая</span>
            </div>
        @empty
            <p class="text-muted mb-0">Нет новых задач</p>
        @endforelse
    </div>
    <div class="col-md-6 mb-4">
        <h5 class="mb-3">Ждут вашей оценки</h5>
        @forelse($awaiting_estimation ?? [] as $task)
            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div>
                    <a href="{{ route('platform.systems.my_tasks.view', $task) }}">{{ $task->name }}</a>
                    <div class="small text-muted">{{ $task->project?->name }}</div>
                </div>
                <span class="badge text-bg-warning">Оценка</span>
            </div>
        @empty
            <p class="text-muted mb-0">Нет задач на оценку</p>
        @endforelse
    </div>
</div>
