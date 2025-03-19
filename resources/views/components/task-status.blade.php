@php
$colors = [
    'new' => 'bg-blue-500 text-white',
    'in_progress' => 'bg-yellow-500 text-white',
    'completed' => 'bg-green-500 text-white',
    'canceled' => 'bg-red-500 text-white',
    'postponed' => 'bg-purple-500 text-white', // Добавлен новый статус
];
@endphp

<span class="px-2 py-1 rounded text-sm {{ $colors[$status] ?? 'bg-gray-500 text-white' }}">
    {{ __('task.status.' . ($status ?? 'unknown')) }}
</span>
