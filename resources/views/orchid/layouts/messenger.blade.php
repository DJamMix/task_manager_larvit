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
     data-has-more-newer="{{ !empty($messages_has_more_newer) ? '1' : '0' }}"
     data-newest-id="{{ $messages_newest_id ?? (isset($messages) ? $messages->last()?->id : '') }}"
     data-calls-enabled="{{ !empty($calls_enabled) ? '1' : '0' }}"
     data-calls-start-url="{{ $calls_start_url ?? '' }}"
     data-call-join-tpl="{{ str_replace('999999', '__ID__', route('platform.systems.chats.calls.join', ['call' => 999999])) }}"
     data-forward-url="{{ ($active_chat_id ?? null) ? route('platform.systems.chats.forward', $active_chat_id) : '' }}"
     data-delete-url="{{ ($active_chat_id ?? null) ? route('platform.systems.chats.messages.delete', $active_chat_id) : '' }}"
     data-read-url="{{ ($active_chat_id ?? null) ? route('platform.systems.chats.read', $active_chat_id) : '' }}"
     data-first-unread="{{ $first_unread_id ?? '' }}"
     data-bot-callback-url="{{ $bot_callback_url ?? '' }}"
     data-bot-commands='@json($bot_commands ?? [])'
     data-chats-picker-url="{{ $chats_picker_url ?? route('platform.systems.chats.picker') }}"
     data-media-url="{{ $chats_media_url ?? '' }}"
     data-can-edit-chat="{{ !empty($can_edit_chat) ? '1' : '0' }}"
     data-chat-settings-url="{{ ($active_chat_id ?? null) && !empty($can_edit_chat) ? route('platform.systems.chats.settings', $active_chat_id) : '' }}"
     data-chat-avatar-url="{{ ($active_chat_id ?? null) && !empty($can_edit_chat) ? route('platform.systems.chats.avatar', $active_chat_id) : '' }}"
     data-chat-members-add-url="{{ ($active_chat_id ?? null) && !empty($can_edit_chat) ? route('platform.systems.chats.members.add', $active_chat_id) : '' }}"
     data-chat-member-remove-tpl="{{ ($active_chat_id ?? null) && !empty($can_edit_chat) ? str_replace('999999', '__ID__', route('platform.systems.chats.members.remove', ['chat' => $active_chat_id, 'user' => 999999])) : '' }}"
     data-chat-destroy-url="{{ ($active_chat_id ?? null) && !empty($can_edit_chat) ? route('platform.systems.chats.destroy', $active_chat_id) : '' }}"
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
            </div>
            <div class="bx-messenger__sidebar-actions">
                <button type="button"
                        class="bx-sidebar-icon"
                        data-bx-open-modal="createDirectModal"
                        title="Написать сообщение">
                    <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                </button>
                @if(!empty($can_create))
                    <button type="button"
                            class="bx-sidebar-icon"
                            data-bx-open-modal="createChatModal"
                            title="Создать группу">
                        <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                @endif
            </div>
        </div>
        <div class="bx-messenger__search">
            <div class="bx-messenger__search-wrap">
                <svg class="bx-messenger__search-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
                <input type="search"
                       id="bx-chat-search"
                       class="bx-messenger__search-input"
                       placeholder="Поиск"
                       autocomplete="off"
                       data-search-url="{{ $chats_search_url ?? route('platform.systems.chats.search') }}">
            </div>
        </div>

        <div class="bx-messenger__list" id="bx-chat-list"
             data-pin-tpl="{{ str_replace('999999', '__ID__', route('platform.systems.chats.pin', ['chat' => 999999])) }}"
             data-mute-tpl="{{ str_replace('999999', '__ID__', route('platform.systems.chats.mute', ['chat' => 999999])) }}">
            @php $chatTimeFmt = app(\App\Services\ChatService::class); @endphp
            @forelse($chatList as $item)
                @php
                    $peer = $item->type === 'direct' ? $item->otherMember() : null;
                    $latest = $item->latestMessage;
                    $previewRaw = trim((string) ($latest?->plain_text ?? ''));
                    if ($previewRaw === '') {
                        $listPreview = $latest ? 'Вложение' : 'Нет сообщений';
                    } elseif ($item->type !== 'direct' && $latest?->user) {
                        $who = (int) $latest->user_id === (int) auth()->id()
                            ? 'Вы'
                            : ($latest->user->displayName() ?: 'Участник');
                        $listPreview = $who . ': ' . $previewRaw;
                    } elseif ($latest && (int) $latest->user_id === (int) auth()->id()) {
                        $listPreview = 'Вы: ' . $previewRaw;
                    } else {
                        $listPreview = $previewRaw;
                    }
                    $listTime = $chatTimeFmt->formatChatListTime($latest?->created_at);
                @endphp
                <a href="{{ route('platform.systems.chats.view', $item) }}"
                   class="bx-chat-item {{ (int)$activeId === (int)$item->id ? 'is-active' : '' }} {{ !empty($item->is_muted) ? 'is-muted' : '' }} {{ !empty($item->is_pinned) ? 'is-pinned' : '' }}"
                   data-chat-id="{{ $item->id }}"
                   data-peer-id="{{ $peer?->id ?? '' }}"
                   data-title="{{ mb_strtolower($item->displayTitle()) }}"
                   data-last-id="{{ $latest?->id ?? '' }}"
                   data-turbo-prefetch="false"
                   data-turbo="true">
                    @if($item->type === 'direct')
                        @include('orchid.layouts.partials.bx-avatar', [
                            'avatarUser' => $peer,
                            'avatarChat' => null,
                            'size' => 'lg',
                            'shape' => 'round',
                            'showOnline' => true,
                            'isOnline' => $presenceOnline($peer?->id),
                        ])
                    @else
                        @include('orchid.layouts.partials.bx-avatar', [
                            'avatarChat' => $item,
                            'avatarUser' => null,
                            'size' => 'lg',
                            'shape' => 'square',
                        ])
                    @endif
                    <div class="bx-chat-item__body">
                        <div class="bx-chat-item__top">
                            <strong class="bx-chat-item__title">{{ $item->displayTitle() }}</strong>
                            <span class="bx-chat-item__meta">
                                @if(!empty($item->is_pinned))
                                    <svg class="bx-chat-item__pin" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                                @endif
                                <span class="bx-chat-item__time">{{ $listTime }}</span>
                            </span>
                        </div>
                        <div class="bx-chat-item__bottom">
                            <div class="bx-chat-item__preview">{{ \Illuminate\Support\Str::limit($listPreview, 64) }}</div>
                            <span class="bx-chat-item__trail">
                                @if(!empty($item->is_muted))
                                    <svg class="bx-chat-item__mute" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 5L6 9H2v6h4l5 4V5z"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
                                @endif
                                @if(($item->unread_count ?? 0) > 0)
                                    <span class="bx-chat-item__badge {{ !empty($item->is_muted) ? 'is-muted' : '' }}">{{ $item->unread_count }}</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="bx-chat-list-empty" id="bx-chat-list-empty">Пока нет чатов</div>
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
                            @if($canEditChat)
                                <button type="button" class="bx-header-menu__item" id="bx-header-edit-chat">
                                    <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                    <span>Изменить группу</span>
                                </button>
                                <button type="button" class="bx-header-menu__item" id="bx-header-add-members">
                                    <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                                    <span>Добавить участников</span>
                                </button>
                                <button type="button" class="bx-header-menu__item bx-header-menu__item--danger" id="bx-header-delete-chat">
                                    <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                                    <span>Удалить группу</span>
                                </button>
                                <div class="bx-header-menu__sep"></div>
                            @endif
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
                @php $unreadDividerDone = false; $firstUnreadId = (int) ($first_unread_id ?? 0); @endphp
                @forelse($feed as $message)
                    @if(!$unreadDividerDone && $firstUnreadId > 0 && (int) $message->id === $firstUnreadId)
                        <div class="bx-unread-divider" id="bx-unread-divider" role="separator">
                            <span>Непрочитанные сообщения</span>
                        </div>
                        @php $unreadDividerDone = true; @endphp
                    @endif
                    @include('orchid.layouts.partials.bx-message', [
                        'message' => $message,
                        'chat' => $active,
                        'viewer' => auth()->user(),
                    ])
                @empty
                    <div class="text-muted text-center py-5" id="bx-feed-empty">Начните переписку</div>
                @endforelse
                <div class="bx-feed-newer {{ !empty($messages_has_more_newer) ? '' : 'd-none' }}" id="bx-feed-newer">
                    <span class="bx-feed-newer__spin d-none" id="bx-load-newer-spin">Загрузка…</span>
                    <button type="button" class="bx-feed-older__btn" id="bx-load-newer">Загрузить новые</button>
                </div>
            </div>

            <div id="bx-typing" class="bx-typing d-none" aria-live="polite"></div>

            <div class="bx-selection-bar" id="bx-selection-bar" hidden>
                <div class="bx-selection-bar__info">
                    <button type="button" class="bx-selection-bar__close" id="bx-selection-cancel" title="Отмена" aria-label="Отмена">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                    <span id="bx-selection-count">Выбрано: 0</span>
                </div>
                <div class="bx-selection-bar__actions">
                    <button type="button" class="bx-selection-bar__btn" id="bx-reply-selected" hidden title="Ответить">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 17H5a2 2 0 01-2-2V5a2 2 0 012-2h11a2 2 0 012 2v3"/><path d="M15 15l5-5-5-5"/><path d="M20 10H11"/></svg>
                        <span>Ответ</span>
                    </button>
                    <button type="button" class="bx-selection-bar__btn" id="bx-copy-selected" title="Копировать текст">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        <span>Копировать</span>
                    </button>
                    <button type="button" class="bx-selection-bar__btn bx-selection-bar__btn--primary" id="bx-forward-selected">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 8l4 4-4 4"/><path d="M6 12h12"/></svg>
                        <span>Переслать</span>
                        <em class="bx-selection-bar__count" id="bx-forward-count">0</em>
                    </button>
                    <button type="button" class="bx-selection-bar__btn bx-selection-bar__btn--danger" id="bx-delete-selected">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                        <span>Удалить</span>
                    </button>
                </div>
            </div>

            @if($can_write ?? true)
            <div class="bx-composer" id="bx-composer"
                 data-mentions='@json($mentionUsers)'>
                <input type="hidden" id="chat-message-parent-id" value="">

                <div id="bx-reply-banner" class="bx-composer__reply d-none">
                    <span class="bx-composer__reply-bar" aria-hidden="true"></span>
                    <button type="button" class="bx-composer__reply-jump" id="bx-reply-jump" title="К сообщению">
                        <span class="bx-composer__reply-label">Ответ <strong id="bx-reply-author"></strong></span>
                        <span class="bx-composer__reply-preview" id="bx-reply-preview"></span>
                    </button>
                    <button type="button" class="bx-composer__icon-btn" id="bx-reply-cancel" title="Отмена">
                        <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                @php
                    $replyKb = $bot_reply_keyboard ?? null;
                    $replyKbRows = is_array($replyKb['keyboard'] ?? null) ? $replyKb['keyboard'] : [];
                    $replyKbOneTime = !empty($replyKb['one_time_keyboard']);
                @endphp
                <div id="bx-bot-reply-keyboard"
                     class="bx-bot-reply-keyboard {{ $replyKbRows === [] ? 'd-none' : '' }}"
                     @if($replyKbOneTime) data-one-time="1" @endif>
                    @foreach($replyKbRows as $row)
                        @if(is_array($row))
                            <div class="bx-bot-reply-keyboard__row">
                                @foreach($row as $btn)
                                    @php
                                        $label = is_array($btn) ? (string) ($btn['text'] ?? '') : (string) $btn;
                                    @endphp
                                    @if($label !== '')
                                        <button type="button" class="bx-bot-reply-keyboard__btn" data-reply-text="{{ $label }}">{{ $label }}</button>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
                <div id="bx-bot-cmd-menu" class="bx-bot-cmd-menu d-none" role="listbox" aria-label="Команды ботов"></div>

                <div class="bx-composer__box">
                    <div id="bx-composer-input"
                         class="bx-composer__input"
                         contenteditable="true"
                         role="textbox"
                         aria-multiline="true"
                         data-placeholder="Написать сообщение… @имя — упомянуть, Enter — отправить"
                         spellcheck="true"
                         autocomplete="none"
                         data-lpignore="true"
                         data-1p-ignore="true"
                         data-bwignore="true"
                         data-form-type="other"></div>

                    <div id="bx-mention-menu" class="bx-mention-menu d-none" role="listbox"></div>

                    <div class="bx-composer__toolbar">
                        <div class="bx-composer__tools">
                            <button type="button" class="bx-composer__tool" id="bx-tool-code" title="Блок кода">
                                <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/></svg>
                            </button>

                            <label class="bx-composer__tool" title="Файлы (до 10)">
                                <svg class="bx-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                                <input type="file"
                                       id="bx-composer-files"
                                       class="d-none"
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
                                    <input type="hidden" id="bx-task-id" value="">
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
        @php
            $infoPeer = $active->type === 'direct' ? $active->otherMember() : null;
            $infoMembersCount = $active->members->count();
        @endphp
        <div class="bx-sheet" id="bx-chat-info" hidden>
            <button type="button" class="bx-sheet__backdrop" id="bx-chat-info-close-bg" aria-label="Закрыть"></button>
            <div class="bx-sheet__panel bx-chat-info__panel" role="dialog" aria-modal="true" aria-labelledby="bx-chat-info-title">
                <div class="bx-chat-info__top">
                    <button type="button" class="bx-chat-info__back" id="bx-chat-info-close" aria-label="Закрыть">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <div class="bx-chat-info__profile">
                        @if($active->type === 'direct')
                            @include('orchid.layouts.partials.bx-avatar', [
                                'avatarUser' => $infoPeer,
                                'avatarChat' => null,
                                'size' => 'lg',
                                'shape' => 'round',
                                'showOnline' => true,
                                'isOnline' => $presenceOnline($infoPeer?->id),
                            ])
                        @else
                            <div class="bx-chat-info__avatar-wrap {{ $canEditChat ? 'is-editable' : '' }}" id="bx-chat-info-avatar-wrap">
                                @include('orchid.layouts.partials.bx-avatar', [
                                    'avatarChat' => $active,
                                    'avatarUser' => null,
                                    'size' => 'lg',
                                    'shape' => 'square',
                                ])
                                @if($canEditChat)
                                    <button type="button" class="bx-chat-info__avatar-edit" id="bx-chat-avatar-btn" title="Сменить фото">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                    </button>
                                    <input type="file" id="bx-chat-avatar-input" class="d-none" accept="image/jpeg,image/png,image/webp,image/gif">
                                @endif
                            </div>
                        @endif
                        <div class="bx-chat-info__profile-meta">
                            <strong id="bx-chat-info-title">{{ $active->displayTitle() }}</strong>
                            <span class="bx-chat-info__subtitle" id="bx-chat-info-subtitle">
                                @if($active->type === 'direct')
                                    {{ $presenceOnline($infoPeer?->id) ? 'в сети' : 'был(а) недавно' }}
                                @else
                                    @php
                                        $n = $infoMembersCount;
                                        $mod10 = $n % 10;
                                        $mod100 = $n % 100;
                                        $membersWord = ($mod10 === 1 && $mod100 !== 11)
                                            ? 'участник'
                                            : (($mod10 >= 2 && $mod10 <= 4 && !in_array($mod100, [12, 13, 14], true))
                                                ? 'участника'
                                                : 'участников');
                                    @endphp
                                    <span id="bx-chat-info-count">{{ $n }}</span> {{ $membersWord }}
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                @if($active->type !== 'direct' && $canEditChat)
                    <div class="bx-chat-info__actions">
                        <button type="button" class="bx-chat-info__action" id="bx-chat-edit-open" title="Изменить">
                            <span class="bx-chat-info__action-ico">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            </span>
                            <span>Изменить</span>
                        </button>
                        <button type="button" class="bx-chat-info__action" id="bx-chat-add-members-open" title="Добавить">
                            <span class="bx-chat-info__action-ico">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                            </span>
                            <span>Добавить</span>
                        </button>
                    </div>
                @endif

                @if($active->type !== 'direct')
                    <div class="bx-chat-info__about {{ trim((string) ($active->description ?? '')) === '' ? 'bx-chat-info__about--empty' : '' }}"
                         id="bx-chat-info-about"
                         @if(trim((string) ($active->description ?? '')) === '' && !$canEditChat) hidden @endif>
                        <div class="bx-chat-info__about-label">Описание</div>
                        <div class="bx-chat-info__about-text" id="bx-chat-info-description">
                            {{ trim((string) ($active->description ?? '')) !== '' ? $active->description : ($canEditChat ? 'Нет описания — нажмите «Изменить»' : '') }}
                        </div>
                    </div>
                @endif

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
                                @php
                                    $isOwnerMember = ($member->pivot?->role ?? '') === 'owner'
                                        || (int) $member->id === (int) $active->created_by;
                                @endphp
                                <li class="bx-members-modal__item" data-user-id="{{ $member->id }}" data-is-owner="{{ $isOwnerMember ? '1' : '0' }}" data-is-bot="{{ $member->is_bot ? '1' : '0' }}">
                                    @include('orchid.layouts.partials.bx-avatar', [
                                        'avatarUser' => $member,
                                        'avatarChat' => null,
                                        'size' => 'md',
                                        'shape' => 'round',
                                        'showOnline' => ! $member->is_bot,
                                        'isOnline' => $presenceOnline($member->id),
                                        'isBot' => (bool) $member->is_bot,
                                    ])
                                    <div class="bx-members-modal__meta">
                                        <div class="bx-members-modal__name">
                                            {{ $member->displayName() }}
                                            @if($member->is_bot)
                                                <span class="bx-msg__bot-tag">бот</span>
                                            @endif
                                        </div>
                                        <div class="bx-members-modal__status {{ $presenceOnline($member->id) && ! $member->is_bot ? 'is-online' : '' }}"
                                             data-online-label="в сети"
                                             data-offline-label="{{ $member->is_bot ? 'бот' : ($isOwnerMember ? 'владелец' : ($member->position ?: 'не в сети')) }}">
                                            @if($member->is_bot)
                                                бот
                                            @elseif($presenceOnline($member->id))
                                                в сети
                                            @elseif($isOwnerMember)
                                                владелец
                                            @elseif($member->position)
                                                {{ $member->position }}
                                            @else
                                                не в сети
                                            @endif
                                        </div>
                                    </div>
                                    @if($canEditChat && !$isOwnerMember)
                                        <button type="button"
                                                class="bx-members-modal__remove"
                                                data-remove-member="{{ $member->id }}"
                                                title="Удалить из чата"
                                                aria-label="Удалить">
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if($canEditChat)
                            <div class="bx-chat-info__danger">
                                <button type="button" class="bx-chat-info__danger-btn" id="bx-chat-delete-btn">
                                    Удалить группу
                                </button>
                            </div>
                        @endif
                    </div>
                    <div class="bx-chat-info__pane" data-info-pane="gallery" hidden>
                        <div id="bx-media-content" class="bx-media-content">Загрузка…</div>
                        <button type="button" class="bx-media-more d-none" id="bx-media-more">Показать ещё</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<div class="bx-sheet" id="bx-chat-edit-sheet" hidden>
    <button type="button" class="bx-sheet__backdrop" id="bx-chat-edit-bg" aria-label="Закрыть"></button>
    <div class="bx-sheet__panel bx-chat-edit__panel" role="dialog" aria-modal="true">
        <div class="bx-sheet__head">
            <strong>Изменить группу</strong>
            <button type="button" class="bx-sheet__close" id="bx-chat-edit-close" aria-label="Закрыть">×</button>
        </div>
        <form id="bx-chat-edit-form" class="bx-chat-edit__form">
            <label class="bx-chat-edit__avatar">
                <span class="bx-chat-edit__avatar-preview" id="bx-chat-edit-avatar-preview"></span>
                <span class="bx-chat-edit__avatar-label">Сменить фото</span>
                <input type="file" id="bx-chat-edit-avatar-file" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
            </label>
            <label class="bx-chat-edit__field">
                <span>Название</span>
                <input type="text" id="bx-chat-edit-title" maxlength="120" required placeholder="Название группы" autocomplete="off">
            </label>
            <label class="bx-chat-edit__field">
                <span>Описание</span>
                <textarea id="bx-chat-edit-description" rows="3" maxlength="1000" placeholder="О чём эта группа"></textarea>
            </label>
            <button type="submit" class="bx-chat-edit__save" id="bx-chat-edit-save">Сохранить</button>
        </form>
    </div>
