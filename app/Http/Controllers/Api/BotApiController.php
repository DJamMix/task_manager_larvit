<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\BotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotApiController extends Controller
{
    public function __construct(private readonly BotService $bots) {}

    public function dispatch(Request $request): JsonResponse
    {
        /** @var Bot $bot */
        $bot = $request->attributes->get('bot');
        $method = ltrim((string) $request->route('method'), '/');

        return match ($method) {
            'getMe' => $this->ok($this->bots->botUserPayload($bot)),
            'getUpdates' => $this->ok($this->bots->getUpdates(
                $bot,
                (int) $request->input('offset', 0),
                (int) $request->input('limit', 100),
                (int) $request->input('timeout', 0),
            )),
            'setWebhook' => $this->ok($this->bots->setWebhook(
                $bot,
                $request->input('url'),
                $request->input('secret_token'),
            )),
            'deleteWebhook' => $this->ok($this->bots->setWebhook($bot, null)),
            'getWebhookInfo' => $this->ok($this->bots->webhookInfo($bot)),
            'sendMessage' => $this->sendMessage($request, $bot),
            'sendDocument', 'sendPhoto' => $this->sendDocument($request, $bot),
            'deleteMessage' => $this->deleteMessage($request, $bot),
            'editMessageText' => $this->editMessageText($request, $bot),
            'getChat' => $this->getChat($request, $bot),
            'getChatMember' => $this->getChatMember($request, $bot),
            'getChatAdministrators' => $this->getChatAdministrators($request, $bot),
            'leaveChat' => $this->ok($this->bots->leaveChat($bot, (int) $request->input('chat_id'))),
            'forwardMessage' => $this->forwardMessage($request, $bot),
            default => response()->json([
                'ok' => false,
                'error_code' => 404,
                'description' => "Unknown method: {$method}",
            ], 404),
        };
    }

    private function sendMessage(Request $request, Bot $bot): JsonResponse
    {
        $chatId = (int) $request->input('chat_id');
        $text = (string) $request->input('text', '');
        $replyTo = $request->filled('reply_to_message_id')
            ? (int) $request->input('reply_to_message_id')
            : null;
        $disable = (bool) $request->boolean('disable_notification');

        $message = $this->bots->sendMessage($bot, $chatId, $text, $replyTo, $disable);
        $chat = Chat::query()->findOrFail($chatId);

        return $this->ok($this->bots->messagePayload($message, $chat));
    }

    private function sendDocument(Request $request, Bot $bot): JsonResponse
    {
        $chatId = (int) $request->input('chat_id');
        $file = $request->file('document')
            ?? $request->file('photo')
            ?? $request->file('file');

        if (! $file || ! $file->isValid()) {
            return $this->fail(400, 'document is required');
        }

        $message = $this->bots->sendDocument(
            $bot,
            $chatId,
            $file,
            $request->input('caption')
        );
        $chat = Chat::query()->findOrFail($chatId);

        return $this->ok($this->bots->messagePayload($message, $chat));
    }

    private function deleteMessage(Request $request, Bot $bot): JsonResponse
    {
        $ok = $this->bots->deleteMessage(
            $bot,
            (int) $request->input('chat_id'),
            (int) $request->input('message_id')
        );

        return $this->ok($ok);
    }

    private function editMessageText(Request $request, Bot $bot): JsonResponse
    {
        $message = $this->bots->editMessageText(
            $bot,
            (int) $request->input('chat_id'),
            (int) $request->input('message_id'),
            (string) $request->input('text', '')
        );

        if (! $message) {
            return $this->fail(400, 'Message not found');
        }

        $chat = Chat::query()->findOrFail((int) $request->input('chat_id'));

        return $this->ok($this->bots->messagePayload($message, $chat));
    }

    private function getChat(Request $request, Bot $bot): JsonResponse
    {
        $chatId = (int) $request->input('chat_id');
        $chat = Chat::query()->find($chatId);
        if (! $chat || ! $chat->isMember($bot->user_id)) {
            return $this->fail(400, 'Chat not found');
        }

        return $this->ok($this->bots->chatPayload($chat, $bot->user));
    }

    private function getChatMember(Request $request, Bot $bot): JsonResponse
    {
        $chatId = (int) $request->input('chat_id');
        $userId = (int) $request->input('user_id');
        $chat = Chat::query()->find($chatId);
        if (! $chat || ! $chat->isMember($bot->user_id)) {
            return $this->fail(400, 'Chat not found');
        }

        $member = $chat->members()->where('users.id', $userId)->first();
        if (! $member) {
            return $this->ok(['status' => 'left', 'user' => ['id' => $userId]]);
        }

        $status = ($member->pivot->role ?? '') === 'owner' ? 'creator' : 'member';

        return $this->ok([
            'status' => $status,
            'user' => [
                'id' => (int) $member->id,
                'is_bot' => (bool) $member->is_bot,
                'first_name' => $member->name,
            ],
        ]);
    }

    private function getChatAdministrators(Request $request, Bot $bot): JsonResponse
    {
        $chatId = (int) $request->input('chat_id');
        $chat = Chat::query()->find($chatId);
        if (! $chat || ! $chat->isMember($bot->user_id)) {
            return $this->fail(400, 'Chat not found');
        }

        $admins = $chat->members()
            ->wherePivot('role', 'owner')
            ->get()
            ->map(fn (User $u) => [
                'status' => 'creator',
                'user' => [
                    'id' => (int) $u->id,
                    'is_bot' => (bool) $u->is_bot,
                    'first_name' => $u->name,
                ],
            ])
            ->values()
            ->all();

        return $this->ok($admins);
    }

    private function forwardMessage(Request $request, Bot $bot): JsonResponse
    {
        $fromChatId = (int) $request->input('from_chat_id');
        $toChatId = (int) $request->input('chat_id');
        $messageId = (int) $request->input('message_id');

        $from = Chat::query()->find($fromChatId);
        $to = Chat::query()->find($toChatId);
        if (! $from || ! $to || ! $from->isMember($bot->user_id) || ! $to->isMember($bot->user_id)) {
            return $this->fail(400, 'Chat not found');
        }

        $src = ChatMessage::query()
            ->where('chat_id', $from->id)
            ->whereKey($messageId)
            ->first();

        if (! $src) {
            return $this->fail(400, 'Message not found');
        }

        $copy = $this->bots->sendMessage(
            $bot,
            $toChatId,
            (string) ($src->plain_text ?: 'Пересланное сообщение'),
            null,
            false
        );

        $copy->forceFill([
            'forwarded_from_message_id' => $src->id,
            'forwarded_from_chat_id' => $from->id,
            'forwarded_from_user_id' => $src->user_id,
        ])->save();

        return $this->ok($this->bots->messagePayload($copy->fresh(['user', 'attachment']), $to));
    }

    private function ok(mixed $result): JsonResponse
    {
        return response()->json(['ok' => true, 'result' => $result]);
    }

    private function fail(int $code, string $description): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error_code' => $code,
            'description' => $description,
        ], $code >= 400 && $code < 600 ? $code : 400);
    }
}
