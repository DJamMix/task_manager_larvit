<div class="bx-messenger"
     data-poll-url="{{ $chats_poll_url ?? route('platform.systems.chats.poll') }}"
     data-active-chat="{{ $active_chat_id ?? '' }}"
     data-send-url="{{ ($active_chat_id ?? null) ? url()->current() . '/sendMessage' : '' }}">
    @php
        $chatList = $chats ?? collect();
        $active = $chat ?? null;
        $feed = $messages ?? collect();
        $activeId = $active_chat_id ?? $active?->id;
        $taskOptions = $composer_tasks ?? [];
        $mentionUsers = $mention_users ?? [];
        $isMuted = (bool) ($chat_is_muted ?? false);
        $isPinned = (bool) ($chat_is_pinned ?? false);
        $canEditChat = (bool) ($can_edit_chat ?? false);
    @endphp

    <aside class="bx-messenger__sidebar">
        <div class="bx-messenger__sidebar-head">
            <strong>Чаты</strong>
            <span class="badge text-bg-light border">{{ $chatList->count() }}</span>
        </div>

        <div class="bx-messenger__list">
            @forelse($chatList as $item)
                <a href="{{ route('platform.systems.chats.view', $item) }}"
                   class="bx-chat-item {{ (int)$activeId === (int)$item->id ? 'is-active' : '' }} {{ !empty($item->is_muted) ? 'is-muted' : '' }} {{ !empty($item->is_pinned) ? 'is-pinned' : '' }}">
                    @if($item->type === 'direct')
                        @include('orchid.layouts.partials.bx-avatar', [
                            'avatarUser' => $item->otherMember(),
                            'avatarChat' => null,
                            'size' => 'md',
                            'shape' => 'round',
                        ])
                    @else
                        @include('orchid.layouts.partials.bx-avatar', [
                            'avatarChat' => $item,
                            'avatarUser' => null,
                            'size' => 'md',
                            'shape' => 'square',
                        ])
                    @endif
                    <div class="bx-chat-item__body">
                        <div class="bx-chat-item__top">
                            <strong>
                                @if(!empty($item->is_pinned))
                                    <svg class="bx-icon bx-icon--xs bx-pin" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                                @endif
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
                    @if($active->type === 'direct')
                        @include('orchid.layouts.partials.bx-avatar', [
                            'avatarUser' => $active->otherMember(),
                            'avatarChat' => null,
                            'size' => 'lg',
                            'shape' => 'round',
                        ])
                    @else
                        @include('orchid.layouts.partials.bx-avatar', [
                            'avatarChat' => $active,
                            'avatarUser' => null,
                            'size' => 'lg',
                            'shape' => 'square',
                        ])
                    @endif
                    <div>
                        <h2 class="h5 mb-0">{{ $active->displayTitle() }}</h2>
                        @php
                            $memberCount = $active->members->count();
                            $memberWord = $memberCount === 1 ? 'участник' : (
                                ($memberCount % 10 >= 2 && $memberCount % 10 <= 4 && !in_array($memberCount % 100, [12, 13, 14], true))
                                    ? 'участника'
                                    : 'участников'
                            );
                        @endphp
                        <button type="button"
                                class="bx-chat-subtitle"
                                id="bx-open-members">
                            @if($active->type === 'direct')
                                Личный чат
                            @else
                                {{ $memberCount }} {{ $memberWord }}
                            @endif
                        </button>
                    </div>
                </div>
                <div class="bx-messenger__header-actions">
                    <button type="submit"
                            class="bx-mute-btn {{ $isPinned ? 'is-active' : '' }}"
                            formaction="{{ url()->current() }}/togglePin"
                            form="post-form"
                            title="{{ $isPinned ? 'Открепить' : 'Закрепить' }}">
                        <svg class="bx-icon" viewBox="0 0 24 24" fill="{{ $isPinned ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                        <span>{{ $isPinned ? 'Закреплён' : 'Закрепить' }}</span>
                    </button>
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
            </div>

            <div class="bx-messenger__feed" id="chat-feed">
                @forelse($feed as $message)
                    @include('orchid.layouts.partials.bx-message', [
                        'message' => $message,
                        'chat' => $active,
                        'viewer' => auth()->user(),
                    ])
                @empty
                    <div class="text-muted text-center py-5" id="bx-feed-empty">Начните переписку</div>
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
                                       accept="image/*,.pdf,.zip,.rar,.doc,.docx,.xls,.xlsx,.txt,.php,.js,.ts,.json,.sql,.css,audio/*">
                            </label>

                            <button type="button" class="bx-composer__tool" id="bx-tool-voice" title="Голосовое сообщение (до 3 мин)">
                                <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/>
                                    <path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/>
                                </svg>
                            </button>

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
                            <button type="button"
                                    class="bx-composer__send"
                                    id="bx-composer-send"
                                    data-send-url="{{ url()->current() }}/sendMessage">
                                Отправить
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bx-voice-record d-none" id="bx-voice-bar" aria-live="polite">
                    <div class="bx-voice-record__main">
                        <span class="bx-voice-record__dot" aria-hidden="true"></span>
                        <span class="bx-voice-record__label">Запись</span>
                        <span class="bx-voice-record__timer" id="bx-voice-timer">0:00</span>
                        <span class="bx-voice-record__limit">/ 3:00</span>
                    </div>
                    <div class="bx-voice-record__actions">
                        <button type="button" class="bx-voice-record__btn" id="bx-voice-cancel">Отмена</button>
                        <button type="button" class="bx-voice-record__btn bx-voice-record__btn--send" id="bx-voice-stop">Отправить</button>
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

    @if($active)
        <div class="bx-members-modal" id="bx-members-sheet" hidden>
            <button type="button" class="bx-members-modal__backdrop" id="bx-members-close-bg" aria-label="Закрыть"></button>
            <div class="bx-members-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bx-members-title">
                <div class="bx-members-modal__head">
                    <strong id="bx-members-title">Участники · {{ $active->members->count() }}</strong>
                    <button type="button" class="bx-members-modal__close" id="bx-members-close" aria-label="Закрыть">×</button>
                </div>
                <ul class="bx-members-modal__list">
                    @foreach($active->members->sortBy(fn ($u) => mb_strtolower($u->displayName())) as $member)
                        <li class="bx-members-modal__item">
                            @include('orchid.layouts.partials.bx-avatar', [
                                'avatarUser' => $member,
                                'avatarChat' => null,
                                'size' => 'md',
                                'shape' => 'round',
                            ])
                            <div class="bx-members-modal__meta">
                                <div class="bx-members-modal__name">{{ $member->displayName() }}</div>
                                @if($member->pivot?->role === 'owner')
                                    <div class="bx-members-modal__role">владелец</div>
                                @elseif($member->position)
                                    <div class="bx-members-modal__role">{{ $member->position }}</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
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
    const lockMessengerHeight = () => {
        if (!root) return;
        // Фиксируем высоту относительно окна — иначе Orchid-layout раздувает блок сообщениями
        const top = root.getBoundingClientRect().top;
        const available = Math.max(320, window.innerHeight - top - 24);
        root.style.height = available + 'px';
        root.style.maxHeight = available + 'px';
    };
    lockMessengerHeight();
    window.addEventListener('resize', lockMessengerHeight);
    window.addEventListener('orientationchange', () => setTimeout(lockMessengerHeight, 200));
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

    /* Members modal */
    const membersSheet = document.getElementById('bx-members-sheet');
    const openMembers = () => membersSheet?.removeAttribute('hidden');
    const closeMembers = () => membersSheet?.setAttribute('hidden', '');
    document.getElementById('bx-open-members')?.addEventListener('click', openMembers);
    document.getElementById('bx-members-close')?.addEventListener('click', closeMembers);
    document.getElementById('bx-members-close-bg')?.addEventListener('click', closeMembers);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && membersSheet && !membersSheet.hasAttribute('hidden')) {
            closeMembers();
        }
    });

    const csrf = document.querySelector('meta[name="csrf_token"]')?.content
        || document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
        || '';
    const sendUrl = root?.getAttribute('data-send-url')
        || document.getElementById('bx-composer-send')?.getAttribute('data-send-url')
        || '';

    const feedMaxId = () => Math.max(
        0,
        ...[...document.querySelectorAll('#chat-feed [id^="chat-msg-"]')]
            .map((el) => parseInt(el.id.replace('chat-msg-', ''), 10) || 0)
    );

    const highlightCodes = (scope) => {
        if (!window.hljs) return;
        (scope || document).querySelectorAll('.tw-codeblock code').forEach((el) => {
            try { window.hljs.highlightElement(el); } catch (e) {}
        });
    };

    const formatVoiceClock = (sec) => {
        if (!isFinite(sec) || sec < 0) return '0:00';
        const s = Math.floor(sec);
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    };

    const seededBars = (seed, count) => {
        let h = 0;
        for (let i = 0; i < seed.length; i++) h = ((h << 5) - h + seed.charCodeAt(i)) | 0;
        const out = [];
        for (let i = 0; i < count; i++) {
            h = (h * 1103515245 + 12345) & 0x7fffffff;
            const n = (h % 1000) / 1000;
            // мягкая «речь»: средние выше, края ниже
            const envelope = 0.35 + 0.65 * Math.sin((i / count) * Math.PI);
            out.push(Math.max(0.12, Math.min(1, (0.25 + n * 0.75) * envelope)));
        }
        return out;
    };

    const renderVoiceBars = (barsEl, values) => {
        barsEl.innerHTML = values.map((v) =>
            `<span class="bx-voice__bar" style="height:${Math.round(12 + v * 88)}%"></span>`
        ).join('');
    };

    const analyzeVoiceBars = async (url, count) => {
        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('fetch');
            const buf = await res.arrayBuffer();
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) throw new Error('ctx');
            const ctx = new Ctx();
            const audioBuffer = await ctx.decodeAudioData(buf.slice(0));
            const data = audioBuffer.getChannelData(0);
            const block = Math.max(1, Math.floor(data.length / count));
            const values = [];
            for (let i = 0; i < count; i++) {
                let sum = 0;
                const start = i * block;
                for (let j = start; j < start + block && j < data.length; j++) {
                    sum += Math.abs(data[j]);
                }
                values.push(sum / block);
            }
            const max = Math.max(...values, 0.01);
            await ctx.close().catch(() => {});
            return values.map((v) => Math.max(0.1, v / max));
        } catch (e) {
            return seededBars(url, count);
        }
    };

    const pauseOtherVoices = (except) => {
        document.querySelectorAll('.bx-voice.is-playing').forEach((el) => {
            if (el === except) return;
            const a = el.querySelector('audio');
            if (a) {
                a.pause();
                el.classList.remove('is-playing');
            }
        });
    };

    const ensureVoiceBlobSrc = async (wrap, audio, src) => {
        if (wrap.getAttribute('data-blob-ready') === '1') return true;
        try {
            const res = await fetch(src, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('fetch ' + res.status);
            const blob = await res.blob();
            if (!blob || blob.size < 64) throw new Error('empty');
            const type = blob.type || 'audio/wav';
            const playable = new Blob([blob], { type: type.includes('audio') || type.includes('video') ? type : 'audio/wav' });
            const objUrl = URL.createObjectURL(playable);
            audio.src = objUrl;
            wrap.setAttribute('data-blob-ready', '1');
            return true;
        } catch (e) {
            return false;
        }
    };

    const initVoicePlayers = (scope) => {
        const rootEl = scope || document;
        rootEl.querySelectorAll('.bx-voice:not([data-ready])').forEach((wrap) => {
            wrap.setAttribute('data-ready', '1');
            const audio = wrap.querySelector('audio');
            const barsEl = wrap.querySelector('.bx-voice__bars');
            const timeEl = wrap.querySelector('.bx-voice__time');
            const playBtn = wrap.querySelector('.bx-voice__play');
            const wave = wrap.querySelector('.bx-voice__wave');
            const src = wrap.getAttribute('data-src') || audio?.getAttribute('src') || '';
            if (!audio || !barsEl || !src) return;

            const BAR_COUNT = 40;
            renderVoiceBars(barsEl, seededBars(src, BAR_COUNT));
            analyzeVoiceBars(src, BAR_COUNT).then((vals) => renderVoiceBars(barsEl, vals));

            // Подгружаем blob заранее — так стабильнее на Safari / чужих форматах
            ensureVoiceBlobSrc(wrap, audio, src);

            const updateProgress = () => {
                const dur = audio.duration || 0;
                const cur = audio.currentTime || 0;
                const ratio = dur > 0 ? cur / dur : 0;
                const bars = barsEl.querySelectorAll('.bx-voice__bar');
                const played = Math.round(ratio * bars.length);
                bars.forEach((bar, i) => bar.classList.toggle('is-played', i < played));
                if (timeEl) {
                    timeEl.textContent = formatVoiceClock(wrap.classList.contains('is-playing') || cur > 0.05 ? cur : (dur || 0));
                }
                if (wave) wave.setAttribute('aria-valuenow', String(Math.round(ratio * 100)));
            };

            audio.addEventListener('loadedmetadata', updateProgress);
            audio.addEventListener('timeupdate', updateProgress);
            audio.addEventListener('ended', () => {
                wrap.classList.remove('is-playing');
                audio.currentTime = 0;
                updateProgress();
            });
            audio.addEventListener('pause', () => {
                if (audio.ended) return;
                wrap.classList.remove('is-playing');
            });
            audio.addEventListener('play', () => wrap.classList.add('is-playing'));
            audio.addEventListener('error', () => {
                wrap.classList.remove('is-playing');
                wrap.classList.add('is-error');
            });

            playBtn?.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (!audio.paused) {
                    audio.pause();
                    return;
                }
                pauseOtherVoices(wrap);
                const ok = await ensureVoiceBlobSrc(wrap, audio, src);
                if (!ok) {
                    alert('Не удалось загрузить голосовое сообщение');
                    return;
                }
                try {
                    await audio.play();
                } catch (err) {
                    // Часто webm с Chrome не играет на iPhone — подсказка
                    alert('Браузер не может воспроизвести это голосовое. Попросите отправителя записать ещё раз (обновлённая версия пишет совместимый формат).');
                    wrap.classList.add('is-error');
                }
            });

            const seekFromEvent = (e) => {
                const rect = wave.getBoundingClientRect();
                const x = ('clientX' in e ? e.clientX : (e.touches?.[0]?.clientX || 0)) - rect.left;
                const ratio = Math.max(0, Math.min(1, x / rect.width));
                if (isFinite(audio.duration) && audio.duration > 0) {
                    audio.currentTime = ratio * audio.duration;
                    updateProgress();
                }
            };
            wave?.addEventListener('click', seekFromEvent);
        });
    };

    const appendMessage = (payload) => {
        if (!feed || !payload?.html || !payload?.id) return false;
        if (document.getElementById('chat-msg-' + payload.id)) return false;
        document.getElementById('bx-feed-empty')?.remove();
        const nearBottom = feed.scrollHeight - feed.scrollTop - feed.clientHeight < 120;
        feed.insertAdjacentHTML('beforeend', payload.html);
        const node = document.getElementById('chat-msg-' + payload.id);
        if (node) {
            highlightCodes(node);
            initVoicePlayers(node);
        }
        if (nearBottom) feed.scrollTop = feed.scrollHeight;

        const activeChat = root?.getAttribute('data-active-chat') || '';
        if (activeChat && payload.preview != null) {
            const link = document.querySelector('.bx-chat-item[href*="/chats/' + activeChat + '"]');
            const preview = link?.querySelector('.bx-chat-item__preview');
            if (preview) preview.textContent = payload.preview;
        }
        return true;
    };

    initVoicePlayers(document);

    const resetComposer = () => {
        if (input) {
            input.value = '';
            autosize();
        }
        if (parentInput) parentInput.value = '';
        replyBanner?.classList.add('d-none');
        if (taskIdInput) taskIdInput.value = '';
        taskPicked?.classList.add('d-none');
        if (taskPicked) taskPicked.innerHTML = '';
        if (filesInput) filesInput.value = '';
        filesLabel?.classList.add('d-none');
        if (filesLabel) filesLabel.textContent = '';
        hideMentionMenu();
    };

    let sending = false;
    const sendMessageAjax = async (extraFormData = null) => {
        if (!sendUrl || sending) return;
        const text = (input?.value || '').trim();
        const hasFiles = filesInput?.files?.length > 0;
        const hasTask = !!(taskIdInput?.value);
        const hasVoice = extraFormData && extraFormData.has('message_voice');
        if (!text && !hasFiles && !hasTask && !hasVoice) return;

        sending = true;
        const btn = document.getElementById('bx-composer-send');
        if (btn) btn.disabled = true;

        try {
            const fd = extraFormData || new FormData();
            if (!extraFormData) {
                fd.append('message[text]', input?.value || '');
                if (parentInput?.value) fd.append('message[parent_id]', parentInput.value);
                if (taskIdInput?.value) fd.append('message[task_id]', taskIdInput.value);
                if (filesInput?.files) {
                    [...filesInput.files].forEach((f) => fd.append('message_files[]', f));
                }
            }
            if (csrf && !fd.has('_token')) fd.append('_token', csrf);

            const res = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                credentials: 'same-origin',
                body: fd,
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                alert(err.message || 'Не удалось отправить сообщение');
                return;
            }
            const data = await res.json();
            if (data.message) {
                appendMessage(data.message);
                const id = parseInt(data.message.id, 10) || 0;
                if (id > since) {
                    since = id;
                    localStorage.setItem(storageKey, String(since));
                }
                if (id > lastBeepMaxId) {
                    lastBeepMaxId = id;
                    sessionStorage.setItem(beepKey, String(lastBeepMaxId));
                }
            }
            resetComposer();
        } catch (e) {
            alert('Не удалось отправить сообщение');
        } finally {
            sending = false;
            if (btn) btn.disabled = false;
        }
    };

    document.getElementById('bx-composer-send')?.addEventListener('click', (e) => {
        e.preventDefault();
        sendMessageAjax();
    });

    /* Voice recording — max 3 minutes */
    const VOICE_MAX_SEC = 180;
    const voiceBtn = document.getElementById('bx-tool-voice');
    const voiceBar = document.getElementById('bx-voice-bar');
    const voiceTimer = document.getElementById('bx-voice-timer');
    let mediaStream = null;
    let voiceAudioCtx = null;
    let voiceProcessor = null;
    let voiceSource = null;
    let voicePcmChunks = [];
    let voiceSampleRate = 16000;
    let voiceRecording = false;
    let voiceStartedAt = 0;
    let voiceTick = null;
    let voiceCancelled = false;

    const formatVoiceTime = (sec) => {
        const s = Math.max(0, Math.floor(sec));
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    };

    const stopVoiceTracks = () => {
        try { voiceProcessor?.disconnect(); } catch (e) {}
        try { voiceSource?.disconnect(); } catch (e) {}
        voiceProcessor = null;
        voiceSource = null;
        mediaStream?.getTracks().forEach((t) => t.stop());
        mediaStream = null;
        if (voiceAudioCtx) {
            voiceAudioCtx.close().catch(() => {});
            voiceAudioCtx = null;
        }
        voiceRecording = false;
    };

    const endVoiceUi = () => {
        clearInterval(voiceTick);
        voiceTick = null;
        voiceBar?.classList.add('d-none');
        voiceBtn?.classList.remove('is-recording');
        composer?.classList.remove('is-voice-recording');
        if (voiceTimer) voiceTimer.textContent = '0:00';
    };

    /** PCM → WAV (играет везде: Chrome, Safari, Firefox, iPhone) */
    const encodeWavBlob = (floatChunks, sampleRate) => {
        let length = 0;
        floatChunks.forEach((c) => { length += c.length; });
        const samples = new Float32Array(length);
        let offset = 0;
        floatChunks.forEach((c) => {
            samples.set(c, offset);
            offset += c.length;
        });
        const buffer = new ArrayBuffer(44 + samples.length * 2);
        const view = new DataView(buffer);
        const writeStr = (pos, str) => {
            for (let i = 0; i < str.length; i++) view.setUint8(pos + i, str.charCodeAt(i));
        };
        writeStr(0, 'RIFF');
        view.setUint32(4, 36 + samples.length * 2, true);
        writeStr(8, 'WAVE');
        writeStr(12, 'fmt ');
        view.setUint32(16, 16, true);
        view.setUint16(20, 1, true);
        view.setUint16(22, 1, true);
        view.setUint32(24, sampleRate, true);
        view.setUint32(28, sampleRate * 2, true);
        view.setUint16(32, 2, true);
        view.setUint16(34, 16, true);
        writeStr(36, 'data');
        view.setUint32(40, samples.length * 2, true);
        let idx = 44;
        for (let i = 0; i < samples.length; i++, idx += 2) {
            const s = Math.max(-1, Math.min(1, samples[i]));
            view.setInt16(idx, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
        }
        return new Blob([buffer], { type: 'audio/wav' });
    };

    const finishVoiceRecording = async () => {
        const duration = Math.min(VOICE_MAX_SEC, Math.round((Date.now() - voiceStartedAt) / 1000));
        const chunks = voicePcmChunks.slice();
        const rate = voiceSampleRate;
        stopVoiceTracks();
        endVoiceUi();
        if (voiceCancelled || !chunks.length || duration < 1) return;
        const blob = encodeWavBlob(chunks, rate);
        if (blob.size < 1000) return;
        const file = new File([blob], 'voice.wav', { type: 'audio/wav' });
        const fd = new FormData();
        fd.append('message_voice', file);
        fd.append('message[voice_duration]', String(duration));
        fd.append('message[text]', '');
        if (parentInput?.value) fd.append('message[parent_id]', parentInput.value);
        await sendMessageAjax(fd);
    };

    const startVoice = async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            alert('Браузер не поддерживает запись голоса');
            return;
        }
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) {
            alert('Браузер не поддерживает запись голоса');
            return;
        }

        voiceBar?.classList.remove('d-none');
        voiceBtn?.classList.add('is-recording');
        composer?.classList.add('is-voice-recording');
        if (voiceTimer) voiceTimer.textContent = '0:00';

        try {
            mediaStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                    channelCount: 1,
                },
            });
            voiceAudioCtx = new Ctx({ sampleRate: 16000 });
            if (voiceAudioCtx.state === 'suspended') await voiceAudioCtx.resume();
            voiceSampleRate = voiceAudioCtx.sampleRate || 16000;
            voiceSource = voiceAudioCtx.createMediaStreamSource(mediaStream);
            voiceProcessor = voiceAudioCtx.createScriptProcessor(4096, 1, 1);
            voicePcmChunks = [];
            voiceCancelled = false;
            voiceStartedAt = Date.now();
            voiceRecording = true;
            voiceProcessor.onaudioprocess = (ev) => {
                if (!voiceRecording) return;
                const input = ev.inputBuffer.getChannelData(0);
                voicePcmChunks.push(new Float32Array(input));
            };
            const mute = voiceAudioCtx.createGain();
            mute.gain.value = 0;
            voiceSource.connect(voiceProcessor);
            voiceProcessor.connect(mute);
            mute.connect(voiceAudioCtx.destination);

            voiceTick = setInterval(() => {
                const elapsed = Math.floor((Date.now() - voiceStartedAt) / 1000);
                if (voiceTimer) voiceTimer.textContent = formatVoiceTime(elapsed);
                if (elapsed >= VOICE_MAX_SEC) stopVoice(false);
            }, 200);
        } catch (e) {
            stopVoiceTracks();
            endVoiceUi();
            alert('Нет доступа к микрофону. Разрешите запись в настройках браузера.');
        }
    };

    const stopVoice = (cancel = false) => {
        voiceCancelled = cancel;
        if (voiceRecording) {
            voiceRecording = false;
            finishVoiceRecording();
        } else {
            stopVoiceTracks();
            endVoiceUi();
        }
    };

    voiceBtn?.addEventListener('click', () => {
        if (voiceRecording) {
            stopVoice(false);
            return;
        }
        startVoice();
    });
    document.getElementById('bx-voice-stop')?.addEventListener('click', () => stopVoice(false));
    document.getElementById('bx-voice-cancel')?.addEventListener('click', () => stopVoice(true));

    /* Live poll */
    const pollUrl = root?.getAttribute('data-poll-url');
    const storageKey = 'bx_chat_poll_since';
    const beepKey = 'bx_chat_last_beep_id';
    let since = Math.max(
        parseInt(localStorage.getItem(storageKey) || '0', 10) || 0,
        feedMaxId()
    );
    let lastBeepMaxId = parseInt(sessionStorage.getItem(beepKey) || String(since), 10) || since;
    let lastBeepAt = 0;
    localStorage.setItem(storageKey, String(since));

    if (window.__bxMessengerPollTimer) {
        clearInterval(window.__bxMessengerPollTimer);
        window.__bxMessengerPollTimer = null;
    }

    const unlockSound = () => {
        window.__bxChatSoundUnlocked = true;
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            window.__bxChatAudioCtx = window.__bxChatAudioCtx || new Ctx();
            if (window.__bxChatAudioCtx.state === 'suspended') {
                window.__bxChatAudioCtx.resume();
            }
        } catch (e) {}
    };
    ['click', 'touchstart', 'pointerdown', 'keydown'].forEach((ev) => {
        document.addEventListener(ev, unlockSound, { once: true, passive: true });
    });

    const playNotifySound = () => {
        if (typeof window.bxPlayChatNotify === 'function') {
            window.bxPlayChatNotify();
            return;
        }
        if (!window.__bxChatSoundUnlocked) return;
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            const ctx = window.__bxChatAudioCtx = window.__bxChatAudioCtx || new Ctx();
            if (ctx.state === 'suspended') ctx.resume();
            const t = ctx.currentTime;
            const beep = (freq, start, dur, vol) => {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.type = 'sine';
                o.frequency.value = freq;
                g.gain.setValueAtTime(0.0001, t + start);
                g.gain.exponentialRampToValueAtTime(vol, t + start + 0.01);
                g.gain.exponentialRampToValueAtTime(0.0001, t + start + dur);
                o.connect(g);
                g.connect(ctx.destination);
                o.start(t + start);
                o.stop(t + start + dur + 0.02);
            };
            beep(1200, 0, 0.1, 0.55);
            beep(1500, 0.12, 0.12, 0.5);
        } catch (e) {}
    };

    const poll = async () => {
        if (!pollUrl) return;
        if (window.__bxMessengerPolling) return;
        window.__bxMessengerPolling = true;
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
            const maxId = parseInt(data.max_id || '0', 10) || 0;

            (data.messages || []).forEach((m) => appendMessage(m));

            if (data.sound && maxId > lastBeepMaxId) {
                const now = Date.now();
                if (now - lastBeepAt > 1200) {
                    playNotifySound();
                    lastBeepAt = now;
                }
                lastBeepMaxId = maxId;
                sessionStorage.setItem(beepKey, String(lastBeepMaxId));
            }
            if (maxId > since) {
                since = maxId;
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
                if (c.preview != null) {
                    const preview = link.querySelector('.bx-chat-item__preview');
                    if (preview) preview.textContent = c.preview || 'Нет сообщений';
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
        finally {
            window.__bxMessengerPolling = false;
        }
    };

    if (pollUrl) {
        poll();
        window.__bxMessengerPollTimer = setInterval(poll, 2000);
    }
})();
</script>