</div>

<div class="bx-sheet" id="bx-chat-add-sheet" hidden>
    <button type="button" class="bx-sheet__backdrop" id="bx-chat-add-bg" aria-label="Закрыть"></button>
    <div class="bx-sheet__panel bx-chat-add__panel" role="dialog" aria-modal="true">
        <div class="bx-sheet__head">
            <strong>Добавить участников</strong>
            <button type="button" class="bx-sheet__close" id="bx-chat-add-close" aria-label="Закрыть">×</button>
        </div>
        <input type="search" id="bx-chat-add-search" class="bx-chat-add__search" placeholder="Поиск…" autocomplete="off">
        <div id="bx-chat-add-list" class="bx-chat-add__list"></div>
        <button type="button" class="bx-chat-edit__save" id="bx-chat-add-submit" disabled>Добавить</button>
    </div>
</div>
<script type="application/json" id="bx-member-options-json">@json(collect($member_options ?? [])->map(fn ($label, $id) => ['id' => (int) $id, 'name' => (string) $label])->values())</script>

{{-- Telegram-style message actions (long-press / context menu) --}}
<div class="bx-sheet bx-msg-actions-sheet" id="bx-msg-actions" hidden>
    <button type="button" class="bx-sheet__backdrop" id="bx-msg-actions-bg" aria-label="Закрыть"></button>
    <div class="bx-sheet__panel bx-msg-actions__panel" role="dialog" aria-modal="true" aria-labelledby="bx-msg-actions-title">
        <div class="bx-msg-actions__preview" id="bx-msg-actions-preview"></div>
        <div class="bx-msg-actions__list" role="menu">
            <button type="button" class="bx-msg-actions__item" data-msg-action="reply" role="menuitem">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 17H5a2 2 0 01-2-2V5a2 2 0 012-2h11a2 2 0 012 2v3"/><path d="M15 15l5-5-5-5"/><path d="M20 10H11"/></svg>
                <span>Ответить</span>
            </button>
            <button type="button" class="bx-msg-actions__item" data-msg-action="forward" role="menuitem">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 8l4 4-4 4"/><path d="M6 12h12"/></svg>
                <span>Переслать</span>
            </button>
            <button type="button" class="bx-msg-actions__item" data-msg-action="copy" role="menuitem">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                <span>Копировать</span>
            </button>
            <button type="button" class="bx-msg-actions__item" data-msg-action="select" role="menuitem">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.2 2.2L16 9.5"/></svg>
                <span>Выбрать</span>
            </button>
            <button type="button" class="bx-msg-actions__item bx-msg-actions__item--danger" data-msg-action="delete" role="menuitem">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                <span>Удалить</span>
            </button>
        </div>
        <button type="button" class="bx-msg-actions__cancel" id="bx-msg-actions-cancel">Отмена</button>
    </div>
</div>

<div class="bx-sheet bx-chat-actions-sheet" id="bx-chat-actions" hidden>
    <button type="button" class="bx-sheet__backdrop" id="bx-chat-actions-bg" aria-label="Закрыть"></button>
    <div class="bx-sheet__panel bx-chat-actions__panel" role="dialog" aria-modal="true">
        <div class="bx-chat-actions__preview" id="bx-chat-actions-preview"></div>
        <div class="bx-chat-actions__list" role="menu">
            <button type="button" class="bx-msg-actions__item" data-chat-action="pin" role="menuitem">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                <span id="bx-chat-action-pin-label">Закрепить</span>
            </button>
            <button type="button" class="bx-msg-actions__item" data-chat-action="mute" role="menuitem">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07"/></svg>
                <span id="bx-chat-action-mute-label">Без звука</span>
            </button>
            <button type="button" class="bx-msg-actions__item" data-chat-action="open" role="menuitem">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                <span>Открыть</span>
            </button>
        </div>
        <button type="button" class="bx-msg-actions__cancel" id="bx-chat-actions-cancel">Отмена</button>
    </div>
</div>

