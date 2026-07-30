<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use App\Services\Mobile\MobileChatPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chats,
        private readonly MobileChatPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertChats($user);

        $items = $this->chats->chatsFor($user)
            ->map(fn (Chat $chat) => $this->presenter->chatSummary($chat, $user))
            ->values();

        return response()->json(['chats' => $items]);
    }

    public function show(Request $request, Chat $chat): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertChats($user);
        $this->assertMember($chat, $user);

        $this->chats->markRead($chat, $user);
        $feed = $this->chats->feedForChat($chat, $user, null, (int) $request->integer('limit', 40));
        $token = $request->bearerToken();

        $listed = $this->chats->chatsFor($user)->firstWhere('id', $chat->id);
        if (!$listed) {
            $chat->loadMissing(['members', 'latestMessage.user']);
            $chat->setAttribute('unread_count', 0);
            $chat->setAttribute('is_muted', false);
            $chat->setAttribute('is_pinned', false);
            $listed = $chat;
        }

        return response()->json([
            'chat' => $this->presenter->chatSummary($listed, $user),
            'messages' => $feed['messages']
                ->map(fn (ChatMessage $m) => $this->presenter->message($m, $user, $token))
                ->values(),
            'has_more' => (bool) $feed['has_more'],
            'oldest_id' => $feed['oldest_id'],
        ]);
    }

    public function history(Request $request, Chat $chat): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertChats($user);
        $this->assertMember($chat, $user);

        $before = (int) $request->integer('before');
        if ($before <= 0) {
            return response()->json(['message' => 'before required'], 422);
        }

        $limit = max(10, min(100, (int) $request->integer('limit', 40)));
        $batch = ChatMessage::query()
            ->where('chat_id', $chat->id)
            ->visibleTo($user)
            ->where('id', '<', $before)
            ->with(['user', 'parent.user', 'task', 'attachment'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();

        $oldestId = $batch->first()?->id ? (int) $batch->first()->id : null;
        $hasMore = $oldestId
            ? ChatMessage::query()->where('chat_id', $chat->id)->visibleTo($user)->where('id', '<', $oldestId)->exists()
            : false;
        $token = $request->bearerToken();

        return response()->json([
            'messages' => $batch->map(fn (ChatMessage $m) => $this->presenter->message($m, $user, $token))->values(),
            'has_more' => $hasMore,
            'oldest_id' => $oldestId,
        ]);
    }

    public function send(Request $request, Chat $chat): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertChats($user);
        $this->assertMember($chat, $user);

        $message = $this->chats->addMessage($chat, $user, $request);
        $message->load(['user', 'parent.user', 'task', 'attachment']);

        return response()->json([
            'message' => $this->presenter->message($message, $user, $request->bearerToken()),
        ], 201);
    }

    public function delete(Request $request, Chat $chat): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertChats($user);
        $this->assertMember($chat, $user);

        $data = $request->validate([
            'message_ids' => ['required', 'array', 'min:1', 'max:20'],
            'message_ids.*' => ['integer'],
            'scope' => ['required', 'in:me,everyone'],
        ]);

        $result = $this->chats->deleteMessages($chat, $user, $data['message_ids'], $data['scope']);

        return response()->json($result);
    }

    public function poll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertChats($user);

        $since = $request->integer('since') ?: null;
        $activeChatId = $request->integer('chat') ?: null;
        $state = $this->chats->pollState($user, $since ? (int) $since : null, $activeChatId ? (int) $activeChatId : null);
        $token = $request->bearerToken();

        // Replace HTML messages with structured DTOs for the active chat
        if ($activeChatId && $since) {
            $messages = ChatMessage::query()
                ->where('chat_id', (int) $activeChatId)
                ->visibleTo($user)
                ->where('id', '>', (int) $since)
                ->with(['user', 'parent.user', 'task', 'attachment'])
                ->orderBy('id')
                ->limit(50)
                ->get()
                ->map(fn (ChatMessage $m) => $this->presenter->message($m, $user, $token))
                ->values()
                ->all();
            $state['messages'] = $messages;
        } else {
            $state['messages'] = [];
        }

        $state['chats'] = $this->chats->chatsFor($user)
            ->map(fn (Chat $chat) => $this->presenter->chatSummary($chat, $user))
            ->values()
            ->all();

        return response()->json($state);
    }

    public function typing(Request $request, Chat $chat): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertChats($user);
        $this->assertMember($chat, $user);
        $this->chats->markTyping($chat, $user);

        return response()->json(['ok' => true]);
    }

    private function assertChats(User $user): void
    {
        if (!$this->chats->canAccessMessenger($user)) {
            abort(403, 'Нет доступа к чатам');
        }
    }

    private function assertMember(Chat $chat, User $user): void
    {
        $chat->loadMissing('members');
        if (!$chat->isMember($user->id)) {
            abort(403);
        }
    }
}
