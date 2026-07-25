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

            return [
                'id' => (int) $call->id,
                'chat_id' => (int) $call->chat_id,
                'chat_title' => $chat?->displayTitle($user->id) ?? 'Чат',
                'status' => $call->status,
                'video' => (bool) $call->video_enabled,
                'started_by' => (int) $call->started_by,
                'starter_name' => $starter?->name ?? $starter?->displayName() ?? 'Участник',
                'starter_avatar' => $starter?->avatarUrl() ?: '',
                'starter_initials' => $starter?->avatarInitials() ?? '?',
                'starter_color' => $starter?->avatarColor() ?? '#64748b',
                'is_mine' => (int) $call->started_by === (int) $user->id,
                'my_status' => $mine?->status ?? ChatCallParticipant::STATUS_INVITED,
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

        $call = DB::transaction(function () use ($chat, $actor, $video) {
            $call = ChatCall::query()->create([
                'chat_id' => $chat->id,
                'started_by' => $actor->id,
                'room_name' => 'chat-' . $chat->id . '-' . Str::lower(Str::random(10)),
                'status' => ChatCall::STATUS_RINGING,
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

        $call->status = ChatCall::STATUS_ACTIVE;
        $call->save();

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
    }

    public function end(ChatCall $call, User $actor, bool $force = false): void
    {
        $call->loadMissing('chat');
        if (!$force) {
            $isStarter = (int) $call->started_by === (int) $actor->id;
            if (!$isStarter) {
                abort(403, 'Завершить звонок для всех может только инициатор');
            }
        }

        if ($call->status === ChatCall::STATUS_ENDED) {
            return;
        }

        $call->status = ChatCall::STATUS_ENDED;
        $call->ended_at = now();
        $call->save();

        ChatCallParticipant::query()
            ->where('chat_call_id', $call->id)
            ->where('status', ChatCallParticipant::STATUS_JOINED)
            ->update([
                'status' => ChatCallParticipant::STATUS_LEFT,
                'left_at' => now(),
            ]);
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

        $call->loadMissing(['participants.user']);

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

        return [
            'call_id' => (int) $call->id,
            'chat_id' => (int) $call->chat_id,
            'status' => $call->status,
            'video' => (bool) $call->video_enabled,
            'room' => $call->room_name,
            'ws_url' => $this->livekit->wsUrl(),
            'token' => $token,
            'is_starter' => (int) $call->started_by === (int) $actor->id,
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
