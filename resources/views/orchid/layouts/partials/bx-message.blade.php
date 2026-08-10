{{-- Single chat message bubble. Expects: $message, $chat (active), $viewer --}}
@php
    /** @var \App\Models\ChatMessage $message */
    /** @var \App\Models\Chat $chat */
    /** @var \App\Models\User $viewer */
    $mine = (int) $message->user_id === (int) $viewer->id;
    $isForwarded = (bool) ($message->forwarded_from_message_id || $message->forwarded_from_user_id);
    $forwardOrigin = $isForwarded ? $message->forwardOriginUser() : null;
    $forwardOriginName = $forwardOrigin?->displayName()
        ?: ($forwardOrigin?->name ?: null);
    $readers = [];
    $readStatus = null;
    if (!$message->is_system && $mine) {
        $readers = $chat->readersForMessage($message);
        $othersCount = $chat->members
            ->reject(fn ($u) => (int) $u->id === (int) $message->user_id)
            ->count();
        if ($othersCount === 0 || count($readers) === 0) {
            $readStatus = 'sent';
        } elseif (count($readers) >= $othersCount) {
            $readStatus = 'read';
        } else {
            $readStatus = 'partial';
        }
    }

    $isVoiceAttachment = function ($file): bool {
        if (($file->group ?? '') === 'voice') {
            return true;
        }
        $mime = strtolower((string) ($file->mime ?? ''));
        if (str_starts_with($mime, 'audio/') || $mime === 'video/mp4') {
            return true;
        }
        $ext = strtolower((string) ($file->extension ?? pathinfo((string) $file->original_name, PATHINFO_EXTENSION)));

        return in_array($ext, ['webm', 'ogg', 'oga', 'mp3', 'm4a', 'mp4', 'wav', 'aac', 'opus'], true);
    };

    $quickPreview = trim(strip_tags((string) ($message->plain_text ?? '')));
    if ($quickPreview === '') {
        $quickPreview = $message->attachment?->isNotEmpty() ? 'Вложение' : 'Сообщение';
    }
