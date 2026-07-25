<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\ChatCall;
use App\Models\ChatCallParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CallService
{
    public function __construct(
        private readonly LiveKitTokenService $livekit,
        private readonly ChatService $chats,
    ) {}

    public function isAvailable(): bool
    {
        return $this->livekit->isConfigured();
    }

    public function activeCallForChat(Chat $chat): ?ChatCall
    {
        return ChatCall::query()
            ->where('chat_id', $chat->id)
            ->whereIn('status', [ChatCall::STATUS_RINGING, ChatCall::STATUS_ACTIVE])
            ->latest('id')
            ->first();
    }

    public function canEnd(ChatCall $call, User $actor): bool
    {
        if ((int) $call->started_by === (int) $actor->id) {
            return true;
        }

        $call->loadMissing('chat');

        return $call->chat
            && $call->chat->type === 'group'
            && $call->chat->isOwner($actor->id);
    }

    public function canManageGuests(ChatCall $call, User $actor): bool
    {
        $call->loadMissing('chat');
        if (!$call->chat || $call->chat->type !== 'group' || !$call->isOpen()) {
            return false;
        }

        return (int) $call->started_by === (int) $actor->id
            || $call->chat->isOwner($actor->id)
            || $this->chats->canCreate($actor);
    }

    /**
     * Активные/входящие звонки для пользователя (для poll / баннера).
     *
     * @return list<array<string, mixed>>
     */
    public function openCallsPayload(User $user): array
    {
        $chatIds = $this->chats->chatsFor($user)->pluck('id');
        if ($chatIds->isEmpty()) {
            return [];
        }

        $calls = ChatCall::query()
            ->whereIn('chat_id', $chatIds)
            ->whereIn('status', [ChatCall::STATUS_RINGING, ChatCall::STATUS_ACTIVE])
            ->with(['chat.members', 'starter', 'participants'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return $calls->map(function (ChatCall $call) use ($user) {
            $chat = $call->chat;
            $mine = $call->participants->firstWhere('user_id', $user->id);
            $starter = $call->starter;
            $chatType = $chat?->type ?? 'group';
            $myStatus = $mine?->status ?? ChatCallParticipant::STATUS_INVITED;
            $isMine = (int) $call->started_by === (int) $user->id;

            // Личный: входящий звонок (ring). Группа: только «присоединиться», без звонка всем.
            $ring = $chatType === 'direct'
                && !$isMine
                && $myStatus === ChatCallParticipant::STATUS_INVITED;

            return [
                'id' => (int) $call->id,
                'chat_id' => (int) $call->chat_id,
                'chat_type' => $chatType,
                'chat_title' => $chat?->displayTitle($user->id) ?? 'Чат',
                'status' => $call->status,
                'video' => (bool) $call->video_enabled,
                'started_by' => (int) $call->started_by,
                'starter_name' => $starter?->name ?? $starter?->displayName() ?? 'Участник',
                'starter_avatar' => $starter?->avatarUrl() ?: '',
                'starter_initials' => $starter?->avatarInitials() ?? '?',
                'starter_color' => $starter?->avatarColor() ?? '#64748b',
                'is_mine' => $isMine,
                'my_status' => $myStatus,
                'ring' => $ring,
                'can_end' => $this->canEnd($call, $user),
                'can_manage_guests' => $this->canManageGuests($call, $user),
                'guest_url' => ($call->guest_token && $this->canManageGuests($call, $user))
                    ? route('calls.guest', $call->guest_token)
                    : null,
                'participants' => $call->participants
                    ->where('status', ChatCallParticipant::STATUS_JOINED)
                    ->count(),
                'url' => $chat ? route('platform.systems.chats.view', $chat) : null,
            ];
        })->values()->all();
    }

    public function start(Chat $chat, User $actor, bool $video = true): array
    {
        if (!$chat->isMember($actor->id)) {
            abort(403);
        }
        $this->chats->assertCanWriteInChat($chat, $actor);

        if (!$this->isAvailable()) {
            throw new RuntimeException('Звонки недоступны: настройте LiveKit (LIVEKIT_URL, LIVEKIT_API_KEY, LIVEKIT_API_SECRET).');
        }

        $existing = $this->activeCallForChat($chat);
        if ($existing) {
            return $this->join($existing, $actor);
        }

        $isDirect = $chat->type === 'direct';

        $call = DB::transaction(function () use ($chat, $actor, $video, $isDirect) {
            $call = ChatCall::query()->create([
                'chat_id' => $chat->id,
                'started_by' => $actor->id,
                'room_name' => 'chat-' . $chat->id . '-' . Str::lower(Str::random(10)),
                // Личный — ringing (входящий у собеседника). Группа — сразу active.
                'status' => $isDirect ? ChatCall::STATUS_RINGING : ChatCall::STATUS_ACTIVE,
                'video_enabled' => $video,
                'e2ee_key' => Str::random(32),
                'started_at' => now(),
            ]);

            $chat->loadMissing('members');
            foreach ($chat->members as $member) {
                ChatCallParticipant::query()->create([
                    'chat_call_id' => $call->id,
                    'user_id' => $member->id,
                    'status' => (int) $member->id === (int) $actor->id
                        ? ChatCallParticipant::STATUS_JOINED
                        : ChatCallParticipant::STATUS_INVITED,
                    'joined_at' => (int) $member->id === (int) $actor->id ? now() : null,
                ]);
            }

            return $call;
        });

        $label = $video ? 'видеозвонок' : 'звонок';
        $this->chats->postSystemMessage(
            $chat,
            $actor,
            "{$actor->displayName()} начал(а) {$label}"
        );

        return $this->connectionPayload($call->fresh(['chat', 'participants']), $actor);
    }

    public function join(ChatCall $call, User $actor): array
    {
        $call->loadMissing(['chat.members', 'participants']);
        if (!$call->chat || !$call->chat->isMember($actor->id)) {
            abort(403);
        }
        if (!$call->isOpen()) {
            throw new RuntimeException('Звонок уже завершён');
        }
        if (!$this->isAvailable()) {
            throw new RuntimeException('LiveKit не настроен');
        }

        $participant = $call->participants->firstWhere('user_id', $actor->id);
        if (!$participant) {
            $participant = ChatCallParticipant::query()->create([
                'chat_call_id' => $call->id,
                'user_id' => $actor->id,
                'status' => ChatCallParticipant::STATUS_INVITED,
            ]);
        }

        $participant->status = ChatCallParticipant::STATUS_JOINED;
        $participant->joined_at = $participant->joined_at ?? now();
        $participant->left_at = null;
        $participant->save();

        if ($call->status === ChatCall::STATUS_RINGING) {
            $call->status = ChatCall::STATUS_ACTIVE;
            $call->save();
        }

        return $this->connectionPayload($call->fresh(['chat', 'participants']), $actor);
    }

    public function leave(ChatCall $call, User $actor): void
    {
        $call->loadMissing('participants');
        $participant = $call->participants->firstWhere('user_id', $actor->id);
        if ($participant) {
            $participant->status = ChatCallParticipant::STATUS_LEFT;
            $participant->left_at = now();
            $participant->save();
        }

        $joinedLeft = $call->participants()
            ->where('status', ChatCallParticipant::STATUS_JOINED)
            ->count();

        if ($joinedLeft === 0) {
            $this->end($call, $actor, force: true);
        }
    }

    public function decline(ChatCall $call, User $actor): void
    {
        $call->loadMissing('participants');
        $participant = $call->participants->firstWhere('user_id', $actor->id);
        if ($participant) {
            $participant->status = ChatCallParticipant::STATUS_DECLINED;
            $participant->left_at = now();
            $participant->save();
        }

        // В личном: если отклонили — завершаем звонок
        $call->loadMissing('chat');
        if ($call->chat?->type === 'direct' && $call->isOpen()) {
            $this->end($call, $actor, force: true);
        }
    }

    public function end(ChatCall $call, User $actor, bool $force = false): void
    {
        $call->loadMissing('chat');
        if (!$force && !$this->canEnd($call, $actor)) {
            abort(403, 'Завершить звонок для всех может инициатор или владелец группы');
        }

        if ($call->status === ChatCall::STATUS_ENDED) {
            return;
        }

        $call->status = ChatCall::STATUS_ENDED;
        $call->ended_at = now();
        $call->guest_token = null;
        $call->save();

        ChatCallParticipant::query()
            ->where('chat_call_id', $call->id)
            ->where('status', ChatCallParticipant::STATUS_JOINED)
            ->update([
                'status' => ChatCallParticipant::STATUS_LEFT,
                'left_at' => now(),
            ]);

        if ($call->chat) {
            $this->chats->postSystemMessage($call->chat, $actor, 'Звонок завершён');
        }
    }

    /**
     * Создать / обновить гостевую ссылку (только групповые звонки).
     *
     * @return array{guest_url: string, guest_token: string}
     */
    public function enableGuestLink(ChatCall $call, User $actor): array
    {
        if (!$this->canManageGuests($call, $actor)) {
            abort(403, 'Гостевую ссылку может создать владелец группы или инициатор звонка');
        }
        if (!$call->isOpen()) {
            throw new RuntimeException('Звонок уже завершён');
        }

        if (!$call->guest_token) {
            $call->guest_token = Str::lower(Str::random(40));
            $call->save();
        }

        return [
            'guest_token' => $call->guest_token,
            'guest_url' => route('calls.guest', $call->guest_token),
        ];
    }

    public function revokeGuestLink(ChatCall $call, User $actor): void
    {
        if (!$this->canManageGuests($call, $actor)) {
            abort(403);
        }
        $call->guest_token = null;
        $call->save();
    }

    public function findOpenByGuestToken(string $token): ?ChatCall
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return ChatCall::query()
            ->where('guest_token', $token)
            ->whereIn('status', [ChatCall::STATUS_RINGING, ChatCall::STATUS_ACTIVE])
            ->with('chat')
            ->first();
    }

    /**
     * Гость входит в комнату по ссылке (без аккаунта).
     *
     * @return array<string, mixed>
     */
    public function joinAsGuest(string $token, string $name, bool $video = false): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('Звонки недоступны');
        }

        $call = $this->findOpenByGuestToken($token);
        if (!$call) {
            throw new RuntimeException('Ссылка недействительна или звонок завершён');
        }

        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '' || mb_strlen($name) < 2) {
            throw new RuntimeException('Укажите имя (минимум 2 символа)');
        }
        if (mb_strlen($name) > 60) {
            $name = mb_substr($name, 0, 60);
        }

        $identity = 'guest-' . Str::lower(Str::random(12));
        $initials = mb_strtoupper(mb_substr($name, 0, 2));
        $colors = ['#3b82f6', '#6366f1', '#8b5cf6', '#ec4899', '#22c55e', '#14b8a6', '#f97316'];
        $color = $colors[crc32($identity) % count($colors)];

        $tokenJwt = $this->livekit->createAccessToken([
            'room' => $call->room_name,
            'identity' => $identity,
            'name' => $name,
            'metadata' => [
                'guest' => true,
                'name' => $name,
                'avatar' => '',
                'initials' => $initials,
                'color' => $color,
            ],
        ]);

        if ($call->status === ChatCall::STATUS_RINGING) {
            $call->status = ChatCall::STATUS_ACTIVE;
            $call->save();
        }

        return [
            'call_id' => (int) $call->id,
            'chat_id' => (int) $call->chat_id,
            'status' => $call->status,
            'video' => $video,
            'room' => $call->room_name,
            'ws_url' => $this->livekit->wsUrl(),
            'token' => $tokenJwt,
            'is_starter' => false,
            'is_guest' => true,
            'can_end' => false,
            'can_manage_guests' => false,
            'guest_url' => null,
            'chat_type' => $call->chat?->type ?? 'group',
            'me' => [
                'id' => $identity,
                'name' => $name,
                'avatar' => '',
                'initials' => $initials,
                'color' => $color,
            ],
            'roster' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function connectionPayload(ChatCall $call, User $actor): array
    {
        $token = $this->livekit->createAccessToken([
            'room' => $call->room_name,
            'identity' => (string) $actor->id,
            'name' => $actor->name ?: $actor->displayName(),
            'metadata' => [
                'user_id' => (string) $actor->id,
                'name' => $actor->name ?: $actor->displayName(),
                'avatar' => $actor->avatarUrl() ?: '',
                'initials' => $actor->avatarInitials(),
                'color' => $actor->avatarColor(),
            ],
        ]);

        $call->loadMissing(['chat', 'participants.user']);

        $roster = $call->participants
            ->where('status', ChatCallParticipant::STATUS_JOINED)
            ->map(function (ChatCallParticipant $p) {
                $u = $p->user;

                return [
                    'id' => (int) $p->user_id,
                    'name' => $u?->name ?: ($u?->displayName() ?? 'Участник'),
                    'avatar' => $u?->avatarUrl() ?: '',
                    'initials' => $u?->avatarInitials() ?? '?',
                    'color' => $u?->avatarColor() ?? '#64748b',
                ];
            })
            ->values()
            ->all();

        $canGuests = $this->canManageGuests($call, $actor);

        return [
            'call_id' => (int) $call->id,
            'chat_id' => (int) $call->chat_id,
            'chat_type' => $call->chat?->type ?? 'group',
            'status' => $call->status,
            'video' => (bool) $call->video_enabled,
            'room' => $call->room_name,
            'ws_url' => $this->livekit->wsUrl(),
            'token' => $token,
            'is_starter' => (int) $call->started_by === (int) $actor->id,
            'can_end' => $this->canEnd($call, $actor),
            'can_manage_guests' => $canGuests,
            'guest_url' => ($canGuests && $call->guest_token)
                ? route('calls.guest', $call->guest_token)
                : null,
            'me' => [
                'id' => (int) $actor->id,
                'name' => $actor->name ?: $actor->displayName(),
                'avatar' => $actor->avatarUrl() ?: '',
                'initials' => $actor->avatarInitials(),
                'color' => $actor->avatarColor(),
            ],
            'roster' => $roster,
            'e2ee_key' => $call->e2ee_key,
            'encryption' => [
                'media' => 'DTLS-SRTP (стандарт WebRTC / LiveKit)',
                'signaling' => 'TLS (HTTPS + WSS)',
            ],
        ];
    }
}
