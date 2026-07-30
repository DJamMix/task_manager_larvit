<script>
    document.documentElement.classList.add('bx-messenger-page');
    if (document.body) document.body.classList.add('bx-messenger-page');
    else document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bx-messenger-page'));
    document.addEventListener('turbo:load', () => {
        if (!document.querySelector('.bx-messenger')) {
            document.documentElement.classList.remove('bx-messenger-page');
            document.body?.classList.remove('bx-messenger-page', 'bx-messenger-mobile');
        }
    });
</script>
<div class="bx-messenger {{ ($active_chat_id ?? null) ? 'is-chat-open' : 'is-list-open' }}"
     data-poll-url="{{ $chats_poll_url ?? route('platform.systems.chats.poll') }}"
     data-active-chat="{{ $active_chat_id ?? '' }}"
     data-send-url="{{ ($active_chat_id ?? null) ? url()->current() . '/sendMessage' : '' }}"
     data-messages-url="{{ $chats_messages_url ?? '' }}"
     data-typing-url="{{ $chats_typing_url ?? '' }}"
     data-chat-type="{{ $chat?->type ?? '' }}"
     data-self-id="{{ auth()->id() }}"
     data-has-more="{{ !empty($messages_has_more) ? '1' : '0' }}"
     data-oldest-id="{{ $messages_oldest_id ?? '' }}"
     data-calls-enabled="{{ !empty($calls_enabled) ? '1' : '0' }}"
     data-calls-start-url="{{ $calls_start_url ?? '' }}"
     data-call-join-tpl="{{ str_replace('999999', '__ID__', route('platform.systems.chats.calls.join', ['call' => 999999])) }}"
     data-forward-url="{{ ($active_chat_id ?? null) ? route('platform.systems.chats.forward', $active_chat_id) : '' }}"
     data-chats-picker-url="{{ $chats_picker_url ?? route('platform.systems.chats.picker') }}"
     data-media-url="{{ $chats_media_url ?? '' }}"
     data-vapid-key-url="{{ route('platform.web-push.vapid-key') }}"
     data-push-subscribe-url="{{ route('platform.web-push.subscribe') }}"
     data-push-unsubscribe-url="{{ route('platform.web-push.unsubscribe') }}"
     data-push-test-url="{{ route('platform.web-push.test') }}"
     data-push-configured="{{ app(\App\Services\WebPushService::class)->isConfigured() ? '1' : '0' }}"
     data-csrf="{{ csrf_token() }}">
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
        $presence = $presence ?? [];
        $presenceOnline = static fn (?int $uid): bool => (bool) ($uid && !empty($presence[$uid] ?? $presence[(string) $uid] ?? false));
    @endphp

    <aside class="bx-messenger__sidebar">
        <div class="bx-messenger__sidebar-head">
            <div class="bx-messenger__sidebar-title">
                <strong>Чаты</strong>
                <span class="badge text-bg-light border">{{ $chatList->count() }}</span>
            </div>
            <div class="bx-messenger__sidebar-actions">
                <button type="button"
                        class="bx-sidebar-action"
                        data-bx-open-modal="createDirectModal"
                        title="Начать личный чат">
                    <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Личный</span>
                </button>
                @if(!empty($can_create))
                    <button type="button"
                            class="bx-sidebar-action bx-sidebar-action--primary"
                            data-bx-open-modal="createChatModal"
                            title="Создать групповой чат">
                        <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M5 12h14"/></svg>
                        <span>Группа</span>
                    </button>
                @endif
            </div>
        </div>
        <div class="bx-messenger__search">
            <input type="search"
                   id="bx-chat-search"
                   class="bx-messenger__search-input"
                   placeholder="Поиск по чатам и сообщениям…"
                   autocomplete="off"
                   data-search-url="{{ $chats_search_url ?? route('platform.systems.chats.search') }}">
        </div>

        <div class="bx-messenger__list" id="bx-chat-list">
            @forelse($chatList as $item)
                @php $peer = $item->type === 'direct' ? $item->otherMember() : null; @endphp
                <a href="{{ route('platform.systems.chats.view', $item) }}"
                   class="bx-chat-item {{ (int)$activeId === (int)$item->id ? 'is-active' : '' }} {{ !empty($item->is_muted) ? 'is-muted' : '' }} {{ !empty($item->is_pinned) ? 'is-pinned' : '' }}"
                   data-chat-id="{{ $item->id }}"
                   data-peer-id="{{ $peer?->id ?? '' }}"
                   data-title="{{ mb_strtolower($item->displayTitle()) }}">
                    @if($item->type === 'direct')
                        @include('orchid.layouts.partials.bx-avatar', [
                            'avatarUser' => $peer,
                            'avatarChat' => null,
                            'size' => 'md',
                            'shape' => 'round',
                            'showOnline' => true,
                            'isOnline' => $presenceOnline($peer?->id),
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
        <div class="bx-search-panel d-none" id="bx-search-panel" hidden>
            <div class="bx-search-panel__section" id="bx-search-chats-wrap">
                <div class="bx-search-panel__title">Чаты</div>
                <div class="bx-search-panel__list" id="bx-search-chats"></div>
            </div>
            <div class="bx-search-panel__section" id="bx-search-msgs-wrap">
                <div class="bx-search-panel__title">Сообщения</div>
                <div class="bx-search-panel__list" id="bx-search-msgs"></div>
            </div>
            <div class="bx-search-panel__empty d-none" id="bx-search-empty">Ничего не найдено</div>
        </div>
    </aside>

    <section class="bx-messenger__main">
        @if($active)
            <div class="bx-messenger__header">
                <div class="bx-messenger__header-main">
                    <a href="{{ route('platform.systems.chats') }}" class="bx-back-chat" title="К списку чатов" aria-label="Назад">
                        <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M15 18l-6-6 6-6"/></svg>
                    </a>
                    @php $headerPeer = $active->type === 'direct' ? $active->otherMember() : null; @endphp
                    <button type="button" class="bx-chat-identity" id="bx-open-chat-info" title="Информация о чате">
                        @if($active->type === 'direct')
                            @include('orchid.layouts.partials.bx-avatar', [
                                'avatarUser' => $headerPeer,
                                'avatarChat' => null,
                                'size' => 'lg',
                                'shape' => 'round',
                                'showOnline' => true,
                                'isOnline' => $presenceOnline($headerPeer?->id),
                            ])
                        @else
                            @include('orchid.layouts.partials.bx-avatar', [
                                'avatarChat' => $active,
                                'avatarUser' => null,
                                'size' => 'lg',
                                'shape' => 'square',
                            ])
                        @endif
                        <div class="bx-chat-identity__text">
                            <h2 class="h5 mb-0 bx-chat-identity__title">{{ $active->displayTitle() }}</h2>
                            @php
                                $memberCount = $active->members->count();
                                $memberWord = $memberCount === 1 ? 'участник' : (
                                    ($memberCount % 10 >= 2 && $memberCount % 10 <= 4 && !in_array($memberCount % 100, [12, 13, 14], true))
                                        ? 'участника'
                                        : 'участников'
                                );
                                $defaultSubtitle = $active->type === 'direct'
                                    ? ($presenceOnline($headerPeer?->id) ? 'в сети' : 'не в сети')
                                    : ($memberCount . ' ' . $memberWord);
                            @endphp
                            <span class="bx-chat-subtitle"
                                  id="bx-open-members"
                                  data-default-subtitle="{{ $defaultSubtitle }}"
                                  data-member-count="{{ $memberCount }}"
                                  data-peer-id="{{ $headerPeer?->id ?? '' }}">
                                {{ $defaultSubtitle }}
                            </span>
                        </div>
                    </button>
                </div>
                <div class="bx-messenger__header-actions">
                    @if(!empty($calls_enabled) && !empty($calls_start_url))
                        <button type="button"
                                class="bx-icon-btn bx-call-btn"
                                id="bx-call-audio"
                                title="Аудиозвонок"
                                aria-label="Аудиозвонок"
                                data-video="0">
                            <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                        </button>
                        <button type="button"
                                class="bx-icon-btn bx-call-btn"
                                id="bx-call-video"
                                title="Видеозвонок"
                                aria-label="Видеозвонок"
                                data-video="1">
                            <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                        </button>
                    @endif
                    <div class="bx-header-menu" id="bx-header-menu">
                        <button type="button"
                                class="bx-icon-btn"
                                id="bx-header-gear"
                                title="Настройки чата"
                                aria-label="Настройки"
                                aria-expanded="false"
                                aria-controls="bx-header-menu-drop">
                            <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                        </button>
                        <div class="bx-header-menu__drop" id="bx-header-menu-drop" hidden>
                            <button type="submit"
                                    class="bx-header-menu__item {{ $isPinned ? 'is-active' : '' }}"
                                    formaction="{{ url()->current() }}/togglePin"
                                    form="post-form">
                                <svg class="bx-icon" viewBox="0 0 24 24" fill="{{ $isPinned ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                                <span>{{ $isPinned ? 'Открепить' : 'Закрепить' }}</span>
                            </button>
                            <button type="submit"
                                    class="bx-header-menu__item {{ $isMuted ? 'is-muted' : '' }}"
                                    formaction="{{ url()->current() }}/toggleMute"
                                    form="post-form">
                                @if($isMuted)
                                    <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 5L6 9H2v6h4l5 4V5z"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
                                    <span>Включить звук</span>
                                @else
                                    <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07"/></svg>
                                    <span>Без звука</span>
                                @endif
                            </button>
                            <div class="bx-header-menu__item bx-header-menu__item--static">
                                <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.54 8.46a5 5 0 010 7.07"/></svg>
                                <span>Громкость</span>
                                <input type="range" id="bx-notify-volume" min="0" max="100" step="5" value="75" class="bx-header-menu__range">
                                <span id="bx-notify-volume-label" class="bx-header-menu__pct">75%</span>
                            </div>
                            <div class="bx-header-menu__sep"></div>
                            <div class="bx-push-settings" id="bx-push-settings">
                                <div class="bx-header-menu__item bx-header-menu__item--static">
                                    <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                                    <span>Push-уведомления</span>
                                    <span class="bx-push-status" id="bx-push-status">Проверка…</span>
                                </div>
                                <button type="button" class="bx-header-menu__item" id="bx-enable-push">
                                    <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                                    <span id="bx-push-action-label">Включить push</span>
                                </button>
                                <button type="button" class="bx-header-menu__item" id="bx-test-push" hidden>
                                    <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
                                    <span>Проверить push</span>
                                </button>
                                <div class="bx-push-hint" id="bx-push-hint" hidden></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="bx-active-call-bar" class="bx-active-call-bar" hidden>
                <div id="bx-active-call-text">Идёт звонок</div>
                <div class="bx-active-call-bar__actions">
                    <button type="button" class="btn btn-sm btn-success" id="bx-active-call-join">Присоединиться</button>
                </div>
            </div>

            <div class="bx-messenger__feed" id="chat-feed">
                <div class="bx-feed-older {{ !empty($messages_has_more) ? '' : 'd-none' }}" id="bx-feed-older">
                    <button type="button" class="bx-feed-older__btn" id="bx-load-older">Загрузить ещё</button>
                    <span class="bx-feed-older__spin d-none" id="bx-load-older-spin">Загрузка…</span>
                </div>
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

            <div id="bx-typing" class="bx-typing d-none" aria-live="polite"></div>

            <div class="bx-selection-bar" id="bx-selection-bar" hidden>
                <span id="bx-selection-count">Выбрано: 0</span>
                <div>
                    <button type="button" class="btn btn-sm btn-primary" id="bx-forward-selected">Переслать (0)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="bx-selection-cancel">Отмена</button>
                </div>
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

                            <label class="bx-composer__tool" title="Файлы (до 10)">
                                <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                                <input type="file"
                                       name="message_files[]"
                                       id="bx-composer-files"
                                       class="d-none"
                                       form="post-form"
                                       multiple
                                       accept="image/*,.pdf,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx,.txt,.php,.js,.ts,.json,.sql,.css,.exe,.msi,audio/*,video/*">
                            </label>

                            <button type="button" class="bx-composer__tool" id="bx-tool-voice" title="Голосовое (до 3 мин). Удерживайте — проверить микрофон">
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
                            <div class="bx-composer__files-preview d-none" id="bx-files-preview"></div>
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
                        <span class="bx-voice-record__meter" title="Уровень микрофона" aria-hidden="true">
                            <span class="bx-voice-record__meter-fill" id="bx-voice-meter"></span>
                        </span>
                    </div>
                    <label class="bx-voice-record__mic">
                        <select id="bx-voice-mic" class="bx-mic-select" title="Микрофон"></select>
                    </label>
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
                <label class="bx-notify-vol bx-notify-vol--empty" title="Громкость звука новых сообщений">
                    Громкость уведомлений
                    <input type="range" id="bx-notify-volume-empty" min="0" max="100" step="5" value="75">
                    <span id="bx-notify-volume-empty-label">75%</span>
                </label>
            </div>
        @endif
    </section>

    <div id="bx-mic-gate" class="bx-mic-gate" hidden>
        <div class="bx-mic-gate__card" role="dialog" aria-modal="true" aria-labelledby="bx-mic-gate-title">
            <h3 id="bx-mic-gate-title" class="bx-mic-gate__title">Нужен микрофон</h3>
            <p class="bx-mic-gate__text" id="bx-mic-gate-text">
                Разрешите доступ, выберите нужный микрофон и <strong>скажите несколько слов</strong> —
                полоска должна двигаться. Иначе голосовое уйдёт без звука.
            </p>
            <label class="bx-mic-gate__device">
                Микрофон
                <select id="bx-mic-gate-device" class="bx-mic-select form-select form-select-sm">
                    <option value="">По умолчанию</option>
                </select>
            </label>
            <div class="bx-mic-gate__meter"><span id="bx-mic-gate-meter"></span></div>
            <p class="bx-mic-gate__hint" id="bx-mic-gate-hint"></p>
            <div class="bx-mic-gate__actions">
                <button type="button" class="btn btn-primary" id="bx-mic-gate-retry">Разрешить / проверить</button>
                <button type="button" class="btn btn-link" id="bx-mic-gate-close">Закрыть</button>
            </div>
        </div>
    </div>

    @if($active)
        <div class="bx-sheet" id="bx-chat-info" hidden>
            <button type="button" class="bx-sheet__backdrop" id="bx-chat-info-close-bg" aria-label="Закрыть"></button>
            <div class="bx-sheet__panel bx-chat-info__panel" role="dialog" aria-modal="true" aria-labelledby="bx-chat-info-title">
                <div class="bx-sheet__head">
                    <strong id="bx-chat-info-title">{{ $active->displayTitle() }}</strong>
                    <button type="button" class="bx-sheet__close" id="bx-chat-info-close" aria-label="Закрыть">×</button>
                </div>
                <div class="bx-chat-info__tabs" role="tablist">
                    <button type="button" class="is-active" data-info-tab="members">Участники</button>
                    <button type="button" data-info-tab="media">Медиа</button>
                    <button type="button" data-info-tab="files">Файлы</button>
                    <button type="button" data-info-tab="links">Ссылки</button>
                </div>
                <div class="bx-chat-info__body">
                    <div class="bx-chat-info__pane is-active" data-info-pane="members">
                        <ul class="bx-members-modal__list" id="bx-members-list">
                            @foreach($active->members->sortBy(fn ($u) => mb_strtolower($u->displayName())) as $member)
                                <li class="bx-members-modal__item" data-user-id="{{ $member->id }}">
                                    @include('orchid.layouts.partials.bx-avatar', [
                                        'avatarUser' => $member,
                                        'avatarChat' => null,
                                        'size' => 'md',
                                        'shape' => 'round',
                                        'showOnline' => true,
                                        'isOnline' => $presenceOnline($member->id),
                                    ])
                                    <div class="bx-members-modal__meta">
                                        <div class="bx-members-modal__name">{{ $member->displayName() }}</div>
                                        <div class="bx-members-modal__status {{ $presenceOnline($member->id) ? 'is-online' : '' }}"
                                             data-online-label="в сети"
                                             data-offline-label="{{ $member->pivot?->role === 'owner' ? 'владелец' : ($member->position ?: 'не в сети') }}">
                                            @if($presenceOnline($member->id))
                                                в сети
                                            @elseif($member->pivot?->role === 'owner')
                                                владелец
                                            @elseif($member->position)
                                                {{ $member->position }}
                                            @else
                                                не в сети
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="bx-chat-info__pane" data-info-pane="gallery" hidden>
                        <div id="bx-media-content" class="bx-media-content">Загрузка…</div>
                        <button type="button" class="btn btn-sm btn-outline-secondary d-none mt-2" id="bx-media-more">Загрузить ещё</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<div class="bx-sheet" id="bx-forward-sheet" hidden>
    <button type="button" class="bx-sheet__backdrop" id="bx-forward-close-bg" aria-label="Закрыть"></button>
    <div class="bx-sheet__panel bx-forward-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="bx-forward-title">
        <div class="bx-sheet__head">
            <strong id="bx-forward-title">Переслать сообщения</strong>
            <button type="button" class="bx-sheet__close" id="bx-forward-close" aria-label="Закрыть">×</button>
        </div>
        <div class="bx-forward-search">
            <input type="search"
                   id="bx-forward-search"
                   class="bx-forward-search__input"
                   placeholder="Поиск по чатам и пользователям…"
                   autocomplete="off">
        </div>
        <div id="bx-forward-chats" class="bx-forward-chats">Загрузка чатов…</div>
    </div>
</div>

<div id="bx-lightbox" class="bx-lightbox" hidden>
    <button type="button" class="bx-lightbox__backdrop" data-bx-lightbox-close aria-label="Закрыть"></button>
    <div class="bx-lightbox__panel" role="dialog" aria-modal="true">
        <button type="button" class="bx-lightbox__close" data-bx-lightbox-close aria-label="Закрыть">×</button>
        <img class="bx-lightbox__img" src="" alt="">
        <a class="bx-lightbox__open" href="#" target="_blank" rel="noopener">Открыть в новой вкладке</a>
    </div>
</div>

<div id="bx-incoming-call" class="bx-incoming-call" hidden>
    <div class="bx-incoming-call__card">
        <div class="bx-incoming-call__row">
            <div class="bx-incoming-call__avatar" id="bx-incoming-avatar"></div>
            <div>
                <div class="bx-incoming-call__title" id="bx-incoming-title">Входящий звонок</div>
                <div class="bx-incoming-call__sub" id="bx-incoming-sub"></div>
            </div>
        </div>
        <div class="bx-incoming-call__actions">
            <button type="button" class="btn btn-success" id="bx-incoming-accept">Ответить</button>
            <button type="button" class="btn btn-outline-danger" id="bx-incoming-decline">Отклонить</button>
        </div>
    </div>
</div>

<div id="bx-call-stage" class="bx-call-stage" hidden>
    <div class="bx-call-stage__top">
        <div>
            <div class="bx-call-stage__title" id="bx-call-title">Звонок</div>
            <div class="bx-call-stage__secure" title="Шифрование медиа DTLS-SRTP, канал WSS/TLS">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                Защищённый звонок
            </div>
        </div>
        <div class="bx-call-stage__meta">
            <span class="bx-call-stage__count" id="bx-call-count"></span>
            <span class="bx-call-stage__timer" id="bx-call-timer">00:00</span>
        </div>
    </div>

    <div class="bx-call-stage__body">
        <div class="bx-call-focus" id="bx-call-focus"></div>
        <div class="bx-call-pip-layer" id="bx-call-pip-layer"></div>
        <div class="bx-call-stage__grid" id="bx-call-grid"></div>
    </div>

    <div class="bx-call-strip-wrap" id="bx-call-strip-wrap" hidden>
        <button type="button" class="bx-call-strip-toggle" id="bx-call-strip-toggle" title="Скрыть / показать участников">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
            <span id="bx-call-strip-label">Участники</span>
        </button>
        <div class="bx-call-strip" id="bx-call-strip"></div>
    </div>

    <div id="bx-call-devices-panel" class="bx-call-devices" hidden>
        <div class="bx-call-devices__head">
            <strong>Устройства</strong>
            <button type="button" class="bx-call-devices__close" id="bx-call-devices-close" title="Закрыть" aria-label="Закрыть">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <label class="bx-call-devices__row">
            <span class="bx-call-devices__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg>
            </span>
            <span class="bx-call-devices__label">Микрофон</span>
            <select id="bx-call-mic-select"></select>
        </label>
        <label class="bx-call-devices__row">
            <span class="bx-call-devices__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
            </span>
            <span class="bx-call-devices__label">Камера</span>
            <select id="bx-call-cam-select"></select>
        </label>
        <div class="bx-call-devices__guest" id="bx-call-guest-box" hidden>
            <div class="bx-call-devices__guest-title">Гостевая ссылка</div>
            <p class="bx-call-devices__guest-hint">Гости войдут без аккаунта и смогут говорить.</p>
            <div class="bx-call-devices__guest-row">
                <input type="text" id="bx-call-guest-url" readonly placeholder="Ссылка ещё не создана">
                <button type="button" class="bx-call-devices__btn" id="bx-call-guest-copy" title="Копировать">Копировать</button>
            </div>
            <div class="bx-call-devices__guest-actions">
                <button type="button" class="bx-call-devices__btn bx-call-devices__btn--accent" id="bx-call-guest-create">Создать / обновить</button>
                <button type="button" class="bx-call-devices__btn bx-call-devices__btn--mute" id="bx-call-guest-revoke">Отключить</button>
            </div>
        </div>
    </div>

    <div class="bx-call-stage__bar">
        <button type="button" class="bx-call-ctrl" id="bx-call-mic" title="Микрофон">
            <span class="bx-call-ctrl__ico bx-call-ctrl__ico--on" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg>
            </span>
            <span class="bx-call-ctrl__ico bx-call-ctrl__ico--off" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 9v3a3 3 0 005.12 2.12M15 9.34V4a3 3 0 00-5.94-.6"/><path d="M17 16.95A7 7 0 015 12v-2m14 0v2a7 7 0 01-.11 1.23M12 19v4M8 23h8M1 1l22 22"/></svg>
            </span>
        </button>
        <button type="button" class="bx-call-ctrl" id="bx-call-cam" title="Камера">
            <span class="bx-call-ctrl__ico bx-call-ctrl__ico--on" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
            </span>
            <span class="bx-call-ctrl__ico bx-call-ctrl__ico--off" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 16v1a2 2 0 01-2 2H3a2 2 0 01-2-2V7a2 2 0 012-2h2m5.66 0H14a2 2 0 012 2v3.34l1 1L23 7v10"/><path d="M1 1l22 22"/></svg>
            </span>
        </button>
        <button type="button" class="bx-call-ctrl" id="bx-call-screen" title="Демонстрация экрана">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        </button>
        <button type="button" class="bx-call-ctrl" id="bx-call-devices" title="Устройства и гости">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
        </button>
        <button type="button" class="bx-call-ctrl bx-call-ctrl--danger" id="bx-call-hang" title="Выйти">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10.68 13.31a16 16 0 003.41 2.6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7 2 2 0 011.72 2v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.42 19.42 0 01-3.33-2.67m-2.67-3.34a19.79 19.79 0 01-3.07-8.63A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91"/><path d="M22 2L2 22"/></svg>
        </button>
        <button type="button" class="bx-call-ctrl bx-call-ctrl--end-all" id="bx-call-end-all" title="Завершить для всех" hidden>
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
            <span class="bx-call-ctrl__txt">Всем</span>
        </button>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>
(() => {
    // Единая функция экранирования (не дублировать const escapeHtml — ломает Turbo morph)
    const escapeHtml = (s) => String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

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
        document.body.classList.add('bx-messenger-page');
        document.documentElement.classList.add('bx-messenger-page');
        const mobile = window.matchMedia('(max-width: 1199.98px)').matches;
        document.body.classList.toggle('bx-messenger-mobile', mobile);
        if (!mobile) {
            // На десктопе высоту даёт flex-раскладка app-shell + messenger workspace
            root.style.height = '';
            root.style.maxHeight = '';
            return;
        }
        const top = root.getBoundingClientRect().top;
        const available = Math.max(260, window.innerHeight - top);
        root.style.height = available + 'px';
        root.style.maxHeight = available + 'px';
    };
    document.body.classList.add('bx-messenger-page');
    lockMessengerHeight();
    window.addEventListener('resize', lockMessengerHeight);
    window.addEventListener('orientationchange', () => setTimeout(lockMessengerHeight, 250));
    window.visualViewport?.addEventListener('resize', lockMessengerHeight);
    const input = document.getElementById('bx-composer-input');
    const parentInput = document.getElementById('chat-message-parent-id');
    const replyBanner = document.getElementById('bx-reply-banner');
    const replyAuthor = document.getElementById('bx-reply-author');
    const filesInput = document.getElementById('bx-composer-files');
    const filesLabel = document.getElementById('bx-files-label');
    const filesPreview = document.getElementById('bx-files-preview');
    const FILES_MAX = 10;
    let pendingFiles = [];

    const syncFilesInput = () => {
        if (!filesInput) return;
        const dt = new DataTransfer();
        pendingFiles.forEach((f) => dt.items.add(f));
        filesInput.files = dt.files;
        renderFilesPreview();
    };

    const renderFilesPreview = () => {
        if (!filesPreview || !filesLabel) return;
        const n = pendingFiles.length;
        if (!n) {
            filesPreview.classList.add('d-none');
            filesPreview.innerHTML = '';
            filesLabel.classList.add('d-none');
            filesLabel.textContent = '';
            return;
        }
        filesLabel.textContent = n + '/' + FILES_MAX;
        filesLabel.classList.remove('d-none');
        filesPreview.classList.remove('d-none');
        filesPreview.innerHTML = pendingFiles.map((f, idx) => {
            const isImg = /^image\//.test(f.type || '');
            const url = isImg ? URL.createObjectURL(f) : '';
            const name = escapeHtml(f.name || 'файл');
            return `<div class="bx-file-chip" data-idx="${idx}" title="${name}">
                ${isImg ? `<img src="${url}" alt="">` : `<span class="bx-file-chip__ext">${escapeHtml((f.name || '').split('.').pop() || 'file')}</span>`}
                <span class="bx-file-chip__name">${name}</span>
                <button type="button" class="bx-file-chip__rm" data-rm="${idx}" aria-label="Убрать">×</button>
            </div>`;
        }).join('');
    };

    filesPreview?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-rm]');
        if (!btn) return;
        const idx = parseInt(btn.getAttribute('data-rm'), 10);
        if (!Number.isFinite(idx)) return;
        pendingFiles.splice(idx, 1);
        syncFilesInput();
    });

    filesInput?.addEventListener('change', () => {
        const incoming = [...(filesInput.files || [])];
        if (!incoming.length) return;
        const room = FILES_MAX - pendingFiles.length;
        if (room <= 0) {
            alert('Можно прикрепить не больше ' + FILES_MAX + ' файлов за раз');
            syncFilesInput();
            return;
        }
        const add = incoming.slice(0, room);
        if (incoming.length > room) {
            alert('Добавлено ' + add.length + ' из ' + incoming.length + ' (лимит ' + FILES_MAX + ')');
        }
        pendingFiles = pendingFiles.concat(add);
        syncFilesInput();
    });
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

    /* Галерея чата (внутри окна информации) */
    const mediaContent = document.getElementById('bx-media-content');
    const mediaMore = document.getElementById('bx-media-more');
    let mediaTab = 'media';
    let mediaPage = 1;
    const loadMedia = async (replace) => {
        const mediaUrl = root?.getAttribute('data-media-url');
        if (!mediaUrl || !mediaContent) return;
        if (replace) mediaContent.textContent = 'Загрузка…';
        mediaContent.classList.toggle('bx-media-content--list', mediaTab !== 'media');
        try {
            const response = await fetch(`${mediaUrl}?tab=${encodeURIComponent(mediaTab)}&page=${mediaPage}`, {
                credentials: 'same-origin', headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Не удалось загрузить материалы');
            const html = (data.items || []).map((item) => {
                if (mediaTab === 'media') {
                    return `<a class="bx-media-image" href="${escapeHtml(item.url)}" data-bx-lightbox="${escapeHtml(item.url)}" title="${escapeHtml(item.name)}"><img src="${escapeHtml(item.url)}" alt="${escapeHtml(item.name)}" loading="lazy"></a>`;
                }
                if (mediaTab === 'files') {
                    return `<a class="bx-media-file" href="${escapeHtml(item.download_url)}">${escapeHtml(item.name)}</a>`;
                }
                return `<a class="bx-media-link" href="${escapeHtml(item.url)}" target="_blank" rel="noopener">${escapeHtml(item.url)}</a>`;
            }).join('') || (replace ? '<div class="text-muted">Пока ничего нет</div>' : '');
            if (replace) mediaContent.innerHTML = html;
            else mediaContent.insertAdjacentHTML('beforeend', html);
            mediaMore?.classList.toggle('d-none', !data.has_more);
        } catch (error) {
            if (replace) mediaContent.textContent = error.message || 'Не удалось загрузить материалы';
        }
    };

    /* Chat info (участники + медиа) */
    const chatInfo = document.getElementById('bx-chat-info');
    const switchInfoTab = (tab) => {
        const isGallery = tab === 'media' || tab === 'files' || tab === 'links';
        document.querySelectorAll('[data-info-tab]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.getAttribute('data-info-tab') === tab);
        });
        document.querySelectorAll('[data-info-pane]').forEach((pane) => {
            const name = pane.getAttribute('data-info-pane');
            const on = name === 'members' ? tab === 'members' : (name === 'gallery' && isGallery);
            pane.classList.toggle('is-active', on);
            pane.toggleAttribute('hidden', !on);
        });
        if (isGallery) {
            mediaTab = tab;
            mediaPage = 1;
            loadMedia(true);
        }
    };
    const openChatInfo = (tab = 'members') => {
        chatInfo?.removeAttribute('hidden');
        switchInfoTab(tab);
    };
    const closeChatInfo = () => chatInfo?.setAttribute('hidden', '');
    document.getElementById('bx-open-chat-info')?.addEventListener('click', () => openChatInfo('members'));
    document.getElementById('bx-chat-info-close')?.addEventListener('click', closeChatInfo);
    document.getElementById('bx-chat-info-close-bg')?.addEventListener('click', closeChatInfo);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && chatInfo && !chatInfo.hasAttribute('hidden')) {
            closeChatInfo();
        }
    });
    document.querySelectorAll('[data-info-tab]').forEach((btn) => {
        btn.addEventListener('click', () => switchInfoTab(btn.getAttribute('data-info-tab') || 'members'));
    });
    mediaMore?.addEventListener('click', () => {
        mediaPage++;
        loadMedia(false);
    });

    /* Меню настроек (шестерёнка) */
    const gearBtn = document.getElementById('bx-header-gear');
    const gearDrop = document.getElementById('bx-header-menu-drop');
    const closeGear = () => {
        gearDrop?.setAttribute('hidden', '');
        gearBtn?.setAttribute('aria-expanded', 'false');
    };
    gearBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const open = gearDrop?.hasAttribute('hidden');
        if (open) {
            gearDrop.removeAttribute('hidden');
            gearBtn.setAttribute('aria-expanded', 'true');
        } else {
            closeGear();
        }
    });
    // На мобилках click по document иногда срабатывает в том же тике — закрываем на следующем кадре.
    document.addEventListener('click', (e) => {
        if (!gearDrop || gearDrop.hasAttribute('hidden')) return;
        if (e.target.closest?.('#bx-header-menu') || e.target.closest?.('#bx-header-gear')) return;
        closeGear();
    });
    document.addEventListener('touchstart', (e) => {
        if (!gearDrop || gearDrop.hasAttribute('hidden')) return;
        if (e.target.closest?.('#bx-header-menu') || e.target.closest?.('#bx-header-gear')) return;
        closeGear();
    }, { passive: true });

    const csrf = document.querySelector('meta[name="csrf_token"]')?.content
        || document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
        || '';

    /* Выбор и пересылка сообщений */
    const selectedMessageIds = new Set();
    const selectionBar = document.getElementById('bx-selection-bar');
    const selectionCount = document.getElementById('bx-selection-count');
    const forwardSelected = document.getElementById('bx-forward-selected');
    const forwardSheet = document.getElementById('bx-forward-sheet');
    const forwardChats = document.getElementById('bx-forward-chats');
    const updateSelection = () => {
        const count = selectedMessageIds.size;
        selectionBar?.toggleAttribute('hidden', count === 0);
        if (selectionCount) selectionCount.textContent = 'Выбрано: ' + count;
        if (forwardSelected) forwardSelected.textContent = 'Переслать (' + count + ')';
        root?.classList.toggle('is-selecting', count > 0);
        document.querySelectorAll('.bx-msg:not(.bx-msg--system)').forEach((message) => {
            const id = Number(String(message.id || '').replace('chat-msg-', ''));
            message.classList.toggle('is-selected', selectedMessageIds.has(id));
        });
    };
    const toggleMessageSelection = (id) => {
        if (!id) return;
        if (selectedMessageIds.has(id)) selectedMessageIds.delete(id);
        else if (selectedMessageIds.size < 20) selectedMessageIds.add(id);
        updateSelection();
    };

    // Удержание ЛКМ / long-press как в Telegram; Ctrl/Cmd+клик — toggle
    document.querySelectorAll('.bx-msg:not(.bx-msg--system)').forEach((message) => {
        let timer = null;
        let holdFired = false;
        const msgId = () => Number(String(message.id || '').replace('chat-msg-', ''));

        message.addEventListener('pointerdown', (e) => {
            if (e.button !== undefined && e.button !== 0) return;
            if (e.target.closest('button,a,input,textarea,.bx-voice')) return;
            holdFired = false;
            message._holdStart = { x: e.clientX || 0, y: e.clientY || 0 };
            if (e.ctrlKey || e.metaKey || selectedMessageIds.size > 0) return;
            timer = window.setTimeout(() => {
                holdFired = true;
                message.classList.add('is-hold');
                toggleMessageSelection(msgId());
                try { navigator.vibrate?.(20); } catch (err) {}
            }, 380);
        });

        const clearHold = () => {
            if (timer) clearTimeout(timer);
            timer = null;
            message.classList.remove('is-hold');
        };
        ['pointerup', 'pointercancel'].forEach((event) => {
            message.addEventListener(event, clearHold);
        });
        message.addEventListener('pointermove', (e) => {
            // Срыв удержания только при заметном движении (как в Telegram)
            if (!timer) return;
            const start = message._holdStart;
            if (!start) return;
            const dx = Math.abs((e.clientX || 0) - start.x);
            const dy = Math.abs((e.clientY || 0) - start.y);
            if (dx > 12 || dy > 12) clearHold();
        });

        message.addEventListener('click', (e) => {
            if (e.target.closest('button,a,input,textarea,.bx-voice')) return;
            if (holdFired) {
                e.preventDefault();
                e.stopPropagation();
                holdFired = false;
                return;
            }
            if (e.ctrlKey || e.metaKey || selectedMessageIds.size > 0) {
                e.preventDefault();
                toggleMessageSelection(msgId());
            }
        });
    });
    document.getElementById('bx-selection-cancel')?.addEventListener('click', () => {
        selectedMessageIds.clear();
        updateSelection();
    });
    const closeForward = () => forwardSheet?.setAttribute('hidden', '');
    document.getElementById('bx-forward-close')?.addEventListener('click', closeForward);
    document.getElementById('bx-forward-close-bg')?.addEventListener('click', closeForward);

    let forwardChatCache = [];
    const renderForwardChats = (query = '') => {
        if (!forwardChats) return;
        const q = String(query || '').trim().toLowerCase();
        const list = forwardChatCache.filter((chat) => {
            if (!q) return true;
            return String(chat.title || '').toLowerCase().includes(q)
                || String(chat.subtitle || '').toLowerCase().includes(q);
        });
        if (!list.length) {
            forwardChats.innerHTML = '<div class="bx-forward-empty">Ничего не найдено</div>';
            return;
        }
        forwardChats.innerHTML = list.map((chat) => {
            const color = escapeHtml(chat.avatar_color || '#64748b');
            const initials = escapeHtml(chat.avatar_initials || '?');
            const img = chat.avatar_url
                ? `<img class="bx-avatar__img" src="${escapeHtml(chat.avatar_url)}" alt="" loading="lazy" onerror="this.remove()">`
                : '';
            const shape = chat.type === 'direct' ? 'round' : 'square';
            return `<button type="button" class="bx-forward-chat" data-target-chat="${chat.id}">
                <span class="bx-avatar bx-avatar--md bx-avatar--${shape}" style="--bx-avatar-bg:${color}">
                    <span class="bx-avatar__initials">${initials}</span>${img}
                </span>
                <span class="bx-forward-chat__meta">
                    <span class="bx-forward-chat__title">${escapeHtml(chat.title)}</span>
                    <span class="bx-forward-chat__sub">${escapeHtml(chat.subtitle || '')}</span>
                </span>
            </button>`;
        }).join('');
    };

    document.getElementById('bx-forward-search')?.addEventListener('input', (e) => {
        renderForwardChats(e.target.value || '');
    });

    forwardSelected?.addEventListener('click', async () => {
        const pickerUrl = root?.getAttribute('data-chats-picker-url');
        if (!pickerUrl || !selectedMessageIds.size) return;
        forwardSheet?.removeAttribute('hidden');
        const searchInput = document.getElementById('bx-forward-search');
        if (searchInput) searchInput.value = '';
        if (forwardChats) forwardChats.textContent = 'Загрузка чатов…';
        try {
            const response = await fetch(pickerUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Не удалось загрузить чаты');
            forwardChatCache = data.chats || [];
            renderForwardChats();
            searchInput?.focus();
        } catch (error) {
            if (forwardChats) forwardChats.textContent = error.message || 'Не удалось загрузить чаты';
        }
    });
    forwardChats?.addEventListener('click', async (e) => {
        const target = e.target.closest?.('[data-target-chat]');
        const forwardUrl = root?.getAttribute('data-forward-url');
        if (!target || !forwardUrl) return;
        target.disabled = true;
        try {
            const response = await fetch(forwardUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ message_ids: [...selectedMessageIds], target_chat_id: Number(target.getAttribute('data-target-chat')) }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Не удалось переслать сообщения');
            selectedMessageIds.clear();
            updateSelection();
            closeForward();
        } catch (error) {
            target.disabled = false;
            alert(error.message || 'Не удалось переслать сообщения');
        }
    });

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
            const buf = await res.arrayBuffer();
            if (!buf || buf.byteLength < 64) throw new Error('empty');
            const head = new Uint8Array(buf.slice(0, 16));
            let type = (res.headers.get('Content-Type') || '').split(';')[0].trim();
            if (head[0] === 0x52 && head[1] === 0x49 && head[2] === 0x46 && head[3] === 0x46) {
                type = 'audio/wav';
            } else if (head[0] === 0x4F && head[1] === 0x67 && head[2] === 0x67 && head[3] === 0x53) {
                type = 'audio/ogg';
            } else if (head[0] === 0x1A && head[1] === 0x45 && head[2] === 0xDF && head[3] === 0xA3) {
                type = 'audio/webm';
            } else if (!type || type === 'application/octet-stream' || type === 'text/html') {
                type = 'audio/wav';
            }

            // webm/ogg с Linux часто не играет в других браузерах — перекодируем в WAV на лету
            if (type === 'audio/webm' || type === 'audio/ogg' || type === 'video/webm') {
                const wav = await remuxArrayBufferToWav(buf);
                if (wav) {
                    audio.src = URL.createObjectURL(wav);
                    wrap.setAttribute('data-blob-ready', '1');
                    wrap.setAttribute('data-audio-type', 'audio/wav');
                    return true;
                }
            }

            const playable = new Blob([buf], { type });
            audio.src = URL.createObjectURL(playable);
            wrap.setAttribute('data-blob-ready', '1');
            wrap.setAttribute('data-audio-type', type);
            return true;
        } catch (e) {
            return false;
        }
    };

    const remuxArrayBufferToWav = async (arrayBuffer) => {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        const ctx = new Ctx();
        try {
            const decoded = await ctx.decodeAudioData(arrayBuffer.slice(0));
            const ch0 = decoded.getChannelData(0);
            let mono = ch0;
            if (decoded.numberOfChannels > 1) {
                const ch1 = decoded.getChannelData(1);
                mono = new Float32Array(ch0.length);
                for (let i = 0; i < ch0.length; i++) mono[i] = (ch0[i] + ch1[i]) * 0.5;
            }
            const targetRate = 16000;
            const ratio = decoded.sampleRate / targetRate;
            const len = Math.max(1, Math.floor(mono.length / ratio));
            const down = new Float32Array(len);
            for (let i = 0; i < len; i++) down[i] = mono[Math.min(mono.length - 1, Math.floor(i * ratio))];
            return encodeWavPcm(down, targetRate);
        } catch (e) {
            return null;
        } finally {
            await ctx.close().catch(() => {});
        }
    };

    const encodeWavPcm = (samples, sampleRate) => {
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
                    // Повтор: принудительный remux (если ещё webm)
                    wrap.removeAttribute('data-blob-ready');
                    const again = await ensureVoiceBlobSrc(wrap, audio, src);
                    if (again) {
                        try {
                            await audio.play();
                            return;
                        } catch (e2) {}
                    }
                    alert('Браузер не может воспроизвести это голосовое. Попросите отправителя записать ещё раз (нужен формат WAV — после обновления чатов).');
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
            const link = document.querySelector('.bx-chat-item[data-chat-id="' + activeChat + '"]');
            const preview = link?.querySelector('.bx-chat-item__preview');
            if (preview) preview.textContent = payload.preview;
            bumpChatInList(activeChat);
        }
        return true;
    };

    const bumpChatInList = (chatId) => {
        const list = document.getElementById('bx-chat-list');
        const link = list?.querySelector('.bx-chat-item[data-chat-id="' + chatId + '"]');
        if (!list || !link) return;
        if (link.classList.contains('is-pinned')) {
            // Среди закреплённых — наверх закреплённого блока
            const firstPinned = list.querySelector('.bx-chat-item.is-pinned');
            if (firstPinned && firstPinned !== link) {
                list.insertBefore(link, firstPinned);
            } else if (!firstPinned) {
                list.insertBefore(link, list.firstChild);
            }
            return;
        }
        // Незакреплённые — сразу после последнего закреплённого
        const pinned = list.querySelectorAll('.bx-chat-item.is-pinned');
        const lastPinned = pinned.length ? pinned[pinned.length - 1] : null;
        if (lastPinned) {
            lastPinned.after(link);
        } else {
            list.insertBefore(link, list.firstChild);
        }
    };

    const applyChatsFromPoll = (chats) => {
        const list = document.getElementById('bx-chat-list');
        if (!list || !Array.isArray(chats) || !chats.length) return;

        chats.forEach((c) => {
            const link = list.querySelector('.bx-chat-item[data-chat-id="' + c.id + '"]');
            if (!link) return;

            link.classList.toggle('is-pinned', !!c.pinned);
            link.classList.toggle('is-muted', !!c.muted);

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

            // Порядок как с сервера: pinned → по последнему сообщению
            list.appendChild(link);
        });
    };

    /* Подгрузка старых сообщений (скролл вверх) */
    let oldestId = parseInt(root?.getAttribute('data-oldest-id') || '0', 10) || 0;
    let hasMoreOlder = root?.getAttribute('data-has-more') === '1';
    let loadingOlder = false;
    const messagesUrl = root?.getAttribute('data-messages-url') || '';
    const olderWrap = document.getElementById('bx-feed-older');
    const olderBtn = document.getElementById('bx-load-older');
    const olderSpin = document.getElementById('bx-load-older-spin');

    const setOlderUi = () => {
        if (!olderWrap) return;
        if (hasMoreOlder) olderWrap.classList.remove('d-none');
        else olderWrap.classList.add('d-none');
    };

    const prependMessages = (items) => {
        if (!feed || !items?.length) return;
        const prevHeight = feed.scrollHeight;
        const prevTop = feed.scrollTop;
        const html = items.map((m) => m.html).join('');
        const anchor = olderWrap || feed.firstChild;
        if (anchor && anchor.insertAdjacentHTML) {
            anchor.insertAdjacentHTML('afterend', html);
        } else {
            feed.insertAdjacentHTML('afterbegin', html);
        }
        items.forEach((m) => {
            const node = document.getElementById('chat-msg-' + m.id);
            if (node) {
                highlightCodes(node);
                initVoicePlayers(node);
            }
        });
        feed.scrollTop = feed.scrollHeight - prevHeight + prevTop;
    };

    const loadOlderMessages = async () => {
        if (!messagesUrl || !hasMoreOlder || loadingOlder || !oldestId) return;
        loadingOlder = true;
        olderBtn?.classList.add('d-none');
        olderSpin?.classList.remove('d-none');
        try {
            const url = messagesUrl + '?before=' + encodeURIComponent(String(oldestId)) + '&limit=40';
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            const items = data.messages || [];
            prependMessages(items);
            hasMoreOlder = !!data.has_more;
            oldestId = parseInt(data.oldest_id || String(oldestId), 10) || oldestId;
            root?.setAttribute('data-has-more', hasMoreOlder ? '1' : '0');
            root?.setAttribute('data-oldest-id', String(oldestId || ''));
            setOlderUi();
        } catch (e) {
        } finally {
            loadingOlder = false;
            olderSpin?.classList.add('d-none');
            if (hasMoreOlder) olderBtn?.classList.remove('d-none');
        }
    };

    olderBtn?.addEventListener('click', () => loadOlderMessages());
    feed?.addEventListener('scroll', () => {
        if (feed.scrollTop < 60) loadOlderMessages();
    }, { passive: true });

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
        pendingFiles = [];
        renderFilesPreview();
        filesLabel?.classList.add('d-none');
        if (filesLabel) filesLabel.textContent = '';
        hideMentionMenu();
    };

    const focusComposer = () => {
        if (!input || input.disabled) return;
        if (!root?.classList.contains('is-chat-open')) return;
        try {
            input.focus({ preventScroll: true });
        } catch (e) {
            try { input.focus(); } catch (err) {}
        }
    };

    const composerFocusBlocked = () => {
        if (!root?.classList.contains('is-chat-open') || !input || input.disabled || sending) return true;
        const ae = document.activeElement;
        if (!ae || ae === input) return false;
        if (ae.closest?.('.modal.show, .modal[open], .bx-chat-info:not([hidden]), #bx-forward-sheet:not([hidden])')) return true;
        if (ae.id === 'bx-chat-search' || ae.closest?.('#bx-chat-search, .bx-forward-search, .bx-task-search')) return true;
        if (ae.matches?.('input:not([type="hidden"]):not([type="file"]):not(#bx-composer-input), textarea:not(#bx-composer-input), select, [contenteditable="true"]')) return true;
        const gearDrop = document.getElementById('bx-header-menu-drop');
        if (gearDrop && !gearDrop.hasAttribute('hidden')) return true;
        return false;
    };

    const keepComposerFocused = () => {
        if (composerFocusBlocked()) return;
        focusComposer();
    };

    // Как в VK: поле всегда активно, пока открыт чат
    if (root?.classList.contains('is-chat-open') && input) {
        setTimeout(focusComposer, 50);
        setTimeout(focusComposer, 300);

        input.addEventListener('blur', () => {
            setTimeout(keepComposerFocused, 0);
        });

        const mainPane = root.querySelector('.bx-messenger__main');
        mainPane?.addEventListener('pointerup', (e) => {
            if (composerFocusBlocked()) return;
            if (e.target.closest?.('a, button, label, input, textarea, select, audio, video, .bx-composer__tool, .bx-msg__receipt, .bx-header-menu, .bx-lightbox, .bx-selection-bar')) {
                // После клика по кнопке композера/отправке — вернуть фокус
                if (e.target.closest?.('#bx-composer, .bx-messenger__feed, .bx-messenger__header')) {
                    setTimeout(keepComposerFocused, 0);
                }
                return;
            }
            keepComposerFocused();
        });

        // Печать сразу, даже если фокус «уехал» на ленту
        root.addEventListener('keydown', (e) => {
            if (composerFocusBlocked()) return;
            if (e.target === input) return;
            if (e.ctrlKey || e.metaKey || e.altKey) return;
            if (e.key === 'Tab' || e.key === 'Escape') return;

            if (e.key.length === 1) {
                e.preventDefault();
                focusComposer();
                const start = input.selectionStart ?? input.value.length;
                const end = input.selectionEnd ?? input.value.length;
                input.value = input.value.slice(0, start) + e.key + input.value.slice(end);
                const caret = start + e.key.length;
                input.setSelectionRange(caret, caret);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }
            if (e.key === 'Backspace' || e.key === 'Enter') {
                focusComposer();
            }
        }, true);
    }

    let sending = false;
    const setSendingUi = (on) => {
        composer?.classList.toggle('is-sending', on);
        if (input) input.disabled = on;
        if (filesInput) filesInput.disabled = on;
        const btn = document.getElementById('bx-composer-send');
        if (btn) {
            btn.disabled = on;
            btn.classList.toggle('is-loading', on);
            btn.innerHTML = on
                ? '<span class="bx-send-spinner" aria-hidden="true"></span><span>Отправка…</span>'
                : 'Отправить';
        }
        document.querySelectorAll('.bx-composer__tool, .bx-composer__tools label').forEach((el) => {
            if (on) el.setAttribute('aria-disabled', 'true');
            else el.removeAttribute('aria-disabled');
            if ('disabled' in el) el.disabled = on;
        });
    };
    const sendMessageAjax = async (extraFormData = null) => {
        if (!sendUrl || sending) return;
        const text = (input?.value || '').trim();
        const hasFiles = pendingFiles.length > 0;
        const hasTask = !!(taskIdInput?.value);
        const hasVoice = extraFormData && extraFormData.has('message_voice');
        if (!text && !hasFiles && !hasTask && !hasVoice) return;

        sending = true;
        setSendingUi(true);

        try {
            const fd = extraFormData || new FormData();
            if (!extraFormData) {
                fd.append('message[text]', input?.value || '');
                if (parentInput?.value) fd.append('message[parent_id]', parentInput.value);
                if (taskIdInput?.value) fd.append('message[task_id]', taskIdInput.value);
                pendingFiles.slice(0, FILES_MAX).forEach((f) => fd.append('message_files[]', f));
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
            setSendingUi(false);
            focusComposer();
        }
    };

    document.getElementById('bx-composer-send')?.addEventListener('click', (e) => {
        e.preventDefault();
        sendMessageAjax();
    });

    /* Вставка картинок из буфера (Ctrl+V) */
    const addClipboardFiles = (fileList) => {
        const incoming = [...fileList].filter((f) => f && (f.type || '').startsWith('image/'));
        if (!incoming.length) return false;
        const room = FILES_MAX - pendingFiles.length;
        if (room <= 0) {
            alert('Можно прикрепить не больше ' + FILES_MAX + ' файлов за раз');
            return true;
        }
        pendingFiles = pendingFiles.concat(incoming.slice(0, room).map((f, i) => {
            if (f.name && f.name !== 'image.png') return f;
            const ext = (f.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
            return new File([f], `paste-${Date.now()}-${i}.${ext}`, { type: f.type });
        }));
        syncFilesInput();
        return true;
    };
    input?.addEventListener('paste', (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;
        const files = [];
        for (const item of items) {
            if (item.kind === 'file' && (item.type || '').startsWith('image/')) {
                const f = item.getAsFile();
                if (f) files.push(f);
            }
        }
        if (files.length && addClipboardFiles(files)) {
            e.preventDefault();
        }
    });
    composer?.addEventListener('dragover', (e) => {
        if ([...e.dataTransfer?.types || []].includes('Files')) {
            e.preventDefault();
            composer.classList.add('is-drop');
        }
    });
    composer?.addEventListener('dragleave', () => composer.classList.remove('is-drop'));
    composer?.addEventListener('drop', (e) => {
        composer.classList.remove('is-drop');
        const files = [...(e.dataTransfer?.files || [])];
        if (!files.length) return;
        e.preventDefault();
        const room = FILES_MAX - pendingFiles.length;
        if (room <= 0) return;
        pendingFiles = pendingFiles.concat(files.slice(0, room));
        syncFilesInput();
    });

    /* Voice: PCM → WAV + проверка тишины + выбор микрофона */
    const VOICE_MAX_SEC = 180;
    const VOICE_TARGET_RATE = 16000;
    const VOICE_SILENCE_PEAK = 0.018;
    const MIC_DEVICE_KEY = 'bx_chat_mic_device';
    const voiceBtn = document.getElementById('bx-tool-voice');
    const voiceBar = document.getElementById('bx-voice-bar');
    const voiceTimer = document.getElementById('bx-voice-timer');
    const voiceMeter = document.getElementById('bx-voice-meter');
    const micGate = document.getElementById('bx-mic-gate');
    const micGateMeter = document.getElementById('bx-mic-gate-meter');
    const micGateHint = document.getElementById('bx-mic-gate-hint');
    const micGateSelect = document.getElementById('bx-mic-gate-device');
    const voiceMicSelect = document.getElementById('bx-voice-mic');

    const getSavedMicId = () => localStorage.getItem(MIC_DEVICE_KEY) || '';
    const saveMicId = (id) => {
        if (id) localStorage.setItem(MIC_DEVICE_KEY, id);
        else localStorage.removeItem(MIC_DEVICE_KEY);
        [micGateSelect, voiceMicSelect].forEach((sel) => {
            if (sel && [...sel.options].some((o) => o.value === id)) sel.value = id;
        });
    };
    const audioConstraints = (deviceId = getSavedMicId()) => {
        const audio = {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl: true,
            channelCount: 1,
        };
        if (deviceId) audio.deviceId = { exact: deviceId };
        return { audio };
    };
    const openMicStream = async () => {
        const preferred = getSavedMicId();
        try {
            return await navigator.mediaDevices.getUserMedia(audioConstraints(preferred));
        } catch (e) {
            if (preferred) {
                // Устройство пропало — берём дефолтный
                saveMicId('');
                return navigator.mediaDevices.getUserMedia(audioConstraints(''));
            }
            throw e;
        }
    };
    const refreshMicDeviceList = async () => {
        if (!navigator.mediaDevices?.enumerateDevices) return;
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const mics = devices.filter((d) => d.kind === 'audioinput');
            const saved = getSavedMicId();
            [micGateSelect, voiceMicSelect].forEach((sel) => {
                if (!sel) return;
                const prev = sel.value || saved;
                sel.innerHTML = '';
                const opt0 = document.createElement('option');
                opt0.value = '';
                opt0.textContent = 'По умолчанию';
                sel.appendChild(opt0);
                mics.forEach((d, i) => {
                    const opt = document.createElement('option');
                    opt.value = d.deviceId;
                    opt.textContent = d.label || ('Микрофон ' + (i + 1));
                    sel.appendChild(opt);
                });
                if (prev && [...sel.options].some((o) => o.value === prev)) sel.value = prev;
            });
        } catch (e) {}
    };
    [micGateSelect, voiceMicSelect].forEach((sel) => {
        sel?.addEventListener('change', async () => {
            saveMicId(sel.value);
            // Если идёт проверка в gate — переподключить выбранный микрофон
            if (micGate && !micGate.hidden) {
                stopMicGateListen();
                try {
                    const stream = await openMicStream();
                    await listenMicLevels(stream);
                    await refreshMicDeviceList();
                    if (micGateHint) micGateHint.textContent = 'Выбран другой микрофон. Скажите пару слов…';
                } catch (e) {
                    if (micGateHint) micGateHint.textContent = micHelpText(e);
                }
            }
            // Если идёт запись — перезапуск на новом устройстве сложно; подсказка
            if (voiceRecording) {
                alert('Смена микрофона применится со следующей записи. Завершите текущую или отмените.');
            }
        });
    });
    let mediaStream = null;
    let voiceAudioCtx = null;
    let voiceProcessor = null;
    let voiceSource = null;
    let voicePcmChunks = [];
    let voiceSampleRate = 48000;
    let voicePeak = 0;
    let voiceRecording = false;
    let voiceStartedAt = 0;
    let voiceTick = null;
    let voiceCancelled = false;
    let micGateStream = null;
    let micGateCtx = null;
    let micGateRaf = 0;
    let micGateHeard = false;

    const formatVoiceTime = (sec) => {
        const s = Math.max(0, Math.floor(sec));
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    };

    const setMeter = (el, peak) => {
        if (!el) return;
        const pct = Math.min(100, Math.round(peak * 220));
        el.style.width = pct + '%';
        el.classList.toggle('is-hot', pct > 8);
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
        setMeter(voiceMeter, 0);
    };

    const stopMicGateListen = () => {
        cancelAnimationFrame(micGateRaf);
        micGateStream?.getTracks().forEach((t) => t.stop());
        micGateStream = null;
        if (micGateCtx) {
            micGateCtx.close().catch(() => {});
            micGateCtx = null;
        }
        setMeter(micGateMeter, 0);
    };

    const closeMicGate = () => {
        stopMicGateListen();
        if (micGate) micGate.hidden = true;
    };

    const openMicGate = (msg) => {
        if (micGateHint) micGateHint.textContent = msg || '';
        if (micGate) micGate.hidden = false;
    };

    const micHelpText = (err) => {
        const name = err?.name || '';
        if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            return 'Микрофон работает только по HTTPS. Откройте сайт как https://…';
        }
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
            return 'Доступ заблокирован. Замочек в адресной строке → Микрофон → Разрешить, затем снова «Разрешить микрофон».';
        }
        if (name === 'NotFoundError') return 'Микрофон не найден. Подключите устройство.';
        if (name === 'NotReadableError') return 'Микрофон занят другой программой.';
        return 'Не удалось открыть микрофон. Нажмите «Разрешить микрофон» ещё раз.';
    };

    const mergePcm = (chunks) => {
        let len = 0;
        chunks.forEach((c) => { len += c.length; });
        const out = new Float32Array(len);
        let o = 0;
        chunks.forEach((c) => { out.set(c, o); o += c.length; });
        return out;
    };

    const downsampleBuffer = (buffer, fromRate, toRate) => {
        if (fromRate === toRate) return buffer;
        const ratio = fromRate / toRate;
        const newLen = Math.max(1, Math.floor(buffer.length / ratio));
        const result = new Float32Array(newLen);
        for (let i = 0; i < newLen; i++) {
            result[i] = buffer[Math.min(buffer.length - 1, Math.floor(i * ratio))];
        }
        return result;
    };

    const peakOf = (samples) => {
        let peak = 0;
        for (let i = 0; i < samples.length; i++) {
            const v = Math.abs(samples[i]);
            if (v > peak) peak = v;
        }
        return peak;
    };

    const finishVoiceRecording = async () => {
        const duration = Math.min(VOICE_MAX_SEC, Math.round((Date.now() - voiceStartedAt) / 1000));
        const chunks = voicePcmChunks.slice();
        const rate = voiceSampleRate;
        const peak = voicePeak;
        stopVoiceTracks();
        endVoiceUi();
        if (voiceCancelled || !chunks.length || duration < 1) return;

        const pcm = mergePcm(chunks);
        const livePeak = Math.max(peak, peakOf(pcm));
        if (livePeak < VOICE_SILENCE_PEAK) {
            openMicGate('Запись без звука (тишина). Разрешите микрофон, скажите пару слов и запишите снова.');
            await requestMicUntilHeard();
            return;
        }

        let targetRate = VOICE_TARGET_RATE;
        let mono = downsampleBuffer(pcm, rate, targetRate);
        let blob = encodeWavPcm(mono, targetRate);
        const MAX_SAFE = 1800 * 1024;
        if (blob.size > MAX_SAFE) {
            targetRate = 8000;
            mono = downsampleBuffer(pcm, rate, targetRate);
            blob = encodeWavPcm(mono, targetRate);
        }
        if (blob.size > MAX_SAFE) {
            alert('Голосовое слишком большое. Запишите короче (до ~1.5 мин) или поднимите upload_max_filesize в PHP до 16M.');
            return;
        }

        const file = new File([blob], 'voice.wav', { type: 'audio/wav' });
        const fd = new FormData();
        fd.append('message_voice', file);
        fd.append('message[voice_duration]', String(duration));
        fd.append('message[text]', '');
        if (parentInput?.value) fd.append('message[parent_id]', parentInput.value);
        await sendMessageAjax(fd);
    };

    const startVoiceCapture = async (stream) => {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) {
            alert('Браузер не поддерживает запись голоса');
            return false;
        }
        mediaStream = stream;
        voiceAudioCtx = new Ctx();
        if (voiceAudioCtx.state === 'suspended') await voiceAudioCtx.resume();
        voiceSampleRate = voiceAudioCtx.sampleRate || 48000;
        voiceSource = voiceAudioCtx.createMediaStreamSource(mediaStream);
        voiceProcessor = voiceAudioCtx.createScriptProcessor(4096, 1, 1);
        voicePcmChunks = [];
        voicePeak = 0;
        voiceCancelled = false;
        voiceStartedAt = Date.now();
        voiceRecording = true;

        voiceProcessor.onaudioprocess = (ev) => {
            if (!voiceRecording) return;
            const input = ev.inputBuffer.getChannelData(0);
            const copy = new Float32Array(input.length);
            copy.set(input);
            voicePcmChunks.push(copy);
            let peak = 0;
            for (let i = 0; i < input.length; i++) {
                const v = Math.abs(input[i]);
                if (v > peak) peak = v;
            }
            if (peak > voicePeak) voicePeak = peak;
            setMeter(voiceMeter, peak);
        };
        const mute = voiceAudioCtx.createGain();
        mute.gain.value = 0;
        voiceSource.connect(voiceProcessor);
        voiceProcessor.connect(mute);
        mute.connect(voiceAudioCtx.destination);

        voiceBar?.classList.remove('d-none');
        voiceBtn?.classList.add('is-recording');
        composer?.classList.add('is-voice-recording');
        if (voiceTimer) voiceTimer.textContent = '0:00';

        voiceTick = setInterval(() => {
            const elapsed = Math.floor((Date.now() - voiceStartedAt) / 1000);
            if (voiceTimer) voiceTimer.textContent = formatVoiceTime(elapsed);
            if (elapsed >= VOICE_MAX_SEC) stopVoice(false);
        }, 200);
        return true;
    };

    const listenMicLevels = async (stream) => {
        stopMicGateListen();
        micGateStream = stream;
        micGateHeard = false;
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        micGateCtx = new Ctx();
        if (micGateCtx.state === 'suspended') await micGateCtx.resume();
        const src = micGateCtx.createMediaStreamSource(stream);
        const analyser = micGateCtx.createAnalyser();
        analyser.fftSize = 2048;
        src.connect(analyser);
        const data = new Uint8Array(analyser.fftSize);
        const tick = () => {
            analyser.getByteTimeDomainData(data);
            let peak = 0;
            for (let i = 0; i < data.length; i++) {
                const v = Math.abs((data[i] - 128) / 128);
                if (v > peak) peak = v;
            }
            setMeter(micGateMeter, peak);
            if (peak > VOICE_SILENCE_PEAK) {
                micGateHeard = true;
                if (micGateHint) micGateHint.textContent = 'Микрофон слышно — можно записывать.';
            }
            micGateRaf = requestAnimationFrame(tick);
        };
        tick();
    };

    const requestMicUntilHeard = async () => {
        openMicGate('');
        try {
            const stream = await openMicStream();
            await listenMicLevels(stream);
            await refreshMicDeviceList();
            if (micGateHint) {
                micGateHint.textContent = 'Говорите сейчас… Когда полоска зелёная — можно записывать. При необходимости смените микрофон выше.';
            }
        } catch (e) {
            if (micGateHint) micGateHint.textContent = micHelpText(e);
            openMicGate(micHelpText(e));
        }
    };

    const startVoice = async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            openMicGate('Браузер не поддерживает микрофон.');
            return;
        }
        if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            openMicGate(micHelpText({ name: 'Insecure' }));
            return;
        }

        try {
            const stream = await openMicStream();
            await refreshMicDeviceList();
            closeMicGate();
            await startVoiceCapture(stream);
        } catch (e) {
            openMicGate(micHelpText(e));
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
    document.getElementById('bx-mic-gate-close')?.addEventListener('click', () => closeMicGate());
    document.getElementById('bx-mic-gate-retry')?.addEventListener('click', async () => {
        // Бесконечно можно жать — браузер снова спросит, если не «запрещено навсегда»
        stopMicGateListen();
        await requestMicUntilHeard();
        if (micGateHeard) {
            // Автостарт записи после успешного теста
            const stream = micGateStream;
            micGateStream = null; // не стопать в close
            cancelAnimationFrame(micGateRaf);
            if (micGateCtx) { micGateCtx.close().catch(() => {}); micGateCtx = null; }
            closeMicGate();
            if (stream) await startVoiceCapture(stream);
        }
    });

    /* Presence + typing */
    const typingEl = document.getElementById('bx-typing');
    const subtitleBtn = document.getElementById('bx-open-members');
    const typingUrl = root?.getAttribute('data-typing-url') || '';
    const chatType = root?.getAttribute('data-chat-type') || '';
    const csrfToken = root?.getAttribute('data-csrf') || '';
    let typingPulseAt = 0;
    let lastTypingKey = '';

    const isUserOnline = (presence, userId) => {
        if (!userId || !presence) return false;
        return !!(presence[userId] || presence[String(userId)]);
    };

    const applyPresence = (presence) => {
        document.querySelectorAll('.bx-avatar-wrap[data-user-id]').forEach((wrap) => {
            const uid = wrap.getAttribute('data-user-id');
            wrap.classList.toggle('is-online', isUserOnline(presence, uid));
        });

        document.querySelectorAll('.bx-members-modal__item[data-user-id]').forEach((item) => {
            const uid = item.getAttribute('data-user-id');
            const online = isUserOnline(presence, uid);
            const status = item.querySelector('.bx-members-modal__status');
            const wrap = item.querySelector('.bx-avatar-wrap');
            wrap?.classList.toggle('is-online', online);
            if (!status) return;
            status.classList.toggle('is-online', online);
            status.textContent = online
                ? (status.getAttribute('data-online-label') || 'в сети')
                : (status.getAttribute('data-offline-label') || 'не в сети');
        });

        if (subtitleBtn && chatType === 'direct' && !subtitleBtn.classList.contains('is-typing')) {
            const peerId = subtitleBtn.getAttribute('data-peer-id');
            const text = isUserOnline(presence, peerId) ? 'в сети' : 'не в сети';
            subtitleBtn.setAttribute('data-default-subtitle', text);
            subtitleBtn.textContent = text;
        }
    };

    const formatTypingLabel = (list) => {
        const names = (list || []).map((t) => String(t.name || '').trim()).filter(Boolean);
        if (!names.length) return '';
        if (chatType === 'direct') return 'печатает…';
        if (names.length === 1) return names[0] + ' печатает…';
        if (names.length === 2) return names[0] + ' и ' + names[1] + ' печатают…';
        return names[0] + ' и ещё ' + (names.length - 1) + ' печатают…';
    };

    const applyTyping = (list) => {
        const label = formatTypingLabel(list);
        const key = (list || []).map((t) => t.user_id).join(',');
        if (typingEl) {
            if (!label) {
                typingEl.classList.add('d-none');
                typingEl.textContent = '';
            } else {
                typingEl.classList.remove('d-none');
                typingEl.innerHTML = '<span class="bx-typing__dots" aria-hidden="true"><span></span><span></span><span></span></span><span>' + escapeHtml(label) + '</span>';
            }
        }
        if (subtitleBtn) {
            if (label) {
                subtitleBtn.classList.add('is-typing');
                subtitleBtn.textContent = label;
            } else {
                subtitleBtn.classList.remove('is-typing');
                subtitleBtn.textContent = subtitleBtn.getAttribute('data-default-subtitle') || subtitleBtn.textContent;
            }
        }
        lastTypingKey = key;
    };

    const sendTyping = () => {
        if (!typingUrl || !csrfToken) return;
        const now = Date.now();
        if (now - typingPulseAt < 2200) return;
        typingPulseAt = now;
        fetch(typingUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: '{}',
        }).catch(() => {});
    };

    input?.addEventListener('input', () => {
        if ((input.value || '').trim() !== '') sendTyping();
    });

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
        clearTimeout(window.__bxMessengerPollTimer);
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

    const NOTIFY_VOL_KEY = 'bx_chat_notify_volume';
    const getNotifyVolume = () => {
        const v = parseInt(localStorage.getItem(NOTIFY_VOL_KEY) || '75', 10);
        return Number.isFinite(v) ? Math.max(0, Math.min(100, v)) : 75;
    };
    const setNotifyVolume = (v) => {
        const n = Math.max(0, Math.min(100, parseInt(v, 10) || 0));
        localStorage.setItem(NOTIFY_VOL_KEY, String(n));
        document.querySelectorAll('#bx-notify-volume, #bx-notify-volume-empty').forEach((el) => {
            el.value = String(n);
        });
        document.querySelectorAll('#bx-notify-volume-label, #bx-notify-volume-empty-label').forEach((el) => {
            el.textContent = n + '%';
        });
        window.__bxChatNotifyVolume = n;
        return n;
    };
    setNotifyVolume(getNotifyVolume());
    document.querySelectorAll('#bx-notify-volume, #bx-notify-volume-empty').forEach((el) => {
        el.addEventListener('input', () => {
            unlockSound();
            setNotifyVolume(el.value);
            if (typeof window.bxPlayChatNotify === 'function') window.bxPlayChatNotify();
        });
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
            const volScale = getNotifyVolume() / 100;
            if (volScale <= 0) return;
            const t = ctx.currentTime;
            const beep = (freq, start, dur, vol) => {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.type = 'sine';
                o.frequency.value = freq;
                const peak = Math.max(0.0001, vol * volScale);
                g.gain.setValueAtTime(0.0001, t + start);
                g.gain.exponentialRampToValueAtTime(peak, t + start + 0.01);
                g.gain.exponentialRampToValueAtTime(0.0001, t + start + dur);
                o.connect(g);
                g.connect(ctx.destination);
                o.start(t + start);
                o.stop(t + start + dur + 0.02);
            };
            beep(1100, 0, 0.12, 0.65);
            beep(1450, 0.14, 0.14, 0.6);
        } catch (e) {}
    };

    const showDesktopNotify = (payload) => {
        if (!payload || !('Notification' in window) || Notification.permission !== 'granted') return;
        // Если Web Push включён — системное уведомление уже придёт из service worker (иначе дубль).
        if (localStorage.getItem('tml_push_enabled') === '1') return;
        const activeChat = String(root?.getAttribute('data-active-chat') || '');
        const notifyUrl = String(payload.url || '');
        const viewingSameChat = activeChat
            && !document.hidden
            && (notifyUrl.includes('/chats/' + activeChat) || notifyUrl.endsWith('/' + activeChat));
        if (viewingSameChat) return;
        try {
            const n = new Notification(payload.title || 'Новое сообщение', {
                body: payload.body || '',
                icon: '/favicon.ico',
                tag: 'tml-chat-' + String(payload.message_id || Date.now()),
                renotify: true,
            });
            n.onclick = () => {
                try { window.focus(); } catch (e) {}
                if (payload.url) window.location.href = payload.url;
                n.close();
            };
            setTimeout(() => { try { n.close(); } catch (e) {} }, 8000);
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
                    if (data.notify) showDesktopNotify(data.notify);
                    lastBeepAt = now;
                }
                lastBeepMaxId = maxId;
                sessionStorage.setItem(beepKey, String(lastBeepMaxId));
            }
            if (typeof window.bxHandleCallsPoll === 'function') {
                window.bxHandleCallsPoll(data.calls || []);
            }
            if (data.presence) applyPresence(data.presence);
            applyTyping(data.typing || []);
            if (maxId > since) {
                since = maxId;
                localStorage.setItem(storageKey, String(since));
            }
            applyChatsFromPoll(data.chats || []);

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
        let pollDelay = 2500;
        let idleTicks = 0;
        const schedulePoll = () => {
            if (window.__bxMessengerPollTimer) clearTimeout(window.__bxMessengerPollTimer);
            window.__bxMessengerPollTimer = setTimeout(async () => {
                const hidden = document.visibilityState === 'hidden';
                pollDelay = hidden ? 10000 : (idleTicks > 8 ? 5000 : 2500);
                await poll();
                idleTicks++;
                schedulePoll();
            }, pollDelay);
        };
        const bumpActivity = () => { idleTicks = 0; };
        ['pointerdown', 'keydown', 'visibilitychange'].forEach((ev) => {
            document.addEventListener(ev, bumpActivity, { passive: true });
        });
        root?.addEventListener('input', bumpActivity, { passive: true });
        poll().finally(schedulePoll);
    }

    /* Кнопки «Личный / Группа» в сайдбаре → Orchid-модалки */
    document.querySelectorAll('[data-bx-open-modal]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const name = btn.getAttribute('data-bx-open-modal');
            if (!name) return;
            const target =
                document.querySelector(`[data-modal-toggle-key-value="${name}"]`)
                || document.querySelector(`.command-bar [data-modal="${name}"]`)
                || document.querySelector(`[data-modal="${name}"]`);
            if (target && typeof target.click === 'function') {
                target.click();
                return;
            }
            alert('Не удалось открыть форму. Обновите страницу.');
        });
    });

    /* Поиск по чатам и сообщениям */
    const searchInput = document.getElementById('bx-chat-search');
    const searchPanel = document.getElementById('bx-search-panel');
    const chatListEl = document.getElementById('bx-chat-list');
    const searchChatsEl = document.getElementById('bx-search-chats');
    const searchMsgsEl = document.getElementById('bx-search-msgs');
    const searchEmptyEl = document.getElementById('bx-search-empty');
    const searchChatsWrap = document.getElementById('bx-search-chats-wrap');
    const searchMsgsWrap = document.getElementById('bx-search-msgs-wrap');
    let searchTimer = null;
    let searchSeq = 0;

    const escapeHtmlSearch = escapeHtml;

    const showSearchMode = (on) => {
        if (!searchPanel || !chatListEl) return;
        if (on) {
            chatListEl.classList.add('d-none');
            searchPanel.classList.remove('d-none');
            searchPanel.hidden = false;
        } else {
            chatListEl.classList.remove('d-none');
            searchPanel.classList.add('d-none');
            searchPanel.hidden = true;
            document.querySelectorAll('.bx-chat-item').forEach((el) => el.classList.remove('d-none'));
        }
    };

    const filterLocalChats = (q) => {
        const needle = q.toLowerCase();
        document.querySelectorAll('#bx-chat-list .bx-chat-item').forEach((el) => {
            const title = (el.getAttribute('data-title') || '').toLowerCase();
            el.classList.toggle('d-none', needle.length >= 1 && !title.includes(needle));
        });
    };

    const renderSearchResults = (data) => {
        const chats = data.chats || [];
        const msgs = data.messages || [];
        const q = data.query || (searchInput?.value || '').trim();

        const avatarHtml = (av) => {
            const a = av || {};
            const shape = a.shape === 'square' ? 'square' : 'round';
            const color = escapeHtmlSearch(a.color || '#64748b');
            const initials = escapeHtmlSearch(a.initials || '?');
            const img = a.url
                ? '<img class="bx-avatar__img" src="' + escapeHtmlSearch(a.url) + '" alt="" loading="lazy" onerror="this.remove()">'
                : '';
            return '<span class="bx-avatar bx-avatar--md bx-avatar--' + shape + '" style="--bx-avatar-bg:' + color + '">'
                + '<span class="bx-avatar__initials">' + initials + '</span>'
                + img
                + '</span>';
        };

        const highlight = (text) => {
            const raw = String(text || '');
            if (!q || q.length < 2) return escapeHtmlSearch(raw);
            const esc = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            try {
                return escapeHtmlSearch(raw).replace(
                    new RegExp('(' + esc + ')', 'ig'),
                    '<mark class="bx-search-mark">$1</mark>'
                );
            } catch (e) {
                return escapeHtmlSearch(raw);
            }
        };

        if (searchChatsEl) {
            searchChatsEl.innerHTML = chats.map((c) => {
                const kind = c.type === 'direct' ? 'Личный' : 'Группа';
                const unread = c.unread > 0
                    ? '<span class="bx-search-hit__badge">' + escapeHtmlSearch(String(c.unread)) + '</span>'
                    : '';
                return '<a class="bx-search-hit bx-search-hit--chat" href="' + escapeHtmlSearch(c.url) + '">'
                    + avatarHtml(c.avatar)
                    + '<div class="bx-search-hit__body">'
                    +   '<div class="bx-search-hit__top">'
                    +     '<span class="bx-search-hit__title">' + highlight(c.title) + '</span>'
                    +     '<span class="bx-search-hit__time">' + escapeHtmlSearch(c.at || '') + '</span>'
                    +   '</div>'
                    +   '<div class="bx-search-hit__row">'
                    +     '<span class="bx-search-hit__kind">' + kind + '</span>'
                    +     '<span class="bx-search-hit__preview">' + highlight(c.preview) + '</span>'
                    +     unread
                    +   '</div>'
                    + '</div>'
                    + '</a>';
            }).join('');
        }

        if (searchMsgsEl) {
            searchMsgsEl.innerHTML = msgs.map((m) => {
                const isDirect = m.chat_type === 'direct';
                const previewLine = isDirect
                    ? highlight(m.preview)
                    : '<span class="bx-search-hit__from">' + escapeHtmlSearch(m.author) + ':</span> ' + highlight(m.preview);
                return '<a class="bx-search-hit bx-search-hit--msg" href="' + escapeHtmlSearch(m.url) + '">'
                    + avatarHtml(m.avatar)
                    + '<div class="bx-search-hit__body">'
                    +   '<div class="bx-search-hit__top">'
                    +     '<span class="bx-search-hit__title">' + escapeHtmlSearch(m.chat_title) + '</span>'
                    +     '<span class="bx-search-hit__time">' + escapeHtmlSearch(m.at || '') + '</span>'
                    +   '</div>'
                    +   '<div class="bx-search-hit__row">'
                    +     '<span class="bx-search-hit__tag">Сообщение</span>'
                    +     '<span class="bx-search-hit__preview">' + previewLine + '</span>'
                    +   '</div>'
                    + '</div>'
                    + '</a>';
            }).join('');
        }

        if (searchChatsWrap) searchChatsWrap.classList.toggle('d-none', !chats.length);
        if (searchMsgsWrap) searchMsgsWrap.classList.toggle('d-none', !msgs.length);
        if (searchEmptyEl) searchEmptyEl.classList.toggle('d-none', chats.length + msgs.length > 0);
    };

    const runSearch = async (q) => {
        const url = searchInput?.getAttribute('data-search-url');
        if (!url || q.length < 2) {
            if (searchChatsEl) searchChatsEl.innerHTML = '';
            if (searchMsgsEl) searchMsgsEl.innerHTML = '';
            // локальный фильтр списка чатов
            if (q.length >= 1) {
                showSearchMode(false);
                chatListEl?.classList.remove('d-none');
                filterLocalChats(q);
            } else {
                showSearchMode(false);
                filterLocalChats('');
            }
            return;
        }
        showSearchMode(true);
        const seq = ++searchSeq;
        try {
            const res = await fetch(url + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok || seq !== searchSeq) return;
            const data = await res.json();
            if (seq !== searchSeq) return;
            renderSearchResults(data);
        } catch (e) {}
    };

    searchInput?.addEventListener('input', () => {
        const q = (searchInput.value || '').trim();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => runSearch(q), 220);
    });
    searchInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            searchInput.value = '';
            runSearch('');
            searchInput.blur();
        }
    });

    /* Переход к сообщению из поиска ?msg= */
    const focusMessageFromQuery = () => {
        const params = new URLSearchParams(window.location.search || '');
        const msgId = params.get('msg');
        if (!msgId) return;
        const el = document.getElementById('chat-msg-' + msgId);
        if (!el) return;
        el.classList.add('bx-msg--highlight');
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        setTimeout(() => el.classList.remove('bx-msg--highlight'), 2600);
        params.delete('msg');
        const next = window.location.pathname + (params.toString() ? ('?' + params) : '') + window.location.hash;
        window.history.replaceState({}, '', next);
    };
    setTimeout(focusMessageFromQuery, 120);

    /* Превью картинок — открытие на весь экран */
    const lightbox = document.getElementById('bx-lightbox');
    const lightboxImg = lightbox?.querySelector('.bx-lightbox__img');
    const lightboxOpen = lightbox?.querySelector('.bx-lightbox__open');
    const openLightbox = (url, alt) => {
        if (!lightbox || !lightboxImg) return;
        lightboxImg.src = url;
        lightboxImg.alt = alt || '';
        if (lightboxOpen) lightboxOpen.href = url;
        lightbox.hidden = false;
        document.body.classList.add('bx-lightbox-open');
    };
    const closeLightbox = () => {
        if (!lightbox) return;
        lightbox.hidden = true;
        if (lightboxImg) lightboxImg.src = '';
        document.body.classList.remove('bx-lightbox-open');
    };
    document.addEventListener('click', (e) => {
        const link = e.target.closest?.('[data-bx-lightbox]');
        if (link) {
            e.preventDefault();
            openLightbox(link.getAttribute('data-bx-lightbox') || link.href, link.getAttribute('title') || '');
            return;
        }
        if (e.target.closest?.('[data-bx-lightbox-close]')) {
            e.preventDefault();
            closeLightbox();
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightbox && !lightbox.hidden) closeLightbox();
    });
})();
</script>
@if(!empty($calls_enabled))
<script src="{{ asset('js/chat-calls.js') }}?v=20260725o"></script>
@endif
