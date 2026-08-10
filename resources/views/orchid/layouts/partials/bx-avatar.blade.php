{{-- Avatar: pass avatarUser OR avatarChat — never ambient $chat/$user (Blade inherits parent scope) --}}
@php
    $size = $size ?? 'md';
    $shape = $shape ?? null;
    /** @var \App\Models\User|null $avatarUser */
    /** @var \App\Models\Chat|null $avatarChat */
    $avatarUser = $avatarUser ?? null;
    $avatarChat = $avatarChat ?? null;
    $showOnline = (bool) ($showOnline ?? false);
    $isOnline = (bool) ($isOnline ?? false);
    $isBot = (bool) ($isBot ?? ($avatarUser?->is_bot ?? false));
    $onlineUserId = $avatarUser?->id;

    if ($avatarChat) {
        $title = $avatarChat->displayTitle();
        $initials = $avatarChat->avatarInitials();
        $color = $avatarChat->avatarColor();
        $url = $avatarChat->avatarUrl();
        if ($shape === null) {
            $shape = $avatarChat->type === 'direct' ? 'round' : 'square';
        }
    } else {
        $title = $avatarUser?->displayName() ?? 'Участник';
        $initials = $avatarUser?->avatarInitials() ?? '?';
        $color = $avatarUser?->avatarColor() ?? '#64748b';
        $url = $avatarUser?->avatarUrl() ?? '';
        if ($shape === null) {
            $shape = 'round';
        }
    }

    $useWrap = $showOnline || $isBot;
@endphp
@if($useWrap)
<span class="bx-avatar-wrap {{ $isOnline ? 'is-online' : '' }} {{ $isBot ? 'is-bot' : '' }}"
      @if($onlineUserId) data-user-id="{{ $onlineUserId }}" @endif
      @if($isBot) data-is-bot="1" @endif>
@endif
<span class="bx-avatar bx-avatar--{{ $size }} bx-avatar--{{ $shape }} {{ $isBot ? 'bx-avatar--bot' : '' }}"
      style="--bx-avatar-bg: {{ $color }}"
      title="{{ $title }}{{ $isBot ? ' · бот' : '' }}">
    <span class="bx-avatar__initials">{{ $initials }}</span>
    @if($url !== '')
        <img class="bx-avatar__img"
             src="{{ $url }}"
             alt=""
             loading="lazy"
             decoding="async"
             onerror="this.remove()">
    @endif
</span>
@if($isBot)
    <span class="bx-bot-badge" title="Бот" aria-label="Бот">
        <svg viewBox="0 0 16 16" width="10" height="10" fill="currentColor" aria-hidden="true">
            <path d="M8 1.5a.75.75 0 0 1 .75.75v.76A3.75 3.75 0 0 1 11.75 6.5h.5a.75.75 0 0 1 0 1.5h-.5v.25c0 .69-.28 1.32-.73 1.77l.98 1.47a.75.75 0 1 1-1.25.83l-.9-1.35a3.73 3.73 0 0 1-1.6.35h-.5c-.56 0-1.1-.12-1.6-.35l-.9 1.35a.75.75 0 1 1-1.25-.83l.98-1.47A2.49 2.49 0 0 1 4.25 8.25V8h-.5a.75.75 0 0 1 0-1.5h.5A3.75 3.75 0 0 1 7.25 3.01V2.25A.75.75 0 0 1 8 1.5zm-1.5 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5zm3 0a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5z"/>
        </svg>
    </span>
@endif
@if($showOnline && $onlineUserId)
    <span class="bx-online-dot" title="В сети" aria-hidden="true"></span>
@endif
@if($useWrap)
</span>
@endif
