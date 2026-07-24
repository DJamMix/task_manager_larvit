@php
    $projects = $availableProjects ?? collect();
    $currentId = $activeProjectId ?? null;
    $current = $activeProject ?? null;
@endphp

@if($projects->isNotEmpty())
    <div class="project-switcher px-3 pt-3 pb-2">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <label class="form-label text-white-50 small mb-0 text-uppercase" style="letter-spacing: .04em; font-size: .7rem;">
                Активный проект
            </label>
            @if($currentId)
                <a href="{{ route('platform.project-context.switch', ['project_id' => '']) }}"
                   class="small link-secondary text-decoration-none"
                   title="Сбросить фильтр проекта">
                    сбросить
                </a>
            @endif
        </div>

        <form method="GET" action="{{ route('platform.project-context.switch') }}" class="m-0">
            <select name="project_id"
                    class="form-select form-select-sm project-switcher__select"
                    onchange="this.form.submit()"
                    aria-label="Выбор активного проекта">
                <option value="">Все проекты</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected((int) $currentId === (int) $project->id)>
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </form>

        @if($current)
            <div class="project-switcher__badge mt-2">
                <span class="badge rounded-pill text-bg-primary w-100 text-truncate d-inline-block"
                      style="max-width: 100%;"
                      title="{{ $current->name }}">
                    {{ $current->name }}
                </span>
            </div>
        @endif
    </div>
@endif
