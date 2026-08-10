<div class="task-workspace task-workspace--tracker">
    @php
        $statusEnum = \App\CoreLayer\Enums\TaskStatusEnum::tryFrom($task->status);
        $priorityEnum = \App\CoreLayer\Enums\TaskPriorityEnum::tryFrom((string) $task->priority);
        $canDiscuss = $can_discuss ?? true;
        $isObserverOnly = $is_observer_only ?? false;
        $discussion = $discussion_comments ?? collect();
        $history = $history_comments ?? $discussion->where('is_system', true)->values();
        $pipeline = $status_pipeline ?? [];
        $statusActions = $status_actions ?? [];
        $statusTransitions = $status_transitions ?? [];
        $canChangeStatus = $can_change_status ?? false;
        $statusHint = $status_hint ?? null;
        $relatedLinks = $related_links ?? collect();
        $linkOptions = $link_task_options ?? [];
        $relationLabels = \App\Models\TaskLink::relationLabels();
        $canManageLinks = $can_manage_links ?? false;
        $viewRoute = $task_view_route ?? route('platform.systems.my_tasks.view', $task);
        $statusLabel = $task_status_label ?? $task->statusLabel();
        $statusColor = $task->statusColor();
    @endphp

    <header class="yt-issue-head">
        <div class="yt-issue-head__top">
            <span class="yt-issue-key">{{ $task->displayKey() }}</span>
            @if($task->queue)
                <span class="yt-issue-queue">{{ $task->queue->key }}</span>
            @endif
            @if($task->sprint)
                <span class="yt-issue-sprint">{{ $task->sprint->name }}</span>
            @endif
        </div>
        <div class="yt-issue-head__row">
            <h1 class="yt-issue-title">{{ $task->name }}</h1>
            <div class="yt-status-dd" data-yt-status>
                <button type="button"
                        class="yt-status-pill {{ $canChangeStatus ? 'is-interactive' : '' }}"
                        style="--st:{{ $statusColor }}"
                        @if($canChangeStatus) data-yt-status-toggle @endif
                        @if(!$canChangeStatus) disabled @endif>
                    <span class="yt-status-pill__dot"></span>
                    {{ $statusLabel }}
                    @if($canChangeStatus)<span class="yt-status-pill__caret">▾</span>@endif
                </button>
                @if($canChangeStatus && !empty($statusTransitions))
                    <div class="yt-status-menu" hidden data-yt-status-menu>
                        <div class="yt-status-menu__label">Перевести в</div>
                        @foreach($statusTransitions as $tr)
                            <form method="post" action="{{ url()->current() }}/changeStatus">
                                @csrf
                                <input type="hidden" name="status" value="{{ $tr['slug'] }}">
                                <button type="submit" class="yt-status-menu__item">
                                    <span class="yt-status-menu__dot" style="background:{{ $tr['color'] }}"></span>
                                    {{ $tr['name'] }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </header>

    <div class="task-workspace__grid">
        <section class="task-workspace__main">
            <div class="tw-card tw-description-card">
                <div class="fw-semibold mb-2">Описание</div>
                <div class="tw-description">
                    {!! $task->description ?: '<span class="text-muted">Описание не заполнено</span>' !!}
                </div>
            </div>
        </section>

        <aside class="task-workspace__sidebar">
            <div class="tw-card">
                <div class="tw-meta">
                    <div class="tw-meta__row">
                        <span>Статус</span>
                        <strong>
                            <span class="yt-inline-status" style="--st:{{ $statusColor }}">{{ $statusLabel }}</span>
                        </strong>
                    </div>
                    <div class="tw-meta__row">
                        <span>Очередь</span>
                        <strong>{{ $task->queue?->key ?? '—' }}</strong>
                    </div>
                    <div class="tw-meta__row">
                        <span>Проект</span>
                        <strong>{{ $task->project?->name ?? '—' }}</strong>
                    </div>
                    <div class="tw-meta__row">
                        <span>Исполнитель</span>
                        <strong>{{ $task->executor?->displayName() ?? 'Не назначен' }}</strong>
                    </div>
                    <div class="tw-meta__row">
                        <span>Автор</span>
                        <strong>{{ $task->creator?->displayName() ?? '—' }}</strong>
                    </div>
                    <div class="tw-meta__row">
                        <span>Приоритет</span>
                        <strong>{!! $priorityEnum?->badgeHtml() ?? '—' !!}</strong>
                    </div>
                    <div class="tw-meta__row">
                        <span>Спринт</span>
                        <strong>{{ $task->sprint?->name ?? 'Бэклог' }}</strong>
                    </div>
                    <div class="tw-meta__row">
                        <span>Оценка / факт</span>
                        <strong>
                            {{ $task->estimation_hours > 0 ? number_format((float)$task->estimation_hours, 1).' ч' : 'нет' }}
                            /
                            {{ number_format((float)$task->hours_spent, 1) }} ч
                        </strong>
                    </div>
                    @if($task->end_datetime)
                        <div class="tw-meta__row">
                            <span>Дедлайн</span>
                            <strong class="{{ $task->isOverdue() ? 'text-danger' : '' }}">
                                {{ $task->end_datetime->format('d.m.Y H:i') }}
                                @if($task->isOverdue()) · просрочено @endif
                            </strong>
                        </div>
                    @endif
                </div>

                @if(($task->observers() ?? collect())->isNotEmpty())
                    <div class="mt-3">
                        <div class="small text-muted mb-1">Наблюдатели</div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($task->observers() as $observer)
                                <span class="badge text-bg-light border">{{ $observer->displayName() }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($isObserverOnly)
                    <div class="alert alert-info mt-3 mb-0 py-2 small">
                        Наблюдатель: только обсуждение
                    </div>
                @endif
            </div>

            @if(!empty($pipeline) || !empty($statusActions) || $statusHint)
                <div class="tw-card mt-3 tw-status">
                    <div class="fw-semibold mb-2">Workflow</div>

                    @if(!empty($pipeline))
                        <div class="tw-pipeline" aria-label="Этапы задачи">
                            @foreach($pipeline as $step)
                                @php
                                    $stepEnum = \App\CoreLayer\Enums\TaskStatusEnum::tryFrom($step['value']);
                                    $state = $step['state'] ?? 'upcoming';
                                @endphp
                                <div class="tw-pipeline__step tw-pipeline__step--{{ $state }}" title="{{ $stepEnum?->label() }}">
                                    <span class="tw-pipeline__dot" style="--dot:{{ $stepEnum?->color() ?? '#94a3b8' }}"></span>
                                    <span class="tw-pipeline__label">{{ $step['short'] ?? $stepEnum?->label() }}</span>
                                </div>
                                @if(!$loop->last)
                                    <div class="tw-pipeline__line {{ $state === 'done' || $state === 'current' ? 'is-done' : '' }}"></div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    @if($statusHint)
                        <div class="small text-muted mt-2">{{ $statusHint }}</div>
                    @endif

                    @if(!empty($statusActions))
                        <div class="tw-status__actions mt-3">
                            @foreach($statusActions as $action)
                                {!! $action !!}
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <div class="tw-card mt-3 tw-related-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="fw-semibold">Связанные задачи</div>
                        <div class="small text-muted">Необязательно</div>
                    </div>
                    <span class="badge text-bg-light border">{{ $relatedLinks->count() }}</span>
                </div>
                <div class="tw-related-list">
                    @forelse($relatedLinks as $link)
                        @php
                            $related = $link->relatedTask;
                            $relStatus = $related ? \App\CoreLayer\Enums\TaskStatusEnum::tryFrom($related->status) : null;
                        @endphp
                        @if($related)
                            <div class="tw-related-item">
                                <div class="tw-related-item__main">
                                    <div class="tw-related-item__top">
                                        <span class="tw-related-item__key">{{ $related->displayKey() }}</span>
                                        <span class="tw-related-item__rel">{{ $link->label() }}</span>
                                    </div>
                                    <a href="{{ route('platform.systems.my_tasks.view', $related) }}" class="tw-related-item__name">
                                        {{ \Illuminate\Support\Str::limit($related->name, 48) }}
                                    </a>
                                    @if($relStatus)
                                        <span class="tw-related-item__status" style="--st:{{ $relStatus->color() }}">{{ $relStatus->label() }}</span>
                                    @endif
                                </div>
                                @if($canManageLinks)
                                    <button type="button"
                                            class="tw-related-item__rm"
                                            data-remove-url="{{ url()->current() }}/removeLink"
                                            data-link-id="{{ $link->id }}"
                                            data-csrf="{{ csrf_token() }}"
                                            title="Убрать связь">×</button>
                                @endif
                            </div>
                        @endif
                    @empty
                        <div class="tw-related-empty">Связей пока нет</div>
                    @endforelse
                </div>

                @if($canManageLinks)
                    <button type="button"
                            class="btn btn-sm btn-outline-primary w-100 mt-2"
                            id="tw-open-link-modal"
                            data-add-url="{{ url()->current() }}/addLink"
                            data-search-url="{{ route('platform.systems.tasks.link-search') }}"
                            data-csrf="{{ csrf_token() }}"
                            data-task-id="{{ $task->id }}"
                            data-project-id="{{ $task->project_id }}">
                        + Добавить связь
                    </button>
                @endif
            </div>

            @if(!empty($show_time_link) && !empty($time_route))
                <div class="tw-card mt-3">
                    <div class="fw-semibold mb-1">Учёт времени</div>
                    <a href="{{ $time_route }}" class="btn btn-sm btn-outline-primary w-100">Журнал времени</a>
                </div>
            @endif
        </aside>
    </div>

    <div class="tw-card tw-bottom mt-3">
        <div class="tw-tabs" role="tablist">
            <button type="button" class="tw-tabs__btn is-active" data-tw-tab="discussion">Обсуждение <span>{{ $discussion->where('is_system', false)->count() }}</span></button>
            <button type="button" class="tw-tabs__btn" data-tw-tab="files">Вложения <span>{{ $task->attachment->count() }}</span></button>
            <button type="button" class="tw-tabs__btn" data-tw-tab="history">История <span>{{ $history->count() }}</span></button>
            <button type="button" class="tw-tabs__btn" data-tw-tab="commits">Коммиты</button>
        </div>

        <div class="tw-tab-panel is-active" data-tw-panel="discussion">
            <div class="tw-discussion tw-discussion--docked">
                <div class="tw-feed" id="task-discussion-feed">
                    @forelse($discussion->where('is_system', false) as $comment)
                        @php
                            $isMine = (int)($comment->user_id) === (int)auth()->id();
                            $parent = $comment->parent;
                        @endphp
                        <article class="tw-msg {{ $isMine ? 'tw-msg--mine' : '' }}"
                                 id="comment-{{ $comment->id }}"
                                 data-comment-id="{{ $comment->id }}"
                                 data-author="{{ $comment->user?->displayName() ?? 'Участник' }}">
                            @if($parent)
                                <div class="tw-msg__reply-to">
                                    Ответ на {{ $parent->user?->displayName() ?? 'сообщение' }}:
                                    <span>{{ \Illuminate\Support\Str::limit(strip_tags($parent->plain_text ?? ''), 80) }}</span>
                                </div>
                            @endif

                            <div class="tw-msg__head">
                                <strong>{{ $comment->user?->displayName() ?? 'Система' }}</strong>
                                <span class="text-muted">{{ $comment->created_at?->format('d.m.Y H:i') }}</span>
                            </div>

                            <div class="tw-msg__body">
                                {!! $comment->formatted_text !!}
                            </div>

                            @if($comment->attachment->isNotEmpty())
                                <div class="tw-msg__files">
                                    @foreach($comment->attachment as $file)
                                        @php
                                            $fileMime = strtolower((string) ($file->mime ?? ''));
                                            $fileExt = strtolower((string) ($file->extension ?? pathinfo((string) $file->original_name, PATHINFO_EXTENSION)));
                                            $isImage = str_starts_with($fileMime, 'image/')
                                                || in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
                                            $fileUrl = route('platform.task.attachment.download', ['attachment' => $file, 'inline' => 1]);
                                            $downloadUrl = route('platform.task.attachment.download', $file);
                                        @endphp
                                        @if($isImage)
                                            <a href="{{ $fileUrl }}"
                                               class="tw-msg__image"
                                               data-bx-lightbox="{{ $fileUrl }}"
                                               title="{{ $file->original_name }}">
                                                <img src="{{ $fileUrl }}"
                                                     alt="{{ $file->original_name }}"
                                                     loading="lazy"
                                                     decoding="async">
                                            </a>
                                        @else
                                            <a href="{{ $downloadUrl }}" class="tw-msg__file-badge">
                                                <span class="tw-msg__file-ext">{{ $fileExt ?: 'file' }}</span>
                                                <span class="tw-msg__file-name">{{ $file->original_name }}</span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            @if($canDiscuss)
                                <div class="tw-msg__actions">
                                    <button type="button"
                                            class="btn btn-sm btn-link px-0 tw-reply-btn"
                                            data-parent-id="{{ $comment->id }}"
                                            data-author="{{ $comment->user?->displayName() ?? 'участник' }}">
                                        Ответить
                                    </button>
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="text-muted text-center py-4">Пока нет сообщений</div>
                    @endforelse
                </div>

                @if($canDiscuss)
                    <div class="tw-composer" id="tw-composer">
                        <input type="hidden" id="comment-parent-id" value="">
                        <div id="tw-reply-banner" class="tw-composer__reply d-none">
                            <div>Ответ для <strong id="tw-reply-author"></strong></div>
                            <button type="button" class="btn btn-sm btn-link" id="tw-reply-cancel">Отмена</button>
                        </div>
                        <div class="tw-composer__editor-wrap" id="tw-composer-editor-wrap">
                            <div id="tw-quill-toolbar">
                                <span class="ql-formats">
                                    <button type="button" class="ql-bold"></button>
                                    <button type="button" class="ql-italic"></button>
                                    <button type="button" class="ql-underline"></button>
                                    <button type="button" class="ql-strike"></button>
                                </span>
                                <span class="ql-formats">
                                    <button type="button" class="ql-list" value="ordered"></button>
                                    <button type="button" class="ql-list" value="bullet"></button>
                                    <button type="button" class="ql-code-block"></button>
                                    <button type="button" class="ql-link"></button>
                                </span>
                            </div>
                            <div id="tw-quill-editor" class="tw-quill-host"></div>
                            <div class="tw-composer__resize" id="tw-composer-resize" title="Потяните, чтобы изменить высоту"></div>
                        </div>
                        <div class="tw-composer__preview d-none" id="tw-files-preview"></div>
                        <div class="tw-composer__bar">
                            <label class="tw-composer__clip" title="Прикрепить файл (до 256 МБ)" for="tw-composer-files">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>
                                </svg>
                                <input type="file" id="tw-composer-files" multiple accept="image/*,.pdf,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx,.txt,.exe,.msi,.psd,.fig">
                            </label>
                            <span class="tw-composer__files-label text-muted small d-none" id="tw-files-label"></span>
                            <button type="button"
                                    class="btn btn-primary btn-sm tw-composer__send"
                                    id="tw-composer-send"
                                    data-url="{{ url()->current() }}/addComment"
                                    data-csrf="{{ csrf_token() }}">
                                Отправить
                            </button>
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning mb-0 mt-2">Нет прав писать в обсуждении</div>
                @endif
            </div>
        </div>

        <div class="tw-tab-panel" data-tw-panel="files" hidden>
            <div class="tw-files-grid">
                @forelse($task->attachment as $file)
                    @php
                        $fileMime = strtolower((string) ($file->mime ?? ''));
                        $fileExt = strtolower((string) ($file->extension ?? pathinfo((string) $file->original_name, PATHINFO_EXTENSION)));
                        $isImage = str_starts_with($fileMime, 'image/')
                            || in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
                        $fileUrl = route('platform.task.attachment.download', ['attachment' => $file, 'inline' => 1]);
                        $downloadUrl = route('platform.task.attachment.download', $file);
                        $sizeLabel = method_exists($file, 'size') || isset($file->size)
                            ? number_format(((int) $file->size) / 1048576, 2) . ' МБ'
                            : '';
                    @endphp
                    <div class="tw-file-card">
                        @if($isImage)
                            <a href="{{ $fileUrl }}" class="tw-file-card__preview" data-bx-lightbox="{{ $fileUrl }}" title="{{ $file->original_name }}">
                                <img src="{{ $fileUrl }}" alt="{{ $file->original_name }}" loading="lazy" decoding="async">
                            </a>
                        @else
                            <div class="tw-file-card__icon" title="{{ $fileExt ?: 'file' }}">
                                <span>{{ strtoupper($fileExt ?: 'FILE') }}</span>
                            </div>
                        @endif
                        <div class="tw-file-card__meta">
                            <div class="tw-file-card__name" title="{{ $file->original_name }}">{{ $file->original_name }}</div>
                            @if($sizeLabel)
                                <div class="tw-file-card__size text-muted">{{ $sizeLabel }}</div>
                            @endif
                            <a class="btn btn-sm btn-outline-secondary" href="{{ $downloadUrl }}">Скачать</a>
                        </div>
                    </div>
                @empty
                    <div class="text-muted small py-3">Нет вложений у задачи</div>
                @endforelse
            </div>
        </div>

        <div class="tw-tab-panel" data-tw-panel="history" hidden>
            <div class="tw-feed tw-feed--history">
                <div class="tw-history-head">
                    <div class="fw-semibold">События</div>
                    <div class="small text-muted">История изменений задачи</div>
                </div>
                @forelse($history as $comment)
                    <article class="tw-event">
                        <div class="tw-event__meta">
                            <strong class="tw-event__user">{{ $comment->user?->displayName() ?? 'Система' }}</strong>
                            <time class="tw-event__time">{{ $comment->created_at?->locale('ru')->translatedFormat('j F, H:i') }}</time>
                        </div>
                        <div class="tw-event__body">{!! $comment->formatted_text !!}</div>
                    </article>
                @empty
                    <div class="text-muted text-center py-4">История пока пуста — события появятся при смене статуса, связях и других действиях</div>
                @endforelse
            </div>
        </div>

        <div class="tw-tab-panel" data-tw-panel="commits" hidden>
            <div class="tw-commits-empty">
                <div class="fw-semibold mb-1">Коммиты</div>
                <p class="text-muted small mb-0">
                    Вкладка готова под интеграцию с Git (как в Яндекс Трекере).
                    Пока коммиты сюда не подтягиваются — подключение репозитория можно добавить отдельно.
                </p>
            </div>
        </div>
    </div>
</div>

@if($canManageLinks)
<div class="tw-sheet" id="tw-link-modal" hidden>
    <button type="button" class="tw-sheet__backdrop" id="tw-link-modal-bg" aria-label="Закрыть"></button>
    <div class="tw-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="tw-link-modal-title">
        <div class="tw-sheet__head">
            <strong id="tw-link-modal-title">Добавить связь</strong>
            <button type="button" class="tw-sheet__close" id="tw-link-modal-close" aria-label="Закрыть">×</button>
        </div>
        <div class="tw-link-modal__body">
            <label class="form-label small mb-1">Поиск задачи</label>
            <input type="search"
                   id="tw-link-modal-search"
                   class="form-control"
                   placeholder="Ключ (PHP-12) или название…"
                   autocomplete="off">
            <div id="tw-link-modal-results" class="tw-link-modal__results">Введите запрос или подождите…</div>
            <input type="hidden" id="tw-link-modal-task-id" value="">
            <div id="tw-link-modal-picked" class="tw-link-modal__picked d-none"></div>
            <label class="form-label small mb-1 mt-2">Тип связи</label>
            <select id="tw-link-modal-relation" class="form-select">
                @foreach($relationLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-primary w-100 mt-3" id="tw-link-modal-submit" disabled>Добавить связь</button>
        </div>
    </div>
</div>
@endif

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
(() => {
    if (window.hljs) {
        document.querySelectorAll('.tw-codeblock code').forEach((el) => {
            try { window.hljs.highlightElement(el); } catch (e) {}
        });
    }

    document.querySelectorAll('.tw-tabs__btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const tab = btn.getAttribute('data-tw-tab');
            document.querySelectorAll('.tw-tabs__btn').forEach((b) => b.classList.toggle('is-active', b === btn));
            document.querySelectorAll('.tw-tab-panel').forEach((panel) => {
                const on = panel.getAttribute('data-tw-panel') === tab;
                panel.classList.toggle('is-active', on);
                panel.hidden = !on;
            });
        });
    });

    const parentInput = document.getElementById('comment-parent-id');
    const replyBanner = document.getElementById('tw-reply-banner');
    const replyAuthor = document.getElementById('tw-reply-author');
    const filesInput = document.getElementById('tw-composer-files');
    const filesLabel = document.getElementById('tw-files-label');
    const filesPreview = document.getElementById('tw-files-preview');
    const composerEl = document.getElementById('tw-composer');
    const editorEl = document.getElementById('tw-quill-editor');
    const sendBtn = document.getElementById('tw-composer-send');
    let quill = null;
    let pendingFiles = [];
    const FILES_MAX = 10;
    let sending = false;

    const renderTwFiles = () => {
        if (!filesPreview || !filesLabel) return;
        if (!pendingFiles.length) {
            filesPreview.classList.add('d-none');
            filesPreview.innerHTML = '';
            filesLabel.classList.add('d-none');
            filesLabel.textContent = '';
            return;
        }
        filesLabel.textContent = pendingFiles.length + ' файл(ов)';
        filesLabel.classList.remove('d-none');
        filesPreview.classList.remove('d-none');
        filesPreview.innerHTML = pendingFiles.map((f, idx) => {
            const isImg = /^image\//.test(f.type || '');
            const url = isImg ? URL.createObjectURL(f) : '';
            const name = (f.name || 'файл').replace(/[<>&"]/g, '');
            return `<div class="tw-file-chip" data-idx="${idx}">
                ${isImg ? `<img src="${url}" alt="">` : `<span>${name}</span>`}
                <button type="button" data-rm="${idx}" aria-label="Убрать">×</button>
            </div>`;
        }).join('');
    };

    filesPreview?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-rm]');
        if (!btn) return;
        pendingFiles.splice(parseInt(btn.getAttribute('data-rm'), 10), 1);
        renderTwFiles();
    });

    const addTwFiles = (list) => {
        const room = FILES_MAX - pendingFiles.length;
        if (room <= 0) return;
        pendingFiles = pendingFiles.concat([...list].slice(0, room));
        renderTwFiles();
    };

    if (editorEl && window.Quill && !editorEl.classList.contains('ql-container')) {
        quill = new Quill(editorEl, {
            theme: 'snow',
            placeholder: 'Написать сообщение… (Ctrl+V — вставить картинку)',
            modules: {
                toolbar: '#tw-quill-toolbar',
            },
        });
    } else if (window.Quill && editorEl) {
        quill = Quill.find(editorEl);
    }

    const setTwSending = (on) => {
        sending = on;
        composerEl?.classList.toggle('is-sending', on);
        if (sendBtn) {
            sendBtn.disabled = on;
            sendBtn.innerHTML = on
                ? '<span class="bx-send-spinner"></span> Отправка…'
                : 'Отправить';
        }
        if (quill) quill.enable(!on);
        if (filesInput) filesInput.disabled = on;
    };

    sendBtn?.addEventListener('click', async () => {
        if (sending) return;
        const url = sendBtn.getAttribute('data-url');
        const token = sendBtn.getAttribute('data-csrf');
        if (!url) return;

        const plain = quill ? (quill.getText() || '').trim() : '';
        const hasFiles = pendingFiles.length > 0;
        if (!plain && !hasFiles) {
            (typeof window.uiToast==='function'?window.uiToast:function(m){console.warn(m);})('Напишите сообщение или прикрепите файл', 'info');
            return;
        }

        const fd = new FormData();
        if (token) fd.append('_token', token);
        if (plain && quill) {
            fd.append('comment[text]', JSON.stringify(quill.getContents()));
        } else {
            fd.append('comment[text]', '');
        }
        if (parentInput?.value) {
            fd.append('comment[parent_id]', parentInput.value);
        }
        pendingFiles.forEach((file) => fd.append('comment_files[]', file));

        setTwSending(true);
        try {
            const res = await fetch(url, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            });
            if (!res.ok) {
                (typeof window.uiToast==='function'?window.uiToast:function(m){console.warn(m);})('Не удалось отправить сообщение', 'error');
                setTwSending(false);
                return;
            }
            window.location.reload();
        } catch (e) {
            (typeof window.uiToast==='function'?window.uiToast:function(m){console.warn(m);})('Не удалось отправить сообщение', 'error');
            setTwSending(false);
        }
    });

    const onPasteImages = (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;
        const files = [];
        for (const item of items) {
            if (item.kind === 'file' && (item.type || '').startsWith('image/')) {
                const f = item.getAsFile();
                if (f) {
                    const ext = (f.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                    files.push(new File([f], `paste-${Date.now()}.${ext}`, { type: f.type }));
                }
            }
        }
        if (files.length) {
            e.preventDefault();
            addTwFiles(files);
        }
    };
    composerEl?.addEventListener('paste', onPasteImages);
    quill?.root?.addEventListener('paste', onPasteImages);

    /* Растягивание высоты редактора */
    const resizeHandle = document.getElementById('tw-composer-resize');
    const editorWrap = document.getElementById('tw-composer-editor-wrap');
    resizeHandle?.addEventListener('pointerdown', (e) => {
        if (e.button !== undefined && e.button !== 0) return;
        e.preventDefault();
        const ql = editorWrap?.querySelector('.ql-editor');
        const container = editorWrap?.querySelector('.ql-container');
        if (!ql) return;
        const startY = e.clientY;
        const startH = ql.getBoundingClientRect().height;
        resizeHandle.classList.add('is-dragging');
        resizeHandle.setPointerCapture?.(e.pointerId);
        const onMove = (ev) => {
            const next = Math.min(520, Math.max(96, startH + (ev.clientY - startY)));
            ql.style.setProperty('min-height', next + 'px', 'important');
            ql.style.setProperty('height', next + 'px', 'important');
            ql.style.setProperty('max-height', 'none', 'important');
            if (container) {
                container.style.setProperty('min-height', next + 'px', 'important');
                container.style.height = 'auto';
            }
        };
        const onUp = (ev) => {
            resizeHandle.classList.remove('is-dragging');
            try { resizeHandle.releasePointerCapture?.(ev.pointerId); } catch (err) {}
            resizeHandle.removeEventListener('pointermove', onMove);
            resizeHandle.removeEventListener('pointerup', onUp);
            resizeHandle.removeEventListener('pointercancel', onUp);
        };
        resizeHandle.addEventListener('pointermove', onMove);
        resizeHandle.addEventListener('pointerup', onUp);
        resizeHandle.addEventListener('pointercancel', onUp);
    });

    /* Модалка связей */
    const openLinkBtn = document.getElementById('tw-open-link-modal');
    const linkModal = document.getElementById('tw-link-modal');
    const linkSearchInput = document.getElementById('tw-link-modal-search');
    const linkResults = document.getElementById('tw-link-modal-results');
    const linkPicked = document.getElementById('tw-link-modal-picked');
    const linkTaskId = document.getElementById('tw-link-modal-task-id');
    const linkRelation = document.getElementById('tw-link-modal-relation');
    const linkSubmit = document.getElementById('tw-link-modal-submit');
    let linkSearchTimer = null;
    let selectedLinkTask = null;

    const closeLinkModal = () => linkModal?.setAttribute('hidden', '');
    const openLinkModal = () => {
        linkModal?.removeAttribute('hidden');
        selectedLinkTask = null;
        if (linkTaskId) linkTaskId.value = '';
        linkPicked?.classList.add('d-none');
        if (linkPicked) linkPicked.innerHTML = '';
        if (linkSubmit) linkSubmit.disabled = true;
        if (linkSearchInput) {
            linkSearchInput.value = '';
            linkSearchInput.focus();
        }
        searchLinkTasks('');
    };
    openLinkBtn?.addEventListener('click', openLinkModal);
    document.getElementById('tw-link-modal-close')?.addEventListener('click', closeLinkModal);
    document.getElementById('tw-link-modal-bg')?.addEventListener('click', closeLinkModal);

    const searchLinkTasks = async (q) => {
        const searchUrl = openLinkBtn?.getAttribute('data-search-url');
        if (!searchUrl || !linkResults) return;
        linkResults.textContent = 'Поиск…';
        const params = new URLSearchParams({
            q: q || '',
            exclude: openLinkBtn.getAttribute('data-task-id') || '',
            project_id: openLinkBtn.getAttribute('data-project-id') || '',
        });
        try {
            const res = await fetch(searchUrl + '?' + params.toString(), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            const tasks = data.tasks || [];
            if (!tasks.length) {
                linkResults.innerHTML = '<div class="text-muted small py-2">Ничего не найдено</div>';
                return;
            }
            linkResults.innerHTML = tasks.map((t) =>
                `<button type="button" class="tw-link-result" data-id="${t.id}" data-label="${(t.label || '').replace(/"/g, '&quot;')}">
                    <strong>${t.key || ('#' + t.id)}</strong>
                    <span>${(t.name || '').replace(/[<>]/g, '')}</span>
                    <em>${(t.status || '').replace(/[<>]/g, '')}</em>
                </button>`
            ).join('');
        } catch (e) {
            linkResults.textContent = 'Ошибка поиска';
        }
    };

    linkSearchInput?.addEventListener('input', () => {
        clearTimeout(linkSearchTimer);
        linkSearchTimer = setTimeout(() => searchLinkTasks(linkSearchInput.value.trim()), 250);
    });

    linkResults?.addEventListener('click', (e) => {
        const btn = e.target.closest('.tw-link-result');
        if (!btn) return;
        selectedLinkTask = {
            id: btn.getAttribute('data-id'),
            label: btn.getAttribute('data-label') || btn.textContent.trim(),
        };
        if (linkTaskId) linkTaskId.value = selectedLinkTask.id;
        if (linkPicked) {
            linkPicked.textContent = 'Выбрано: ' + selectedLinkTask.label;
            linkPicked.classList.remove('d-none');
        }
        if (linkSubmit) linkSubmit.disabled = false;
        linkResults.querySelectorAll('.tw-link-result').forEach((el) => {
            el.classList.toggle('is-active', el === btn);
        });
    });

    linkSubmit?.addEventListener('click', async () => {
        const url = openLinkBtn?.getAttribute('data-add-url');
        const token = openLinkBtn?.getAttribute('data-csrf');
        if (!url || !linkTaskId?.value) return;
        const fd = new FormData();
        fd.append('related_task_id', linkTaskId.value);
        fd.append('relation', linkRelation?.value || 'relates');
        if (token) fd.append('_token', token);
        linkSubmit.disabled = true;
        try {
            const res = await fetch(url, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                (typeof window.uiToast==='function'?window.uiToast:function(m){console.warn(m);})('Не удалось добавить связь', 'error');
                linkSubmit.disabled = false;
                return;
            }
            window.location.reload();
        } catch (e) {
            (typeof window.uiToast==='function'?window.uiToast:function(m){console.warn(m);})('Не удалось добавить связь', 'error');
            linkSubmit.disabled = false;
        }
    });

    document.querySelectorAll('.tw-related-item__rm').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const url = btn.getAttribute('data-remove-url');
            const token = btn.getAttribute('data-csrf');
            const linkId = btn.getAttribute('data-link-id');
            if (!url || !linkId) return;
            const fd = new FormData();
            fd.append('link_id', linkId);
            if (token) fd.append('_token', token);
            try {
                const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) {
                    (typeof window.uiToast==='function'?window.uiToast:function(m){console.warn(m);})('Не удалось удалить связь', 'error');
                    return;
                }
                window.location.reload();
            } catch (e) {
                (typeof window.uiToast==='function'?window.uiToast:function(m){console.warn(m);})('Не удалось удалить связь', 'error');
            }
        });
    });

    filesInput?.addEventListener('change', () => {
        const n = filesInput.files?.length || 0;
        if (n > 0) {
            addTwFiles(filesInput.files);
            filesInput.value = '';
        }
    });

    document.querySelectorAll('.tw-reply-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (parentInput) parentInput.value = btn.getAttribute('data-parent-id') || '';
            if (replyAuthor) replyAuthor.textContent = btn.getAttribute('data-author') || '';
            replyBanner?.classList.remove('d-none');
            quill?.focus();
        });
    });
    document.getElementById('tw-reply-cancel')?.addEventListener('click', () => {
        if (parentInput) parentInput.value = '';
        replyBanner?.classList.add('d-none');
    });

    const feed = document.getElementById('task-discussion-feed');
    if (feed) feed.scrollTop = feed.scrollHeight;

    // Статус-меню как в Трекере
    document.querySelectorAll('[data-yt-status]').forEach((wrap) => {
        const toggle = wrap.querySelector('[data-yt-status-toggle]');
        const menu = wrap.querySelector('[data-yt-status-menu]');
        if (!toggle || !menu) return;
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = menu.hasAttribute('hidden');
            document.querySelectorAll('[data-yt-status-menu]').forEach((m) => m.setAttribute('hidden', ''));
            if (open) menu.removeAttribute('hidden');
        });
    });
    document.addEventListener('click', () => {
        document.querySelectorAll('[data-yt-status-menu]').forEach((m) => m.setAttribute('hidden', ''));
    });

    // Простой lightbox для картинок в обсуждении
    document.querySelectorAll('[data-bx-lightbox]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const url = el.getAttribute('data-bx-lightbox');
            if (!url) return;
            const overlay = document.createElement('div');
            overlay.className = 'tw-lightbox';
            overlay.innerHTML = '<button type="button" class="tw-lightbox__bg"></button><img src="' + url + '" alt="">';
            const close = () => overlay.remove();
            overlay.querySelector('.tw-lightbox__bg')?.addEventListener('click', close);
            overlay.addEventListener('click', (ev) => { if (ev.target === overlay) close(); });
            document.body.appendChild(overlay);
        });
    });
})();
</script>
