{{-- Avatar: pass avatarUser OR avatarChat — never ambient $chat/$user (Blade inherits parent scope) --}}
@php
    $size = $size ?? 'md';
    $shape = $shape ?? null;
    /** @var \App\Models\User|null $avatarUser */
    /** @var \App\Models\Chat|null $avatarChat */
    $avatarUser = $avatarUser ?? null;
    $avatarChat = $avatarChat ?? null;

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
