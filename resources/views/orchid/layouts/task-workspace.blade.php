<div class="task-workspace">
    @php
        $statusEnum = \App\CoreLayer\Enums\TaskStatusEnum::tryFrom($task->status);
        $priorityEnum = \App\CoreLayer\Enums\TaskPriorityEnum::tryFrom((string) $task->priority);
        $canDiscuss = $can_discuss ?? true;
        $isObserverOnly = $is_observer_only ?? false;
        $discussion = $discussion_comments ?? collect();
        $history = $history_comments ?? $discussion->where('is_system', true)->values();
        $pipeline = $status_pipeline ?? [];
        $statusActions = $status_actions ?? [];
        $statusHint = $status_hint ?? null;
        $relatedLinks = $related_links ?? collect();
        $linkOptions = $link_task_options ?? [];
        $relationLabels = \App\Models\TaskLink::relationLabels();
        $canManageLinks = $can_manage_links ?? false;
        $viewRoute = $task_view_route ?? route('platform.systems.my_tasks.view', $task);
    @endphp

    <div class="task-workspace__grid">
        <aside class="task-workspace__sidebar">
            <div class="tw-card">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <div class="text-muted small text-uppercase mb-1">{{ $task->displayKey() }}</div>
                        <h2 class="h5 mb-0 text-body-emphasis">{{ $task->name }}</h2>
                    </div>
                    @if($statusEnum)
                        <span class="badge" style="background:{{ $statusEnum->color() }};color:#fff;">{{ $statusEnum->label() }}</span>
                    @endif
                </div>

                <div class="tw-meta">
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
                    <div class="fw-semibold mb-2">Статус</div>

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

            <div class="tw-card mt-3">
                <div class="fw-semibold mb-2">Связанные задачи</div>
                <ul class="tw-related-list">
                    @forelse($relatedLinks as $link)
                        @php $related = $link->relatedTask; @endphp
                        @if($related)
                            <li class="tw-related-item">
                                <div>
                                    <div class="tw-related-item__rel">{{ $link->label() }}</div>
                                    <a href="{{ route('platform.systems.my_tasks.view', $related) }}" class="tw-related-item__link">
                                        {{ $related->displayKey() }} · {{ \Illuminate\Support\Str::limit($related->name, 42) }}
                                    </a>
                                </div>
                                @if($canManageLinks)
                                    <form method="post" action="{{ url()->current() }}/removeLink">
                                        @csrf
                                        <input type="hidden" name="link_id" value="{{ $link->id }}">
                                        <button type="submit" class="btn btn-sm btn-link text-danger px-0" title="Убрать">×</button>
                                    </form>
                                @endif
                            </li>
                        @endif
                    @empty
                        <li class="text-muted small">Пока нет связей</li>
                    @endforelse
                </ul>

                @if($canManageLinks)
                    <form method="post" action="{{ url()->current() }}/addLink" class="tw-related-form mt-2">
                        @csrf
                        <select name="related_task_id" class="form-select form-select-sm" required>
                            <option value="">Задача…</option>
                            @foreach($linkOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="relation" class="form-select form-select-sm" required>
                            @foreach($relationLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Связать</button>
                    </form>
                @endif
            </div>

            @if(!empty($show_time_link) && !empty($time_route))
                <div class="tw-card mt-3">
                    <div class="fw-semibold mb-1">Учёт времени</div>
                    <a href="{{ $time_route }}" class="btn btn-sm btn-outline-primary w-100">Журнал времени</a>
                </div>
            @endif
        </aside>

        <section class="task-workspace__main">
            <div class="tw-card tw-description-card">
                <div class="fw-semibold mb-2">Описание</div>
                <div class="tw-description">
                    {!! $task->description ?: '<span class="text-muted">Описание не заполнено</span>' !!}
                </div>
            </div>
        </section>
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
                                        <a href="{{ route('platform.task.attachment.download', $file) }}" class="badge text-bg-light border text-decoration-none">
                                            {{ $file->original_name }}
                                        </a>
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
                    <form class="tw-composer" id="tw-composer" method="post" action="{{ url()->current() }}/addComment" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="comment[parent_id]" id="comment-parent-id" value="">
                        <div id="tw-reply-banner" class="tw-composer__reply d-none">
                            <div>Ответ для <strong id="tw-reply-author"></strong></div>
                            <button type="button" class="btn btn-sm btn-link" id="tw-reply-cancel">Отмена</button>
                        </div>
                        <textarea name="comment[text]"
                                  id="tw-composer-input"
                                  class="tw-composer__input"
                                  rows="2"
                                  placeholder="Написать сообщение… Enter — отправить, Shift+Enter — новая строка"></textarea>
                        <div class="tw-composer__bar">
                            <label class="tw-composer__attach" title="Файлы">
                                <input type="file" name="comment_files[]" id="tw-composer-files" multiple accept="image/*,.pdf,.zip,.rar,.doc,.docx,.xls,.xlsx,.txt">
                                <span>Файлы</span>
                            </label>
                            <span class="tw-composer__files-label text-muted small d-none" id="tw-files-label"></span>
                            <button type="submit" class="btn btn-primary btn-sm tw-composer__send">Отправить</button>
                        </div>
                    </form>
                @else
                    <div class="alert alert-warning mb-0 mt-2">Нет прав писать в обсуждении</div>
                @endif
            </div>
        </div>

        <div class="tw-tab-panel" data-tw-panel="files" hidden>
            <div class="tw-files-grid">
                @forelse($task->attachment as $file)
                    <div class="tw-file-row">
                        <div class="text-truncate" title="{{ $file->original_name }}">{{ $file->original_name }}</div>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('platform.task.attachment.download', $file) }}">Скачать</a>
                    </div>
                @empty
                    <div class="text-muted small py-3">Нет вложений у задачи</div>
                @endforelse
            </div>
        </div>

        <div class="tw-tab-panel" data-tw-panel="history" hidden>
            <div class="tw-feed tw-feed--history">
                @forelse($history as $comment)
                    <article class="tw-msg tw-msg--system">
                        <div class="tw-msg__head">
                            <strong>{{ $comment->user?->displayName() ?? 'Система' }}</strong>
                            <span class="text-muted">{{ $comment->created_at?->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="tw-msg__body">{!! $comment->formatted_text !!}</div>
                    </article>
                @empty
                    <div class="text-muted text-center py-4">История пока пуста</div>
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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
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

    const input = document.getElementById('tw-composer-input');
    const parentInput = document.getElementById('comment-parent-id');
    const replyBanner = document.getElementById('tw-reply-banner');
    const replyAuthor = document.getElementById('tw-reply-author');
    const filesInput = document.getElementById('tw-composer-files');
    const filesLabel = document.getElementById('tw-files-label');
    const MAX_H = 180;

    const autosize = () => {
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, MAX_H) + 'px';
        input.style.overflowY = input.scrollHeight > MAX_H ? 'auto' : 'hidden';
    };
    input?.addEventListener('input', autosize);
    autosize();

    input?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('tw-composer')?.requestSubmit();
        }
    });

    filesInput?.addEventListener('change', () => {
        const n = filesInput.files?.length || 0;
        if (!filesLabel) return;
        if (n > 0) {
            filesLabel.textContent = n + ' файл(ов)';
            filesLabel.classList.remove('d-none');
        } else {
            filesLabel.classList.add('d-none');
        }
    });

    document.querySelectorAll('.tw-reply-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (parentInput) parentInput.value = btn.getAttribute('data-parent-id') || '';
            if (replyAuthor) replyAuthor.textContent = btn.getAttribute('data-author') || '';
            replyBanner?.classList.remove('d-none');
            input?.focus();
        });
    });
    document.getElementById('tw-reply-cancel')?.addEventListener('click', () => {
        if (parentInput) parentInput.value = '';
        replyBanner?.classList.add('d-none');
    });

    const feed = document.getElementById('task-discussion-feed');
    if (feed) feed.scrollTop = feed.scrollHeight;
})();
</script>
