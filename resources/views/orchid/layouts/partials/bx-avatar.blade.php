{{-- Avatar: custom photo / chat avatar / initials / Gravatar --}}
@php
    $size = $size ?? 'md';
    /** @var \App\Models\User|null $user */
    /** @var \App\Models\Chat|null $chat */
    $chat = $chat ?? null;
    $user = $user ?? null;

    if ($chat) {
        $title = $chat->displayTitle();
        $initials = $chat->avatarInitials();
        $color = $chat->avatarColor();
        $url = $chat->avatarUrl();
    } else {
        $title = $user?->displayName() ?? 'Участник';
        $initials = $user?->avatarInitials() ?? '?';
        $color = $user?->avatarColor() ?? '#64748b';
        $url = $user?->avatarUrl() ?? '';
    }
@endphp
<span class="bx-avatar bx-avatar--{{ $size }}"
      style="--bx-avatar-bg: {{ $color }}"
      title="{{ $title }}">
    <span class="bx-avatar__initials">{{ $initials }}</span>
    @if($url !== '')
        <img class="bx-avatar__img"
             src="{{ $url }}"
             alt=""
             loading="lazy"
             onerror="this.remove()">
    @endif
</span>