@endphp
<article class="bx-msg {{ $mine ? 'bx-msg--mine' : '' }} {{ $message->is_system ? 'bx-msg--system' : '' }} {{ $isForwarded ? 'bx-msg--forwarded' : '' }} {{ ($message->user?->is_bot && ! $message->is_system) ? 'bx-msg--bot' : '' }}"
         id="chat-msg-{{ $message->id }}"
         data-author="{{ $message->user?->displayName() ?? 'участник' }}"
         data-preview="{{ \Illuminate\Support\Str::limit($quickPreview, 120) }}"
         @if($message->user?->is_bot) data-is-bot="1" @endif>
    @unless($message->is_system)
        <button type="button"
                class="bx-msg__check"
                data-msg-check
                aria-label="Выбрать сообщение"
                title="Выбрать"
                tabindex="-1">
            <span class="bx-msg__check-box" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12.5l4.2 4.2L19 7.5"/></svg>
            </span>
        </button>
        <div class="bx-msg__avatar">
            @include('orchid.layouts.partials.bx-avatar', [
                'avatarUser' => $isForwarded && $forwardOrigin ? $forwardOrigin : $message->user,
                'avatarChat' => null,
                'size' => 'sm',
                'shape' => 'round',
            ])
        </div>
    @endunless

    <div class="bx-msg__bubble">
        @if($message->parent)
            @if($message->parent->trashed())
                <div class="bx-msg__reply bx-msg__reply--deleted">
                    <span class="bx-msg__reply-bar" aria-hidden="true"></span>
                    <span class="bx-msg__reply-content">
                        <span class="bx-msg__reply-author">Удалённое сообщение</span>
                        <span class="bx-msg__reply-text">Оригинал недоступен</span>
                    </span>
                </div>
            @else
                @php
                    $replyPreview = trim(strip_tags((string) ($message->parent->plain_text ?? '')));
                    if ($replyPreview === '') {
                        $replyPreview = 'Сообщение';
                    }
                @endphp
                <button type="button"
                        class="bx-msg__reply"
                        data-goto-msg="{{ $message->parent->id }}"
                        title="Перейти к сообщению">
                    <span class="bx-msg__reply-bar" aria-hidden="true"></span>
                    <span class="bx-msg__reply-content">
                        <span class="bx-msg__reply-author">{{ $message->parent->user?->displayName() ?? 'Участник' }}</span>
                        <span class="bx-msg__reply-text">{{ \Illuminate\Support\Str::limit($replyPreview, 90) }}</span>
                    </span>
                </button>
            @endif
        @endif

        @if($isForwarded)
            <div class="bx-msg__forwarded">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 8l4 4-4 4"/><path d="M6 12h12"/></svg>
                <span class="bx-msg__forwarded-text">
                    Переслано от <span class="bx-msg__forwarded-name">{{ $forwardOriginName ?: 'пользователя' }}</span>
                    @if($forwardOrigin?->is_bot)
                        <span class="bx-msg__bot-tag">бот</span>
                    @endif
                </span>
            </div>
        @elseif(!$message->is_system)
            <div class="bx-msg__meta">
                <strong>{{ $message->user?->displayName() ?? 'Участник' }}</strong>
                @if($message->user?->is_bot)
                    <span class="bx-msg__bot-tag">бот</span>
                @endif
            </div>
        @endif

        @php
            $voiceFiles = $message->attachment->filter(fn ($f) => $isVoiceAttachment($f));
            $otherFiles = $message->attachment->reject(fn ($f) => $isVoiceAttachment($f));
            $body = trim(strip_tags($message->formatted_text ?? ''));
            $hideBody = $voiceFiles->isNotEmpty()
                && ($body === '' || str_starts_with($body, 'Голосовое сообщение'));
        @endphp

        @unless($hideBody)
            <div class="bx-msg__body tw-msg__body {{ $message->user?->is_bot ? 'bx-msg__body--bot' : '' }}">
                {!! $message->formatted_text !!}
            </div>
        @endunless

        @php
            $inlineKeyboard = $message->botInlineKeyboard();
        @endphp
        @if($inlineKeyboard !== [])
            <div class="bx-bot-keyboard" data-msg-id="{{ $message->id }}">
                @foreach($inlineKeyboard as $row)
                    @if(is_array($row))
                        <div class="bx-bot-keyboard__row">
                            @foreach($row as $btn)
                                @if(is_array($btn) && !empty($btn['text']))
                                    @if(!empty($btn['url']))
                                        <a class="bx-bot-btn bx-bot-btn--url"
                                           href="{{ $btn['url'] }}"
                                           target="_blank"
                                           rel="noopener noreferrer">{{ $btn['text'] }}</a>
                                    @elseif(!empty($btn['callback_data']))
                                        <button type="button"
                                                class="bx-bot-btn bx-bot-btn--callback"
                                                data-callback="{{ $btn['callback_data'] }}"
                                                data-msg-id="{{ $message->id }}">
                                            {{ $btn['text'] }}
                                        </button>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        @if($voiceFiles->isNotEmpty())
            <div class="bx-msg__voices">
                @foreach($voiceFiles as $file)
                    <div class="bx-voice {{ $mine ? 'bx-voice--mine' : '' }}"
                         data-src="{{ route('platform.task.attachment.download', ['attachment' => $file, 'inline' => 1]) }}">
                        <button type="button" class="bx-voice__play" aria-label="Воспроизвести">
                            <svg class="bx-voice__icon bx-voice__icon--play" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="currentColor" d="M8 5v14l11-7z"/>
                            </svg>
                            <svg class="bx-voice__icon bx-voice__icon--pause" viewBox="0 0 24 24" aria-hidden="true" hidden>
                                <path fill="currentColor" d="M6 5h4v14H6zm8 0h4v14h-4z"/>
                            </svg>
                        </button>
                        <div class="bx-voice__body">
                            <div class="bx-voice__wave" tabindex="0" role="slider" aria-label="Прогресс" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                <div class="bx-voice__bars"></div>
                            </div>
                            <span class="bx-voice__time">0:00</span>
                        </div>
                        <audio preload="metadata" src="{{ route('platform.task.attachment.download', ['attachment' => $file, 'inline' => 1]) }}"></audio>
                    </div>
                @endforeach
            </div>
        @endif

        @if($message->task)
            @php
                $linkedTask = $message->task;
                $canOpenTask = $linkedTask->canView((int) $viewer->id);
                $taskHref = $canOpenTask
                    ? app(\App\Services\DashboardNotifier::class)->taskUrlFor($viewer, $linkedTask)
                    : null;
            @endphp
            @if($taskHref)
                <a class="bx-task-card" href="{{ $taskHref }}">
                    <span class="bx-task-card__id">#{{ $linkedTask->id }}</span>
                    <span class="bx-task-card__name">{{ $linkedTask->name }}</span>
                </a>
            @else
                <div class="bx-task-card bx-task-card--locked" title="Нет доступа к этой задаче">
                    <span class="bx-task-card__id">#{{ $linkedTask->id }}</span>
                    <span class="bx-task-card__name">{{ $linkedTask->name }}</span>
                    <span class="bx-task-card__lock">нет доступа</span>
                </div>
            @endif
        @endif

        @if($otherFiles->isNotEmpty())
            <div class="bx-msg__files">
                @foreach($otherFiles as $file)
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
                           class="bx-msg__image"
                           data-bx-lightbox="{{ $fileUrl }}"
                           title="{{ $file->original_name }}"
                           style="width:96px;height:96px;max-width:96px;max-height:96px;flex:0 0 96px;display:block;overflow:hidden;border-radius:10px;line-height:0;">
                            <img src="{{ $fileUrl }}"
                                 alt="{{ $file->original_name }}"
                                 width="96"
                                 height="96"
                                 loading="lazy"
                                 decoding="async"
                                 style="width:96px;height:96px;object-fit:cover;object-position:center;display:block;">
                        </a>
                    @else
                        <a href="{{ $downloadUrl }}" class="badge text-bg-light border text-decoration-none">
                            {{ $file->original_name }}
                        </a>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="bx-msg__footer">
            @unless($message->is_system)
                <div class="bx-msg__actions">
                    <button type="button"
                            class="bx-msg__action bx-msg__reply-btn"
                            data-parent-id="{{ $message->id }}"
                            data-author="{{ $isForwarded ? ($forwardOriginName ?: 'участник') : ($message->user?->displayName() ?? 'участник') }}"
                            data-preview="{{ \Illuminate\Support\Str::limit($quickPreview, 120) }}"
                            title="Ответить">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 17H5a2 2 0 01-2-2V5a2 2 0 012-2h11a2 2 0 012 2v3"/><path d="M15 15l5-5-5-5"/><path d="M20 10H11"/></svg>
                        <span>Ответить</span>
                    </button>
                </div>
            @endunless

            <span class="bx-msg__time">{{ $message->created_at?->format('H:i') }}</span>

            @if($mine && $readStatus)
                <div class="bx-msg__receipt bx-msg__receipt--{{ $readStatus }}" tabindex="0">
                    <span class="bx-msg__checks" aria-hidden="true">
                        @if($readStatus === 'sent')
                            <svg viewBox="0 0 16 12" width="16" height="12"><path fill="currentColor" d="M5.5 9.5L1.8 5.8l1-1L5.5 7.4 12.2.7l1 1z"/></svg>
                        @else
                            <svg viewBox="0 0 22 12" width="20" height="12"><path fill="currentColor" d="M15.2 1.2l1 1-7.7 7.7L5 6.4l1-1 2.5 2.5 6.7-6.7zm-5 0l1 1-7.7 7.7L.1 6.4l1-1 2.5 2.5L10.2 1.2z"/></svg>
                        @endif
                    </span>
                    @if(count($readers))
                        <div class="bx-msg__receipt-tip" role="tooltip">
                            <div class="bx-msg__receipt-tip-title">
                                {{ $readStatus === 'read' ? 'Прочитано всеми' : 'Прочитали' }}
                                · {{ count($readers) }}
                            </div>
                            <ul class="bx-msg__receipt-list">
                                @foreach($readers as $reader)
                                    <li>
                                        <span class="bx-avatar bx-avatar--xs" style="--bx-avatar-bg: {{ $reader['color'] }}">
                                            <span class="bx-avatar__initials">{{ $reader['initials'] }}</span>
                                        </span>
                                        <span class="bx-msg__receipt-name">{{ $reader['name'] }}</span>
                                        <span class="bx-msg__receipt-time">{{ $reader['read_at'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="bx-msg__receipt-tip" role="tooltip">
                            <div class="bx-msg__receipt-tip-title">Отправлено</div>
                            <div class="bx-msg__receipt-empty">Ещё никто не просмотрел</div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</article>
