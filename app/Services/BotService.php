<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bot;
use App\Models\BotUpdate;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Orchid\Attachment\File;

class BotService
{
    public function __construct(
        private readonly CommentService $comments,
        private readonly ChatService $chats,
    ) {}

    public function canManageBots(?User $user): bool
    {
        return (bool) ($user?->hasAccess('platform.systems.bots'));
    }

    public function canAddBotsToChats(?User $user): bool
    {
        return (bool) ($user?->hasAccess('platform.systems.bots')
            || $user?->hasAccess('platform.systems.chats.create'));
    }

    /**
     * @return array{bot: Bot, token: string}
     */
    public function create(User $actor, array $data): array
    {
        if (! $this->canManageBots($actor)) {
            abort(403);
        }

        $username = $this->normalizeUsername((string) ($data['username'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Укажите имя бота']);
        }

        return DB::transaction(function () use ($actor, $data, $username, $name) {
            $botUser = User::query()->create([
                'name' => $name,
                'email' => 'bot_'.$username.'_'.Str::lower(Str::random(6)).'@bots.local',
                'password' => Hash::make(Str::random(48)),
                'position' => 'бот',
                'is_bot' => true,
                'permissions' => [
                    'platform.systems.chats' => true,
                ],
            ]);

            [$token, $hash, $hint] = $this->mintToken();

            $bot = Bot::query()->create([
                'user_id' => $botUser->id,
                'name' => $name,
                'username' => $username,
                'description' => $data['description'] ?? null,
                'token_hash' => $hash,
                'token_hint' => $hint,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'can_join_groups' => (bool) ($data['can_join_groups'] ?? true),
                'can_read_messages' => (bool) ($data['can_read_messages'] ?? true),
                'commands' => $data['commands'] ?? null,
                'settings' => $data['settings'] ?? null,
                'created_by' => $actor->id,
            ]);

            return ['bot' => $bot->fresh(['user']), 'token' => $token];
        });
    }

    public function update(Bot $bot, User $actor, array $data): Bot
    {
        if (! $this->canManageBots($actor)) {
            abort(403);
        }

        if (isset($data['name'])) {
            $bot->name = trim((string) $data['name']);
            $bot->user?->forceFill([
                'name' => $bot->name,
                'position' => 'бот',
            ])->save();
        }

        if (array_key_exists('description', $data)) {
            $bot->description = $data['description'];
        }
        if (array_key_exists('is_active', $data)) {
            $bot->is_active = (bool) $data['is_active'];
        }
        if (array_key_exists('can_join_groups', $data)) {
            $bot->can_join_groups = (bool) $data['can_join_groups'];
        }
        if (array_key_exists('can_read_messages', $data)) {
            $bot->can_read_messages = (bool) $data['can_read_messages'];
        }
        if (array_key_exists('commands', $data)) {
            $bot->commands = $data['commands'];
        }
        if (array_key_exists('webhook_url', $data)) {
            $url = trim((string) ($data['webhook_url'] ?? ''));
            $bot->webhook_url = $url !== '' ? $url : null;
            if ($bot->webhook_url) {
                $bot->webhook_error_count = 0;
                $bot->webhook_last_error = null;
                $bot->webhook_last_error_at = null;
            }
        }
        if (array_key_exists('webhook_secret', $data)) {
            $secret = trim((string) ($data['webhook_secret'] ?? ''));
            $bot->webhook_secret = $secret !== '' ? $secret : null;
        }

        $bot->save();

        return $bot->fresh(['user']);
    }

    /**
     * @return array{bot: Bot, token: string}
     */
    public function regenerateToken(Bot $bot, User $actor): array
    {
        if (! $this->canManageBots($actor)) {
            abort(403);
        }

        [$token, $hash, $hint] = $this->mintToken();
        $bot->forceFill([
            'token_hash' => $hash,
            'token_hint' => $hint,
        ])->save();

        return ['bot' => $bot->fresh(), 'token' => $token];
    }

    public function delete(Bot $bot, User $actor): void
    {
        if (! $this->canManageBots($actor)) {
            abort(403);
        }

        DB::transaction(function () use ($bot) {
            $userId = (int) $bot->user_id;
            $bot->delete();
            User::query()->whereKey($userId)->where('is_bot', true)->delete();
        });
    }

    public function findByToken(string $token): ?Bot
    {
        $token = trim($token);
        if ($token === '' || ! str_contains($token, ':')) {
            return null;
        }

        $hash = hash('sha256', $token);

        return Bot::query()
            ->with('user')
            ->where('token_hash', $hash)
            ->where('is_active', true)
            ->first();
    }

    public function botUserPayload(Bot $bot): array
    {
        $user = $bot->user;

        return [
            'id' => (int) $user->id,
            'is_bot' => true,
            'first_name' => $bot->name,
            'username' => $bot->username,
            'can_join_groups' => (bool) $bot->can_join_groups,
            'can_read_all_group_messages' => (bool) $bot->can_read_messages,
        ];
    }

    public function chatPayload(Chat $chat, ?User $viewer = null): array
    {
        return [
            'id' => (int) $chat->id,
            'type' => $chat->type === 'direct' ? 'private' : 'group',
            'title' => $chat->displayTitle($viewer?->id),
            'description' => $chat->description,
        ];
    }

    public function messagePayload(ChatMessage $message, Chat $chat): array
    {
        $message->loadMissing(['user', 'attachment', 'parent.user']);

        $from = $message->user;
        $text = (string) ($message->plain_text ?? '');

        $payload = [
            'message_id' => (int) $message->id,
            'date' => $message->created_at?->timestamp,
            'chat' => $this->chatPayload($chat, $from),
            'from' => $from ? [
                'id' => (int) $from->id,
                'is_bot' => (bool) $from->is_bot,
                'first_name' => $from->name,
                'username' => $from->is_bot
                    ? (string) (Bot::query()->where('user_id', $from->id)->value('username') ?? '')
                    : null,
            ] : null,
            'text' => $text,
        ];

        if ($message->parent_id && $message->parent) {
            $payload['reply_to_message'] = [
                'message_id' => (int) $message->parent_id,
                'text' => (string) ($message->parent->plain_text ?? ''),
            ];
        }

        $files = [];
        foreach ($message->attachment ?? [] as $file) {
            $files[] = [
                'file_id' => (string) $file->id,
                'file_name' => (string) ($file->original_name ?? 'file'),
                'mime_type' => (string) ($file->mime ?? ''),
                'file_size' => (int) ($file->size() ?? 0),
            ];
        }
        if ($files !== []) {
            $payload['documents'] = $files;
            if (count($files) === 1) {
                $payload['document'] = $files[0];
            }
        }

        return $payload;
    }

    public function sendMessage(Bot $bot, int $chatId, string $text, ?int $replyToMessageId = null, bool $disableNotification = false): ChatMessage
    {
        $chat = $this->assertBotChat($bot, $chatId);
        $actor = $bot->user;
        abort_unless($actor, 500);

        if (trim($text) === '') {
            abort(400, 'text is empty');
        }

        $quill = $this->comments->normalizeQuill($text);

        $message = $chat->messages()->create([
            'user_id' => $actor->id,
            'parent_id' => $replyToMessageId,
            'text' => $quill,
            'plain_text' => trim($text),
            'mentioned_user_ids' => [],
            'is_system' => false,
        ]);

        $chat->touch();

        if (! $disableNotification) {
            // Уведомляем людей через ChatService-совместимый путь
            $chat->loadMissing('members');
            $this->notifyHumans($chat, $actor, $message);
        }

        return $message->fresh(['user', 'attachment']);
    }

    public function sendDocument(Bot $bot, int $chatId, UploadedFile $file, ?string $caption = null): ChatMessage
    {
        $chat = $this->assertBotChat($bot, $chatId);
        $actor = $bot->user;
        abort_unless($actor, 500);

        $attachment = (new File($file, 'public'))->load();
        $plain = trim((string) ($caption ?? '')) ?: ((string) ($attachment->original_name ?? 'Файл'));

        $message = $chat->messages()->create([
            'user_id' => $actor->id,
            'text' => $this->comments->normalizeQuill($plain),
            'plain_text' => $plain,
            'mentioned_user_ids' => [],
            'is_system' => false,
        ]);
        $message->attachment()->syncWithoutDetaching([$attachment->id]);
        $chat->touch();
        $this->notifyHumans($chat, $actor, $message);

        return $message->fresh(['user', 'attachment']);
    }

    public function deleteMessage(Bot $bot, int $chatId, int $messageId): bool
    {
        $chat = $this->assertBotChat($bot, $chatId);
        $message = ChatMessage::query()
            ->where('chat_id', $chat->id)
            ->whereKey($messageId)
            ->where('user_id', $bot->user_id)
            ->first();

        if (! $message) {
            return false;
        }

        $message->deleted_by = $bot->user_id;
        $message->save();
        $message->delete();

        return true;
    }

    public function editMessageText(Bot $bot, int $chatId, int $messageId, string $text): ?ChatMessage
    {
        $chat = $this->assertBotChat($bot, $chatId);
        $message = ChatMessage::query()
            ->where('chat_id', $chat->id)
            ->whereKey($messageId)
            ->where('user_id', $bot->user_id)
            ->first();

        if (! $message) {
            return null;
        }

        $message->forceFill([
            'text' => $this->comments->normalizeQuill($text),
            'plain_text' => trim($text),
        ])->save();

        return $message->fresh(['user', 'attachment']);
    }

    public function leaveChat(Bot $bot, int $chatId): bool
    {
        $chat = Chat::query()->find($chatId);
        if (! $chat || ! $chat->isMember($bot->user_id)) {
            return false;
        }

        $chat->members()->detach($bot->user_id);
        $this->chats->postSystemMessage($chat, $bot->user, $bot->name.' покинул(а) чат');

        return true;
    }

    public function addBotToChat(Chat $chat, Bot $bot, User $actor): Chat
    {
        if (! $this->canAddBotsToChats($actor)) {
            abort(403, 'Нет права добавлять ботов в чаты');
        }
        if ($chat->type === 'direct') {
            abort(422, 'Бота нельзя добавить в личный чат');
        }
        if (! $bot->is_active || ! $bot->can_join_groups) {
            abort(422, 'Бот отключён или не может вступать в группы');
        }
        if ($chat->isMember($bot->user_id)) {
            return $chat->fresh(['members']);
        }

        $chat->members()->attach($bot->user_id, [
            'role' => 'member',
            'last_read_at' => now(),
            'is_muted' => false,
            'is_pinned' => false,
        ]);

        $this->chats->postSystemMessage(
            $chat,
            $actor,
            "{$actor->displayName()} добавил(а) бота {$bot->displayUsername()}"
        );

        $this->pushUpdate($bot, 'my_chat_member', [
            'chat' => $this->chatPayload($chat, $bot->user),
            'from' => [
                'id' => (int) $actor->id,
                'is_bot' => false,
                'first_name' => $actor->name,
            ],
            'new_chat_member' => [
                'user' => $this->botUserPayload($bot),
                'status' => 'member',
            ],
        ]);

        return $chat->fresh(['members']);
    }

    /**
     * Создать отдельный чат «сервис → бот» и сразу добавить бота.
     */
    public function createBotChannel(User $actor, Bot $bot, string $title, ?string $description = null): Chat
    {
        if (! $this->canManageBots($actor)) {
            abort(403);
        }

        $chat = $this->chats->createGroup($actor, $title, [], $description);
        $this->addBotToChat($chat, $bot, $actor);

        return $chat->fresh(['members']);
    }

    public function getUpdates(Bot $bot, int $offset = 0, int $limit = 100, int $timeout = 0): array
    {
        $limit = max(1, min(100, $limit));

        if ($offset > 0) {
            BotUpdate::query()
                ->where('bot_id', $bot->id)
                ->where('id', '<', $offset)
                ->delete();
        }

        $query = BotUpdate::query()
            ->where('bot_id', $bot->id)
            ->when($offset > 0, fn ($q) => $q->where('id', '>=', $offset))
            ->orderBy('id')
            ->limit($limit);

        $rows = $query->get();
        if ($rows->isEmpty() && $timeout > 0) {
            $timeout = min(25, $timeout);
            $deadline = microtime(true) + $timeout;
            while (microtime(true) < $deadline) {
                usleep(400000);
                $rows = $query->get();
                if ($rows->isNotEmpty()) {
                    break;
                }
            }
        }

        return $rows->map(fn (BotUpdate $u) => array_merge(
            ['update_id' => (int) $u->id],
            $u->payload ?? []
        ))->all();
    }

    public function setWebhook(Bot $bot, ?string $url, ?string $secret = null): array
    {
        $url = trim((string) $url);
        if ($url === '') {
            $bot->forceFill([
                'webhook_url' => null,
                'webhook_secret' => null,
                'webhook_error_count' => 0,
                'webhook_last_error' => null,
                'webhook_last_error_at' => null,
            ])->save();

            return ['url' => '', 'has_custom_certificate' => false, 'pending_update_count' => 0];
        }

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with($url, 'http')) {
            abort(400, 'Bad webhook url');
        }

        $bot->forceFill([
            'webhook_url' => $url,
            'webhook_secret' => $secret ?: $bot->webhook_secret,
            'webhook_error_count' => 0,
            'webhook_last_error' => null,
            'webhook_last_error_at' => null,
        ])->save();

        return $this->webhookInfo($bot);
    }

    public function webhookInfo(Bot $bot): array
    {
        return [
            'url' => (string) ($bot->webhook_url ?? ''),
            'has_custom_certificate' => false,
            'pending_update_count' => BotUpdate::query()
                ->where('bot_id', $bot->id)
                ->whereNull('delivered_at')
                ->count(),
            'last_error_date' => $bot->webhook_last_error_at?->timestamp,
            'last_error_message' => $bot->webhook_last_error,
            'max_connections' => 40,
            'ip_address' => null,
        ];
    }

    /**
     * Вызывается из ChatService после нового сообщения человека.
     */
    public function dispatchMessageToBots(Chat $chat, ChatMessage $message, User $actor): void
    {
        if ($actor->is_bot || $message->is_system) {
            return;
        }

        $chat->loadMissing('members');
        $botUserIds = $chat->members
            ->filter(fn (User $u) => (bool) $u->is_bot && (int) $u->id !== (int) $actor->id)
            ->pluck('id')
            ->all();

        if ($botUserIds === []) {
            return;
        }

        $bots = Bot::query()
            ->with('user')
            ->whereIn('user_id', $botUserIds)
            ->where('is_active', true)
            ->where('can_read_messages', true)
            ->get();

        foreach ($bots as $bot) {
            $this->pushUpdate($bot, 'message', [
                'message' => $this->messagePayload($message, $chat),
            ]);
        }
    }

    public function pushUpdate(Bot $bot, string $type, array $payload): BotUpdate
    {
        $body = array_key_exists($type, $payload)
            ? [$type => $payload[$type]]
            : [$type => $payload];

        $update = BotUpdate::query()->create([
            'bot_id' => $bot->id,
            'update_type' => $type,
            'payload' => $body,
        ]);

        if ($bot->webhook_url) {
            $this->deliverWebhook($bot, $update);
        }

        return $update;
    }

    public function deliverWebhook(Bot $bot, BotUpdate $update): void
    {
        if (! $bot->webhook_url) {
            return;
        }

        $body = array_merge(['update_id' => (int) $update->id], $update->payload ?? []);
        $headers = ['Accept' => 'application/json'];
        if ($bot->webhook_secret) {
            $headers['X-Bot-Api-Secret-Token'] = $bot->webhook_secret;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders($headers)
                ->post($bot->webhook_url, $body);

            if ($response->successful()) {
                $update->forceFill(['delivered_at' => now()])->save();
                if ($bot->webhook_error_count > 0) {
                    $bot->forceFill([
                        'webhook_error_count' => 0,
                        'webhook_last_error' => null,
                        'webhook_last_error_at' => null,
                    ])->save();
                }

                return;
            }

            $bot->forceFill([
                'webhook_error_count' => $bot->webhook_error_count + 1,
                'webhook_last_error' => 'HTTP '.$response->status().': '.Str::limit($response->body(), 300),
                'webhook_last_error_at' => now(),
            ])->save();
        } catch (ConnectionException|\Throwable $e) {
            $bot->forceFill([
                'webhook_error_count' => $bot->webhook_error_count + 1,
                'webhook_last_error' => Str::limit($e->getMessage(), 300),
                'webhook_last_error_at' => now(),
            ])->save();
        }
    }

    public function normalizeUsername(string $username): string
    {
        $username = Str::lower(trim($username));
        $username = ltrim($username, '@');
        if (! preg_match('/^[a-z][a-z0-9_]{2,31}bot$/', $username) && ! preg_match('/^[a-z][a-z0-9_]{2,31}$/', $username)) {
            throw ValidationException::withMessages([
                'username' => 'Username: 3–32 символа, латиница/цифры/_, начинается с буквы. Рекомендуется оканчивать на bot',
            ]);
        }

        if (Bot::query()->where('username', $username)->exists()) {
            throw ValidationException::withMessages(['username' => 'Такой username уже занят']);
        }

        return $username;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function mintToken(): array
    {
        $plain = random_int(100000000, 999999999).':'.Str::random(35);
        $hash = hash('sha256', $plain);
        $hint = substr($plain, 0, 8).'…'.substr($plain, -4);

        return [$plain, $hash, $hint];
    }

    private function assertBotChat(Bot $bot, int $chatId): Chat
    {
        $chat = Chat::query()->find($chatId);
        if (! $chat) {
            abort(400, 'Chat not found');
        }
        if (! $chat->isMember($bot->user_id)) {
            abort(403, 'Bot is not a member of the chat');
        }

        return $chat;
    }

    private function notifyHumans(Chat $chat, User $actor, ChatMessage $message): void
    {
        $chat->loadMissing('members');
        $preview = Str::limit($message->plain_text, 140);
        $body = "{$actor->displayName()}: {$preview}";
        $url = route('platform.systems.chats.view', $chat).'?msg='.(int) $message->id;
        $meta = ['message_id' => (int) $message->id, 'chat_id' => (int) $chat->id];

        $notifier = app(DashboardNotifier::class);
        foreach ($chat->members as $user) {
            if ((int) $user->id === (int) $actor->id) {
                continue;
            }
            if ($user->is_bot) {
                continue;
            }
            if ((bool) ($user->pivot?->is_muted ?? false)) {
                continue;
            }
            $notifier->send($user, 'Новое сообщение в чате', $body, $url, \Orchid\Support\Color::INFO, $meta);
        }
    }
}
