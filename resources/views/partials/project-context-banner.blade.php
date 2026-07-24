@if(!empty($activeProject))
    <div class="alert project-context-banner d-flex align-items-center justify-content-between mb-3 py-2" role="status">
        <div>
            <strong>Контекст проекта:</strong>
            <span class="ms-1">{{ $activeProject->name }}</span>
            <span class="text-muted ms-2 small">Новые задачи и акты по умолчанию привязываются к этому проекту</span>
        </div>
        <a href="{{ route('platform.project-context.switch', ['project_id' => '']) }}" class="btn btn-sm btn-outline-secondary">
            Сбросить
        </a>
    </div>
@endif
