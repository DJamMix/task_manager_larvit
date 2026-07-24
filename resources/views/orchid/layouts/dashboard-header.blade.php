@php
    $scope = $scope ?? ['role' => 'user'];
    $project = $activeProject ?? ($scope['project'] ?? null);
    $roleLabel = match ($scope['role'] ?? 'user') {
        'admin' => 'Админ / менеджер',
        'employee' => 'Сотрудник',
        'client' => 'Клиент',
        default => 'Пользователь',
    };
@endphp

<div class="bg-white rounded shadow-sm p-4 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="text-muted small text-uppercase mb-1" style="letter-spacing:.04em;">Рабочий стол</div>
            <h2 class="h4 mb-1 text-body-emphasis">
                Здравствуйте, {{ $user->name ?? 'коллега' }}
            </h2>
            <div class="text-muted">
                Режим: <strong>{{ $roleLabel }}</strong>
                @if($project)
                    · проект <strong>{{ $project->name }}</strong>
                @else
                    · все доступные проекты
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if(($scope['role'] ?? '') === 'admin')
                <span class="badge text-bg-dark">Полная аналитика</span>
            @endif
            <span class="badge text-bg-light border">Обновлено {{ now()->format('d.m.Y H:i') }}</span>
        </div>
    </div>
</div>
