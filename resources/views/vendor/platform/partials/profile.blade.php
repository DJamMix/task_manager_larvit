@php
    $user = Auth::user();
    $presenter = $user?->presenter();
    $image = $presenter?->image();
    $title = $presenter?->title() ?? ($user?->name ?? 'Профиль');
    $subtitle = $presenter?->subTitle() ?? '';
    $initials = method_exists($user, 'avatarInitials') ? $user->avatarInitials() : mb_strtoupper(mb_substr((string) $title, 0, 1));
    $avatarColor = method_exists($user, 'avatarColor') ? $user->avatarColor() : '#64748b';
@endphp

<div class="profile-container crewdev-profile">
    <a href="{{ route(config('platform.profile', 'platform.profile')) }}"
       class="crewdev-profile__user"
       title="{{ $title }}">
        @if($image)
            <img src="{{ $image }}"
                 alt="{{ $title }}"
                 class="crewdev-profile__avatar"
                 width="32"
                 height="32"
                 loading="lazy"
                 decoding="async">
        @else
            <span class="crewdev-profile__avatar crewdev-profile__avatar--fallback"
                  style="--crewdev-avatar-bg: {{ $avatarColor }}">
                {{ $initials }}
            </span>
        @endif

        <span class="crewdev-profile__meta">
            <span class="crewdev-profile__name">{{ $title }}</span>
            @if($subtitle !== '')
                <span class="crewdev-profile__sub">{{ $subtitle }}</span>
            @endif
        </span>
    </a>

    <div class="crewdev-profile__notify">
        <x-orchid-notification/>
    </div>
</div>
