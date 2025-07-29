@props(['status'])

@php
    $enum = \App\CoreLayer\Enums\TaskStatusEnum::from($status);
@endphp

<div class="status-badge">
    <span class="status-dot pulse-animation" style="--status-color: {{ $enum->color() }};"></span>
    <span class="status-label" style="color: {{ $enum->color() }};">{{ $enum->label() }}</span>
</div>

<style>
    .status-badge {
        display: inline-flex;
        align-items: center;
        font-family: 'Inter', sans-serif;
        gap: 8px;
    }
    
    .status-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: var(--status-color);
        position: relative;
        flex-shrink: 0;
    }
    
    .status-dot.pulse-animation::after {
        content: '';
        position: absolute;
        top: -3px;
        left: -3px;
        right: -3px;
        bottom: -3px;
        background-color: var(--status-color);
        border-radius: 50%;
        opacity: 0.4;
        animation: pulse 2s infinite;
    }
    
    .status-label {
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
    }
    
    @keyframes pulse {
        0% {
            transform: scale(0.8);
            opacity: 0.4;
        }
        70% {
            transform: scale(1.3);
            opacity: 0;
        }
        100% {
            transform: scale(0.8);
            opacity: 0;
        }
    }
</style>