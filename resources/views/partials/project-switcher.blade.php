@php
    $projects = $availableProjects ?? collect();
    $currentId = $activeProjectId ?? null;
    $current = $activeProject ?? null;
    $switchUrl = route('platform.project-context.switch');
@endphp

@if($projects->isNotEmpty())
    <div class="project-switcher" data-project-switcher>
        {{-- Развёрнутый вид --}}
        <div class="project-switcher__full px-3 pt-3 pb-2">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <label class="form-label text-white-50 small mb-0 text-uppercase project-switcher__label">
                    Активный проект
                </label>
                @if($currentId)
                    <a href="{{ $switchUrl }}?project_id="
                       class="small link-secondary text-decoration-none"
                       title="Сбросить фильтр проекта">
                        сбросить
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ $switchUrl }}" class="m-0">
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

        {{-- Свёрнутый вид: кнопка → модалка --}}
        <div class="project-switcher__compact">
            <button type="button"
                    class="project-switcher__btn"
                    data-bs-toggle="modal"
                    data-bs-target="#crewdev-project-modal"
                    title="{{ $current?->name ? 'Проект: '.$current->name : 'Выбрать проект' }}"
                    aria-label="Выбрать активный проект">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                @if($currentId)
                    <span class="project-switcher__dot" aria-hidden="true"></span>
                @endif
            </button>
        </div>
    </div>

    <div class="modal fade" id="crewdev-project-modal" tabindex="-1" aria-labelledby="crewdev-project-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="crewdev-project-modal-title">Активный проект</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="{{ $switchUrl }}?project_id="
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ !$currentId ? 'active' : '' }}">
                            Все проекты
                            @unless($currentId)
                                <span class="badge text-bg-light text-dark">текущий</span>
                            @endunless
                        </a>
                        @foreach($projects as $project)
                            <a href="{{ $switchUrl }}?project_id={{ $project->id }}"
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ (int)$currentId === (int)$project->id ? 'active' : '' }}">
                                <span class="text-truncate">{{ $project->name }}</span>
                                @if((int)$currentId === (int)$project->id)
                                    <span class="badge text-bg-light text-dark">текущий</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var modal = document.getElementById('crewdev-project-modal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    })();
    </script>
@endif
