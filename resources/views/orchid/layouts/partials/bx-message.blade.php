{{-- Single chat message bubble. Expects: $message, $chat (active), $viewer --}}
@php
    /** @var \App\Models\ChatMessage $message */
    /** @var \App\Models\Chat $chat */
    /** @var \App\Models\User $viewer */
    $mine = (int) $message->user_id === (int) $viewer->id;
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
        $mime = strtolower((string) ($file->mime ?? ''));
        if (str_starts_with($mime, 'audio/')) {
            return true;
        }
        $ext = strtolower((string) ($file->extension ?? pathinfo((string) $file->original_name, PATHINFO_EXTENSION)));

        return in_array($ext, ['webm', 'ogg', 'oga', 'mp3', 'm4a', 'wav', 'aac', 'opus'], true);
    };
@endphp
<article class="bx-msg {{ $mine ? 'bx-msg--mine' : '' }} {{ $message->is_system ? 'bx-msg--system' : '' }}"
         id="chat-msg-{{ $message->id }}">
    @unless($message->is_system)
        <div class="bx-msg__avatar">
            @include('orchid.layouts.partials.bx-avatar', [
                'avatarUser' => $message->user,
                'avatarChat' => null,
                'size' => 'sm',
                'shape' => 'round',
            ])
        </div>
    @endunless

    <div class="bx-msg__bubble">
        @if($message->parent)
            <div class="bx-msg__reply">
                Ответ на {{ $message->parent->user?->displayName() }}:
                {{ \Illuminate\Support\Str::limit(strip_tags($message->parent->plain_text ?? ''), 70) }}
            </div>
        @endif

        @unless($message->is_system)
            <div class="bx-msg__meta">
                <strong>{{ $message->user?->displayName() ?? 'Участник' }}</strong>
                <span>{{ $message->created_at?->format('d.m H:i') }}</span>
            </div>
        @endunless

        @php
            $voiceFiles = $message->attachment->filter(fn ($f) => $isVoiceAttachment($f));
            $otherFiles = $message->attachment->reject(fn ($f) => $isVoiceAttachment($f));
            $body = trim(strip_tags($message->formatted_text ?? ''));
            $hideBody = $voiceFiles->isNotEmpty()
                && ($body === '' || str_starts_with($body, 'Голосовое сообщение'));
        @endphp

        @unless($hideBody)
            <div class="bx-msg__body tw-msg__body">
                {!! $message->formatted_text !!}
            </div>
        @endunless

        @if($voiceFiles->isNotEmpty())
            <div class="bx-msg__voices">
                @foreach($voiceFiles as $file)
                    <audio class="bx-voice-player"
                           controls
                           preload="metadata"
                           src="{{ route('platform.task.attachment.download', ['attachment' => $file, 'inline' => 1]) }}"></audio>
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
                    <a href="{{ route('platform.task.attachment.download', $file) }}" class="badge text-bg-light border text-decoration-none">
                        {{ $file->original_name }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="bx-msg__footer">
            @unless($message->is_system)
                <button type="button"
                        class="bx-msg__reply-btn"
                        data-parent-id="{{ $message->id }}"
                        data-author="{{ $message->user?->displayName() ?? 'участник' }}">
                    Ответить
                </button>
            @endunless

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
