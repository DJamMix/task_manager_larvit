<div class="bx-messenger"
     data-poll-url="{{ $chats_poll_url ?? route('platform.systems.chats.poll') }}"
     data-active-chat="{{ $active_chat_id ?? '' }}">
    @php
        $chatList = $chats ?? collect();
        $active = $chat ?? null;
        $feed = $messages ?? collect();
        $activeId = $active_chat_id ?? $active?->id;
        $taskOptions = $composer_tasks ?? [];
        $mentionUsers = $mention_users ?? [];
        $isMuted = (bool) ($chat_is_muted ?? false);
    @endphp

    <aside class="bx-messenger__sidebar">
        <div class="bx-messenger__sidebar-head">
            <strong>Чаты</strong>
            <span class="badge text-bg-light border">{{ $chatList->count() }}</span>
        </div>

        <div class="bx-messenger__list">
            @forelse($chatList as $item)
                @php
                    $listAvatarUser = $item->type === 'direct' ? $item->otherMember() : null;
                @endphp
                <a href="{{ route('platform.systems.chats.view', $item) }}"
                   class="bx-chat-item {{ (int)$activeId === (int)$item->id ? 'is-active' : '' }} {{ !empty($item->is_muted) ? 'is-muted' : '' }}">
                    @if($listAvatarUser)
                        @include('orchid.layouts.partials.bx-avatar', ['user' => $listAvatarUser, 'size' => 'md'])
                    @else
                        <div class="bx-chat-item__avatar">
                            {{ mb_strtoupper(mb_substr($item->displayTitle(), 0, 1)) }}
                        </div>
                    @endif
                    <div class="bx-chat-item__body">
                        <div class="bx-chat-item__top">
                            <strong>
                                {{ $item->displayTitle() }}
                                @if(!empty($item->is_muted))
                                    <svg class="bx-icon bx-icon--xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 5L6 9H2v6h4l5 4V5z"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
                                @endif
                            </strong>
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
                <div class="text-muted small p-3">Пока нет чатов. Напишите коллеге или создайте группу.</div>
            @endforelse
        </div>
    </aside>

    <section class="bx-messenger__main">
        @if($active)
            <div class="bx-messenger__header">
                <div class="bx-messenger__header-main">
                    @php
                        $headerAvatar = $active->type === 'direct' ? $active->otherMember() : null;
                    @endphp
                    @if($headerAvatar)
                        @include('orchid.layouts.partials.bx-avatar', ['user' => $headerAvatar, 'size' => 'lg'])
                    @else
                        <div class="bx-chat-item__avatar bx-chat-item__avatar--lg">
                            {{ mb_strtoupper(mb_substr($active->displayTitle(), 0, 1)) }}
                        </div>
                    @endif
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
                <button type="submit"
                        class="bx-mute-btn {{ $isMuted ? 'is-muted' : '' }}"
                        formaction="{{ url()->current() }}/toggleMute"
                        form="post-form"
                        title="{{ $isMuted ? 'Включить звук' : 'Выключить звук' }}">
                    @if($isMuted)
                        <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 5L6 9H2v6h4l5 4V5z"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
                        <span>Без звука</span>
                    @else
                        <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07"/></svg>
                        <span>Звук</span>
                    @endif
                </button>
            </div>

            <div class="bx-messenger__feed" id="chat-feed">
                @forelse($feed as $message)
                    @php
                        $mine = (int)$message->user_id === (int)auth()->id();
                        $readers = [];
                        $readStatus = null;
                        if (!$message->is_system && $mine) {
                            $readers = $active->readersForMessage($message);
                            $othersCount = $active->members
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
                    @endphp
                    <article class="bx-msg {{ $mine ? 'bx-msg--mine' : '' }} {{ $message->is_system ? 'bx-msg--system' : '' }}"
                             id="chat-msg-{{ $message->id }}">
                        @unless($message->is_system)
                            <div class="bx-msg__avatar">
                                @include('orchid.layouts.partials.bx-avatar', ['user' => $message->user, 'size' => 'sm'])
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

                            <div class="bx-msg__body tw-msg__body">
                                {!! $message->formatted_text !!}
                            </div>

                            @if($message->task)
                                @php
                                    $viewer = auth()->user();
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

                            @if($message->attachment->isNotEmpty())
                                <div class="bx-msg__files">
                                    @foreach($message->attachment as $file)
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
                @empty
                    <div class="text-muted text-center py-5">Начните переписку</div>
                @endforelse
            </div>

            @if($can_write ?? true)
            <div class="bx-composer" id="bx-composer"
                 data-mentions='@json($mentionUsers)'>
                <input type="hidden" name="message[parent_id]" id="chat-message-parent-id" form="post-form" value="">

                <div id="bx-reply-banner" class="bx-composer__reply d-none">
                    <div>
                        Ответ для <strong id="bx-reply-author"></strong>
                    </div>
                    <button type="button" class="bx-composer__icon-btn" id="bx-reply-cancel" title="Отмена">
                        <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="bx-composer__box">
                    <textarea name="message[text]"
                              id="bx-composer-input"
                              class="bx-composer__input"
                              form="post-form"
                              rows="1"
                              placeholder="Написать сообщение… @имя — упомянуть, Enter — отправить"></textarea>

                    <div id="bx-mention-menu" class="bx-mention-menu d-none" role="listbox"></div>

                    <div class="bx-composer__toolbar">
                        <div class="bx-composer__tools">
                            <button type="button" class="bx-composer__tool" id="bx-tool-code" title="Блок кода">
                                <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/></svg>
                            </button>

                            <label class="bx-composer__tool" title="Файл">
                                <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                                <input type="file"
                                       name="message_files[]"
                                       id="bx-composer-files"
                                       class="d-none"
                                       form="post-form"
                                       multiple
                                       accept="image/*,.pdf,.zip,.rar,.doc,.docx,.xls,.xlsx,.txt,.php,.js,.ts,.json,.sql,.css">
                            </label>

                            <div class="bx-composer__dropdown">
                                <button type="button" class="bx-composer__tool" data-bx-drop="task" title="Задача">
                                    <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                                </button>
                                <div class="bx-composer__menu bx-composer__menu--task" data-bx-menu="task">
                                    <div class="bx-composer__menu-title">Прикрепить задачу</div>
                                    <input type="search"
                                           id="bx-task-search"
                                           class="bx-composer__select"
                                           placeholder="Поиск: номер или название…"
                                           autocomplete="off">
                                    <input type="hidden" name="message[task_id]" id="bx-task-id" form="post-form" value="">
                                    <div id="bx-task-picked" class="bx-task-picked d-none"></div>
                                    <div id="bx-task-results" class="bx-task-results"
                                         data-search-url="{{ $composer_tasks_search_url ?? route('platform.systems.chats.tasks') }}"
                                         data-tasks='@json($taskOptions)'></div>
                                </div>
                            </div>

                            <button type="button" class="bx-composer__tool" id="bx-tool-mention" title="Упомянуть (@)">
                                <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zm0 0c0 1.657 1.007 3 2.25 3S21 13.657 21 12a9 9 0 10-2.636 6.364M16.5 12V8.25"/>
                                </svg>
                            </button>
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
                <div class="alert alert-warning mb-0 m-3">
                    Нет права писать клиентам в личных чатах. Обратитесь к проектному менеджеру — нужно право «Чаты с клиентами».
                </div>
            @endif
        @else
            <div class="bx-messenger__empty">
                <h2 class="h4">Корпоративные чаты</h2>
                <p class="text-muted mb-0">Напишите коллеге лично или создайте групповой чат (если есть право).</p>
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

    const root = document.querySelector('.bx-messenger');
    const input = document.getElementById('bx-composer-input');
    const parentInput = document.getElementById('chat-message-parent-id');
    const replyBanner = document.getElementById('bx-reply-banner');
    const replyAuthor = document.getElementById('bx-reply-author');
    const filesInput = document.getElementById('bx-composer-files');
    const filesLabel = document.getElementById('bx-files-label');
    const mentionMenu = document.getElementById('bx-mention-menu');
    const composer = document.getElementById('bx-composer');
    const taskSearch = document.getElementById('bx-task-search');
    const taskIdInput = document.getElementById('bx-task-id');
    const taskResults = document.getElementById('bx-task-results');
    const taskPicked = document.getElementById('bx-task-picked');

    let mentionUsers = [];
    try {
        mentionUsers = JSON.parse(composer?.getAttribute('data-mentions') || '[]');
    } catch (e) { mentionUsers = []; }

    let initialTasks = [];
    try {
        initialTasks = JSON.parse(taskResults?.getAttribute('data-tasks') || '[]');
    } catch (e) { initialTasks = []; }

    const escapeHtml = (s) => String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const autosize = () => {
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    };
    input?.addEventListener('input', () => {
        autosize();
        updateMentionMenu();
    });
    input?.addEventListener('keydown', (e) => {
        if (mentionMenu && !mentionMenu.classList.contains('d-none')) {
            const items = [...mentionMenu.querySelectorAll('[data-mention-name]')];
            const active = mentionMenu.querySelector('.is-active');
            let idx = items.indexOf(active);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                items.forEach((el) => el.classList.remove('is-active'));
                items[(idx + 1) % items.length]?.classList.add('is-active');
                if (idx < 0) items[0]?.classList.add('is-active');
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                items.forEach((el) => el.classList.remove('is-active'));
                items[(idx - 1 + items.length) % items.length]?.classList.add('is-active');
                return;
            }
            if (e.key === 'Enter' || e.key === 'Tab') {
                const pick = mentionMenu.querySelector('.is-active') || items[0];
                if (pick) {
                    e.preventDefault();
                    insertMention(pick.getAttribute('data-mention-name'));
                    return;
                }
            }
            if (e.key === 'Escape') {
                hideMentionMenu();
                return;
            }
        }

        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.querySelector('.bx-composer__send')?.click();
        }
    });

    let mentionStart = -1;

    const getMentionQuery = () => {
        if (!input) return null;
        const pos = input.selectionStart ?? 0;
        const before = input.value.slice(0, pos);
        const m = before.match(/(^|[\s([{])@([^\s@]*)$/);
        if (!m) return null;
        mentionStart = before.length - m[2].length - 1;
        return m[2].toLowerCase();
    };

    const hideMentionMenu = () => {
        mentionMenu?.classList.add('d-none');
        mentionMenu && (mentionMenu.innerHTML = '');
    };

    const updateMentionMenu = () => {
        if (!mentionMenu || !mentionUsers.length) return;
        const q = getMentionQuery();
        if (q === null) {
            hideMentionMenu();
            return;
        }
        const filtered = mentionUsers.filter((u) => {
            const hay = (u.aliases || [u.name]).join(' ').toLowerCase();
            return hay.includes(q);
        }).slice(0, 8);

        if (!filtered.length) {
            hideMentionMenu();
            return;
        }

        mentionMenu.innerHTML = filtered.map((u, i) =>
            `<button type="button" class="bx-mention-item ${i === 0 ? 'is-active' : ''}" data-mention-name="${escapeHtml(u.name)}" role="option">
                <span class="bx-mention-item__avatar">${escapeHtml((u.name || '?').slice(0, 1).toUpperCase())}</span>
                <span>${escapeHtml(u.name)}</span>
            </button>`
        ).join('');
        mentionMenu.classList.remove('d-none');
    };

    const insertMention = (name) => {
        if (!input || mentionStart < 0) return;
        const pos = input.selectionStart ?? 0;
        const before = input.value.slice(0, mentionStart);
        const after = input.value.slice(pos);
        input.value = before + '@' + name + ' ' + after;
        const caret = before.length + name.length + 2;
        input.focus();
        input.setSelectionRange(caret, caret);
        hideMentionMenu();
        autosize();
    };

    mentionMenu?.addEventListener('mousedown', (e) => {
        const btn = e.target.closest?.('[data-mention-name]');
        if (!btn) return;
        e.preventDefault();
        insertMention(btn.getAttribute('data-mention-name'));
    });

    document.getElementById('bx-tool-mention')?.addEventListener('click', () => {
        if (!input) return;
        const pos = input.selectionStart ?? input.value.length;
        input.value = input.value.slice(0, pos) + '@' + input.value.slice(pos);
        input.focus();
        input.setSelectionRange(pos + 1, pos + 1);
        autosize();
        updateMentionMenu();
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

    /* Task attach: search by id / name */
    const renderTaskResults = (tasks) => {
        if (!taskResults) return;
        if (!tasks.length) {
            taskResults.innerHTML = '<div class="bx-task-results__empty">Ничего не найдено</div>';
            return;
        }
        taskResults.innerHTML = tasks.map((t) =>
            `<button type="button" class="bx-task-result" data-task-id="${t.id}" data-task-label="${escapeHtml(t.label)}">
                <strong>#${t.id}</strong>
                <span>${escapeHtml(t.name)}</span>
            </button>`
        ).join('');
    };

    const pickTask = (id, label) => {
        if (taskIdInput) taskIdInput.value = id || '';
        if (!taskPicked) return;
        if (!id) {
            taskPicked.classList.add('d-none');
            taskPicked.innerHTML = '';
            return;
        }
        taskPicked.classList.remove('d-none');
        taskPicked.innerHTML = `<span>${escapeHtml(label)}</span>
            <button type="button" class="bx-composer__icon-btn" id="bx-task-clear" title="Убрать">×</button>`;
    };

    let taskSearchTimer = null;
    const runTaskSearch = async (q) => {
        const query = (q || '').trim();
        if (!query) {
            renderTaskResults(initialTasks.slice(0, 12));
            return;
        }

        const local = initialTasks.filter((t) => {
            const hay = (`${t.id} ${t.name} ${t.label}`).toLowerCase();
            return hay.includes(query.toLowerCase()) || String(t.id) === query.replace(/^#/, '');
        }).slice(0, 12);
        renderTaskResults(local);

        const url = taskResults?.getAttribute('data-search-url');
        if (!url) return;
        try {
            const res = await fetch(url + '?q=' + encodeURIComponent(query), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            if (Array.isArray(data.tasks)) renderTaskResults(data.tasks);
        } catch (e) {}
    };

    taskSearch?.addEventListener('input', () => {
        clearTimeout(taskSearchTimer);
        taskSearchTimer = setTimeout(() => runTaskSearch(taskSearch.value), 220);
    });
    taskSearch?.addEventListener('keydown', (e) => e.stopPropagation());
    taskSearch?.addEventListener('click', (e) => e.stopPropagation());

    taskResults?.addEventListener('mousedown', (e) => {
        const btn = e.target.closest?.('.bx-task-result');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        pickTask(btn.getAttribute('data-task-id'), btn.getAttribute('data-task-label'));
        if (taskSearch) taskSearch.value = '';
        renderTaskResults([]);
    });

    taskPicked?.addEventListener('click', (e) => {
        if (e.target.closest?.('#bx-task-clear')) {
            pickTask('', '');
            runTaskSearch('');
        }
    });

    document.querySelectorAll('[data-bx-drop]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const key = btn.getAttribute('data-bx-drop');
            document.querySelectorAll('[data-bx-menu]').forEach((menu) => {
                if (menu.getAttribute('data-bx-menu') === key) {
                    const opening = !menu.classList.contains('is-open');
                    menu.classList.toggle('is-open');
                    if (opening && key === 'task') {
                        runTaskSearch(taskSearch?.value || '');
                        setTimeout(() => taskSearch?.focus(), 30);
                    }
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
        if (!e.target.closest?.('#bx-composer')) {
            hideMentionMenu();
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

    autosize();

    /* Sound notifications — poll like Bitrix / modern messengers */
    const pollUrl = root?.getAttribute('data-poll-url');
    const storageKey = 'bx_chat_poll_since';
    let since = parseInt(localStorage.getItem(storageKey) || '0', 10) || 0;
    let soundUnlocked = false;

    const unlockSound = () => { soundUnlocked = true; };
    document.addEventListener('click', unlockSound, { once: true });
    document.addEventListener('keydown', unlockSound, { once: true });

    const playNotifySound = () => {
        if (!soundUnlocked) return;
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            const ctx = new Ctx();
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(880, ctx.currentTime);
            o.frequency.exponentialRampToValueAtTime(660, ctx.currentTime + 0.12);
            g.gain.setValueAtTime(0.0001, ctx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.08, ctx.currentTime + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.28);
            o.connect(g);
            g.connect(ctx.destination);
            o.start();
            o.stop(ctx.currentTime + 0.3);
            setTimeout(() => ctx.close().catch(() => {}), 400);
        } catch (e) {}
    };

    const poll = async () => {
        if (!pollUrl) return;
        try {
            const activeChat = root?.getAttribute('data-active-chat') || '';
            const params = new URLSearchParams();
            if (since) params.set('since', String(since));
            if (activeChat) params.set('chat', activeChat);
            const qs = params.toString();
            const url = pollUrl + (qs ? ('?' + qs) : '');
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data.sound) playNotifySound();
            if (data.max_id && data.max_id > since) {
                since = data.max_id;
                localStorage.setItem(storageKey, String(since));
            }
            (data.chats || []).forEach((c) => {
                const link = document.querySelector('.bx-chat-item[href*="/chats/' + c.id + '"]');
                if (!link) return;
                let b = link.querySelector('.bx-chat-item__badge');
                if (c.unread > 0) {
                    if (!b) {
                        b = document.createElement('span');
                        b.className = 'bx-chat-item__badge';
                        link.querySelector('.bx-chat-item__top')?.appendChild(b);
                    }
                    b.textContent = String(c.unread);
                } else if (b) {
                    b.remove();
                }
            });

            (data.receipts || []).forEach((r) => {
                const article = document.getElementById('chat-msg-' + r.id);
                const receipt = article?.querySelector('.bx-msg__receipt');
                if (!receipt) return;
                receipt.classList.remove('bx-msg__receipt--sent', 'bx-msg__receipt--partial', 'bx-msg__receipt--read');
                receipt.classList.add('bx-msg__receipt--' + r.status);
                const checks = receipt.querySelector('.bx-msg__checks');
                if (checks) {
                    checks.innerHTML = r.status === 'sent'
                        ? '<svg viewBox="0 0 16 12" width="16" height="12"><path fill="currentColor" d="M5.5 9.5L1.8 5.8l1-1L5.5 7.4 12.2.7l1 1z"/></svg>'
                        : '<svg viewBox="0 0 22 12" width="20" height="12"><path fill="currentColor" d="M15.2 1.2l1 1-7.7 7.7L5 6.4l1-1 2.5 2.5 6.7-6.7zm-5 0l1 1-7.7 7.7L.1 6.4l1-1 2.5 2.5L10.2 1.2z"/></svg>';
                }
                const tip = receipt.querySelector('.bx-msg__receipt-tip');
                if (!tip) return;
                const readers = r.readers || [];
                if (!readers.length) {
                    tip.innerHTML = '<div class="bx-msg__receipt-tip-title">Отправлено</div><div class="bx-msg__receipt-empty">Ещё никто не просмотрел</div>';
                    return;
                }
                const title = r.status === 'read' ? 'Прочитано всеми' : 'Прочитали';
                tip.innerHTML = '<div class="bx-msg__receipt-tip-title">' + title + ' · ' + readers.length + '</div><ul class="bx-msg__receipt-list">' +
                    readers.map((u) =>
                        '<li><span class="bx-avatar bx-avatar--xs" style="--bx-avatar-bg:' + u.color + '"><span class="bx-avatar__initials">' +
                        escapeHtml(u.initials) + '</span></span><span class="bx-msg__receipt-name">' + escapeHtml(u.name) +
                        '</span><span class="bx-msg__receipt-time">' + escapeHtml(u.read_at || '') + '</span></li>'
                    ).join('') + '</ul>';
            });
        } catch (e) {}
    };

    if (pollUrl) {
        poll();
        setInterval(poll, 5000);
    }
})();
</script>
