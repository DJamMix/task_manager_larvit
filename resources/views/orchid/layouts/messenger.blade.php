<div class="bx-messenger">
    @php
        $chatList = $chats ?? collect();
        $active = $chat ?? null;
        $feed = $messages ?? collect();
        $activeId = $active_chat_id ?? $active?->id;
    @endphp

    <aside class="bx-messenger__sidebar">
        <div class="bx-messenger__sidebar-head">
            <strong>Чаты</strong>
            <span class="badge text-bg-light border">{{ $chatList->count() }}</span>
        </div>

        <div class="bx-messenger__list">
            @forelse($chatList as $item)
                <a href="{{ route('platform.systems.chats.view', $item) }}"
                   class="bx-chat-item {{ (int)$activeId === (int)$item->id ? 'is-active' : '' }}">
                    <div class="bx-chat-item__avatar">
                        {{ mb_strtoupper(mb_substr($item->displayTitle(), 0, 1)) }}
                    </div>
                    <div class="bx-chat-item__body">
                        <div class="bx-chat-item__top">
                            <strong>{{ $item->displayTitle() }}</strong>
                            @if(($item->unread_count ?? 0) > 0)
                                <span class="bx-chat-item__badge">{{ $item->unread_count }}</span>
                            @endif
                        </div>
                        <div class="bx-chat-item__preview text-muted">
                            {{ \Illuminate\Support\Str::limit($item->latestMessage?->plain_text ?? 'Нет сообщений', 48) }}
                        </div>
                    </div>
                </a>
            @empty
                <div class="text-muted small p-3">Пока нет чатов. Создайте групповой или личный.</div>
            @endforelse
        </div>
    </aside>

    <section class="bx-messenger__main">
        @if($active)
            <div class="bx-messenger__header">
                <div>
                    <h2 class="h5 mb-0">{{ $active->displayTitle() }}</h2>
                    <div class="small text-muted">
                        {{ $active->type === 'direct' ? 'Личный чат' : 'Группа' }}
                        ·
                        {{ $active->members->count() }} уч.
                        ·
                        {{ $active->members->take(4)->map->displayName()->implode(', ') }}
                        @if($active->members->count() > 4)…@endif
                    </div>
                </div>
            </div>

            <div class="bx-messenger__feed" id="chat-feed">
                @forelse($feed as $message)
                    @php
                        $mine = (int)$message->user_id === (int)auth()->id();
                    @endphp
                    <article class="bx-msg {{ $mine ? 'bx-msg--mine' : '' }} {{ $message->is_system ? 'bx-msg--system' : '' }}"
                             id="chat-msg-{{ $message->id }}">
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

                        <div class="bx-msg__body tw-msg__body">
                            {!! $message->formatted_text !!}
                        </div>

                        @if($message->task)
                            @php
                                $taskHref = auth()->user()->hasAccess('platform.systems.tasks')
                                    ? route('platform.systems.tasks.edit', $message->task)
                                    : (auth()->user()->hasAccess('platform.systems.my_tasks')
                                        ? route('platform.systems.my_tasks.view', $message->task)
                                        : '#');
                            @endphp
                            <a class="bx-task-card" href="{{ $taskHref }}">
                                <span class="bx-task-card__id">#{{ $message->task->id }}</span>
                                <span class="bx-task-card__name">{{ $message->task->name }}</span>
                            </a>
                        @endif

                        @if($message->attachment->isNotEmpty())
                            <div class="bx-msg__files">
                                @foreach($message->attachment as $file)
                                    <a href="{{ route('platform.task.attachment.download', $file) }}" class="badge text-bg-light border text-decoration-none">
                                        {{ $file->original_name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @unless($message->is_system)
                            <button type="button"
                                    class="btn btn-link btn-sm px-0 bx-reply-btn"
                                    data-parent-id="{{ $message->id }}"
                                    data-author="{{ $message->user?->displayName() ?? 'участник' }}">
                                Ответить
                            </button>
                        @endunless
                    </article>
                @empty
                    <div class="text-muted text-center py-5">Начните переписку</div>
                @endforelse
            </div>
        @else
            <div class="bx-messenger__empty">
                <h2 class="h4">Корпоративные чаты</h2>
                <p class="text-muted mb-0">Создайте групповой чат или напишите коллеге лично.</p>
            </div>
        @endif
    </section>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>
(() => {
    const highlightAll = () => {
        if (!window.hljs) return;
        document.querySelectorAll('.tw-codeblock code, pre code.hljs').forEach((el) => {
            try { window.hljs.highlightElement(el); } catch (e) {}
        });
    };
    highlightAll();

    const feed = document.getElementById('chat-feed');
    if (feed) feed.scrollTop = feed.scrollHeight;

    document.addEventListener('click', (e) => {
        const reply = e.target.closest?.('.bx-reply-btn');
        if (reply) {
            const input = document.getElementById('chat-message-parent-id');
            if (input) input.value = reply.getAttribute('data-parent-id') || '';
            const banner = document.getElementById('reply-banner');
            const author = document.getElementById('reply-author');
            banner?.classList.remove('d-none');
            if (author) author.textContent = reply.getAttribute('data-author') || 'участник';
            document.getElementById('task-composer-anchor')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => document.querySelector('.ql-editor')?.focus(), 150);
        }

        const copyBtn = e.target.closest?.('.tw-code-copy');
        if (copyBtn) {
            const code = copyBtn.closest('.tw-codeblock')?.querySelector('code')?.innerText || '';
            if (!code) return;
            const done = () => {
                const prev = copyBtn.textContent;
                copyBtn.textContent = 'Скопировано';
                setTimeout(() => copyBtn.textContent = prev || 'Копировать', 1200);
            };
            navigator.clipboard?.writeText(code).then(done).catch(() => {});
        }
    });
})();
</script>
