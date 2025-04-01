<div class="space-y-4">
    <h3 class="text-lg font-medium">Комментарии</h3>
    
    @forelse($comments as $comment)
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-start mb-2">
                <div class="font-medium text-gray-900">
                    {{ $comment->user->name }}
                </div>
                <div class="text-sm text-gray-500">
                    {{ $comment->created_at->format('d.m.Y H:i') }}
                </div>
            </div>
            <div class="text-gray-700 whitespace-pre-line">
                {{ $comment->text }}
            </div>
        </div>
    @empty
        <div class="text-center text-gray-500 py-4">
            Пока нет комментариев
        </div>
    @endforelse
</div>