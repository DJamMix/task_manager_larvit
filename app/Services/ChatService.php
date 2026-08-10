<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bot;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\ChatMessageHide;
use App\Models\Task;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Orchid\Support\Color;

class ChatService
{
    public function __construct(
        private readonly CommentService $comments,
        private readonly DashboardNotifier $notifier,
    ) {}

    /** Участники чатов: сотрудники + клиентские контакты (+ боты для админов) */
    public function chatMemberOptions(?int $exceptId = null, bool $includeBots = false): array
    {
        $humans = User::query()
            ->where(fn ($q) => $q->where('is_bot', false)->orWhereNull('is_bot'))
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', RoleCatalog::CHAT_MEMBER_SLUGS))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->displayName()]);

        if (! $includeBots) {
            return $humans->all();
        }

        $bots = User::query()
            ->where('is_bot', true)
            ->with('bot')
            ->whereHas('bot', fn ($q) => $q->where('is_active', true)->where('can_join_groups', true))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $u) => [
                $u->id => '[бот] '.$u->name.($u->bot?->username ? ' (@'.$u->bot->username.')' : ''),
            ]);

        return $humans->union($bots)->all();
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
                ->where(fn ($q) => $q->where('is_bot', false)->orWhereNull('is_bot'))
                ->whereHas('roles', fn ($q) => $q->whereIn('slug', RoleCatalog::STAFF_SLUGS))
                ->get()
                ->filter(fn (User $u) => $u->hasAccess('platform.systems.chats.clients'))
                ->sortBy('name')
                ->mapWithKeys(fn (User $u) => [$u->id => $u->displayName()])
                ->all();
        }

        if ($this->canChatWithClients($actor)) {
            return $this->chatMemberOptions($exceptId, false);
        }

        // Обычный сотрудник — только коллеги
        return User::query()
            ->whereKeyNot($exceptId)
            ->where(fn ($q) => $q->where('is_bot', false)->orWhereNull('is_bot'))
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
        if ($actor->is_bot) {
            return;
        }

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
                $latest = ChatMessage::query()
                    ->where('chat_id', $chat->id)
                    ->visibleTo($user)
                    ->orderByDesc('id')
                    ->first();
                $chat->setRelation('latestMessage', $latest);
            });
    }

    /**
     * Список чатов для пересылки: с аватарами и типом.
     *
     * @return list<array{id: int, title: string, subtitle: string, type: string, avatar_url: string, avatar_initials: string, avatar_color: string}>
     */
    public function chatPickerPayload(User $user): array
    {
        return $this->chatsFor($user)
            ->map(function (Chat $chat) use ($user) {
                $isDirect = $chat->type === 'direct';
                $peer = $isDirect ? $chat->otherMember($user->id) : null;
                $count = $chat->members->count();

                return [
                    'id' => (int) $chat->id,
                    'title' => $chat->displayTitle($user->id),
                    'subtitle' => $isDirect
                        ? 'Личный чат'
                        : ($count . ' ' . ($count === 1 ? 'участник' : (($count % 10 >= 2 && $count % 10 <= 4 && !in_array($count % 100, [12, 13, 14], true)) ? 'участника' : 'участников'))),
                    'type' => (string) $chat->type,
                    'avatar_url' => $isDirect
                        ? (string) ($peer?->avatarUrl() ?? '')
                        : (string) $chat->avatarUrl($user->id),
                    'avatar_initials' => $isDirect
                        ? (string) ($peer?->avatarInitials() ?? '?')
                        : (string) $chat->avatarInitials($user->id),
                    'avatar_color' => $isDirect
                        ? (string) ($peer?->avatarColor() ?? '#64748b')
                        : (string) $chat->avatarColor($user->id),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Пересылает до 20 сообщений текущего чата в доступный пользователю чат.
     *
     * @param  list<int>  $messageIds
     */
    public function forwardMessages(Chat $source, Chat $target, User $actor, array $messageIds): void
    {
        if (!$source->isMember($actor->id) || !$target->isMember($actor->id)) {
            abort(403);
        }

        $this->assertCanWriteInChat($target, $actor);
        $ids = collect($messageIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty() || $ids->count() > 20) {
            abort(422, 'Можно переслать от 1 до 20 сообщений');
        }

        $messages = ChatMessage::query()
            ->where('chat_id', $source->id)
            ->whereIn('id', $ids)
            ->where('is_system', false)
            ->with(['attachment', 'user', 'forwardedFromUser'])
            ->orderBy('id')
            ->get();

        if ($messages->count() !== $ids->count()) {
            abort(422, 'Некоторые сообщения недоступны для пересылки');
        }

        DB::transaction(function () use ($messages, $source, $target, $actor) {
            foreach ($messages as $original) {
                // Цепочка как в Telegram: сохраняем первого автора, а не промежуточного пересыльщика
                $originUserId = (int) ($original->forwarded_from_user_id ?: $original->user_id);
                $originMessageId = (int) ($original->forwarded_from_message_id ?: $original->id);
                $originChatId = (int) ($original->forwarded_from_chat_id ?: $source->id);

                $forwarded = $target->messages()->create([
                    'user_id' => $actor->id,
                    'text' => $original->text,
                    'plain_text' => $original->plain_text,
                    'task_id' => $original->task_id,
                    'is_system' => false,
                    'forwarded_from_message_id' => $originMessageId,
                    'forwarded_from_chat_id' => $originChatId,
                    'forwarded_from_user_id' => $originUserId ?: null,
                ]);

                $forwarded->attachment()->sync($original->attachment->pluck('id')->all());
                $this->notifyMembers($target, $actor, $forwarded, []);
            }

            $target->touch();
            $this->markRead($target, $actor);
        });
    }

    /**
     * Вложения и ссылки чата постранично для окна «Медиа».
     *
     * @return array{items: list<array>, page: int, has_more: bool}
     */
    public function chatMediaPayload(Chat $chat, User $user, string $tab = 'media', int $page = 1, int $perPage = 60): array
    {
        if (!$chat->isMember($user->id)) {
            abort(403);
        }

        $tab = in_array($tab, ['media', 'files', 'links'], true) ? $tab : 'media';
        $page = max(1, $page);
        $perPage = max(1, min(60, $perPage));
        $offset = ($page - 1) * $perPage;

        if ($tab === 'links') {
            $items = [];
            $messages = ChatMessage::query()
                ->where('chat_id', $chat->id)
                ->where('is_system', false)
                ->whereNotNull('plain_text')
                ->with('user')
                ->orderByDesc('id')
                ->get(['id', 'user_id', 'plain_text', 'created_at']);

            foreach ($messages as $message) {
                preg_match_all('~https?://[^\s<>"\']+~iu', (string) $message->plain_text, $matches);
                foreach ($matches[0] ?? [] as $url) {
                    $clean = rtrim($url, '.,;:!?)]}');
                    $host = parse_url($clean, PHP_URL_HOST) ?: '';
                    $host = preg_replace('/^www\./i', '', (string) $host);
                    $path = (string) (parse_url($clean, PHP_URL_PATH) ?: '');
                    $items[] = [
                        'id' => (int) $message->id,
                        'message_id' => (int) $message->id,
                        'url' => $clean,
                        'domain' => $host,
                        'path' => $path === '/' ? '' : \Illuminate\Support\Str::limit($path, 48),
                        'title' => $host !== '' ? $host : $clean,
                        'text' => \Illuminate\Support\Str::limit(trim((string) $message->plain_text), 140),
                        'author' => (string) ($message->user?->displayName() ?? 'Участник'),
                        'created_at' => $message->created_at?->format('d.m.Y H:i'),
                        'created_ts' => $message->created_at?->timestamp,
                    ];
                }
            }

            $slice = array_slice($items, $offset, $perPage);

            return ['items' => $slice, 'page' => $page, 'has_more' => count($items) > $offset + $perPage];
        }

        $messages = ChatMessage::query()
            ->where('chat_id', $chat->id)
            ->where('is_system', false)
            ->with(['attachment', 'user'])
            ->orderByDesc('id')
            ->get();
        $items = [];

        foreach ($messages as $message) {
            foreach ($message->attachment as $file) {
                $mime = strtolower((string) ($file->mime ?? ''));
                $extension = strtolower((string) ($file->extension ?? pathinfo((string) $file->original_name, PATHINFO_EXTENSION)));
                $group = strtolower((string) ($file->group ?? ''));
                $isVoice = $group === 'voice'
                    || str_starts_with($mime, 'audio/')
                    || in_array($extension, ['webm', 'ogg', 'oga', 'mp3', 'm4a', 'wav', 'aac', 'opus'], true)
                    || str_starts_with(strtolower((string) $file->original_name), 'voice.');
                $isImage = str_starts_with($mime, 'image/')
                    || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);

                if ($isVoice) {
                    continue;
                }

                if (($tab === 'media') !== $isImage) {
                    continue;
                }

                $size = (int) ($file->size ?? 0);
                $items[] = [
                    'id' => (int) $file->id,
                    'message_id' => (int) $message->id,
                    'name' => (string) $file->original_name,
                    'ext' => $extension !== '' ? strtoupper($extension) : 'FILE',
                    'mime' => $mime,
                    'size' => $size,
                    'size_label' => $this->formatBytes($size),
                    'url' => route('platform.task.attachment.download', ['attachment' => $file, 'inline' => 1]),
                    'download_url' => route('platform.task.attachment.download', $file),
                    'author' => (string) ($message->user?->displayName() ?? 'Участник'),
                    'created_at' => $message->created_at?->format('d.m.Y H:i'),
                    'created_ts' => $message->created_at?->timestamp,
                ];
            }
        }

        $slice = array_slice($items, $offset, $perPage);

        return ['items' => $slice, 'page' => $page, 'has_more' => count($items) > $offset + $perPage];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '';
        }
        $units = ['Б', 'КБ', 'МБ', 'ГБ'];
        $pow = (int) floor(log($bytes, 1024));
        $pow = max(0, min($pow, count($units) - 1));
        $value = $bytes / (1024 ** $pow);

        return rtrim(rtrim(number_format($value, $pow > 0 ? 1 : 0, '.', ' '), '0'), '.')
            . ' ' . $units[$pow];
    }

    public function pollState(User $user, ?int $sinceMessageId = null, ?int $activeChatId = null): array
    {
        $this->touchPresence($user);

        $active = null;
        if ($activeChatId) {
            $active = Chat::query()->with('members')->find($activeChatId);
            if (!$active || !$active->isMember($user->id)) {
                $active = null;
            }
            // Не помечаем прочитанным здесь — только когда сообщения реально на экране
            // (IntersectionObserver / mark-read endpoint), как в Telegram.
        }

        $chats = $this->chatsFor($user);
        $chatIds = $chats->pluck('id')->filter()->values();
        $mutedIds = $chats->where('is_muted', true)->pluck('id')->all();

        $maxId = (int) (ChatMessage::query()
            ->whereIn('chat_id', $chatIds)
            ->max('id') ?? 0);

        $sound = false;
        $notify = null;
        if ($sinceMessageId && $sinceMessageId > 0) {
            // Звук для любых новых чужих сообщений (кроме замьюченных чатов).
            $latest = ChatMessage::query()
                ->where('user_id', '!=', $user->id)
                ->where('id', '>', $sinceMessageId)
                ->whereIn('chat_id', $chatIds)
                ->when($mutedIds !== [], fn ($q) => $q->whereNotIn('chat_id', $mutedIds))
                ->with(['user', 'chat'])
                ->orderByDesc('id')
                ->first();

            $sound = $latest !== null;
            if ($latest) {
                $notify = [
                    'title' => $latest->chat?->displayTitle($user->id) ?: 'Новое сообщение',
                    'body' => trim(($latest->user?->displayName() ?: 'Участник') . ': ' . \Illuminate\Support\Str::limit((string) $latest->plain_text, 120)),
                    'url' => route('platform.systems.chats.view', $latest->chat_id),
                    'message_id' => (int) $latest->id,
                ];
            }
        }

        $memberIds = $chats
            ->flatMap(fn (Chat $chat) => $chat->members->pluck('id'))
            ->push($user->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return [
            'unread_total' => (int) $chats->sum('unread_count'),
            'sound' => $sound,
            'notify' => $notify,
            'max_id' => $maxId,
            'chats' => $chats->map(function (Chat $chat) use ($user) {
                $peerId = $chat->type === 'direct'
                    ? $chat->otherMember($user->id)?->id
                    : null;
                $latest = $chat->latestMessage;
                $preview = trim((string) ($latest?->plain_text ?? ''));
                if ($preview === '') {
                    $preview = $latest ? 'Вложение' : 'Нет сообщений';
                } elseif ($chat->type !== 'direct' && $latest?->user) {
                    $who = $latest->user->id === $user->id
                        ? 'Вы'
                        : ($latest->user->displayName() ?: 'Участник');
                    $preview = $who . ': ' . $preview;
                } elseif ($latest && (int) $latest->user_id === (int) $user->id) {
                    $preview = 'Вы: ' . $preview;
                }

                return [
                    'id' => (int) $chat->id,
                    'unread' => (int) ($chat->unread_count ?? 0),
                    'last_id' => $latest?->id ? (int) $latest->id : null,
                    'preview' => \Illuminate\Support\Str::limit($preview, 64),
                    'time' => $this->formatChatListTime($latest?->created_at),
                    'muted' => (bool) $chat->is_muted,
                    'pinned' => (bool) $chat->is_pinned,
                    'peer_id' => $peerId ? (int) $peerId : null,
                ];
            })->values()->all(),
            'messages' => $this->messagesPayload($user, $activeChatId, $sinceMessageId),
            'removed_ids' => $this->removedMessageIds($user, $activeChatId),
            'receipts' => $this->receiptsPayload($user, $activeChatId),
            'calls' => app(CallService::class)->openCallsPayload($user),
            'presence' => $this->presenceMap($memberIds),
            'typing' => $active ? $this->typingPayload($active, $user) : [],
        ];
    }

    /** Отметить пользователя онлайн (heartbeat при опросе чатов). */
    public function touchPresence(User $user): void
    {
        Cache::put($this->presenceCacheKey($user->id), now()->timestamp, now()->addSeconds(60));
    }

    /**
     * @param  list<int>|Collection  $userIds
     * @return array<int, true> map user_id => true
     */
    public function presenceMap(iterable $userIds): array
    {
        $ids = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $online = [];
        $threshold = now()->timestamp - 45;

        foreach ($ids as $id) {
            $cached = Cache::get($this->presenceCacheKey($id));
            if ($cached && (int) $cached >= $threshold) {
                $online[$id] = true;
            }
        }

        $remaining = $ids->reject(fn (int $id) => isset($online[$id]));
        if ($remaining->isNotEmpty()) {
            try {
                DB::table('sessions')
                    ->whereIn('user_id', $remaining->all())
                    ->where('last_activity', '>=', $threshold)
                    ->distinct()
                    ->pluck('user_id')
                    ->each(function ($id) use (&$online) {
                        $online[(int) $id] = true;
                    });
            } catch (\Throwable) {
                // session driver без таблицы sessions — достаточно cache heartbeat
            }
        }

        return $online;
    }

    public function markTyping(Chat $chat, User $actor): void
    {
        if (!$chat->isMember($actor->id)) {
            abort(403);
        }

        $this->assertCanWriteInChat($chat, $actor);
        $this->touchPresence($actor);

        Cache::put($this->typingCacheKey($chat->id, $actor->id), [
            'user_id' => (int) $actor->id,
            'name' => $actor->displayName(),
        ], now()->addSeconds(5));
    }

    public function clearTyping(Chat $chat, User $actor): void
    {
        Cache::forget($this->typingCacheKey($chat->id, $actor->id));
    }

    /**
     * @return list<array{user_id: int, name: string}>
     */
    public function typingPayload(Chat $chat, User $viewer): array
    {
        $chat->loadMissing('members');
        $out = [];

        foreach ($chat->members as $member) {
            if ((int) $member->id === (int) $viewer->id) {
                continue;
            }
            $data = Cache::get($this->typingCacheKey($chat->id, $member->id));
            if (is_array($data) && !empty($data['name'])) {
                $out[] = [
                    'user_id' => (int) ($data['user_id'] ?? $member->id),
                    'name' => (string) $data['name'],
                ];
            }
        }

        return $out;
    }

    public function postSystemMessage(Chat $chat, User $actor, string $text): void
    {
        $this->postSystem($chat, $actor, $text);
    }

    private function presenceCacheKey(int $userId): string
    {
        return "chat:presence:{$userId}";
    }

    private function typingCacheKey(int $chatId, int $userId): string
    {
        return "chat:{$chatId}:typing:{$userId}";
    }

    /**
     * Поиск по чатам (название/собеседник) и сообщениям (plain_text).
     *
     * @return array{chats: list<array>, messages: list<array>}
     */
    public function search(User $user, string $query, int $limit = 25): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return ['chats' => [], 'messages' => []];
        }

        $chats = $this->chatsFor($user);
        $chatIds = $chats->pluck('id')->filter()->values();
        $needle = mb_strtolower($q);

        $matchedChats = $chats
            ->filter(function (Chat $chat) use ($needle, $user) {
                $title = mb_strtolower($chat->displayTitle($user->id));
                if (str_contains($title, $needle)) {
                    return true;
                }
                if ($chat->type === 'group' && str_contains(mb_strtolower((string) $chat->description), $needle)) {
                    return true;
                }
                foreach ($chat->members as $member) {
                    if (str_contains(mb_strtolower($member->displayName()), $needle)) {
                        return true;
                    }
                }

                return false;
            })
            ->take($limit)
            ->map(function (Chat $chat) use ($user) {
                $latest = $chat->latestMessage;

                return [
                    'id' => (int) $chat->id,
                    'title' => $chat->displayTitle($user->id),
                    'preview' => \Illuminate\Support\Str::limit($latest?->plain_text ?? 'Нет сообщений', 72),
                    'type' => $chat->type,
                    'url' => route('platform.systems.chats.view', $chat),
                    'unread' => (int) ($chat->unread_count ?? 0),
                    'at' => $latest?->created_at?->format('d.m') ?? '',
                    'avatar' => $this->chatAvatarPayload($chat, $user),
                ];
            })
            ->values()
            ->all();

        $like = '%' . addcslashes($q, '%_\\') . '%';
        $messages = ChatMessage::query()
            ->whereIn('chat_id', $chatIds)
            ->visibleTo($user)
            ->where('is_system', false)
            ->whereNotNull('plain_text')
            ->where('plain_text', 'like', $like)
            ->with(['user', 'chat.members'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (ChatMessage $message) use ($user) {
                $chat = $message->chat;
                if (!$chat) {
                    return null;
                }

                return [
                    'id' => (int) $message->id,
                    'chat_id' => (int) $message->chat_id,
                    'chat_title' => $chat->displayTitle($user->id),
                    'chat_type' => $chat->type,
                    'author' => $message->user?->displayName() ?? 'Участник',
                    'preview' => \Illuminate\Support\Str::limit((string) $message->plain_text, 100),
                    'at' => $message->created_at?->format('d.m H:i') ?? '',
                    'url' => route('platform.systems.chats.view', $chat) . '?msg=' . $message->id,
                    // Личка → аватар собеседника; группа → аватар чата
                    'avatar' => $this->chatAvatarPayload($chat, $user),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'chats' => $matchedChats,
            'messages' => $messages,
            'query' => $q,
        ];
    }

    /**
     * @return array{url: string, initials: string, color: string, shape: string}
     */
    private function chatAvatarPayload(Chat $chat, User $viewer): array
    {
        return [
            'url' => $chat->avatarUrl($viewer->id),
            'initials' => $chat->avatarInitials($viewer->id),
            'color' => $chat->avatarColor($viewer->id),
            'shape' => $chat->type === 'direct' ? 'round' : 'square',
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
            ->visibleTo($user)
            ->where('id', '>', $sinceMessageId)
            ->with(['user', 'parent.user' => fn ($q) => $q->withTrashed(), 'task', 'attachment', 'forwardedFromUser'])
            ->orderBy('id')
            ->limit(50)
            ->get();

        return $messages
            ->map(fn (ChatMessage $message) => $this->renderMessagePayload($chat, $message, $user))
            ->values()
            ->all();
    }

    /**
     * Последние N сообщений (или окно вокруг focusMessageId для перехода из поиска).
     *
     * @return array{messages: \Illuminate\Support\Collection, has_more: bool, oldest_id: int|null, has_more_newer: bool, newest_id: int|null}
     */
    public function feedForChat(Chat $chat, User $viewer, ?int $focusMessageId = null, int $limit = 40): array
    {
        $chat->loadMissing('members');
        if (!$chat->isMember($viewer->id)) {
            abort(403);
        }

        $with = ['user', 'parent' => fn ($q) => $q->withTrashed()->with('user'), 'task', 'attachment', 'forwardedFromUser'];
        $limit = max(10, min(100, $limit));

        if ($focusMessageId) {
            $focus = ChatMessage::query()
                ->where('chat_id', $chat->id)
                ->visibleTo($viewer)
                ->whereKey($focusMessageId)
                ->first();

            if ($focus) {
                $before = ChatMessage::query()
                    ->where('chat_id', $chat->id)
                    ->visibleTo($viewer)
                    ->where('id', '<=', $focus->id)
                    ->with($with)
                    ->orderByDesc('id')
                    ->limit($limit)
                    ->get()
                    ->sortBy('id')
                    ->values();

                // Не грузим всю непрочитанную историю сразу — догрузим при скролле вниз
                $after = ChatMessage::query()
                    ->where('chat_id', $chat->id)
                    ->visibleTo($viewer)
                    ->where('id', '>', $focus->id)
                    ->with($with)
                    ->orderBy('id')
                    ->limit($limit)
                    ->get();

                $messages = $before->concat($after)->unique('id')->sortBy('id')->values();
                $oldestId = $messages->first()?->id ? (int) $messages->first()->id : null;
                $newestId = $messages->last()?->id ? (int) $messages->last()->id : null;
                $hasMore = $oldestId
                    ? ChatMessage::query()->where('chat_id', $chat->id)->visibleTo($viewer)->where('id', '<', $oldestId)->exists()
                    : false;
                $hasMoreNewer = $newestId
                    ? ChatMessage::query()->where('chat_id', $chat->id)->visibleTo($viewer)->where('id', '>', $newestId)->exists()
                    : false;

                return [
                    'messages' => $messages,
                    'has_more' => $hasMore,
                    'oldest_id' => $oldestId,
                    'has_more_newer' => $hasMoreNewer,
                    'newest_id' => $newestId,
                ];
            }
        }

        $messages = ChatMessage::query()
            ->where('chat_id', $chat->id)
            ->visibleTo($viewer)
            ->with($with)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();

        $oldestId = $messages->first()?->id ? (int) $messages->first()->id : null;
        $newestId = $messages->last()?->id ? (int) $messages->last()->id : null;
        $hasMore = $oldestId
            ? ChatMessage::query()->where('chat_id', $chat->id)->visibleTo($viewer)->where('id', '<', $oldestId)->exists()
            : false;
        $hasMoreNewer = $newestId
            ? ChatMessage::query()->where('chat_id', $chat->id)->visibleTo($viewer)->where('id', '>', $newestId)->exists()
            : false;

        return [
            'messages' => $messages,
            'has_more' => $hasMore,
            'oldest_id' => $oldestId,
            'has_more_newer' => $hasMoreNewer,
            'newest_id' => $newestId,
        ];
    }

    /**
     * Более старые сообщения для бесконечного скролла вверх.
     *
     * @return array{messages: list<array>, has_more: bool, oldest_id: int|null, has_more_newer?: bool, newest_id?: int|null}
     */
    public function historyPayload(User $user, Chat $chat, int $beforeId, int $limit = 40): array
    {
        $chat->loadMissing('members');
        if (!$chat->isMember($user->id)) {
            abort(403);
        }

        $limit = max(10, min(100, $limit));
        $batch = ChatMessage::query()
            ->where('chat_id', $chat->id)
            ->visibleTo($user)
            ->where('id', '<', $beforeId)
            ->with(['user', 'parent' => fn ($q) => $q->withTrashed()->with('user'), 'task', 'attachment', 'forwardedFromUser'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();

        $oldestId = $batch->first()?->id ? (int) $batch->first()->id : null;
        $hasMore = $oldestId
            ? ChatMessage::query()->where('chat_id', $chat->id)->visibleTo($user)->where('id', '<', $oldestId)->exists()
            : false;

        return [
            'messages' => $batch
                ->map(fn (ChatMessage $message) => $this->renderMessagePayload($chat, $message, $user))
                ->values()
                ->all(),
            'has_more' => $hasMore,
            'oldest_id' => $oldestId,
        ];
    }

    /**
     * Более новые сообщения для бесконечного скролла вниз (после прыжка к непрочитанным).
     *
     * @return array{messages: list<array>, has_more_newer: bool, newest_id: int|null}
     */
    public function newerPayload(User $user, Chat $chat, int $afterId, int $limit = 40): array
    {
        $chat->loadMissing('members');
        if (!$chat->isMember($user->id)) {
            abort(403);
        }

        $limit = max(10, min(100, $limit));
        $batch = ChatMessage::query()
            ->where('chat_id', $chat->id)
            ->visibleTo($user)
            ->where('id', '>', $afterId)
            ->with(['user', 'parent' => fn ($q) => $q->withTrashed()->with('user'), 'task', 'attachment', 'forwardedFromUser'])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->values();

        $newestId = $batch->last()?->id ? (int) $batch->last()->id : $afterId;
        $hasMoreNewer = $newestId
            ? ChatMessage::query()->where('chat_id', $chat->id)->visibleTo($user)->where('id', '>', $newestId)->exists()
            : false;

        return [
            'messages' => $batch
                ->map(fn (ChatMessage $message) => $this->renderMessagePayload($chat, $message, $user))
                ->values()
                ->all(),
            'has_more_newer' => $hasMoreNewer,
            'newest_id' => $newestId,
        ];
    }

    /**
     * @return array{id: int, html: string, preview: string}
     */
    public function renderMessagePayload(Chat $chat, ChatMessage $message, User $viewer): array
    {
        $chat->loadMissing('members');
        $message->loadMissing(['user', 'parent.user', 'task', 'attachment', 'forwardedFromUser']);

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

    /** Время в списке чатов как в Telegram. */
    public function formatChatListTime(mixed $at): string
    {
        if (!$at) {
            return '';
        }

        try {
            $dt = \Illuminate\Support\Carbon::parse($at)->timezone(config('app.timezone'));
        } catch (\Throwable) {
            return '';
        }

        if ($dt->isToday()) {
            return $dt->format('H:i');
        }
        if ($dt->isYesterday()) {
            return 'вчера';
        }
        if ($dt->greaterThan(now()->subDays(6)->startOfDay())) {
            $map = [1 => 'пн', 2 => 'вт', 3 => 'ср', 4 => 'чт', 5 => 'пт', 6 => 'сб', 7 => 'вс'];

            return $map[(int) $dt->dayOfWeekIso] ?? $dt->format('d.m');
        }
        if ((int) $dt->year === (int) now()->year) {
            return $dt->format('d.m');
        }

        return $dt->format('d.m.y');
    }

    public function updateChat(Chat $chat, User $actor, array $data): Chat
    {
        $this->assertCanManageGroup($chat, $actor);

        $chat->fill([
            'title' => $data['title'] ?? $chat->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $chat->description,
            'avatar_path' => array_key_exists('avatar_path', $data) ? $data['avatar_path'] : $chat->avatar_path,
        ])->save();

        $this->postSystem($chat, $actor, "{$actor->displayName()} обновил(а) настройки чата");

        return $chat->fresh(['members']);
    }

    public function uploadChatAvatar(Chat $chat, User $actor, \Illuminate\Http\UploadedFile $file): Chat
    {
        $this->assertCanManageGroup($chat, $actor);

        $path = $file->store('chat-avatars', 'public');
        $publicPath = '/storage/'.$path;

        if ($chat->avatar_path && str_starts_with((string) $chat->avatar_path, '/storage/chat-avatars/')) {
            $relative = preg_replace('#^/storage/#', '', (string) $chat->avatar_path);
            if ($relative && \Illuminate\Support\Facades\Storage::disk('public')->exists($relative)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($relative);
            }
        }

        $chat->avatar_path = $publicPath;
        $chat->save();
        $this->postSystem($chat, $actor, "{$actor->displayName()} обновил(а) фото чата");

        return $chat->fresh(['members']);
    }

    public function deleteGroup(Chat $chat, User $actor): void
    {
        $this->assertCanManageGroup($chat, $actor);

        DB::transaction(function () use ($chat) {
            $chat->loadMissing(['messages.attachment']);

            foreach ($chat->messages as $message) {
                foreach ($message->attachment as $file) {
                    $file->delete();
                }
            }

            $chat->delete();
        });
    }

    /**
     * @param  list<int>  $memberIds
     */
    public function addMembers(Chat $chat, User $actor, array $memberIds): Chat
    {
        $this->assertCanManageGroup($chat, $actor);

        $ids = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn ($id) => $id > 0 && $this->isChatMemberUserId($id))
            ->reject(fn ($id) => $chat->isMember($id))
            ->values();

        $botIds = User::query()->whereIn('id', $ids)->where('is_bot', true)->pluck('id');
        if ($botIds->isNotEmpty() && ! app(BotService::class)->canAddBotsToChats($actor)) {
            abort(403, 'Нет права добавлять ботов в чаты');
        }

        foreach ($ids as $id) {
            $chat->members()->attach($id, [
                'role' => 'member',
                'last_read_at' => now(),
                'is_muted' => false,
                'is_pinned' => false,
            ]);
        }

        if ($ids->isNotEmpty()) {
            $names = User::query()->whereIn('id', $ids)->with('bot')->get()
                ->map(fn (User $u) => $u->displayName())
                ->filter()
                ->implode(', ');
            $this->postSystem(
                $chat,
                $actor,
                $names !== ''
                    ? "{$actor->displayName()} добавил(а): {$names}"
                    : "{$actor->displayName()} добавил(а) участников"
            );

            foreach ($botIds as $botUserId) {
                $bot = Bot::query()->where('user_id', $botUserId)->first();
                if ($bot) {
                    app(BotService::class)->pushUpdate($bot, 'my_chat_member', [
                        'chat' => app(BotService::class)->chatPayload($chat, $bot->user),
                        'from' => [
                            'id' => (int) $actor->id,
                            'is_bot' => false,
                            'first_name' => $actor->name,
                        ],
                        'new_chat_member' => [
                            'user' => app(BotService::class)->botUserPayload($bot),
                            'status' => 'member',
                        ],
                    ]);
                }
            }
        }

        return $chat->fresh(['members']);
    }

    public function removeMember(Chat $chat, User $actor, int $memberId): Chat
    {
        $this->assertCanManageGroup($chat, $actor);

        if ($chat->type === 'direct') {
            abort(422, 'В личном чате состав нельзя менять');
        }

        $member = $chat->members()->where('users.id', $memberId)->first();
        if (!$member) {
            abort(404, 'Участник не найден');
        }
        if (($member->pivot->role ?? '') === 'owner' || (int) $memberId === (int) $chat->created_by) {
            abort(422, 'Владельца нельзя удалить из чата');
        }

        $chat->members()->detach($memberId);
        $this->postSystem(
            $chat,
            $actor,
            "{$actor->displayName()} удалил(а) {$member->displayName()} из чата"
        );

        return $chat->fresh(['members']);
    }

    public function canManageGroup(Chat $chat, User $actor): bool
    {
        if ($chat->type === 'direct') {
            return false;
        }

        return $chat->isOwner($actor->id) || $actor->hasAccess('platform.systems.chats.create');
    }

    protected function assertCanManageGroup(Chat $chat, User $actor): void
    {
        if ($chat->type === 'direct') {
            abort(422, 'Личный чат нельзя редактировать');
        }

        if (!$this->canManageGroup($chat, $actor)) {
            abort(403);
        }
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
        $this->assertCanManageGroup($chat, $actor);

        if ($chat->type === 'direct') {
            abort(422, 'В личном чате состав нельзя менять');
        }

        $ids = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->push($actor->id)
            ->unique()
            ->filter(fn ($id) => $this->isChatMemberUserId($id))
            ->values();

        // Владелец всегда остаётся
        $ownerIds = $chat->members()
            ->wherePivot('role', 'owner')
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->push((int) $chat->created_by)
            ->unique();
        $ids = $ids->merge($ownerIds)->unique()->values();

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
        $this->clearTyping($chat, $actor);

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
            if (!$uploaded || !$uploaded->isValid()) {
                $code = $uploaded?->getError();
                if (in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                    abort(422, 'Голосовое слишком большое для сервера (увеличьте upload_max_filesize в PHP до 16M).');
                }
                abort(422, 'Не удалось загрузить голосовое. Проверьте микрофон и попробуйте ещё раз.');
            }

            $mime = strtolower((string) $uploaded->getMimeType());
            $ext = strtolower((string) $uploaded->getClientOriginalExtension());
            $allowedExt = ['webm', 'ogg', 'oga', 'mp3', 'm4a', 'mp4', 'wav', 'aac', 'opus'];
            $okMime = str_starts_with($mime, 'audio/')
                || $mime === 'video/mp4'
                || $mime === 'video/webm'
                || $mime === 'application/octet-stream';
            if (!$okMime && !in_array($ext, $allowedExt, true)) {
                abort(422, 'Голосовое сообщение должно быть аудиофайлом');
            }
            // WAV 16 kHz / 3 мин ≈ 6 МБ
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
            if ($mime === '' || $mime === 'application/octet-stream' || $mime === 'video/webm') {
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
        } elseif ((int) $request->input('message.voice_duration', 0) > 0) {
            // Клиент думал, что отправил голос, но PHP отбросил файл (часто из‑за upload_max_filesize)
            abort(422, 'Голосовое не дошло до сервера. Частая причина — лимит PHP upload_max_filesize (нужно ≥ 8–16M).');
        }

        if ($request->hasFile('message_files')) {
            $count = 0;
            foreach ((array) $request->file('message_files') as $uploaded) {
                if ($count >= 10) {
                    break;
                }
                if (!$uploaded || !$uploaded->isValid()) {
                    continue;
                }
                $file = new \Orchid\Attachment\File($uploaded, 'public');
                $attachment = $file->load();
                $attachmentIds->push($attachment->id);
                $count++;
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

        try {
            app(BotService::class)->dispatchMessageToBots($chat, $message, $actor);
        } catch (\Throwable) {
            // боты не должны ломать отправку сообщений
        }

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

    public function markRead(Chat $chat, User $user, ?\Carbon\CarbonInterface $at = null): void
    {
        if (!$chat->isMember($user->id)) {
            return;
        }

        $at = $at ?: now();
        $current = $chat->members()->where('users.id', $user->id)->first()?->pivot?->last_read_at;
        // Не откатываем курсор прочтения назад
        if ($current && $at->lt($current)) {
            return;
        }

        $chat->members()->updateExistingPivot($user->id, [
            'last_read_at' => $at,
        ]);
    }

    /**
     * Пометить прочитанным до конкретного сообщения (включительно), как в Telegram.
     */
    public function markReadUpTo(Chat $chat, User $user, int $messageId): void
    {
        if (!$chat->isMember($user->id)) {
            abort(403);
        }

        $message = ChatMessage::query()
            ->where('chat_id', $chat->id)
            ->whereKey($messageId)
            ->first();

        if (!$message || !$message->created_at) {
            return;
        }

        $this->markRead($chat, $user, $message->created_at);
    }

    /**
     * Первое непрочитанное чужое сообщение (для divider и скролла).
     */
    public function firstUnreadMessageId(Chat $chat, User $user): ?int
    {
        if (!$chat->isMember($user->id)) {
            return null;
        }

        $lastReadAt = $chat->members->firstWhere('id', $user->id)?->pivot?->last_read_at
            ?? $chat->members()->where('users.id', $user->id)->first()?->pivot?->last_read_at;

        $id = ChatMessage::query()
            ->where('chat_id', $chat->id)
            ->visibleTo($user)
            ->where('user_id', '!=', $user->id)
            ->where('is_system', false)
            ->where('created_at', '>', $lastReadAt ?: '1970-01-01 00:00:00')
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
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
        $preview = \Illuminate\Support\Str::limit($message->plain_text, 140);
        // Только автор + текст. Название чата не дублируем: в личке это имя получателя («это я»).
        $body = "{$actor->displayName()}: {$preview}";
        $url = route('platform.systems.chats.view', $chat) . '?msg=' . (int) $message->id;
        $meta = [
            'message_id' => (int) $message->id,
            'chat_id' => (int) $chat->id,
        ];

        $recipients = $chat->members
            ->reject(fn (User $u) => (int) $u->id === (int) $actor->id)
            ->reject(fn (User $u) => (bool) $u->is_bot)
            ->reject(fn (User $u) => (bool) ($u->pivot?->is_muted ?? false));

        // Упоминания — всегда (даже если чат замьючен), как в Telegram/Bitrix
        if ($mentionIds !== []) {
            $mentioned = $chat->members
                ->reject(fn (User $u) => (int) $u->id === (int) $actor->id)
                ->reject(fn (User $u) => (bool) $u->is_bot)
                ->filter(fn (User $u) => in_array((int) $u->id, $mentionIds, true));

            foreach ($mentioned as $user) {
                $this->notifier->send($user, 'Вас упомянули в чате', $body, $url, Color::INFO, $meta);
            }

            // Остальным незамьюченным — обычное «новое сообщение»
            $mentionedIds = $mentioned->pluck('id')->all();
            foreach ($recipients->reject(fn (User $u) => in_array((int) $u->id, $mentionedIds, true)) as $user) {
                $this->notifier->send($user, 'Новое сообщение в чате', $body, $url, Color::INFO, $meta);
            }

            return;
        }

        foreach ($recipients as $user) {
            $this->notifier->send($user, 'Новое сообщение в чате', $body, $url, Color::INFO, $meta);
        }
    }

    /**
     * Удаление сообщений как в Telegram/VK.
     * scope=me — скрыть у себя; scope=everyone — soft-delete (только свои сообщения).
     *
     * @param  list<int>  $messageIds
     * @return array{deleted_ids: list<int>, scope: string}
     */
    public function deleteMessages(Chat $chat, User $actor, array $messageIds, string $scope): array
    {
        if (!$chat->isMember($actor->id)) {
            abort(403);
        }

        $scope = $scope === 'everyone' ? 'everyone' : 'me';
        $ids = collect($messageIds)->map(fn ($id) => (int) $id)->filter()->unique()->take(20)->values();
        if ($ids->isEmpty()) {
            return ['deleted_ids' => [], 'scope' => $scope];
        }

        $messages = ChatMessage::query()
            ->where('chat_id', $chat->id)
            ->whereIn('id', $ids)
            ->where('is_system', false)
            ->get();

        $deletedIds = [];

        foreach ($messages as $message) {
            if ($scope === 'everyone') {
                if ((int) $message->user_id !== (int) $actor->id) {
                    continue;
                }
                $message->deleted_by = $actor->id;
                $message->save();
                $message->delete();
                $deletedIds[] = (int) $message->id;
                continue;
            }

            ChatMessageHide::query()->firstOrCreate([
                'chat_message_id' => $message->id,
                'user_id' => $actor->id,
            ]);
            $deletedIds[] = (int) $message->id;
        }

        if ($deletedIds !== []) {
            $chat->touch();
            // Удаление «у всех» — убираем связанные уведомления из колокольчика
            if ($scope === 'everyone') {
                $this->notifier->deleteForChatMessages($deletedIds);
            }
        }

        return ['deleted_ids' => $deletedIds, 'scope' => $scope];
    }

    /**
     * ID сообщений, которые нужно убрать из UI у пользователя (удалены у всех / скрыты у себя).
     *
     * @return list<int>
     */
    public function removedMessageIds(User $user, ?int $chatId, ?\Carbon\CarbonInterface $since = null): array
    {
        if (!$chatId) {
            return [];
        }

        $since = $since ?: now()->subMinutes(3);

        $trashed = ChatMessage::onlyTrashed()
            ->where('chat_id', $chatId)
            ->where('deleted_at', '>=', $since)
            ->pluck('id')
            ->all();

        $hidden = ChatMessageHide::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->whereHas('message', fn ($q) => $q->withTrashed()->where('chat_id', $chatId))
            ->pluck('chat_message_id')
            ->all();

        return array_values(array_unique(array_map('intval', array_merge($trashed, $hidden))));
    }

    private function isChatMemberUserId(int $userId): bool
    {
        $user = User::query()->whereKey($userId)->first();
        if (! $user) {
            return false;
        }

        if ($user->is_bot) {
            return Bot::query()
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->where('can_join_groups', true)
                ->exists();
        }

        return $user->roles()
            ->whereIn('slug', RoleCatalog::CHAT_MEMBER_SLUGS)
            ->exists();
    }
}
