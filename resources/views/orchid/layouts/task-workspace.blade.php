<div class="task-workspace">
    @php
        $statusEnum = \App\CoreLayer\Enums\TaskStatusEnum::tryFrom($task->status);
        $priorityEnum = \App\CoreLayer\Enums\TaskPriorityEnum::tryFrom((string) $task->priority);
        $viewerRole = $viewer_role ?? 'employee';
        $canDiscuss = $can_discuss ?? true;
        $isObserverOnly = $is_observer_only ?? false;
        $discussion = $discussion_comments ?? collect();
    @endphp

    <div class="task-workspace__grid">
        <aside class="task-workspace__sidebar">
            <div class="tw-card">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <div class="text-muted small text-uppercase mb-1">Задача #{{ $task->id }}</div>
                        <h2 class="h4 mb-0 text-body-emphasis">{{ $task->name }}</h2>
                    </div>
                    @if($statusEnum)
                        <span class="badge" style="background:{{ $statusEnum->color() }};color:#fff;">{{ $statusEnum->label() }}</span>
                    @endif
                </div>

                <div class="tw-meta">
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
                        Вы наблюдатель: можно писать в обсуждении. Статус и время недоступны.
                    </div>
                @endif
            </div>

            <div class="tw-card mt-3">
                <div class="fw-semibold mb-2">Описание</div>
                <div class="tw-description">
                    {!! $task->description ?: '<span class="text-muted">Описание не заполнено</span>' !!}
                </div>
            </div>

            <div class="tw-card mt-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-semibold">Файлы задачи</div>
                    <span class="badge text-bg-light border">{{ $task->attachment->count() }}</span>
                </div>
                @forelse($task->attachment as $file)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2">
                        <div class="small text-truncate" title="{{ $file->original_name }}">{{ $file->original_name }}</div>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('platform.task.attachment.download', $file) }}">Скачать</a>
                    </div>
                @empty
                    <div class="text-muted small">Файлов пока нет</div>
                @endforelse
            </div>

            @if(!empty($show_time_link) && !empty($time_route))
                <div class="tw-card mt-3">
                    <div class="fw-semibold mb-1">Учёт времени</div>
                    <div class="small text-muted mb-2">Вынесен отдельно, чтобы не мешать обсуждению</div>
                    <a href="{{ $time_route }}" class="btn btn-sm btn-outline-primary">Открыть журнал времени</a>
                </div>
            @endif
        </aside>

        <section class="task-workspace__discussion">
            <div class="tw-card tw-discussion">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h3 class="h5 mb-0">Обсуждение</h3>
                        <div class="small text-muted">Как в мессенджере: лента + ответ + уведомления</div>
                    </div>
                    <span class="badge text-bg-secondary">{{ $discussion->count() }}</span>
                </div>

                <div class="tw-feed" id="task-discussion-feed">
                    @forelse($discussion as $comment)
                        @php
                            $isMine = (int)($comment->user_id) === (int)auth()->id();
                            $parent = $comment->parent;
                        @endphp
                        <article class="tw-msg {{ $isMine ? 'tw-msg--mine' : '' }} {{ $comment->is_system ? 'tw-msg--system' : '' }}"
                                 id="comment-{{ $comment->id }}"
                                 data-comment-id="{{ $comment->id }}"
                                 data-author="{{ $comment->user?->displayName() ?? 'Участник' }}">
                            @if($parent)
                                <div class="tw-msg__reply-to">
                                    Ответ на {{ $parent->user?->displayName() ?? 'сообщение' }}:
                                    <span>{{ \Illuminate\Support\Str::limit($parent->plain_text, 80) }}</span>
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
                                            📎 {{ $file->original_name }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            @if(!$comment->is_system && $canDiscuss)
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
                        <div class="text-muted text-center py-5">Пока нет сообщений — начните обсуждение ниже</div>
                    @endforelse
                </div>

                @if(!$canDiscuss)
                    <div class="alert alert-warning mb-0 mt-3">У вас нет прав писать в этом обсуждении</div>
                @endif
            </div>
        </section>
    </div>
</div>
