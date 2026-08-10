@php
    $user = Auth::user();
    $presenter = $user?->presenter();
    $image = $presenter?->image();
    $title = $presenter?->title() ?? ($user?->name ?? 'Профиль');
    $subtitle = $presenter?->subTitle() ?? '';
    $initials = method_exists($user, 'avatarInitials') ? $user->avatarInitials() : mb_strtoupper(mb_substr((string) $title, 0, 1));
    $avatarColor = method_exists($user, 'avatarColor') ? $user->avatarColor() : '#64748b';
@endphp

<div class="profile-container crewdev-profile d-flex align-items-stretch p-3 rounded lh-sm position-relative overflow-hidden">
    <a href="{{ route(config('platform.profile', 'platform.profile')) }}"
       class="crewdev-profile__user col-10 d-flex align-items-center gap-3"
       title="{{ $title }}">
        @if($image)
            <img src="{{ $image }}"
                 alt="{{ $title }}"
                 class="thumb-sm avatar b crewdev-profile__avatar"
                 type="image/*">
        @else
            <span class="crewdev-profile__avatar crewdev-profile__avatar--fallback"
                  style="--crewdev-avatar-bg: {{ $avatarColor }}">
                {{ $initials }}
            </span>
        @endif

        <small class="crewdev-profile__meta d-flex flex-column lh-1 col-9">
            <span class="text-ellipsis text-white">{{ $title }}</span>
            @if($subtitle !== '')
                <span class="text-ellipsis text-muted">{{ $subtitle }}</span>
            @endif
        </small>
    </a>

    <div class="crewdev-profile__notify">
        <x-orchid-notification/>
    </div>
</div>
