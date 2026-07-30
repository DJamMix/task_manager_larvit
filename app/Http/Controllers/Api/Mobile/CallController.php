<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatCall;
use App\Models\User;
use App\Services\CallService;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function __construct(
        private readonly CallService $calls,
        private readonly ChatService $chats,
    ) {}

    public function start(Request $request, Chat $chat): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assert($user, $chat);

        if (!$this->calls->isAvailable()) {
            return response()->json(['message' => 'Звонки не настроены (LiveKit)'], 503);
        }

        $video = $request->boolean('video', true);
        $payload = $this->calls->start($chat, $user, $video);

        return response()->json($payload);
    }

    public function join(Request $request, ChatCall $call): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $call->loadMissing('chat.members');
        if (!$call->chat || !$call->chat->isMember($user->id)) {
            abort(403);
        }
        if (!$this->chats->canAccessMessenger($user)) {
            abort(403);
        }

        return response()->json($this->calls->join($call, $user));
    }

    public function leave(Request $request, ChatCall $call): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->calls->leave($call, $user);

        return response()->json(['ok' => true]);
    }

    public function end(Request $request, ChatCall $call): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->calls->end($call, $user);

        return response()->json(['ok' => true]);
    }

    private function assert(User $user, Chat $chat): void
    {
        if (!$this->chats->canAccessMessenger($user)) {
            abort(403);
        }
        $chat->loadMissing('members');
        if (!$chat->isMember($user->id)) {
            abort(403);
        }
    }
}
