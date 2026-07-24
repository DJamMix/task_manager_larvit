{{-- Bitrix-style avatar: initials + optional Gravatar --}}
@php
    /** @var \App\Models\User|null $user */
    $size = $size ?? 'md';
    $title = $user?->displayName() ?? 'Участник';
    $initials = $user?->avatarInitials() ?? '?';
    $color = $user?->avatarColor() ?? '#64748b';
    $url = $user?->avatarUrl() ?? '';
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
