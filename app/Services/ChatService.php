<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Task;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Orchid\Support\Color;

class ChatService
{
    public function __construct(
        private readonly CommentService $comments,
        private readonly DashboardNotifier $notifier,
    ) {}

    public function staffUserOptions(?int $exceptId = null): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', RoleCatalog::STAFF_SLUGS))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->displayName()])
            ->all();
    }

    public function canCreate(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return (bool) $user?->hasAccess('platform.systems.chats.create');
    }

    public function canAccessMessenger(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return (bool) $user?->hasAccess('platform.systems.chats');
    }

    /**
     * @return Collection<int, Chat>
     */
    public function chatsFor(User $user): Collection
    {
        return Chat::query()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->with(['members', 'latestMessage.user'])
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->where('user_id', '!=', $user->id)
                    ->whereRaw('chat_messages.created_at > COALESCE(
                        (SELECT last_read_at FROM chat_user WHERE chat_user.chat_id = chat_messages.chat_id AND chat_user.user_id = ?),
                        "1970-01-01"
                    )', [$user->id]);
            }])
            ->orderByDesc(
                ChatMessage::select('created_at')
                    ->whereColumn('chat_id', 'chats.id')
                    ->latest()
                    ->limit(1)
            )
            ->orderByDesc('chats.updated_at')
            ->get();
    }

    /**
     * @param  list<int>  $memberIds
     */
    public function createGroup(User $actor, string $title, array $memberIds, ?string $description = null): Chat
    {
        return DB::transaction(function () use ($actor, $title, $memberIds, $description) {
            $chat = Chat::query()->create([
                'title' => trim($title) !== '' ? trim($title) : 'Групповой чат',
                'type' => 'group',
                'created_by' => $actor->id,
                'description' => $description,
            ]);

            $ids = collect($memberIds)
                ->map(fn ($id) => (int) $id)
                ->push($actor->id)
                ->unique()
                ->filter(fn ($id) => $this->isStaffUserId($id))
                ->values();

            $sync = [];
            foreach ($ids as $id) {
                $sync[$id] = [
                    'role' => (int) $id === (int) $actor->id ? 'owner' : 'member',
                    'last_read_at' => now(),
                ];
            }
            $chat->members()->sync($sync);

            $this->postSystem($chat, $actor, "{$actor->displayName()} создал(а) чат «{$chat->title}»");

            return $chat->fresh(['members']);
        });
    }

    public function findOrCreateDirect(User $actor, int $otherUserId): Chat
    {
        if (!$this->isStaffUserId($otherUserId) || (int) $otherUserId === (int) $actor->id) {
            abort(422, 'Нельзя создать личный чат с этим пользователем');
        }

        $existing = Chat::query()
            ->where('type', 'direct')
            ->whereHas('members', fn ($q) => $q->where('users.id', $actor->id))
            ->whereHas('members', fn ($q) => $q->where('users.id', $otherUserId))
            ->withCount('members')
            ->having('members_count', '=', 2)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($actor, $otherUserId) {
            $other = User::findOrFail($otherUserId);
            $chat = Chat::query()->create([
                'title' => null,
                'type' => 'direct',
                'created_by' => $actor->id,
            ]);

            $chat->members()->sync([
                $actor->id => ['role' => 'owner', 'last_read_at' => now()],
                $other->id => ['role' => 'member', 'last_read_at' => now()],
            ]);

            $this->postSystem($chat, $actor, 'Личный чат создан');

            return $chat->fresh(['members']);
        });
    }

    /**
     * @param  list<int>  $memberIds
     */
    public function syncMembers(Chat $chat, User $actor, array $memberIds): void
    {
        if (!$chat->isOwner($actor->id) && !$actor->hasAccess('platform.systems.chats.create')) {
            abort(403);
        }

        if ($chat->type === 'direct') {
            abort(422, 'В личном чате состав нельзя менять');
        }

        $ids = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->push($actor->id)
            ->unique()
            ->filter(fn ($id) => $this->isStaffUserId($id))
            ->values();

        $sync = [];
        foreach ($ids as $id) {
            $existingRole = $chat->members()->where('users.id', $id)->first()?->pivot?->role;
            $sync[$id] = [
                'role' => $existingRole === 'owner' || (int) $id === (int) $chat->created_by ? 'owner' : 'member',
                'last_read_at' => $chat->members()->where('users.id', $id)->first()?->pivot?->last_read_at ?? now(),
            ];
        }

        $chat->members()->sync($sync);
        $this->postSystem($chat, $actor, "{$actor->displayName()} обновил(а) участников чата");
    }

    public function addMessage(Chat $chat, User $actor, Request $request): ChatMessage
    {
        if (!$chat->isMember($actor->id)) {
            abort(403);
        }

        $quill = $this->comments->normalizeQuill($request->input('message.text'));
        $plain = $this->comments->extractPlainText($quill);

        $taskId = $request->input('message.task_id');
        $task = null;
        if ($taskId) {
            $task = Task::query()->find($taskId);
        }

        // Авто-привязка задачи по ссылке в тексте
        if (!$task && preg_match('/(?:tasks\/|my-tasks\/|tasks\/)(\d+)/', $plain, $m)) {
            $task = Task::query()->find((int) $m[1]);
        }

        if (trim($plain) === '' && empty($request->input('message.attachments')) && !$task) {
            abort(422, 'Напишите сообщение, прикрепите файл или задачу');
        }

        $parentId = $request->input('message.parent_id');
        $parent = null;
        if ($parentId) {
            $parent = ChatMessage::query()
                ->where('chat_id', $chat->id)
                ->whereKey($parentId)
                ->first();
        }

        $mentions = collect($request->input('message.notify_user_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter();

        if ($parent?->user_id && (int) $parent->user_id !== (int) $actor->id) {
            $mentions->push((int) $parent->user_id);
        }

        $mentions = $mentions->unique()->reject(fn ($id) => $id === (int) $actor->id)->values();

        $message = $chat->messages()->create([
            'user_id' => $actor->id,
            'parent_id' => $parent?->id,
            'text' => $quill,
            'plain_text' => $plain !== '' ? $plain : ($task ? 'Задача #' . $task->id : '📎 Вложение'),
            'mentioned_user_ids' => $mentions->all(),
            'task_id' => $task?->id,
            'is_system' => false,
        ]);

        $attachments = $request->input('message.attachments', []);
        if (!empty($attachments)) {
            $message->attachment()->syncWithoutDetaching($attachments);
        }

        $chat->touch();
        $this->markRead($chat, $actor);
        $this->notifyMembers($chat, $actor, $message, $mentions->all());

        return $message;
    }

    public function markRead(Chat $chat, User $user): void
    {
        $chat->members()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
        ]);
    }

    private function postSystem(Chat $chat, User $actor, string $text): void
    {
        $chat->messages()->create([
            'user_id' => $actor->id,
            'text' => ['ops' => [['insert' => $text . "\n"]], 'html' => e($text)],
            'plain_text' => $text,
            'is_system' => true,
        ]);
    }

    private function notifyMembers(Chat $chat, User $actor, ChatMessage $message, array $mentionIds): void
    {
        $title = $mentionIds !== [] ? 'Вас упомянули в чате' : 'Новое сообщение в чате';
        $preview = \Illuminate\Support\Str::limit($message->plain_text, 140);
        $body = "{$actor->displayName()} · {$chat->displayTitle($actor->id)}: {$preview}";
        $url = route('platform.systems.chats.view', $chat);

        $recipients = $chat->members
            ->reject(fn (User $u) => (int) $u->id === (int) $actor->id);

        if ($mentionIds !== []) {
            $recipients = $recipients->filter(fn (User $u) => in_array((int) $u->id, $mentionIds, true));
        }

        foreach ($recipients as $user) {
            $this->notifier->send($user, $title, $body, $url, Color::INFO);
        }
    }

    private function isStaffUserId(int $userId): bool
    {
        return User::query()
            ->whereKey($userId)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', RoleCatalog::STAFF_SLUGS))
            ->exists();
    }
}