<div class="bx-sheet" id="bx-forward-sheet" hidden>
    <button type="button" class="bx-sheet__backdrop" id="bx-forward-close-bg" aria-label="Закрыть"></button>
    <div class="bx-sheet__panel bx-forward-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="bx-forward-title">
        <div class="bx-sheet__head">
            <strong id="bx-forward-title">Переслать</strong>
            <button type="button" class="bx-sheet__close" id="bx-forward-close" aria-label="Закрыть">×</button>
        </div>
        <div class="bx-forward-preview" id="bx-forward-preview" hidden></div>
        <div class="bx-forward-search">
            <input type="search"
                   id="bx-forward-search"
                   class="bx-forward-search__input"
                   placeholder="Поиск по чатам…"
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
        <div class="bx-lightbox__actions">
            <button type="button" class="bx-lightbox__goto d-none" id="bx-lightbox-goto">Показать в чате</button>
            <a class="bx-lightbox__open" href="#" target="_blank" rel="noopener">Открыть оригинал</a>
        </div>
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

    const root = document.querySelector('.bx-messenger');
    const feed = document.getElementById('chat-feed');
    const firstUnreadAttr = root?.getAttribute('data-first-unread') || '';
    const firstUnreadId = parseInt(firstUnreadAttr, 10) || 0;
    const scrollFeedInitial = () => {
        if (!feed) return;
        if (firstUnreadId > 0) {
            const divider = document.getElementById('bx-unread-divider');
            const target = divider || document.getElementById('chat-msg-' + firstUnreadId);
            if (target) {
                // Как в Telegram: к первому непрочитанному, а не в самый низ
                const feedRect = feed.getBoundingClientRect();
                const targetRect = target.getBoundingClientRect();
                const delta = (targetRect.top - feedRect.top) - Math.max(24, feed.clientHeight * 0.18);
                feed.scrollTop = Math.max(0, feed.scrollTop + delta);
                return;
            }
        }
        feed.scrollTop = feed.scrollHeight;
    };
    if (feed) {
        // Дождаться layout (аватары/код), затем позиционировать
        requestAnimationFrame(() => requestAnimationFrame(scrollFeedInitial));
    }

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

    // После POST Orchid (пин/мьют/создание чата) F5 иначе спрашивает про повтор формы
    try {
        if (window.history?.replaceState) {
            window.history.replaceState(null, document.title, window.location.href);
        }
    } catch (e) {}

    const input = document.getElementById('bx-composer-input');
    const parentInput = document.getElementById('chat-message-parent-id');
    const replyBanner = document.getElementById('bx-reply-banner');
    const replyAuthor = document.getElementById('bx-reply-author');
    const replyPreview = document.getElementById('bx-reply-preview');
    const replyJump = document.getElementById('bx-reply-jump');

    const clearReply = () => {
        if (parentInput) parentInput.value = '';
        replyBanner?.classList.add('d-none');
        if (replyPreview) replyPreview.textContent = '';
        replyBanner?.removeAttribute('data-reply-id');
    };

    const startReplyTo = (opts = {}) => {
        const id = String(opts.id || '').trim();
        if (!id || !parentInput) return;
        // Выход из режима выбора при ответе
        if (typeof selectedMessageIds !== 'undefined' && selectedMessageIds?.size) {
            selectedMessageIds.clear();
            if (typeof updateSelection === 'function') updateSelection();
        }
        parentInput.value = id;
        replyBanner?.classList.remove('d-none');
        replyBanner?.setAttribute('data-reply-id', id);
        if (replyAuthor) replyAuthor.textContent = opts.author || 'участник';
        if (replyPreview) replyPreview.textContent = opts.preview || 'Сообщение';
        input?.focus?.();
        try {
            const el = document.getElementById('chat-msg-' + id);
            el?.classList.add('bx-msg--replying');
            setTimeout(() => el?.classList.remove('bx-msg--replying'), 900);
        } catch (e) {}
    };

    const messageAuthorFromEl = (msgEl) => {
        const forwarded = msgEl?.querySelector('.bx-msg__forwarded-name')?.textContent?.trim();
        if (forwarded) return forwarded;
        const fromData = msgEl?.getAttribute('data-author');
        if (fromData) return fromData;
        const btn = msgEl?.querySelector('.bx-msg__reply-btn');
        return btn?.getAttribute('data-author')
            || msgEl?.querySelector('.bx-msg__meta strong')?.textContent?.trim()
            || 'участник';
    };

    const messagePreviewFromEl = (msgEl) => {
        if (!msgEl) return 'Сообщение';
        const fromData = msgEl.getAttribute('data-preview');
        if (fromData) return fromData;
        const btn = msgEl.querySelector('.bx-msg__reply-btn');
        const fromBtn = btn?.getAttribute('data-preview');
        if (fromBtn) return fromBtn;
        const body = (msgEl.querySelector('.bx-msg__body')?.innerText || '').replace(/\s+/g, ' ').trim();
        if (body) return body.slice(0, 120);
        if (msgEl.querySelector('.bx-voice')) return 'Голосовое сообщение';
        if (msgEl.querySelector('.bx-msg__image, .bx-msg__files')) return 'Вложение';
        return 'Сообщение';
    };
    const filesInput = document.getElementById('bx-composer-files');
    const filesLabel = document.getElementById('bx-files-label');
    const filesPreview = document.getElementById('bx-files-preview');
    const FILES_MAX = 10;
    let pendingFiles = [];

    // contenteditable вместо textarea — Яндекс.Браузер не показывает автозаполнение контактов
    const getComposerText = () => {
        if (!input) return '';
        return (input.innerText || '').replace(/\u00a0/g, ' ').replace(/\n$/, '');
    };
    const setComposerText = (text) => {
        if (!input) return;
        const next = text || '';
        input.textContent = next;
        setCaretOffset(next.length);
    };
    const getCaretOffset = () => {
        const sel = window.getSelection();
        if (!input || !sel || !sel.rangeCount) return getComposerText().length;
        const range = sel.getRangeAt(0);
        if (!input.contains(range.startContainer)) return getComposerText().length;
        const pre = range.cloneRange();
        pre.selectNodeContents(input);
        pre.setEnd(range.startContainer, range.startOffset);
        return pre.toString().length;
    };
    const getCaretEndOffset = () => {
        const sel = window.getSelection();
        if (!input || !sel || !sel.rangeCount) return getCaretOffset();
        const range = sel.getRangeAt(0);
        if (!input.contains(range.endContainer)) return getCaretOffset();
        const pre = range.cloneRange();
        pre.selectNodeContents(input);
        pre.setEnd(range.endContainer, range.endOffset);
        return pre.toString().length;
    };
    const setCaretOffset = (pos) => {
        if (!input) return;
        const walk = document.createTreeWalker(input, NodeFilter.SHOW_TEXT, null);
        let node; let left = Math.max(0, pos);
        let target = input; let targetOffset = 0;
        while ((node = walk.nextNode())) {
            const len = node.nodeValue?.length || 0;
            if (left <= len) {
                target = node;
                targetOffset = left;
                break;
            }
            left -= len;
            target = node;
            targetOffset = len;
        }
        const range = document.createRange();
        try {
            range.setStart(target, Math.min(targetOffset, target.nodeType === 3 ? target.nodeValue.length : 0));
            range.collapse(true);
            const sel = window.getSelection();
            sel?.removeAllRanges();
            sel?.addRange(range);
        } catch (err) {}
    };
    // Совместимость со старым API textarea
    if (input) {
        Object.defineProperty(input, 'value', {
            configurable: true,
            get() { return getComposerText(); },
            set(v) { setComposerText(v); },
        });
        Object.defineProperty(input, 'selectionStart', {
            configurable: true,
            get() { return getCaretOffset(); },
            set(v) { setCaretOffset(Number(v) || 0); },
        });
        Object.defineProperty(input, 'selectionEnd', {
            configurable: true,
            get() { return getCaretEndOffset(); },
            set(v) { setCaretOffset(Number(v) || 0); },
        });
        input.setSelectionRange = (start) => setCaretOffset(Number(start) || 0);
        Object.defineProperty(input, 'disabled', {
            configurable: true,
            get() { return input.getAttribute('contenteditable') === 'false'; },
            set(v) { input.setAttribute('contenteditable', v ? 'false' : 'true'); },
        });
        // Вставка только plain text — без HTML и без автозаполнения
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData)?.getData('text/plain') || '';
            document.execCommand('insertText', false, text);
        });
    }

    const toast = (msg, type = 'info') => {
        if (typeof window.uiToast === 'function') window.uiToast(msg, type);
        else console.warn(msg);
    };

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
            toast('Можно прикрепить не больше ' + FILES_MAX + ' файлов за раз', 'info');
            syncFilesInput();
            return;
        }
        const add = incoming.slice(0, room);
        if (incoming.length > room) {
            toast('Добавлено ' + add.length + ' из ' + incoming.length + ' (лимит ' + FILES_MAX + ')', 'info');
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
        const empty = getComposerText().trim() === '';
        input.classList.toggle('is-empty', empty);
        if (empty && input.innerHTML === '<br>') input.innerHTML = '';
    };
    input?.addEventListener('input', () => {
        autosize();
        updateMentionMenu();
    });
    autosize();
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

        mentionMenu.innerHTML = filtered.map((u, i) => {
            const color = escapeHtml(u.avatar_color || '#64748b');
            const initials = escapeHtml((u.avatar_initials || (u.name || '?').slice(0, 1)).toUpperCase());
            const img = u.avatar_url
                ? `<img class="bx-avatar__img" src="${escapeHtml(u.avatar_url)}" alt="" loading="lazy" onerror="this.remove()">`
                : '';
            return `<button type="button" class="bx-mention-item ${i === 0 ? 'is-active' : ''}" data-mention-name="${escapeHtml(u.name)}" role="option">
                <span class="bx-avatar bx-avatar--xs bx-avatar--round bx-mention-item__avatar" style="--bx-avatar-bg:${color}">
                    <span class="bx-avatar__initials">${initials}</span>${img}
                </span>
                <span class="bx-mention-item__name">${escapeHtml(u.name)}</span>
            </button>`;
        }).join('');
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
            startReplyTo({
                id: reply.getAttribute('data-parent-id'),
                author: reply.getAttribute('data-author'),
                preview: reply.getAttribute('data-preview'),
            });
            return;
        }

        const quote = e.target.closest?.('.bx-msg__reply[data-goto-msg]');
        if (quote) {
            e.preventDefault();
            goToChatMessage(quote.getAttribute('data-goto-msg'));
            return;
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

    document.getElementById('bx-reply-cancel')?.addEventListener('click', clearReply);
    replyJump?.addEventListener('click', (e) => {
        e.preventDefault();
        const id = replyBanner?.getAttribute('data-reply-id') || parentInput?.value;
        if (id) goToChatMessage(id);
    });

    autosize();

    /* Галерея чата (внутри окна информации) — как Shared Media в Telegram */
    const mediaContent = document.getElementById('bx-media-content');
    const mediaMore = document.getElementById('bx-media-more');
    let mediaTab = 'media';
    let mediaPage = 1;

    const fileExtColor = (ext) => {
        const e = String(ext || '').toUpperCase();
        if (['PDF'].includes(e)) return '#e11d48';
        if (['DOC', 'DOCX', 'TXT', 'RTF', 'ODT'].includes(e)) return '#2563eb';
        if (['XLS', 'XLSX', 'CSV'].includes(e)) return '#16a34a';
        if (['PPT', 'PPTX'].includes(e)) return '#ea580c';
        if (['ZIP', 'RAR', '7Z', 'GZ'].includes(e)) return '#7c3aed';
        if (['MP4', 'MOV', 'AVI', 'MKV', 'WEBM'].includes(e)) return '#0f766e';
        return '#64748b';
    };

    const domainHue = (domain) => {
        let h = 0;
        const s = String(domain || '');
        for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
        return h % 360;
    };

    const goToChatMessage = async (messageId) => {
        const id = Number(messageId) || 0;
        if (!id) return;
        try { typeof closeChatInfo === 'function' && closeChatInfo(); } catch (e) {}
        let el = document.getElementById('chat-msg-' + id);
        if (!el) {
            // Подгрузить историю вверх, пока не найдём (как «Show in chat»)
            let guard = 0;
            while (!el && guard < 8 && root?.getAttribute('data-has-more') === '1') {
                guard++;
                try {
                    await (typeof loadOlderMessages === 'function' ? loadOlderMessages() : Promise.resolve());
                } catch (e) { break; }
                el = document.getElementById('chat-msg-' + id);
            }
        }
        if (!el) {
            const base = window.location.pathname;
            window.location.href = base + '?msg=' + id;
            return;
        }
        el.classList.add('bx-msg--highlight');
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        setTimeout(() => el.classList.remove('bx-msg--highlight'), 2800);
    };

    const renderMediaItems = (items) => {
        if (mediaTab === 'media') {
            return items.map((item) => `
                <div class="bx-shared-media__cell" data-message-id="${escapeHtml(item.message_id)}">
                    <a class="bx-shared-media__thumb" href="${escapeHtml(item.url)}" data-bx-lightbox="${escapeHtml(item.url)}" data-message-id="${escapeHtml(item.message_id)}" title="${escapeHtml(item.name)}">
                        <img src="${escapeHtml(item.url)}" alt="${escapeHtml(item.name)}" loading="lazy">
                    </a>
                    <button type="button" class="bx-shared-media__goto" data-goto-msg="${escapeHtml(item.message_id)}" title="Показать в чате" aria-label="Показать в чате">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                    </button>
                </div>`).join('');
        }

        if (mediaTab === 'files') {
            return items.map((item) => {
                const ext = escapeHtml(item.ext || 'FILE');
                const color = fileExtColor(item.ext);
                const meta = [item.size_label, item.author, item.created_at].filter(Boolean).join(' · ');
                return `
                <div class="bx-shared-row bx-shared-row--file" data-message-id="${escapeHtml(item.message_id)}">
                    <a class="bx-shared-row__main" href="${escapeHtml(item.download_url)}" download title="Скачать">
                        <span class="bx-shared-file-icon" style="--bx-file-bg:${color}">${ext.slice(0, 4)}</span>
                        <span class="bx-shared-row__body">
                            <span class="bx-shared-row__title">${escapeHtml(item.name)}</span>
                            <span class="bx-shared-row__meta">${escapeHtml(meta)}</span>
                        </span>
                    </a>
                    <button type="button" class="bx-shared-row__goto" data-goto-msg="${escapeHtml(item.message_id)}" title="Показать в чате">В чат</button>
                </div>`;
            }).join('');
        }

        // links
        return items.map((item) => {
            const domain = item.domain || '';
            const hue = domainHue(domain);
            const letter = escapeHtml((domain || item.title || '?').slice(0, 1).toUpperCase());
            const subtitle = [item.path, item.author, item.created_at].filter(Boolean).join(' · ');
            return `
            <div class="bx-shared-row bx-shared-row--link" data-message-id="${escapeHtml(item.message_id || item.id)}">
                <a class="bx-shared-row__main" href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer">
                    <span class="bx-shared-link-icon" style="--bx-link-hue:${hue}">${letter}</span>
                    <span class="bx-shared-row__body">
                        <span class="bx-shared-row__title">${escapeHtml(item.title || item.url)}</span>
                        <span class="bx-shared-row__url">${escapeHtml(item.url)}</span>
                        <span class="bx-shared-row__meta">${escapeHtml(subtitle || item.text || '')}</span>
                    </span>
                </a>
                <button type="button" class="bx-shared-row__goto" data-goto-msg="${escapeHtml(item.message_id || item.id)}" title="Показать в чате">В чат</button>
            </div>`;
        }).join('');
    };

    const loadMedia = async (replace) => {
        const mediaUrl = root?.getAttribute('data-media-url');
        if (!mediaUrl || !mediaContent) return;
        if (replace) mediaContent.innerHTML = '<div class="bx-shared-empty">Загрузка…</div>';
        mediaContent.className = 'bx-media-content'
            + (mediaTab === 'media' ? ' bx-media-content--grid' : ' bx-media-content--list');
        try {
            const response = await fetch(`${mediaUrl}?tab=${encodeURIComponent(mediaTab)}&page=${mediaPage}`, {
                credentials: 'same-origin', headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Не удалось загрузить материалы');
            const items = data.items || [];
            const html = items.length
                ? renderMediaItems(items)
                : (replace ? '<div class="bx-shared-empty">Пока ничего нет</div>' : '');
            if (replace) mediaContent.innerHTML = html;
            else mediaContent.insertAdjacentHTML('beforeend', html);
            mediaMore?.classList.toggle('d-none', !data.has_more);
        } catch (error) {
            if (replace) mediaContent.innerHTML = '<div class="bx-shared-empty">'
                + escapeHtml(error.message || 'Не удалось загрузить материалы') + '</div>';
        }
    };

    mediaContent?.addEventListener('click', (e) => {
        const goto = e.target.closest?.('[data-goto-msg]');
        if (!goto) return;
        e.preventDefault();
        e.stopPropagation();
        goToChatMessage(goto.getAttribute('data-goto-msg'));
    });

    /* Chat info (участники + медиа) */
    const chatInfo = document.getElementById('bx-chat-info');
    const switchInfoTab = (tab) => {
        const isGallery = tab === 'media' || tab === 'files' || tab === 'links';
        document.querySelectorAll('#bx-chat-info [data-info-tab]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.getAttribute('data-info-tab') === tab);
        });
        document.querySelectorAll('#bx-chat-info [data-info-pane]').forEach((pane) => {
            const name = pane.getAttribute('data-info-pane');
            const on = name === 'members' ? tab === 'members' : (name === 'gallery' && isGallery);
            pane.classList.toggle('is-active', on);
            pane.toggleAttribute('hidden', !on);
        });
        const body = document.querySelector('#bx-chat-info .bx-chat-info__body');
        if (body) body.scrollTop = 0;
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
        if (e.key !== 'Escape') return;
        if (msgActionsSheet && !msgActionsSheet.hasAttribute('hidden')) {
            closeMsgActions();
            return;
        }
        if (chatInfo && !chatInfo.hasAttribute('hidden')) {
            closeChatInfo();
        }
    });
    document.querySelectorAll('#bx-chat-info [data-info-tab]').forEach((btn) => {
        btn.addEventListener('click', () => switchInfoTab(btn.getAttribute('data-info-tab') || 'members'));
    });
    mediaMore?.addEventListener('click', () => {
        mediaPage++;
        loadMedia(false);
    });

    /* Редактирование группы как в Telegram */
    (() => {
        if (root?.getAttribute('data-can-edit-chat') !== '1') return;
        const csrfToken = root.getAttribute('data-csrf')
            || document.querySelector('meta[name="csrf_token"]')?.content
            || document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="_token"]')?.value
            || '';

        const settingsUrl = root.getAttribute('data-chat-settings-url') || '';
        const avatarUrl = root.getAttribute('data-chat-avatar-url') || '';
        const addUrl = root.getAttribute('data-chat-members-add-url') || '';
        const removeTpl = root.getAttribute('data-chat-member-remove-tpl') || '';
        const destroyUrl = root.getAttribute('data-chat-destroy-url') || '';

        const editSheet = document.getElementById('bx-chat-edit-sheet');
        const addSheet = document.getElementById('bx-chat-add-sheet');
        const titleInput = document.getElementById('bx-chat-edit-title');
        const descInput = document.getElementById('bx-chat-edit-description');
        const avatarPreview = document.getElementById('bx-chat-edit-avatar-preview');
        const editAvatarFile = document.getElementById('bx-chat-edit-avatar-file');
        const infoAvatarInput = document.getElementById('bx-chat-avatar-input');
        const addList = document.getElementById('bx-chat-add-list');
        const addSearch = document.getElementById('bx-chat-add-search');
        const addSubmit = document.getElementById('bx-chat-add-submit');

        let memberOptions = [];
        try {
            memberOptions = JSON.parse(document.getElementById('bx-member-options-json')?.textContent || '[]');
        } catch (e) { memberOptions = []; }
        const selectedAdd = new Set();

        const jsonHeaders = () => ({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        });

        const openSheet = (el) => el?.removeAttribute('hidden');
        const closeSheet = (el) => el?.setAttribute('hidden', '');

        const syncHeaderTitle = (title) => {
            const el = document.getElementById('bx-chat-info-title');
            if (el) el.textContent = title;
            const head = document.querySelector('.bx-chat-identity__title, .bx-messenger__header strong');
            if (head) head.textContent = title;
            const listItem = document.querySelector('.bx-chat-item.is-active .bx-chat-item__title');
            if (listItem) listItem.textContent = title;
        };

        const syncDescription = (text) => {
            const about = document.getElementById('bx-chat-info-about');
            const desc = document.getElementById('bx-chat-info-description');
            if (!about || !desc) return;
            const clean = String(text || '').trim();
            about.hidden = false;
            about.classList.toggle('bx-chat-info__about--empty', !clean);
            desc.textContent = clean || 'Нет описания — нажмите «Изменить»';
        };

        const setAvatarPreview = (url, initials, color) => {
            if (!avatarPreview) return;
            if (url) {
                avatarPreview.innerHTML = `<img src="${escapeHtml(url)}" alt="">`;
                avatarPreview.style.background = '';
            } else {
                avatarPreview.textContent = initials || '#';
                avatarPreview.style.background = color || '#64748b';
            }
        };

        const applyAvatarToDom = (url) => {
            const targets = [
                document.querySelector('#bx-chat-info-avatar-wrap .bx-avatar'),
                document.querySelector('.bx-chat-identity .bx-avatar'),
                document.querySelector('.bx-chat-item.is-active .bx-avatar'),
            ].filter(Boolean);
            targets.forEach((av) => {
                let img = av.querySelector('.bx-avatar__img');
                if (url) {
                    if (!img) {
                        img = document.createElement('img');
                        img.className = 'bx-avatar__img';
                        img.alt = '';
                        av.appendChild(img);
                    }
                    img.src = url;
                }
            });
            if (avatarPreview && url) setAvatarPreview(url);
        };

        const uploadAvatar = async (file) => {
            if (!file || !avatarUrl) return;
            const fd = new FormData();
            fd.append('avatar', file);
            if (csrfToken) fd.append('_token', csrfToken);
            const res = await fetch(avatarUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}) },
                credentials: 'same-origin',
                body: fd,
            });
            if (!res.ok) throw new Error('avatar');
            const data = await res.json();
            applyAvatarToDom(data.avatar_url);
            toast('Фото обновлено', 'success');
        };

        const openEdit = () => {
            const title = document.getElementById('bx-chat-info-title')?.textContent?.trim() || '';
            const descEl = document.getElementById('bx-chat-info-description');
            let desc = descEl?.textContent?.trim() || '';
            if (desc.startsWith('Нет описания')) desc = '';
            if (titleInput) titleInput.value = title;
            if (descInput) descInput.value = desc;
            const infoAv = document.querySelector('#bx-chat-info-avatar-wrap .bx-avatar');
            const img = infoAv?.querySelector('.bx-avatar__img')?.getAttribute('src');
            const initials = infoAv?.querySelector('.bx-avatar__initials')?.textContent || '#';
            const color = getComputedStyle(infoAv || document.body).getPropertyValue('--bx-avatar-bg').trim() || '#64748b';
            setAvatarPreview(img, initials, color);
            openSheet(editSheet);
        };

        const membersWord = (n) => {
            const mod10 = n % 10;
            const mod100 = n % 100;
            if (mod10 === 1 && mod100 !== 11) return 'участник';
            if (mod10 >= 2 && mod10 <= 4 && ![12, 13, 14].includes(mod100)) return 'участника';
            return 'участников';
        };

        const renderMembers = (members) => {
            const list = document.getElementById('bx-members-list');
            if (!list || !Array.isArray(members)) return;
            const selfId = String(root.getAttribute('data-self-id') || '');
            list.innerHTML = members.map((m) => {
                const online = !!m.online && !m.is_bot;
                const owner = !!m.is_owner;
                const isBot = !!m.is_bot;
                const status = isBot ? 'бот' : (online ? 'в сети' : (owner ? 'владелец' : (m.position || 'не в сети')));
                const img = m.avatar_url
                    ? `<img class="bx-avatar__img" src="${escapeHtml(m.avatar_url)}" alt="" loading="lazy" onerror="this.remove()">`
                    : '';
                const botBadge = isBot
                    ? `<span class="bx-bot-badge" title="Бот" aria-label="Бот"><svg viewBox="0 0 16 16" width="10" height="10" fill="currentColor" aria-hidden="true"><path d="M8 1.5a.75.75 0 0 1 .75.75v.76A3.75 3.75 0 0 1 11.75 6.5h.5a.75.75 0 0 1 0 1.5h-.5v.25c0 .69-.28 1.32-.73 1.77l.98 1.47a.75.75 0 1 1-1.25.83l-.9-1.35a3.73 3.73 0 0 1-1.6.35h-.5c-.56 0-1.1-.12-1.6-.35l-.9 1.35a.75.75 0 1 1-1.25-.83l.98-1.47A2.49 2.49 0 0 1 4.25 8.25V8h-.5a.75.75 0 0 1 0-1.5h.5A3.75 3.75 0 0 1 7.25 3.01V2.25A.75.75 0 0 1 8 1.5zm-1.5 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5zm3 0a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5z"/></svg></span>`
                    : '';
                const botTag = isBot ? ' <span class="bx-msg__bot-tag">бот</span>' : '';
                const removeBtn = (!owner)
                    ? `<button type="button" class="bx-members-modal__remove" data-remove-member="${m.id}" title="Удалить из чата" aria-label="Удалить"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>`
                    : '';
                return `<li class="bx-members-modal__item" data-user-id="${m.id}" data-is-owner="${owner ? '1' : '0'}" data-is-bot="${isBot ? '1' : '0'}">
                    <div class="bx-avatar-wrap ${isBot ? 'is-bot' : ''}" data-user-id="${m.id}" ${isBot ? 'data-is-bot="1"' : ''}>
                        <span class="bx-avatar bx-avatar--md bx-avatar--round ${isBot ? 'bx-avatar--bot' : ''}" style="--bx-avatar-bg:${escapeHtml(m.avatar_color || '#64748b')}">
                            <span class="bx-avatar__initials">${escapeHtml(m.avatar_initials || '?')}</span>${img}
                        </span>
                        ${botBadge}
                        <span class="bx-online-dot ${online ? '' : 'd-none'}"></span>
                    </div>
                    <div class="bx-members-modal__meta">
                        <div class="bx-members-modal__name">${escapeHtml(m.name || 'Участник')}${String(m.id) === selfId ? ' (вы)' : ''}${botTag}</div>
                        <div class="bx-members-modal__status ${online ? 'is-online' : ''}" data-online-label="в сети" data-offline-label="${escapeHtml(isBot ? 'бот' : (owner ? 'владелец' : (m.position || 'не в сети')))}">${escapeHtml(status)}</div>
                    </div>
                    ${removeBtn}
                </li>`;
            }).join('');
            const countEl = document.getElementById('bx-chat-info-count');
            const sub = document.getElementById('bx-chat-info-subtitle');
            if (countEl) countEl.textContent = String(members.length);
            else if (sub) sub.innerHTML = `<span id="bx-chat-info-count">${members.length}</span> ${membersWord(members.length)}`;
        };

        const currentMemberIds = () => [...document.querySelectorAll('#bx-members-list [data-user-id]')]
            .map((el) => Number(el.getAttribute('data-user-id')))
            .filter(Boolean);

        const renderAddList = (query = '') => {
            if (!addList) return;
            const q = query.trim().toLowerCase();
            const existing = new Set(currentMemberIds());
            const items = memberOptions.filter((o) => !existing.has(Number(o.id))
                && (!q || String(o.name || '').toLowerCase().includes(q)));
            if (!items.length) {
                addList.innerHTML = '<div class="bx-shared-empty">Никого не найдено</div>';
                return;
            }
            addList.innerHTML = items.map((o) => {
                const on = selectedAdd.has(Number(o.id));
                return `<button type="button" class="bx-chat-add__item ${on ? 'is-on' : ''}" data-add-id="${o.id}">
                    <span class="bx-chat-add__item-name">${escapeHtml(o.name || 'Участник')}</span>
                    <span class="bx-chat-add__check" aria-hidden="true"></span>
                </button>`;
            }).join('');
        };

        const openAdd = () => {
            selectedAdd.clear();
            if (addSearch) addSearch.value = '';
            renderAddList();
            if (addSubmit) addSubmit.disabled = true;
            openSheet(addSheet);
        };

        const destroyChat = async () => {
            if (!destroyUrl) return;
            const ok = window.confirm('Удалить групповой чат безвозвратно? Сообщения и файлы будут удалены.');
            if (!ok) return;
            try {
                const res = await fetch(destroyUrl, {
                    method: 'DELETE',
                    headers: jsonHeaders(),
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('fail');
                const data = await res.json();
                toast('Чат удалён', 'success');
                window.location.href = data.redirect || '/admin/chats';
            } catch (e) {
                toast('Не удалось удалить чат', 'error');
            }
        };

        document.getElementById('bx-chat-edit-open')?.addEventListener('click', openEdit);
        document.getElementById('bx-header-edit-chat')?.addEventListener('click', () => {
            document.getElementById('bx-header-menu-drop')?.setAttribute('hidden', '');
            openEdit();
        });
        document.getElementById('bx-chat-add-members-open')?.addEventListener('click', openAdd);
        document.getElementById('bx-header-add-members')?.addEventListener('click', () => {
            document.getElementById('bx-header-menu-drop')?.setAttribute('hidden', '');
            openAdd();
        });
        document.getElementById('bx-chat-delete-btn')?.addEventListener('click', destroyChat);
        document.getElementById('bx-header-delete-chat')?.addEventListener('click', () => {
            document.getElementById('bx-header-menu-drop')?.setAttribute('hidden', '');
            destroyChat();
        });

        document.getElementById('bx-chat-edit-close')?.addEventListener('click', () => closeSheet(editSheet));
        document.getElementById('bx-chat-edit-bg')?.addEventListener('click', () => closeSheet(editSheet));
        document.getElementById('bx-chat-add-close')?.addEventListener('click', () => closeSheet(addSheet));
        document.getElementById('bx-chat-add-bg')?.addEventListener('click', () => closeSheet(addSheet));

        document.getElementById('bx-chat-avatar-btn')?.addEventListener('click', () => infoAvatarInput?.click());
        infoAvatarInput?.addEventListener('change', async () => {
            const file = infoAvatarInput.files?.[0];
            infoAvatarInput.value = '';
            if (!file) return;
            try { await uploadAvatar(file); } catch (e) { toast('Не удалось загрузить фото', 'error'); }
        });
        editAvatarFile?.addEventListener('change', async () => {
            const file = editAvatarFile.files?.[0];
            if (!file) return;
            if (avatarPreview) {
                const url = URL.createObjectURL(file);
                setAvatarPreview(url);
            }
            try { await uploadAvatar(file); } catch (e) { toast('Не удалось загрузить фото', 'error'); }
            editAvatarFile.value = '';
        });

        document.getElementById('bx-chat-edit-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!settingsUrl) return;
            const title = (titleInput?.value || '').trim();
            if (!title) {
                toast('Укажите название', 'error');
                return;
            }
            const saveBtn = document.getElementById('bx-chat-edit-save');
            if (saveBtn) saveBtn.disabled = true;
            try {
                const res = await fetch(settingsUrl, {
                    method: 'POST',
                    headers: { ...jsonHeaders(), 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        title,
                        description: (descInput?.value || '').trim(),
                        _token: csrfToken,
                    }),
                });
                if (!res.ok) throw new Error('fail');
                const data = await res.json();
                syncHeaderTitle(data.title || title);
                syncDescription(data.description || '');
                closeSheet(editSheet);
                toast('Сохранено', 'success');
            } catch (err) {
                toast('Не удалось сохранить', 'error');
            } finally {
                if (saveBtn) saveBtn.disabled = false;
            }
        });

        addSearch?.addEventListener('input', () => renderAddList(addSearch.value));
        addList?.addEventListener('click', (e) => {
            const btn = e.target.closest?.('[data-add-id]');
            if (!btn) return;
            const id = Number(btn.getAttribute('data-add-id'));
            if (!id) return;
            if (selectedAdd.has(id)) selectedAdd.delete(id);
            else selectedAdd.add(id);
            btn.classList.toggle('is-on', selectedAdd.has(id));
            if (addSubmit) addSubmit.disabled = selectedAdd.size === 0;
        });
        addSubmit?.addEventListener('click', async () => {
            if (!addUrl || !selectedAdd.size) return;
            addSubmit.disabled = true;
            try {
                const res = await fetch(addUrl, {
                    method: 'POST',
                    headers: { ...jsonHeaders(), 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ member_ids: [...selectedAdd], _token: csrfToken }),
                });
                if (!res.ok) throw new Error('fail');
                const data = await res.json();
                renderMembers(data.members || []);
                closeSheet(addSheet);
                toast('Участники добавлены', 'success');
            } catch (e) {
                toast('Не удалось добавить', 'error');
                addSubmit.disabled = false;
            }
        });

        document.getElementById('bx-members-list')?.addEventListener('click', async (e) => {
            const btn = e.target.closest?.('[data-remove-member]');
            if (!btn) return;
            const id = btn.getAttribute('data-remove-member');
            const url = (removeTpl || '').replace('__ID__', String(id));
            if (!url || url.includes('__ID__')) return;
            const name = btn.closest('.bx-members-modal__item')?.querySelector('.bx-members-modal__name')?.textContent?.trim() || 'участника';
            if (!window.confirm('Удалить ' + name + ' из чата?')) return;
            try {
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: jsonHeaders(),
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.message || 'fail');
                }
                const data = await res.json();
                document.querySelector('#bx-members-list [data-user-id="' + id + '"]')?.remove();
                const countEl = document.getElementById('bx-chat-info-count');
                if (countEl && data.count != null) countEl.textContent = String(data.count);
                toast('Участник удалён', 'success');
            } catch (err) {
                toast(err.message && err.message !== 'fail' ? err.message : 'Не удалось удалить', 'error');
            }
        });
    })();

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
    const replySelected = document.getElementById('bx-reply-selected');
    const copySelected = document.getElementById('bx-copy-selected');
    const forwardSheet = document.getElementById('bx-forward-sheet');
    const forwardChats = document.getElementById('bx-forward-chats');
    const forwardPreview = document.getElementById('bx-forward-preview');
    const updateSelection = () => {
        const count = selectedMessageIds.size;
        selectionBar?.toggleAttribute('hidden', count === 0);
        if (selectionCount) selectionCount.textContent = count ? ('Выбрано: ' + count) : 'Выбрано: 0';
        const forwardCount = document.getElementById('bx-forward-count');
        if (forwardCount) forwardCount.textContent = String(count);
        if (forwardSelected) {
            forwardSelected.setAttribute('aria-label', 'Переслать (' + count + ')');
        }
        // Ответ только на одно сообщение
        replySelected?.toggleAttribute('hidden', count !== 1);
        root?.classList.toggle('is-selecting', count > 0);
        document.querySelectorAll('.bx-msg:not(.bx-msg--system)').forEach((message) => {
            const id = Number(String(message.id || '').replace('chat-msg-', ''));
            const on = selectedMessageIds.has(id);
            message.classList.toggle('is-selected', on);
            const check = message.querySelector('[data-msg-check]');
            if (check) {
                check.classList.toggle('is-on', on);
                check.setAttribute('aria-pressed', on ? 'true' : 'false');
                check.setAttribute('aria-label', on ? 'Снять выделение' : 'Выбрать сообщение');
            }
        });
        // Скрыть композер в режиме выбора — как в VK/Telegram
        document.getElementById('bx-composer')?.classList.toggle('is-dimmed', count > 0);
    };
    const toggleMessageSelection = (id) => {
        if (!id) return;
        if (selectedMessageIds.has(id)) selectedMessageIds.delete(id);
        else if (selectedMessageIds.size < 20) selectedMessageIds.add(id);
        updateSelection();
    };

    /* Telegram action sheet для сообщения */
    const msgActionsSheet = document.getElementById('bx-msg-actions');
    const msgActionsPreview = document.getElementById('bx-msg-actions-preview');
    let msgActionsTargetId = 0;

    const closeMsgActions = () => {
        msgActionsSheet?.setAttribute('hidden', '');
        msgActionsTargetId = 0;
        if (msgActionsPreview) msgActionsPreview.innerHTML = '';
    };

    const openMsgActions = (msgEl) => {
        if (!msgActionsSheet || !msgEl) return;
        const id = Number(String(msgEl.id || '').replace('chat-msg-', ''));
        if (!id) return;
        msgActionsTargetId = id;
        const author = escapeHtml(messageAuthorFromEl(msgEl));
        const preview = escapeHtml(messagePreviewFromEl(msgEl));
        if (msgActionsPreview) {
            msgActionsPreview.innerHTML = `<div class="bx-msg-actions__preview-card"><strong>${author}</strong><span>${preview}</span></div>`;
        }
        msgActionsSheet.removeAttribute('hidden');
        try { navigator.vibrate?.(18); } catch (e) {}
    };

    const openForwardForIds = (ids) => {
        selectedMessageIds.clear();
        ids.forEach((id) => selectedMessageIds.add(Number(id)));
        updateSelection();
        // Не оставляем панель выбора видимой при одиночной пересылке из меню
        if (ids.length === 1) {
            selectionBar?.setAttribute('hidden', '');
            root?.classList.remove('is-selecting');
            document.getElementById('bx-composer')?.classList.remove('is-dimmed');
        }
        document.getElementById('bx-forward-selected')?.click();
    };

    document.getElementById('bx-msg-actions-bg')?.addEventListener('click', closeMsgActions);
    document.getElementById('bx-msg-actions-cancel')?.addEventListener('click', closeMsgActions);
    msgActionsSheet?.addEventListener('click', async (e) => {
        const btn = e.target.closest?.('[data-msg-action]');
        if (!btn) return;
        const action = btn.getAttribute('data-msg-action');
        const id = msgActionsTargetId;
        const el = document.getElementById('chat-msg-' + id);
        closeMsgActions();
        if (!id || !action) return;

        if (action === 'reply') {
            startReplyTo({
                id,
                author: messageAuthorFromEl(el),
                preview: messagePreviewFromEl(el),
            });
            return;
        }
        if (action === 'forward') {
            openForwardForIds([id]);
            return;
        }
        if (action === 'copy') {
            const text = messagePreviewFromEl(el);
            try {
                await navigator.clipboard.writeText(text);
                toast('Скопировано', 'success');
            } catch (err) {
                toast('Не удалось скопировать', 'error');
            }
            return;
        }
        if (action === 'select') {
            selectedMessageIds.clear();
            selectedMessageIds.add(id);
            updateSelection();
            return;
        }
        if (action === 'delete') {
            selectedMessageIds.clear();
            selectedMessageIds.add(id);
            updateSelection();
            document.getElementById('bx-delete-selected')?.click();
        }
    });

    // Удержание / long-press как в Telegram; Ctrl/Cmd+клик и tap в режиме выбора — toggle.
    // Делегирование на ленту: новые сообщения тоже работают; на мобиле блокируем scroll во время удержания.
    // Флаг: пользователь тянет выделение текста в сообщении — композер не должен воровать фокус.
    let selectingMessageText = false;
    const isMessageTextTarget = (t) => !!t?.closest?.(
        '.bx-msg__body, .bx-msg__meta, .bx-msg__forwarded, .bx-msg__time, .tw-codeblock, pre, code'
    );
    const hasFeedTextSelection = () => {
        const sel = window.getSelection?.();
        if (!sel || sel.isCollapsed || !String(sel).trim()) return false;
        const node = sel.anchorNode;
        if (!node || (input && input.contains(node))) return false;
        const feedEl = document.getElementById('chat-feed');
        return !!(feedEl && feedEl.contains(node));
    };
    const markMessageTextSelect = () => {
        selectingMessageText = true;
    };
    const releaseMessageTextSelectSoon = () => {
        window.setTimeout(() => {
            if (hasFeedTextSelection()) return;
            selectingMessageText = false;
        }, 80);
    };

    (() => {
        const feedEl = document.getElementById('chat-feed');
        if (!feedEl) return;

        let timer = null;
        let holdFired = false;
        let suppressClickUntil = 0;
        let activeMsg = null;
        let holdStart = null;
        let holding = false;

        const isCoarse = () => window.matchMedia('(pointer: coarse)').matches
            || ('ontouchstart' in window);
        const moveTol = () => (isCoarse() ? 28 : 12);
        const holdMs = () => (isCoarse() ? 450 : 380);
        const msgIdOf = (el) => Number(String(el?.id || '').replace('chat-msg-', ''));
        const msgFromTarget = (t) => t?.closest?.('.bx-msg:not(.bx-msg--system)');
        const isInteractive = (t) => !!t?.closest?.('button:not([data-msg-check]),a,input,textarea,label,.bx-voice,.bx-msg__receipt,.bx-lightbox,.bx-msg__body a,.bx-msg__reply,.bx-msg__actions');
        const isCheckTarget = (t) => !!t?.closest?.('[data-msg-check]');
        const hasTextSelection = () => hasFeedTextSelection();

        const clearHoldTimer = () => {
            if (timer) clearTimeout(timer);
            timer = null;
        };
        const endHoldVisual = () => {
            activeMsg?.classList.remove('is-hold');
            feedEl.classList.remove('is-press-hold');
            holding = false;
            activeMsg = null;
            holdStart = null;
        };
        const clearHold = () => {
            clearHoldTimer();
            endHoldVisual();
        };

        const startHold = (message, x, y) => {
            clearHold();
            holdFired = false;
            holding = true;
            activeMsg = message;
            holdStart = { x, y };
            feedEl.classList.add('is-press-hold');
            timer = window.setTimeout(() => {
                timer = null;
                if (!activeMsg) return;
                // Пока тянули текст — не открываем меню и не блокируем select
                if (hasTextSelection() || selectingMessageText) {
                    clearHold();
                    return;
                }
                holdFired = true;
                suppressClickUntil = Date.now() + 600;
                activeMsg.classList.add('is-hold');
                feedEl.classList.remove('is-press-hold');
                const id = msgIdOf(activeMsg);
                // Уже в режиме выбора — toggle; иначе меню действий как в Telegram
                if (selectedMessageIds.size > 0) {
                    toggleMessageSelection(id);
                } else if (typeof openMsgActions === 'function') {
                    openMsgActions(activeMsg);
                } else {
                    toggleMessageSelection(id);
                }
                try { navigator.vibrate?.(25); } catch (err) {}
                try { input?.blur(); } catch (err) {}
            }, holdMs());
        };

        const movedTooFar = (x, y) => {
            if (!holdStart) return false;
            return Math.abs(x - holdStart.x) > moveTol() || Math.abs(y - holdStart.y) > moveTol();
        };

        const finishPointer = (e, { toggleIfSelecting = false } = {}) => {
            const message = activeMsg || msgFromTarget(e?.target);
            const wasHold = holdFired;
            const inSelectMode = selectedMessageIds.size > 0;
            clearHoldTimer();
            endHoldVisual();
            releaseMessageTextSelectSoon();

            if (wasHold) {
                holdFired = false;
                suppressClickUntil = Date.now() + 600;
                e?.preventDefault?.();
                return;
            }
            if (Date.now() < suppressClickUntil) return;
            if (!toggleIfSelecting || !message || isInteractive(e?.target)) return;
            if (!inSelectMode) return;
            // pointerup + touchend на одном жесте — один toggle
            suppressClickUntil = Date.now() + 350;
            toggleMessageSelection(msgIdOf(message));
        };

        feedEl.addEventListener('pointerdown', (e) => {
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            const message = msgFromTarget(e.target);
            if (!message) return;

            // Галочка VK — сразу выбор, без long-press
            if (isCheckTarget(e.target)) {
                clearHold();
                holding = false;
                e.preventDefault();
                toggleMessageSelection(msgIdOf(message));
                suppressClickUntil = Date.now() + 400;
                return;
            }
            if (isInteractive(e.target)) return;

            // В режиме выбора клик по строке SMS переключает галочку (как в VK)
            if (selectedMessageIds.size > 0 || e.ctrlKey || e.metaKey) {
                holding = false;
                return;
            }

            // Клик по тексту сообщения — обычное выделение/копирование, без long-press и без фокуса композера
            if (isMessageTextTarget(e.target) && !e.ctrlKey && !e.metaKey) {
                markMessageTextSelect();
                clearHold();
                return;
            }
            if (hasTextSelection()) {
                markMessageTextSelect();
                return;
            }

            startHold(message, e.clientX || 0, e.clientY || 0);
        });

        // Критично для мобилок: не давать браузеру увести жест в scroll/cancel
        feedEl.addEventListener('touchmove', (e) => {
            if (!holding || !holdStart) return;
            const t = e.touches?.[0];
            if (!t) return;
            if (holdFired || !movedTooFar(t.clientX, t.clientY)) {
                e.preventDefault();
                return;
            }
            clearHold();
        }, { passive: false });

        feedEl.addEventListener('pointermove', (e) => {
            if (!holding || !timer) return;
            if (movedTooFar(e.clientX || 0, e.clientY || 0)) clearHold();
        });

        feedEl.addEventListener('pointerup', (e) => {
            // На touch завершение обрабатывает touchend (иначе двойной toggle)
            if (e.pointerType === 'touch') {
                if (holding || holdFired) finishPointer(e, { toggleIfSelecting: false });
                return;
            }
            finishPointer(e, { toggleIfSelecting: true });
        });
        feedEl.addEventListener('touchend', (e) => {
            finishPointer(e, { toggleIfSelecting: true });
        });
        // На iOS/Android pointercancel часто срывает long-press при малейшем jitter —
        // не сбрасываем таймер, если палец почти не сдвинулся (touchmove уже решает).
        feedEl.addEventListener('pointercancel', (e) => {
            if (holdFired) {
                finishPointer(e);
                return;
            }
            if (holding && timer && holdStart && !movedTooFar(e.clientX || holdStart.x, e.clientY || holdStart.y)) {
                return;
            }
            clearHold();
        });

        feedEl.addEventListener('click', (e) => {
            if (Date.now() < suppressClickUntil) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            const message = msgFromTarget(e.target);
            if (!message) return;
            if (isCheckTarget(e.target)) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            if (isInteractive(e.target)) return;
            // Ctrl/Cmd — выбор; в режиме выбора toggle уже на pointerup/touchend
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                e.stopPropagation();
                toggleMessageSelection(msgIdOf(message));
                return;
            }
            if (selectedMessageIds.size > 0) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);

        // Desktop: ПКМ — меню действий Telegram (не браузерное меню)
        feedEl.addEventListener('contextmenu', (e) => {
            if (hasTextSelection()) return;
            const message = msgFromTarget(e.target);
            if (!message || isInteractive(e.target)) return;
            e.preventDefault();
            if (selectedMessageIds.size > 0) {
                toggleMessageSelection(msgIdOf(message));
                return;
            }
            openMsgActions(message);
        });
    })();
    document.getElementById('bx-selection-cancel')?.addEventListener('click', () => {
        selectedMessageIds.clear();
        updateSelection();
    });

    replySelected?.addEventListener('click', () => {
        if (selectedMessageIds.size !== 1) return;
        const id = [...selectedMessageIds][0];
        const el = document.getElementById('chat-msg-' + id);
        startReplyTo({
            id,
            author: messageAuthorFromEl(el),
            preview: messagePreviewFromEl(el),
        });
    });

    copySelected?.addEventListener('click', async () => {
        const ids = [...selectedMessageIds].sort((a, b) => a - b);
        const parts = ids.map((id) => {
            const el = document.getElementById('chat-msg-' + id);
            const author = messageAuthorFromEl(el);
            const text = messagePreviewFromEl(el);
            return author + ': ' + text;
        }).filter(Boolean);
        if (!parts.length) return;
        try {
            await navigator.clipboard.writeText(parts.join('\n\n'));
            toast('Скопировано', 'success');
        } catch (e) {
            toast('Не удалось скопировать', 'error');
        }
    });

    /* Свайп вправо → ответ (как в Telegram) */
    (() => {
        const feedEl = document.getElementById('chat-feed');
        if (!feedEl) return;
        let swipe = null;
        feedEl.addEventListener('touchstart', (e) => {
            if (selectedMessageIds.size > 0) return;
            if (isMessageTextTarget(e.target)) return;
            const msg = e.target.closest?.('.bx-msg:not(.bx-msg--system)');
            if (!msg || e.target.closest?.('button,a,.bx-voice,.bx-msg__reply')) return;
            const t = e.touches?.[0];
            if (!t) return;
            swipe = { msg, x: t.clientX, y: t.clientY, dx: 0, active: false };
        }, { passive: true });
        feedEl.addEventListener('touchmove', (e) => {
            if (!swipe) return;
            const t = e.touches?.[0];
            if (!t) return;
            const dx = t.clientX - swipe.x;
            const dy = t.clientY - swipe.y;
            if (!swipe.active) {
                if (Math.abs(dy) > 12 && Math.abs(dy) > Math.abs(dx)) {
                    swipe = null;
                    return;
                }
                if (dx > 14 && Math.abs(dx) > Math.abs(dy) * 1.2) {
                    swipe.active = true;
                    swipe.msg.classList.add('is-swiping');
                } else {
                    return;
                }
            }
            swipe.dx = Math.max(0, Math.min(72, dx));
            swipe.msg.style.transform = 'translateX(' + swipe.dx + 'px)';
        }, { passive: true });
        const endSwipe = () => {
            if (!swipe) return;
            const { msg, dx, active } = swipe;
            swipe = null;
            msg.classList.remove('is-swiping');
            msg.style.transform = '';
            if (active && dx > 48) {
                const id = Number(String(msg.id || '').replace('chat-msg-', ''));
                startReplyTo({
                    id,
                    author: messageAuthorFromEl(msg),
                    preview: messagePreviewFromEl(msg),
                });
                try { navigator.vibrate?.(12); } catch (err) {}
            }
        };
        feedEl.addEventListener('touchend', endSwipe);
        feedEl.addEventListener('touchcancel', endSwipe);
    })();

    const removeMessagesFromDom = (ids) => {
        (ids || []).forEach((id) => {
            document.getElementById('chat-msg-' + id)?.remove();
            selectedMessageIds.delete(Number(id));
        });
        updateSelection();
    };

    document.getElementById('bx-delete-selected')?.addEventListener('click', async () => {
        const deleteUrl = root?.getAttribute('data-delete-url');
        if (!deleteUrl || !selectedMessageIds.size) return;

        const ids = [...selectedMessageIds];
        const allMine = ids.every((id) => {
            const el = document.getElementById('chat-msg-' + id);
            return el?.classList.contains('bx-msg--mine');
        });

        const options = [
            {
                value: 'me',
                label: 'Удалить у себя',
                hint: 'Сообщение исчезнет только у вас',
            },
        ];
        if (allMine) {
            options.unshift({
                value: 'everyone',
                label: 'Удалить у всех',
                hint: 'Как в Telegram — пропадёт у всех участников',
            });
        }

        const scope = typeof window.uiChoice === 'function'
            ? await window.uiChoice({
                title: ids.length > 1 ? 'Удалить сообщения?' : 'Удалить сообщение?',
                message: allMine
                    ? 'По умолчанию — удалить у всех. Чужие сообщения можно убрать только у себя.'
                    : 'Чужие сообщения удаляются только у вас.',
                options,
                defaultValue: allMine ? 'everyone' : 'me',
                confirmText: 'Удалить',
                danger: true,
            })
            : null;

        if (!scope) return;

        try {
            const response = await fetch(deleteUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ message_ids: ids, scope }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || 'Не удалось удалить');
            removeMessagesFromDom(data.deleted_ids || ids);
            toast(scope === 'everyone' ? 'Удалено у всех' : 'Удалено у вас', 'success');
        } catch (error) {
            toast(error.message || 'Не удалось удалить сообщения', 'error');
        }
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
        const title = document.getElementById('bx-forward-title');
        if (title) title.textContent = selectedMessageIds.size === 1
            ? 'Переслать сообщение'
            : ('Переслать · ' + selectedMessageIds.size);
        if (forwardPreview) {
            const ids = [...selectedMessageIds].sort((a, b) => a - b).slice(0, 6);
            forwardPreview.hidden = false;
            forwardPreview.innerHTML = ids.map((id) => {
                const el = document.getElementById('chat-msg-' + id);
                const author = escapeHtml(messageAuthorFromEl(el));
                const text = escapeHtml(messagePreviewFromEl(el));
                return `<div class="bx-forward-preview__item"><strong>${author}</strong><span>${text}</span></div>`;
            }).join('') + (selectedMessageIds.size > 6
                ? `<div class="bx-forward-preview__more">и ещё ${selectedMessageIds.size - 6}</div>`
                : '');
        }
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
            toast('Переслано', 'success');
            // Если переслали в этот же чат — подтянется через poll; иначе просто успех
        } catch (error) {
            target.disabled = false;
            toast(error.message || 'Не удалось переслать сообщения', 'error');
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
                    toast('Не удалось загрузить голосовое сообщение', 'error');
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
                    toast('Браузер не может воспроизвести это голосовое. Попросите отправителя записать ещё раз (нужен формат WAV — после обновления чатов).', 'info');
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
        // Пока догружаем «хвост» непрочитанных — не вставляем live-сообщения вразрез
        if (root?.getAttribute('data-has-more-newer') === '1') return false;
        document.getElementById('bx-feed-empty')?.remove();
        const nearBottom = feed.scrollHeight - feed.scrollTop - feed.clientHeight < 120;
        const newerAnchor = document.getElementById('bx-feed-newer');
        if (newerAnchor) newerAnchor.insertAdjacentHTML('beforebegin', payload.html);
        else feed.insertAdjacentHTML('beforeend', payload.html);
        const node = document.getElementById('chat-msg-' + payload.id);
        if (node) {
            highlightCodes(node);
            initVoicePlayers(node);
        }
        if (nearBottom) feed.scrollTop = feed.scrollHeight;

        const msgId = parseInt(payload.id, 10) || 0;
        const curNewest = parseInt(root?.getAttribute('data-newest-id') || '0', 10) || 0;
        if (msgId > curNewest) {
            root?.setAttribute('data-newest-id', String(msgId));
        }

        const activeChat = root?.getAttribute('data-active-chat') || '';
        if (activeChat && payload.preview != null) {
            const link = document.querySelector('.bx-chat-item[data-chat-id="' + activeChat + '"]');
            const preview = link?.querySelector('.bx-chat-item__preview');
            if (preview) {
                const text = String(payload.preview || '').trim();
                preview.textContent = text ? ('Вы: ' + text) : preview.textContent;
            }
            const timeEl = link?.querySelector('.bx-chat-item__time');
            if (timeEl) {
                const now = new Date();
                timeEl.textContent = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
            }
            if (payload.id) link?.setAttribute('data-last-id', String(payload.id));
            bumpChatInList(activeChat);
        }
        return true;
    };

    const bumpChatInList = (chatId) => {
        const list = document.getElementById('bx-chat-list');
        const link = list?.querySelector('.bx-chat-item[data-chat-id="' + chatId + '"]');
        if (!list || !link) return;

        const items = [...list.querySelectorAll('.bx-chat-item')];
        const pinned = items.filter((el) => el.classList.contains('is-pinned'));
        const unpinned = items.filter((el) => !el.classList.contains('is-pinned'));

        let desired;
        if (link.classList.contains('is-pinned')) {
            desired = [link, ...pinned.filter((el) => el !== link), ...unpinned];
        } else {
            desired = [...pinned, link, ...unpinned.filter((el) => el !== link)];
        }

        let same = desired.length === items.length;
        if (same) {
            for (let i = 0; i < desired.length; i++) {
                if (desired[i] !== items[i]) { same = false; break; }
            }
        }
        if (same) return;

        const scrollTop = list.scrollTop;
        const frag = document.createDocumentFragment();
        desired.forEach((el) => frag.appendChild(el));
        list.appendChild(frag);
        list.scrollTop = scrollTop;
    };

    const syncChatPinIcon = (link, pinned) => {
        const meta = link.querySelector('.bx-chat-item__meta');
        if (!meta) return;
        let pin = meta.querySelector('.bx-chat-item__pin');
        if (pinned && !pin) {
            pin = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            pin.setAttribute('class', 'bx-chat-item__pin');
            pin.setAttribute('viewBox', '0 0 24 24');
            pin.setAttribute('aria-hidden', 'true');
            pin.innerHTML = '<path fill="currentColor" d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/>';
            meta.insertBefore(pin, meta.firstChild);
        } else if (!pinned && pin) {
            pin.remove();
        }
    };

    const syncChatMuteIcon = (link, muted) => {
        const trail = link.querySelector('.bx-chat-item__trail');
        if (!trail) return;
        let mute = trail.querySelector('.bx-chat-item__mute');
        if (muted && !mute) {
            mute = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            mute.setAttribute('class', 'bx-chat-item__mute');
            mute.setAttribute('viewBox', '0 0 24 24');
            mute.setAttribute('fill', 'none');
            mute.setAttribute('stroke', 'currentColor');
            mute.setAttribute('stroke-width', '2');
            mute.setAttribute('aria-hidden', 'true');
            mute.innerHTML = '<path d="M11 5L6 9H2v6h4l5 4V5z"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/>';
            trail.insertBefore(mute, trail.firstChild);
        } else if (!muted && mute) {
            mute.remove();
        }
        const badge = trail.querySelector('.bx-chat-item__badge');
        badge?.classList.toggle('is-muted', !!muted);
    };

    const applyChatsFromPoll = (chats) => {
        const list = document.getElementById('bx-chat-list');
        if (!list || !Array.isArray(chats) || !chats.length) return;

        const scrollTop = list.scrollTop;
        const desired = [];

        chats.forEach((c) => {
            const link = list.querySelector('.bx-chat-item[data-chat-id="' + c.id + '"]');
            if (!link) return;
            desired.push(link);

            const wasPinned = link.classList.contains('is-pinned');
            const wasMuted = link.classList.contains('is-muted');
            link.classList.toggle('is-pinned', !!c.pinned);
            link.classList.toggle('is-muted', !!c.muted);
            if (wasPinned !== !!c.pinned) syncChatPinIcon(link, !!c.pinned);
            if (wasMuted !== !!c.muted) syncChatMuteIcon(link, !!c.muted);
            if (c.last_id != null) link.dataset.lastId = String(c.last_id);

            let trail = link.querySelector('.bx-chat-item__trail');
            if (!trail) {
                const bottom = link.querySelector('.bx-chat-item__bottom') || link.querySelector('.bx-chat-item__body');
                trail = document.createElement('span');
                trail.className = 'bx-chat-item__trail';
                bottom?.appendChild(trail);
            }
            let b = trail.querySelector('.bx-chat-item__badge');
            if (c.unread > 0) {
                if (!b) {
                    b = document.createElement('span');
                    b.className = 'bx-chat-item__badge';
                    trail.appendChild(b);
                }
                b.textContent = String(c.unread);
                b.classList.toggle('is-muted', !!c.muted);
            } else if (b) {
                b.remove();
            }

            if (c.preview != null) {
                const preview = link.querySelector('.bx-chat-item__preview');
                if (preview) preview.textContent = c.preview || 'Нет сообщений';
            }
            if (c.time != null) {
                const timeEl = link.querySelector('.bx-chat-item__time');
                if (timeEl) timeEl.textContent = c.time;
            }
        });

        const current = [...list.querySelectorAll('.bx-chat-item')];
        let needsReorder = desired.length > 0 && desired.length === current.length;
        if (needsReorder) {
            needsReorder = false;
            for (let i = 0; i < desired.length; i++) {
                if (desired[i] !== current[i]) {
                    needsReorder = true;
                    break;
                }
            }
        } else if (desired.length && desired.length !== current.length) {
            needsReorder = true;
        }

        if (needsReorder) {
            const frag = document.createDocumentFragment();
            const seen = new Set(desired);
            desired.forEach((el) => frag.appendChild(el));
            current.forEach((el) => { if (!seen.has(el)) frag.appendChild(el); });
            list.appendChild(frag);
            list.scrollTop = scrollTop;
        }
    };

    /* Подгрузка старых (вверх) и новых (вниз) сообщений */
    let oldestId = parseInt(root?.getAttribute('data-oldest-id') || '0', 10) || 0;
    let newestId = parseInt(root?.getAttribute('data-newest-id') || '0', 10) || 0;
    let hasMoreOlder = root?.getAttribute('data-has-more') === '1';
    let hasMoreNewer = root?.getAttribute('data-has-more-newer') === '1';
    let loadingOlder = false;
    let loadingNewer = false;
    const messagesUrl = root?.getAttribute('data-messages-url') || '';
    const olderWrap = document.getElementById('bx-feed-older');
    const olderBtn = document.getElementById('bx-load-older');
    const olderSpin = document.getElementById('bx-load-older-spin');
    const newerWrap = document.getElementById('bx-feed-newer');
    const newerBtn = document.getElementById('bx-load-newer');
    const newerSpin = document.getElementById('bx-load-newer-spin');

    if (!newestId && feed) {
        const lastMsg = [...feed.querySelectorAll('.bx-msg[id^="chat-msg-"]')].pop();
        newestId = parseInt(String(lastMsg?.id || '').replace('chat-msg-', ''), 10) || 0;
    }

    const setOlderUi = () => {
        if (!olderWrap) return;
        if (hasMoreOlder) olderWrap.classList.remove('d-none');
        else olderWrap.classList.add('d-none');
    };
    const setNewerUi = () => {
        if (!newerWrap) return;
        if (hasMoreNewer) newerWrap.classList.remove('d-none');
        else newerWrap.classList.add('d-none');
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

    const appendHistoryMessages = (items) => {
        if (!feed || !items?.length) return 0;
        let added = 0;
        const anchor = newerWrap;
        items.forEach((m) => {
            if (!m?.id || document.getElementById('chat-msg-' + m.id)) return;
            if (anchor) anchor.insertAdjacentHTML('beforebegin', m.html);
            else feed.insertAdjacentHTML('beforeend', m.html);
            const node = document.getElementById('chat-msg-' + m.id);
            if (node) {
                highlightCodes(node);
                initVoicePlayers(node);
            }
            added++;
        });
        return added;
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

    const loadNewerMessages = async () => {
        if (!messagesUrl || !hasMoreNewer || loadingNewer || !newestId) return;
        loadingNewer = true;
        newerBtn?.classList.add('d-none');
        newerSpin?.classList.remove('d-none');
        try {
            const url = messagesUrl + '?after=' + encodeURIComponent(String(newestId)) + '&limit=40';
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            const items = data.messages || [];
            appendHistoryMessages(items);
            hasMoreNewer = !!data.has_more_newer;
            const nextNewest = parseInt(data.newest_id || '0', 10) || 0;
            if (nextNewest > newestId) newestId = nextNewest;
            root?.setAttribute('data-has-more-newer', hasMoreNewer ? '1' : '0');
            root?.setAttribute('data-newest-id', String(newestId || ''));
            setNewerUi();
        } catch (e) {
        } finally {
            loadingNewer = false;
            newerSpin?.classList.add('d-none');
            if (hasMoreNewer) newerBtn?.classList.remove('d-none');
        }
    };

    olderBtn?.addEventListener('click', () => loadOlderMessages());
    newerBtn?.addEventListener('click', () => loadNewerMessages());
    feed?.addEventListener('scroll', () => {
        if (feed.scrollTop < 60) loadOlderMessages();
        const distBottom = feed.scrollHeight - feed.scrollTop - feed.clientHeight;
        if (distBottom < 140) loadNewerMessages();
    }, { passive: true });
    setNewerUi();

    initVoicePlayers(document);

    const resetComposer = () => {
        if (input) {
            input.value = '';
            autosize();
        }
        if (parentInput) parentInput.value = '';
        replyBanner?.classList.add('d-none');
        if (replyPreview) replyPreview.textContent = '';
        replyBanner?.removeAttribute('data-reply-id');
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
        // Не тянуть фокус в композер во время выбора сообщений (особенно на мобиле)
        if (root.classList.contains('is-selecting')) return true;
        if (document.getElementById('chat-feed')?.classList.contains('is-press-hold')) return true;
        // Не сбрасывать выделение текста внутри SMS (иначе копировать кусок нельзя)
        if (typeof selectingMessageText !== 'undefined' && selectingMessageText) return true;
        if (typeof hasFeedTextSelection === 'function' && hasFeedTextSelection()) return true;
        const ae = document.activeElement;
        if (!ae || ae === input) return false;
        if (ae.closest?.('.modal.show, .modal[open], .bx-chat-info:not([hidden]), #bx-forward-sheet:not([hidden]), #bx-chat-edit-sheet:not([hidden]), #bx-chat-add-sheet:not([hidden]), .ui-choice-overlay, .ui-toast-root')) return true;
        if (ae.id === 'bx-chat-search' || ae.closest?.('#bx-chat-search, .bx-forward-search, .bx-task-search, .bx-selection-bar')) return true;
        if (ae.matches?.('input:not([type="hidden"]):not([type="file"]):not(#bx-composer-input), textarea:not(#bx-composer-input), select, [contenteditable="true"]:not(#bx-composer-input)')) return true;
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
        input.addEventListener('focus', () => {
            selectingMessageText = false;
        });

        const mainPane = root.querySelector('.bx-messenger__main');
        mainPane?.addEventListener('pointerup', (e) => {
            if (composerFocusBlocked()) return;
            if (typeof isMessageTextTarget === 'function' && isMessageTextTarget(e.target)) {
                releaseMessageTextSelectSoon();
                return;
            }
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
            // Пока выделен текст в SMS — Ctrl+C / копирование, не перехват в композер
            if (typeof hasFeedTextSelection === 'function' && hasFeedTextSelection()) return;

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
                toast(err.message || 'Не удалось отправить сообщение', 'error');
                return;
            }
            const data = await res.json();
            if (data.message) {
                // Если читали непрочитанные с середины — сначала догрузим хвост
                if (hasMoreNewer) {
                    let guard = 0;
                    while (hasMoreNewer && guard++ < 25) {
                        await loadNewerMessages();
                    }
                }
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
                if (id > newestId) {
                    newestId = id;
                    root?.setAttribute('data-newest-id', String(newestId));
                }
            }
            resetComposer();
        } catch (e) {
            toast('Не удалось отправить сообщение', 'error');
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

    /* ===== Боты: inline / reply keyboard / команды ===== */
    const botCallbackUrl = root?.getAttribute('data-bot-callback-url') || '';
    let botCommands = [];
    try { botCommands = JSON.parse(root?.getAttribute('data-bot-commands') || '[]'); } catch (e) { botCommands = []; }
    const botCmdMenu = document.getElementById('bx-bot-cmd-menu');
    const botReplyKb = document.getElementById('bx-bot-reply-keyboard');

    document.addEventListener('click', async (e) => {
        const cbBtn = e.target.closest?.('.bx-bot-btn--callback');
        if (cbBtn) {
            e.preventDefault();
            if (!botCallbackUrl) return;
            const msgId = cbBtn.getAttribute('data-msg-id');
            const data = cbBtn.getAttribute('data-callback');
            cbBtn.disabled = true;
            try {
                const res = await fetch(botCallbackUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        message_id: Number(msgId),
                        callback_data: data,
                        _token: csrf,
                    }),
                });
                if (!res.ok) throw new Error('fail');
                toast('Отправлено боту', 'success');
            } catch (err) {
                toast('Не удалось нажать кнопку', 'error');
            } finally {
                cbBtn.disabled = false;
            }
            return;
        }

        const replyBtn = e.target.closest?.('.bx-bot-reply-keyboard__btn');
        if (replyBtn) {
            e.preventDefault();
            const text = replyBtn.getAttribute('data-reply-text') || '';
            if (!text) return;
            setComposerText(text);
            focusComposer();
            if (botReplyKb?.getAttribute('data-one-time') === '1') {
                botReplyKb.classList.add('d-none');
            }
            sendMessageAjax();
        }
    });

    const renderBotCmdMenu = (query) => {
        if (!botCmdMenu || !botCommands.length) return;
        const q = String(query || '').replace(/^\//, '').toLowerCase();
        const items = botCommands.filter((c) => {
            const cmd = String(c.command || '').replace(/^\//, '').toLowerCase();
            return !q || cmd.startsWith(q);
        }).slice(0, 8);
        if (!items.length) {
            botCmdMenu.classList.add('d-none');
            botCmdMenu.innerHTML = '';
            return;
        }
        botCmdMenu.innerHTML = items.map((c) => (
            `<button type="button" class="bx-bot-cmd-menu__item" data-cmd="${escapeHtml(c.command)}">
                <strong>${escapeHtml(c.command)}</strong>
                <span>${escapeHtml(c.description || ('@' + (c.bot || 'bot')))}</span>
            </button>`
        )).join('');
        botCmdMenu.classList.remove('d-none');
    };

    botCmdMenu?.addEventListener('click', (e) => {
        const item = e.target.closest?.('[data-cmd]');
        if (!item) return;
        setComposerText(item.getAttribute('data-cmd') + ' ');
        botCmdMenu.classList.add('d-none');
        focusComposer();
    });

    input?.addEventListener('input', () => {
        const text = (input.innerText || '').trim();
        if (text.startsWith('/') && botCommands.length) {
            renderBotCmdMenu(text.split(/\s+/)[0]);
        } else {
            botCmdMenu?.classList.add('d-none');
        }
    });

    /* Вставка картинок из буфера (Ctrl+V) */
    const addClipboardFiles = (fileList) => {
        const incoming = [...fileList].filter((f) => f && (f.type || '').startsWith('image/'));
        if (!incoming.length) return false;
        const room = FILES_MAX - pendingFiles.length;
        if (room <= 0) {
            toast('Можно прикрепить не больше ' + FILES_MAX + ' файлов за раз', 'info');
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
                toast('Смена микрофона применится со следующей записи. Завершите текущую или отмените.', 'info');
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
            toast('Голосовое слишком большое. Запишите короче (до ~1.5 мин) или поднимите upload_max_filesize в PHP до 16M.', 'error');
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
            toast('Браузер не поддерживает запись голоса', 'error');
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
            if (Array.isArray(data.removed_ids) && data.removed_ids.length) {
                removeMessagesFromDom(data.removed_ids);
            }

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
            toast('Не удалось открыть форму. Обновите страницу.', 'error');
        });
    });

    /* Контекстное меню чата (ПКМ / long-press) — как в Telegram */
    (() => {
        const list = document.getElementById('bx-chat-list');
        const sheet = document.getElementById('bx-chat-actions');
        const preview = document.getElementById('bx-chat-actions-preview');
        const pinLabel = document.getElementById('bx-chat-action-pin-label');
        const muteLabel = document.getElementById('bx-chat-action-mute-label');
        if (!list || !sheet) return;

        let targetLink = null;
        let holdTimer = null;
        let suppressNavUntil = 0;
        let holdStart = null;

        const closeChatActions = () => {
            sheet.setAttribute('hidden', '');
            targetLink = null;
        };
        const openChatActions = (link) => {
            if (!link) return;
            targetLink = link;
            const title = link.querySelector('.bx-chat-item__title')?.textContent?.trim()
                || link.getAttribute('data-title')
                || 'Чат';
            const sub = link.querySelector('.bx-chat-item__preview')?.textContent?.trim() || '';
            if (preview) {
                preview.innerHTML = `<div class="bx-msg-actions__preview-card"><strong>${escapeHtml(title)}</strong><span>${escapeHtml(sub)}</span></div>`;
            }
            const pinned = link.classList.contains('is-pinned');
            const muted = link.classList.contains('is-muted');
            if (pinLabel) pinLabel.textContent = pinned ? 'Открепить' : 'Закрепить';
            if (muteLabel) muteLabel.textContent = muted ? 'Включить звук' : 'Без звука';
            sheet.removeAttribute('hidden');
        };

        const chatActionUrl = (kind, id) => {
            const tpl = list.getAttribute(kind === 'pin' ? 'data-pin-tpl' : 'data-mute-tpl') || '';
            return tpl.replace('__ID__', String(id));
        };

        const postChatAction = async (kind, link) => {
            if (!link) return;
            const id = link.getAttribute('data-chat-id');
            const url = chatActionUrl(kind, id);
            if (!url || url.includes('__ID__')) return;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    },
                    credentials: 'same-origin',
                    body: csrf ? new URLSearchParams({ _token: csrf }) : undefined,
                });
                if (!res.ok) throw new Error('fail');
                const data = await res.json();
                if (kind === 'pin') {
                    link.classList.toggle('is-pinned', !!data.pinned);
                    syncChatPinIcon(link, !!data.pinned);
                    bumpChatInList(id);
                    toast(data.pinned ? 'Чат закреплён' : 'Чат откреплён', 'success');
                } else {
                    link.classList.toggle('is-muted', !!data.muted);
                    syncChatMuteIcon(link, !!data.muted);
                    toast(data.muted ? 'Без звука' : 'Звук включён', 'success');
                }
            } catch (e) {
                toast('Не удалось выполнить действие', 'error');
            }
        };

        document.getElementById('bx-chat-actions-bg')?.addEventListener('click', closeChatActions);
        document.getElementById('bx-chat-actions-cancel')?.addEventListener('click', closeChatActions);
        sheet.addEventListener('click', (e) => {
            const btn = e.target.closest?.('[data-chat-action]');
            if (!btn || !targetLink) return;
            const action = btn.getAttribute('data-chat-action');
            const link = targetLink;
            closeChatActions();
            if (action === 'open') {
                link.click();
                return;
            }
            if (action === 'pin' || action === 'mute') postChatAction(action, link);
        });

        list.addEventListener('contextmenu', (e) => {
            const link = e.target.closest?.('.bx-chat-item');
            if (!link || !list.contains(link)) return;
            e.preventDefault();
            openChatActions(link);
        });

        list.addEventListener('click', (e) => {
            if (Date.now() < suppressNavUntil) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);

        list.addEventListener('pointerdown', (e) => {
            if (e.pointerType !== 'touch') return;
            const link = e.target.closest?.('.bx-chat-item');
            if (!link) return;
            holdStart = { x: e.clientX || 0, y: e.clientY || 0 };
            holdTimer = window.setTimeout(() => {
                holdTimer = null;
                suppressNavUntil = Date.now() + 600;
                openChatActions(link);
                try { navigator.vibrate?.(18); } catch (err) {}
            }, 480);
        });
        const clearHold = () => {
            if (holdTimer) clearTimeout(holdTimer);
            holdTimer = null;
            holdStart = null;
        };
        list.addEventListener('pointerup', clearHold);
        list.addEventListener('pointercancel', clearHold);
        list.addEventListener('pointermove', (e) => {
            if (!holdTimer || !holdStart) return;
            if (Math.abs((e.clientX || 0) - holdStart.x) > 12
                || Math.abs((e.clientY || 0) - holdStart.y) > 12) {
                clearHold();
            }
        });
    })();

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
                return '<a class="bx-search-hit bx-search-hit--chat" href="' + escapeHtmlSearch(c.url) + '" data-turbo-prefetch="false">'
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
                return '<a class="bx-search-hit bx-search-hit--msg" href="' + escapeHtmlSearch(m.url) + '" data-turbo-prefetch="false">'
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

    /* Прочтение как в Telegram: только видимые сообщения */
    (() => {
        const readUrl = root?.getAttribute('data-read-url') || '';
        const selfId = String(root?.getAttribute('data-self-id') || '');
        if (!feed || !readUrl) return;

        let pendingMaxId = 0;
        let sentMaxId = 0;
        let timer = null;
        let busy = false;

        const flushRead = async () => {
            if (busy || pendingMaxId <= sentMaxId) return;
            busy = true;
            const upTo = pendingMaxId;
            try {
                const res = await fetch(readUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ up_to: upTo }),
                });
                if (!res.ok) return;
                sentMaxId = Math.max(sentMaxId, upTo);
                const data = await res.json().catch(() => ({}));
                const nextUnread = parseInt(data.first_unread_id || '0', 10) || 0;
                if (!nextUnread) {
                    document.getElementById('bx-unread-divider')?.remove();
                    const activeChat = root?.getAttribute('data-active-chat') || '';
                    const badge = document.querySelector('.bx-chat-item[data-chat-id="' + activeChat + '"] .bx-chat-item__badge');
                    badge?.remove();
                } else if (nextUnread > (parseInt(root?.getAttribute('data-first-unread') || '0', 10) || 0)) {
                    root?.setAttribute('data-first-unread', String(nextUnread));
                }
            } catch (e) {
            } finally {
                busy = false;
                if (pendingMaxId > sentMaxId) scheduleFlush(120);
            }
        };

        const scheduleFlush = (ms = 450) => {
            if (timer) clearTimeout(timer);
            timer = setTimeout(flushRead, ms);
        };

        const observeMessage = (el) => {
            if (!el || el.dataset.readObserved === '1') return;
            const id = Number(String(el.id || '').replace('chat-msg-', ''));
            if (!id) return;
            // Свои сообщения не двигают «непрочитанные»
            if (el.classList.contains('bx-msg--mine') || el.classList.contains('bx-msg--system')) {
                el.dataset.readObserved = '1';
                return;
            }
            el.dataset.readObserved = '1';
            observer.observe(el);
        };

        const observer = new IntersectionObserver((entries) => {
            let advanced = false;
            entries.forEach((entry) => {
                if (!entry.isIntersecting || entry.intersectionRatio < 0.55) return;
                const id = Number(String(entry.target.id || '').replace('chat-msg-', ''));
                if (!id) return;
                if (id > pendingMaxId) {
                    pendingMaxId = id;
                    advanced = true;
                }
            });
            if (advanced) scheduleFlush();
        }, {
            root: feed,
            threshold: [0.55, 0.8],
            rootMargin: '0px 0px -8% 0px',
        });

        feed.querySelectorAll('.bx-msg:not(.bx-msg--system)').forEach(observeMessage);

        const mo = new MutationObserver((mutations) => {
            mutations.forEach((m) => {
                m.addedNodes.forEach((node) => {
                    if (!(node instanceof HTMLElement)) return;
                    if (node.classList?.contains('bx-msg')) observeMessage(node);
                    node.querySelectorAll?.('.bx-msg:not(.bx-msg--system)').forEach(observeMessage);
                });
            });
        });
        mo.observe(feed, { childList: true, subtree: true });

        // При уходе со страницы — дожать прочтение видимого
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') flushRead();
        });
        window.addEventListener('pagehide', () => { flushRead(); });
    })();

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
    const lightboxGoto = document.getElementById('bx-lightbox-goto');
    let lightboxMessageId = 0;
    const openLightbox = (url, alt, messageId) => {
        if (!lightbox || !lightboxImg) return;
        lightboxImg.src = url;
        lightboxImg.alt = alt || '';
        if (lightboxOpen) lightboxOpen.href = url;
        lightboxMessageId = Number(messageId) || 0;
        lightboxGoto?.classList.toggle('d-none', !lightboxMessageId);
        lightbox.hidden = false;
        document.body.classList.add('bx-lightbox-open');
    };
    const closeLightbox = () => {
        if (!lightbox) return;
        lightbox.hidden = true;
        if (lightboxImg) lightboxImg.src = '';
        lightboxMessageId = 0;
        lightboxGoto?.classList.add('d-none');
        document.body.classList.remove('bx-lightbox-open');
    };
    lightboxGoto?.addEventListener('click', () => {
        const id = lightboxMessageId;
        closeLightbox();
        if (id) goToChatMessage(id);
    });
    document.addEventListener('click', (e) => {
        const link = e.target.closest?.('[data-bx-lightbox]');
        if (link) {
            e.preventDefault();
            const msgId = link.getAttribute('data-message-id')
                || link.closest?.('[data-message-id]')?.getAttribute('data-message-id')
                || (link.closest?.('.bx-msg')?.id || '').replace('chat-msg-', '')
                || '';
            openLightbox(
                link.getAttribute('data-bx-lightbox') || link.href,
                link.getAttribute('title') || '',
                msgId
            );
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
<script src="{{ asset('js/chat-calls.js') }}?v=20260730a"></script>
@endif
