<div class="bx-messenger">
    @php
        $chatList = $chats ?? collect();
        $active = $chat ?? null;
        $feed = $messages ?? collect();
        $activeId = $active_chat_id ?? $active?->id;
        $taskOptions = $composer_tasks ?? [];
        $notifyOptions = $composer_members ?? [];
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
                                    class="bx-msg__reply-btn"
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

            {{-- Кастомный композер в стиле мессенджера (поля уходят в #post-form Orchid) --}}
            <div class="bx-composer" id="bx-composer">
                <input type="hidden" name="message[parent_id]" id="chat-message-parent-id" value="">

                <div id="bx-reply-banner" class="bx-composer__reply d-none">
                    <div>
                        Ответ для <strong id="bx-reply-author"></strong>
                    </div>
                    <button type="button" class="bx-composer__icon-btn" id="bx-reply-cancel" title="Отмена">×</button>
                </div>

                <div class="bx-composer__box">
                    <textarea name="message[text]"
                              id="bx-composer-input"
                              class="bx-composer__input"
                              rows="1"
                              placeholder="Написать сообщение… Enter — отправить, Shift+Enter — новая строка"></textarea>

                    <div class="bx-composer__toolbar">
                        <div class="bx-composer__tools">
                            <button type="button" class="bx-composer__tool" id="bx-tool-code" title="Блок кода">
                                &lt;/&gt;
                            </button>

                            <label class="bx-composer__tool" title="Файл">
                                📎
                                <input type="file"
                                       name="message_files[]"
                                       id="bx-composer-files"
                                       class="d-none"
                                       multiple
                                       accept="image/*,.pdf,.zip,.rar,.doc,.docx,.xls,.xlsx,.txt,.php,.js,.ts,.json,.sql,.css">
                            </label>

                            <div class="bx-composer__dropdown">
                                <button type="button" class="bx-composer__tool" data-bx-drop="task" title="Задача">
                                    ✅
                                </button>
                                <div class="bx-composer__menu" data-bx-menu="task">
                                    <div class="bx-composer__menu-title">Прикрепить задачу</div>
                                    <select name="message[task_id]" class="bx-composer__select">
                                        <option value="">Без задачи</option>
                                        @foreach($taskOptions as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            @if(count($notifyOptions))
                                <div class="bx-composer__dropdown">
                                    <button type="button" class="bx-composer__tool" data-bx-drop="notify" title="Уведомить">
                                        🔔
                                    </button>
                                    <div class="bx-composer__menu" data-bx-menu="notify">
                                        <div class="bx-composer__menu-title">Уведомить</div>
                                        <select name="message[notify_user_ids][]" class="bx-composer__select" multiple size="5">
                                            @foreach($notifyOptions as $id => $label)
                                                <option value="{{ $id }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <div class="bx-composer__hint">Пусто = все участники</div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="bx-composer__right">
                            <span class="bx-composer__files-label d-none" id="bx-files-label"></span>
                            <button type="submit"
                                    class="bx-composer__send"
                                    formaction="{{ url()->current() }}/sendMessage"
                                    form="post-form">
                                Отправить
                            </button>
                        </div>
                    </div>
                </div>
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
    if (window.hljs) {
        document.querySelectorAll('.tw-codeblock code').forEach((el) => {
            try { window.hljs.highlightElement(el); } catch (e) {}
        });
    }

    const feed = document.getElementById('chat-feed');
    if (feed) feed.scrollTop = feed.scrollHeight;

    const input = document.getElementById('bx-composer-input');
    const parentInput = document.getElementById('chat-message-parent-id');
    const replyBanner = document.getElementById('bx-reply-banner');
    const replyAuthor = document.getElementById('bx-reply-author');
    const filesInput = document.getElementById('bx-composer-files');
    const filesLabel = document.getElementById('bx-files-label');

    const autosize = () => {
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    };
    input?.addEventListener('input', autosize);

    input?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.querySelector('.bx-composer__send')?.click();
        }
    });

    document.getElementById('bx-tool-code')?.addEventListener('click', () => {
        if (!input) return;
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? input.value.length;
        const selected = input.value.slice(start, end) || 'код';
        const block = '```\n' + selected + '\n```';
        input.value = input.value.slice(0, start) + block + input.value.slice(end);
        input.focus();
        autosize();
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

    document.querySelectorAll('[data-bx-drop]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const key = btn.getAttribute('data-bx-drop');
            document.querySelectorAll('[data-bx-menu]').forEach((menu) => {
                if (menu.getAttribute('data-bx-menu') === key) {
                    menu.classList.toggle('is-open');
                } else {
                    menu.classList.remove('is-open');
                }
            });
        });
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest?.('.bx-composer__dropdown')) {
            document.querySelectorAll('[data-bx-menu]').forEach((m) => m.classList.remove('is-open'));
        }

        const reply = e.target.closest?.('.bx-msg__reply-btn');
        if (reply) {
            if (parentInput) parentInput.value = reply.getAttribute('data-parent-id') || '';
            replyBanner?.classList.remove('d-none');
            if (replyAuthor) replyAuthor.textContent = reply.getAttribute('data-author') || 'участник';
            input?.focus();
        }

        const copyBtn = e.target.closest?.('.tw-code-copy');
        if (copyBtn) {
            const code = copyBtn.closest('.tw-codeblock')?.querySelector('code')?.innerText || '';
            if (!code) return;
            navigator.clipboard?.writeText(code).then(() => {
                const prev = copyBtn.textContent;
                copyBtn.textContent = 'Скопировано';
                setTimeout(() => copyBtn.textContent = prev || 'Копировать', 1200);
            }).catch(() => {});
        }
    });

    document.getElementById('bx-reply-cancel')?.addEventListener('click', () => {
        if (parentInput) parentInput.value = '';
        replyBanner?.classList.add('d-none');
    });
})();
</script>
