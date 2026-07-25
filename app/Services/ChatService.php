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

    /** Участники чатов: сотрудники + клиентские контакты */
    public function chatMemberOptions(?int $exceptId = null): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', RoleCatalog::CHAT_MEMBER_SLUGS))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->displayName()])
            ->all();
    }

    /**
     * Кого можно выбрать для личного чата.
     * Сотрудник без права chats.clients — только коллеги (staff).
     * С правом — staff + клиенты/контакты.
     * Клиент — только сотрудники с правом chats.clients (PM и т.п.).
     */
    public function directInterlocutorOptions(User $actor): array
    {
        $exceptId = (int) $actor->id;

        if ($actor->isClientAccount()) {
            return User::query()
                ->whereKeyNot($exceptId)
                ->whereHas('roles', fn ($q) => $q->whereIn('slug', RoleCatalog::STAFF_SLUGS))
                ->get()
                ->filter(fn (User $u) => $u->hasAccess('platform.systems.chats.clients'))
                ->sortBy('name')
                ->mapWithKeys(fn (User $u) => [$u->id => $u->displayName()])
                ->all();
        }

        if ($this->canChatWithClients($actor)) {
            return $this->chatMemberOptions($exceptId);
        }

        // Обычный сотрудник — только коллеги
        return User::query()
            ->whereKeyNot($exceptId)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', RoleCatalog::STAFF_SLUGS))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->displayName()])
            ->all();
    }

    /** @deprecated use chatMemberOptions */
    public function staffUserOptions(?int $exceptId = null): array
    {
        return $this->chatMemberOptions($exceptId);
    }

    public function canCreate(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return (bool) $user?->hasAccess('platform.systems.chats.create');
    }

    public function canChatWithClients(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return (bool) $user?->hasAccess('platform.systems.chats.clients');
    }

    public function canAccessMessenger(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return (bool) $user?->hasAccess('platform.systems.chats');
    }

    /**
     * Задачи, которые пользователь может открыть / прикрепить в чат.
     *
     * @return list<array{id: int, label: string, name: string}>
     */
    public function attachableTasksFor(User $user, ?string $search = null, int $limit = 40): array
    {
        $query = Task::query()->orderByDesc('id');

        if ($user->hasAccess('platform.systems.tasks')) {
            // полный список
        } elseif ($user->isClientContact()) {
            // Контакт — только задачи, где он наблюдатель, в своих проектах
            $uid = (int) $user->id;
            $projectIds = $user->projects()->pluck('projects.id');
            $query->whereIn('project_id', $projectIds)
                ->whereRaw('JSON_CONTAINS(COALESCE(observers_ids, "[]"), ?)', [json_encode($uid)]);
        } elseif ($user->isClientWithTaskAccess() || $user->isClientAccount()) {
            $projectIds = $user->projects()->pluck('projects.id');
            $query->whereIn('project_id', $projectIds);
        } else {
            $uid = (int) $user->id;
            $query->where(function ($q) use ($uid) {
                $q->where('executor_id', $uid)
                    ->orWhere('creator_id', $uid)
                    ->orWhereRaw('JSON_CONTAINS(COALESCE(observers_ids, "[]"), ?)', [json_encode($uid)]);
            });
        }

        $search = trim((string) $search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->where('id', (int) $search)
                        ->orWhere('name', 'like', '%' . $search . '%');
                } else {
                    $digits = preg_replace('/\D+/', '', $search);
                    if ($digits !== '') {
                        $q->where('id', (int) $digits)
                            ->orWhere('name', 'like', '%' . $search . '%');
                    } else {
                        $q->where('name', 'like', '%' . $search . '%');
                    }
                }
            });
        }

        return $query
            ->limit($limit)
            ->get(['id', 'name'])
            ->map(fn (Task $t) => [
                'id' => (int) $t->id,
                'name' => (string) $t->name,
                'label' => '#' . $t->id . ' · ' . $t->name,
            ])
            ->values()
            ->all();
    }

    public function canAttachTask(User $user, Task $task): bool
    {
        if ($user->hasAccess('platform.systems.tasks')) {
            return true;
        }

        return $task->canView($user->id);
    }

    public function isClientSideUser(User $user): bool
    {
        return $user->isClientAccount();
    }

    public function assertCanDirectWith(User $actor, int $otherUserId): void
    {
        if ((int) $otherUserId === (int) $actor->id || !$this->isChatMemberUserId($otherUserId)) {
            abort(422, 'Нельзя создать личный чат с этим пользователем');
        }

        $other = User::query()->with('roles')->findOrFail($otherUserId);
        $otherIsClient = $this->isClientSideUser($other);
        $actorIsClient = $this->isClientSideUser($actor);

        if ($actorIsClient) {
            if ($otherIsClient || !$other->hasAccess('platform.systems.chats.clients')) {
                abort(403, 'Личный чат с этим пользователем недоступен');
            }

            return;
        }

        // Сотрудник → клиент только с правом
        if ($otherIsClient && !$this->canChatWithClients($actor)) {
            abort(403, 'Нет права писать клиентам в личных чатах');
        }
    }

    /** Личный чат с клиентом — писать может только staff с chats.clients (и сам клиент). */
    public function assertCanWriteInChat(Chat $chat, User $actor): void
    {
        if ($chat->type !== 'direct') {
            return;
        }

        $chat->loadMissing('members.roles');

        $hasClient = $chat->members->contains(fn (User $u) => $this->isClientSideUser($u));
        if (!$hasClient) {
            return;
        }

        if ($this->isClientSideUser($actor)) {
            return;
        }

        if (!$this->canChatWithClients($actor)) {
            abort(403, 'Нет права писать клиентам в личных чатах');
        }
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
            ->orderByRaw(
                '(SELECT COALESCE(is_pinned, 0) FROM chat_user WHERE chat_user.chat_id = chats.id AND chat_user.user_id = ?) DESC',
                [$user->id]
            )
            ->orderByRaw(
                '(SELECT pinned_at FROM chat_user WHERE chat_user.chat_id = chats.id AND chat_user.user_id = ?) DESC',
                [$user->id]
            )
            ->orderByDesc(
                ChatMessage::select('created_at')
                    ->whereColumn('chat_id', 'chats.id')
                    ->latest()
                    ->limit(1)
            )
            ->orderByDesc('chats.updated_at')
            ->get()
            ->each(function (Chat $chat) use ($user) {
                $pivot = $chat->members->firstWhere('id', $user->id)?->pivot;
                $chat->setAttribute('is_muted', (bool) ($pivot?->is_muted ?? false));
                $chat->setAttribute('is_pinned', (bool) ($pivot?->is_pinned ?? false));
            });
    }

    public function pollState(User $user, ?int $sinceMessageId = null, ?int $activeChatId = null): array
    {
        if ($activeChatId) {
            $active = Chat::query()->find($activeChatId);
            if ($active && $active->isMember($user->id)) {
                $this->markRead($active, $user);
            }
        }

        $chats = $this->chatsFor($user);
        $chatIds = $chats->pluck('id')->filter()->values();
        $mutedIds = $chats->where('is_muted', true)->pluck('id')->all();

        $maxId = (int) (ChatMessage::query()
            ->whereIn('chat_id', $chatIds)
            ->max('id') ?? 0);

        $sound = false;
        if ($sinceMessageId && $sinceMessageId > 0) {
            // Звук для любых новых чужих сообщений (кроме замьюченных чатов).
            // Активный чат тоже учитываем — клиент сам решит, играть ли при открытой вкладке.
            $sound = ChatMessage::query()
                ->where('user_id', '!=', $user->id)
                ->where('id', '>', $sinceMessageId)
                ->whereIn('chat_id', $chatIds)
                ->when($mutedIds !== [], fn ($q) => $q->whereNotIn('chat_id', $mutedIds))
                ->exists();
        }

        return [
            'unread_total' => (int) $chats->sum('unread_count'),
            'sound' => $sound,
            'max_id' => $maxId,
            'chats' => $chats->map(fn (Chat $chat) => [
                'id' => (int) $chat->id,
                'unread' => (int) ($chat->unread_count ?? 0),
                'last_id' => $chat->latestMessage?->id ? (int) $chat->latestMessage->id : null,
                'preview' => \Illuminate\Support\Str::limit($chat->latestMessage?->plain_text ?? '', 48),
                'muted' => (bool) $chat->is_muted,
            ])->values()->all(),
            'messages' => $this->messagesPayload($user, $activeChatId, $sinceMessageId),
            'receipts' => $this->receiptsPayload($user, $activeChatId),
        ];
    }

    /**
     * Новые сообщения активного чата для live-ленты.
     *
     * @return list<array{id: int, html: string, preview: string}>
     */
    public function messagesPayload(User $user, ?int $activeChatId, ?int $sinceMessageId): array
    {
        if (!$activeChatId || !$sinceMessageId || $sinceMessageId <= 0) {
            return [];
        }

        $chat = Chat::query()->with('members')->find($activeChatId);
        if (!$chat || !$chat->isMember($user->id)) {
            return [];
        }

        $messages = ChatMessage::query()
            ->where('chat_id', $chat->id)
            ->where('id', '>', $sinceMessageId)
            ->with(['user', 'parent.user', 'task', 'attachment'])
            ->orderBy('id')
            ->limit(50)
            ->get();

        return $messages
            ->map(fn (ChatMessage $message) => $this->renderMessagePayload($chat, $message, $user))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, html: string, preview: string}
     */
    public function renderMessagePayload(Chat $chat, ChatMessage $message, User $viewer): array
    {
        $chat->loadMissing('members');
        $message->loadMissing(['user', 'parent.user', 'task', 'attachment']);

        return [
            'id' => (int) $message->id,
            'html' => view('orchid.layouts.partials.bx-message', [
                'message' => $message,
                'chat' => $chat,
                'viewer' => $viewer,
            ])->render(),
            'preview' => \Illuminate\Support\Str::limit((string) ($message->plain_text ?? ''), 48),
        ];
    }

    /**
     * @return list<array{id: int, status: string, readers: list<array{id: int, name: string, initials: string, color: string, read_at: string|null}>}>
     */
    private function receiptsPayload(User $user, ?int $activeChatId): array
    {
        if (!$activeChatId) {
            return [];
        }

        $chat = Chat::query()->with('members')->find($activeChatId);
        if (!$chat || !$chat->isMember($user->id)) {
            return [];
        }

        $ownMessages = $chat->messages()
            ->where('user_id', $user->id)
            ->where('is_system', false)
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        $othersCount = $chat->members
            ->reject(fn (User $u) => (int) $u->id === (int) $user->id)
            ->count();

        return $ownMessages->map(function (ChatMessage $message) use ($chat, $othersCount) {
            $readers = $chat->readersForMessage($message);
            $status = 'sent';
            if ($othersCount > 0 && count($readers) > 0) {
                $status = count($readers) >= $othersCount ? 'read' : 'partial';
            }

            return [
                'id' => (int) $message->id,
                'status' => $status,
                'readers' => $readers,
            ];
        })->values()->all();
    }

    public function toggleMute(Chat $chat, User $user): bool
    {
        if (!$chat->isMember($user->id)) {
            abort(403);
        }

        $current = (bool) $chat->members()->where('users.id', $user->id)->first()?->pivot?->is_muted;
        $next = !$current;
        $chat->members()->updateExistingPivot($user->id, ['is_muted' => $next]);

        return $next;
    }

    public function togglePin(Chat $chat, User $user): bool
    {
        if (!$chat->isMember($user->id)) {
            abort(403);
        }

        $current = (bool) $chat->members()->where('users.id', $user->id)->first()?->pivot?->is_pinned;
        $next = !$current;
        $chat->members()->updateExistingPivot($user->id, [
            'is_pinned' => $next,
            'pinned_at' => $next ? now() : null,
        ]);

        return $next;
    }

    public function updateChat(Chat $chat, User $actor, array $data): Chat
    {
        if ($chat->type === 'direct') {
            abort(422, 'Личный чат нельзя редактировать');
        }

        if (!$chat->isOwner($actor->id) && !$actor->hasAccess('platform.systems.chats.create')) {
            abort(403);
        }

        $chat->fill([
            'title' => $data['title'] ?? $chat->title,
            'description' => $data['description'] ?? $chat->description,
            'avatar_path' => array_key_exists('avatar_path', $data) ? $data['avatar_path'] : $chat->avatar_path,
        ])->save();

        $this->postSystem($chat, $actor, "{$actor->displayName()} обновил(а) настройки чата");

        return $chat->fresh(['members']);
    }

    /**
     * @param  list<int>  $memberIds
     */
    public function createGroup(User $actor, string $title, array $memberIds, ?string $description = null, ?string $avatarPath = null): Chat
    {
        return DB::transaction(function () use ($actor, $title, $memberIds, $description, $avatarPath) {
            $chat = Chat::query()->create([
                'title' => trim($title) !== '' ? trim($title) : 'Групповой чат',
                'type' => 'group',
                'created_by' => $actor->id,
                'description' => $description,
                'avatar_path' => $avatarPath,
            ]);

            $ids = collect($memberIds)
                ->map(fn ($id) => (int) $id)
                ->push($actor->id)
                ->unique()
                ->filter(fn ($id) => $this->isChatMemberUserId($id))
                ->values();

            $sync = [];
            foreach ($ids as $id) {
                $sync[$id] = [
                    'role' => (int) $id === (int) $actor->id ? 'owner' : 'member',
                    'last_read_at' => now(),
                    'is_muted' => false,
                    'is_pinned' => false,
                ];
            }
            $chat->members()->sync($sync);

            $this->postSystem($chat, $actor, "{$actor->displayName()} создал(а) чат «{$chat->title}»");

            return $chat->fresh(['members']);
        });
    }

    public function findOrCreateDirect(User $actor, int $otherUserId): Chat
    {
        $this->assertCanDirectWith($actor, $otherUserId);

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
                $actor->id => ['role' => 'owner', 'last_read_at' => now(), 'is_muted' => false, 'is_pinned' => false],
                $other->id => ['role' => 'member', 'last_read_at' => now(), 'is_muted' => false, 'is_pinned' => false],
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
            ->filter(fn ($id) => $this->isChatMemberUserId($id))
            ->values();

        $sync = [];
        foreach ($ids as $id) {
            $member = $chat->members()->where('users.id', $id)->first();
            $existingRole = $member?->pivot?->role;
            $sync[$id] = [
                'role' => $existingRole === 'owner' || (int) $id === (int) $chat->created_by ? 'owner' : 'member',
                'last_read_at' => $member?->pivot?->last_read_at ?? now(),
                'is_muted' => (bool) ($member?->pivot?->is_muted ?? false),
                'is_pinned' => (bool) ($member?->pivot?->is_pinned ?? false),
                'pinned_at' => $member?->pivot?->pinned_at,
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

        $this->assertCanWriteInChat($chat, $actor);

        $rawText = $request->input('message.text');
        $quill = $this->comments->normalizeQuill($rawText);
        $plain = is_string($rawText)
            ? trim($rawText)
            : $this->comments->extractPlainText($quill);

        $taskId = $request->input('message.task_id');
        $task = null;
        if ($taskId) {
            $task = Task::query()->find($taskId);
            if ($task && !$this->canAttachTask($actor, $task)) {
                abort(403, 'Нельзя прикрепить эту задачу');
            }
            if (!$task) {
                abort(422, 'Задача не найдена');
            }
        }

        if (!$task && preg_match('/(?:tasks\/|my-tasks\/)(\d+)/', $plain, $m)) {
            $candidate = Task::query()->find((int) $m[1]);
            if ($candidate && $this->canAttachTask($actor, $candidate)) {
                $task = $candidate;
            }
        }

        $attachmentIds = collect($request->input('message.attachments', []))->filter();
        $isVoice = false;
        $voiceDuration = min(180, max(0, (int) $request->input('message.voice_duration', 0)));

        if ($request->hasFile('message_voice')) {
            $uploaded = $request->file('message_voice');
            if ($uploaded && $uploaded->isValid()) {
                $mime = strtolower((string) $uploaded->getMimeType());
                $ext = strtolower((string) $uploaded->getClientOriginalExtension());
                $allowedExt = ['webm', 'ogg', 'oga', 'mp3', 'm4a', 'mp4', 'wav', 'aac', 'opus'];
                $okMime = str_starts_with($mime, 'audio/')
                    || $mime === 'video/mp4'
                    || $mime === 'application/octet-stream';
                if (!$okMime && !in_array($ext, $allowedExt, true)) {
                    abort(422, 'Голосовое сообщение должно быть аудиофайлом');
                }
                // WAV до ~3 мин / 16kHz ≈ 6 МБ; webm меньше
                if ($uploaded->getSize() > 12 * 1024 * 1024) {
                    abort(422, 'Голосовое сообщение слишком большое (макс. 3 минуты)');
                }
                if ($voiceDuration > 180) {
                    abort(422, 'Голосовое сообщение не длиннее 3 минут');
                }

                $file = new \Orchid\Attachment\File($uploaded, 'public');
                $attachment = $file->load();
                $attachment->group = 'voice';
                // Нормализуем mime/расширение — иначе на других браузерах файл «не звучит»
                if ($ext === '' || $ext === 'bin' || $ext === 'tmp') {
                    if (str_contains($mime, 'wav')) {
                        $ext = 'wav';
                    } elseif (str_contains($mime, 'mp4') || str_contains($mime, 'm4a') || str_contains($mime, 'aac')) {
                        $ext = 'm4a';
                    } elseif (str_contains($mime, 'ogg')) {
                        $ext = 'ogg';
                    } elseif (str_contains($mime, 'webm')) {
                        $ext = 'webm';
                    } elseif (str_contains($mime, 'mpeg') || str_contains($mime, 'mp3')) {
                        $ext = 'mp3';
                    } else {
                        $ext = 'wav';
                    }
                }
                if ($mime === '' || $mime === 'application/octet-stream') {
                    $mime = match ($ext) {
                        'wav' => 'audio/wav',
                        'm4a', 'mp4' => 'audio/mp4',
                        'ogg', 'oga', 'opus' => 'audio/ogg',
                        'webm' => 'audio/webm',
                        'mp3' => 'audio/mpeg',
                        'aac' => 'audio/aac',
                        default => 'audio/wav',
                    };
                }
                $attachment->mime = $mime;
                $attachment->extension = $ext;
                if (empty($attachment->original_name) || !str_contains((string) $attachment->original_name, '.')) {
                    $attachment->original_name = 'voice.' . $ext;
                }
                $attachment->save();
                $attachmentIds->push($attachment->id);
                $isVoice = true;
            }
        }

        if ($request->hasFile('message_files')) {
            foreach ((array) $request->file('message_files') as $uploaded) {
                if (!$uploaded || !$uploaded->isValid()) {
                    continue;
                }
                $file = new \Orchid\Attachment\File($uploaded, 'public');
                $attachment = $file->load();
                $attachmentIds->push($attachment->id);
            }
        }

        if ($plain === '' && $attachmentIds->isEmpty() && !$task) {
            abort(422, 'Напишите сообщение, прикрепите файл или задачу');
        }

        if ($isVoice && $plain === '') {
            $plain = 'Голосовое сообщение' . ($voiceDuration > 0
                ? ' · ' . sprintf('%d:%02d', intdiv($voiceDuration, 60), $voiceDuration % 60)
                : '');
        }

        $parentId = $request->input('message.parent_id');
        $parent = null;
        if ($parentId) {
            $parent = ChatMessage::query()
                ->where('chat_id', $chat->id)
                ->whereKey($parentId)
                ->first();
        }

        $chat->loadMissing('members');
        $mentions = $this->parseMentionsFromText($plain, $chat->members)
            ->merge(collect($request->input('message.notify_user_ids', []))->map(fn ($id) => (int) $id));

        if ($parent?->user_id && (int) $parent->user_id !== (int) $actor->id) {
            $mentions->push((int) $parent->user_id);
        }

        $mentions = $mentions
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === (int) $actor->id)
            ->values();

        $message = $chat->messages()->create([
            'user_id' => $actor->id,
            'parent_id' => $parent?->id,
            'text' => $quill,
            'plain_text' => $plain !== '' ? $plain : ($task ? 'Задача #' . $task->id : 'Вложение'),
            'mentioned_user_ids' => $mentions->all(),
            'task_id' => $task?->id,
            'is_system' => false,
        ]);

        if ($attachmentIds->isNotEmpty()) {
            $message->attachment()->syncWithoutDetaching($attachmentIds->all());
        }

        $chat->touch();
        $this->markRead($chat, $actor);
        $this->notifyMembers($chat, $actor, $message, $mentions->all());

        return $message;
    }

    /**
     * @param  Collection<int, User>  $members
     * @return Collection<int, int>
     */
    public function parseMentionsFromText(string $plain, Collection $members): Collection
    {
        $ids = collect();

        foreach ($members as $member) {
            $labels = array_filter([
                $member->displayName(),
                $member->name,
                $member->email ? strtok($member->email, '@') : null,
            ]);

            foreach ($labels as $label) {
                $label = trim((string) $label);
                if ($label === '') {
                    continue;
                }
                $quoted = preg_quote($label, '/');
                if (preg_match('/(^|[\s([{])@' . $quoted . '(?=$|[\s,.:;!?)\]}])/u', $plain)) {
                    $ids->push((int) $member->id);
                    break;
                }
            }
        }

        return $ids->unique()->values();
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
            ->reject(fn (User $u) => (int) $u->id === (int) $actor->id)
            ->reject(fn (User $u) => (bool) ($u->pivot?->is_muted ?? false));

        // Упоминания — всегда (даже если чат замьючен), как в Telegram/Bitrix
        if ($mentionIds !== []) {
            $mentioned = $chat->members
                ->reject(fn (User $u) => (int) $u->id === (int) $actor->id)
                ->filter(fn (User $u) => in_array((int) $u->id, $mentionIds, true));

            foreach ($mentioned as $user) {
                $this->notifier->send($user, 'Вас упомянули в чате', $body, $url, Color::INFO);
            }

            // Остальным незамьюченным — обычное «новое сообщение»
            $mentionedIds = $mentioned->pluck('id')->all();
            foreach ($recipients->reject(fn (User $u) => in_array((int) $u->id, $mentionedIds, true)) as $user) {
                $this->notifier->send($user, 'Новое сообщение в чате', $body, $url, Color::INFO);
            }

            return;
        }

        foreach ($recipients as $user) {
            $this->notifier->send($user, $title, $body, $url, Color::INFO);
        }
    }

    private function isChatMemberUserId(int $userId): bool
    {
        return User::query()
            ->whereKey($userId)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', RoleCatalog::CHAT_MEMBER_SLUGS))
            ->exists();
    }
}
