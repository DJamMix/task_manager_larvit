{{-- Avatar: custom photo / chat avatar / initials --}}
@php
    $size = $size ?? 'md';
    /** @var \App\Models\User|null $user */
    /** @var \App\Models\Chat|null $chat */
    $chat = $chat ?? null;
    $user = $user ?? null;
    $shape = $shape ?? null;

    if ($chat) {
        $title = $chat->displayTitle();
        $initials = $chat->avatarInitials();
        $color = $chat->avatarColor();
        $url = $chat->avatarUrl();
        if ($shape === null) {
            $shape = $chat->type === 'direct' ? 'round' : 'square';
        }
    } else {
        $title = $user?->displayName() ?? 'Участник';
        $initials = $user?->avatarInitials() ?? '?';
        $color = $user?->avatarColor() ?? '#64748b';
        $url = $user?->avatarUrl() ?? '';
        if ($shape === null) {
            $shape = 'round';
        }
    }
@endphp
<span class="bx-avatar bx-avatar--{{ $size }} bx-avatar--{{ $shape }}"
      style="--bx-avatar-bg: {{ $color }}"
      title="{{ $title }}">
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
