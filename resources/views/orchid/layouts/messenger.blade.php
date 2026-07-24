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
                <a href="{{ route('platform.systems.chats.view', $item) }}"
                   class="bx-chat-item {{ (int)$activeId === (int)$item->id ? 'is-active' : '' }} {{ !empty($item->is_muted) ? 'is-muted' : '' }}">
                    <div class="bx-chat-item__avatar">
                        {{ mb_strtoupper(mb_substr($item->displayTitle(), 0, 1)) }}
                    </div>
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
                    <div class="bx-composer__input-wrap">
                        <div class="bx-composer__highlight" id="bx-composer-highlight" aria-hidden="true"></div>
                        <textarea name="message[text]"
                                  id="bx-composer-input"
                                  class="bx-composer__input"
                                  form="post-form"
                                  rows="1"
                                  placeholder="Написать сообщение… @имя — упомянуть, Enter — отправить"></textarea>
                    </div>

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
                                <div class="bx-composer__menu" data-bx-menu="task">
                                    <div class="bx-composer__menu-title">Прикрепить задачу</div>
                                    <select name="message[task_id]" class="bx-composer__select" form="post-form">
                                        <option value="">Без задачи</option>
                                        @foreach($taskOptions as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <button type="button" class="bx-composer__tool" id="bx-tool-mention" title="Упомянуть (@)">
                                <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 8a6 6 0 11-4.5 10.1"/><circle cx="12" cy="12" r="10"/><path d="M16 12a4 4 0 10-4 4"/></svg>
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
    const highlight = document.getElementById('bx-composer-highlight');
    const parentInput = document.getElementById('chat-message-parent-id');
    const replyBanner = document.getElementById('bx-reply-banner');
    const replyAuthor = document.getElementById('bx-reply-author');
    const filesInput = document.getElementById('bx-composer-files');
    const filesLabel = document.getElementById('bx-files-label');
    const mentionMenu = document.getElementById('bx-mention-menu');
    const composer = document.getElementById('bx-composer');

    let mentionUsers = [];
    try {
        mentionUsers = JSON.parse(composer?.getAttribute('data-mentions') || '[]');
    } catch (e) { mentionUsers = []; }

    const escapeHtml = (s) => String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const mentionNames = mentionUsers
        .flatMap((u) => (u.aliases || [u.name]).map((a) => String(a)))
        .filter(Boolean)
        .sort((a, b) => b.length - a.length);

    const renderHighlight = () => {
        if (!input || !highlight) return;
        let text = input.value || '';
        let html = escapeHtml(text);
        mentionNames.forEach((name) => {
            const re = new RegExp('(^|[^\\w])(@' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')(?![\\w])', 'gu');
            html = html.replace(re, '$1<span class="bx-mention">$2</span>');
        });
        highlight.innerHTML = html.replace(/\n$/g, '\n\n').replace(/\n/g, '<br>');
    };

    const autosize = () => {
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
        if (highlight) highlight.style.height = input.style.height;
        renderHighlight();
    };
    input?.addEventListener('input', () => {
        autosize();
        updateMentionMenu();
    });
    input?.addEventListener('scroll', () => {
        if (highlight) highlight.scrollTop = input.scrollTop;
    });
    input?.addEventListener('keydown', (e) => {
        if (mentionMenu && !mentionMenu.classList.contains('d-none')) {
            const items = [...mentionMenu.querySelectorAll('[data-mention-name]')];
            const active = mentionMenu.querySelector('.is-active');
            let idx = items.indexOf(active);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                items[(idx + 1) % items.length]?.classList.add('is-active');
                active?.classList.remove('is-active');
                if (idx < 0) items[0]?.classList.add('is-active');
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                items[(idx - 1 + items.length) % items.length]?.classList.add('is-active');
                active?.classList.remove('is-active');
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
        input.setSelectionRange(caret, caret);
        hideMentionMenu();
        autosize();
        input.focus();
    };

    mentionMenu?.addEventListener('click', (e) => {
        const btn = e.target.closest?.('[data-mention-name]');
        if (btn) insertMention(btn.getAttribute('data-mention-name'));
    });

    document.getElementById('bx-tool-mention')?.addEventListener('click', () => {
        if (!input) return;
        const pos = input.selectionStart ?? input.value.length;
        input.value = input.value.slice(0, pos) + '@' + input.value.slice(pos);
        input.setSelectionRange(pos + 1, pos + 1);
        input.focus();
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
            const url = pollUrl + (since ? ('?since=' + since) : '');
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
            const badge = document.querySelector('[href*="chats"] .badge, .menu .badge');
            // leave Orchid menu badge to page reload; update sidebar badges if present
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
        } catch (e) {}
    };

    if (pollUrl) {
        poll();
        setInterval(poll, 5000);
    }
})();
</script>
