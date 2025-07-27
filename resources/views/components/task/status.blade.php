@props(['status'])

@php
    $enum = \App\CoreLayer\Enums\TaskStatusEnum::from($status);
@endphp

<div class="d-flex align-items-center">
    <span 
        class="d-inline-block me-2" 
        style="
            width: 12px; 
            height: 12px; 
            background-color: {{ $enum->color() }};
            border-radius: 50%;
        "
        title="{{ $enum->label() }}"
    ></span>
    <span class="text-truncate" style="max-width: 150px;">
        {{ $enum->label() }}
    </span>
</div>