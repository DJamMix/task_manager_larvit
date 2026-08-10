@php($chats = $bot_chats ?? collect())
@if(($bot ?? null)?->exists)
    <div class="bg-white rounded shadow-sm p-3 mb-3">
        <h5 class="mb-3">Чаты с этим ботом</h5>
        @forelse($chats as $chat)
            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div>
                    <strong>{{ $chat->title ?: ('Чат #'.$chat->id) }}</strong>
                    <div class="small text-muted">chat_id = {{ $chat->id }} · {{ $chat->type }}</div>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('platform.systems.chats.view', $chat) }}">Открыть</a>
            </div>
        @empty
            <div class="text-muted">Бот ещё не состоит ни в одном чате. Создайте сервисный чат выше или добавьте бота в группу через «Участники».</div>
        @endforelse
    </div>
@endif
