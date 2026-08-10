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
            'editMessageReplyMarkup' => $this->editMessageReplyMarkup($request, $bot),
            'getChat' => $this->getChat($request, $bot),
            'getChatMember' => $this->getChatMember($request, $bot),
            'getChatAdministrators' => $this->getChatAdministrators($request, $bot),
            'leaveChat' => $this->ok($this->bots->leaveChat($bot, (int) $request->input('chat_id'))),
            'forwardMessage' => $this->forwardMessage($request, $bot),
            'setMyCommands' => $this->ok($this->bots->setMyCommands($bot, $this->commandsInput($request))),
            'getMyCommands' => $this->ok($this->bots->getMyCommands($bot)),
            'deleteMyCommands' => $this->ok($this->bots->setMyCommands($bot, [])),
            'answerCallbackQuery' => $this->ok($this->bots->answerCallbackQuery(
                $bot,
                (string) $request->input('callback_query_id', ''),
                $request->input('text'),
                (bool) $request->boolean('show_alert'),
            )),
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
        $parseMode = $request->input('parse_mode');

        $message = $this->bots->sendMessage(
            $bot,
            $chatId,
            (string) $request->input('text', ''),
            $request->filled('reply_to_message_id') ? (int) $request->input('reply_to_message_id') : null,
            (bool) $request->boolean('disable_notification'),
            is_string($parseMode) ? $parseMode : null,
            $request->input('reply_markup'),
        );

        return $this->ok($this->bots->messagePayload($message, Chat::query()->findOrFail($chatId)));
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

        $parseMode = $request->input('parse_mode');
        $message = $this->bots->sendDocument(
            $bot,
            $chatId,
            $file,
            $request->input('caption'),
            is_string($parseMode) ? $parseMode : null,
            $request->input('reply_markup'),
        );

        return $this->ok($this->bots->messagePayload($message, Chat::query()->findOrFail($chatId)));
    }

    private function deleteMessage(Request $request, Bot $bot): JsonResponse
    {
        return $this->ok($this->bots->deleteMessage(
            $bot,
            (int) $request->input('chat_id'),
            (int) $request->input('message_id')
        ));
    }

    private function editMessageText(Request $request, Bot $bot): JsonResponse
    {
        $parseMode = $request->input('parse_mode');
        $message = $this->bots->editMessageText(
            $bot,
            (int) $request->input('chat_id'),
            (int) $request->input('message_id'),
            (string) $request->input('text', ''),
            is_string($parseMode) ? $parseMode : null,
            $request->has('reply_markup') ? $request->input('reply_markup') : null,
        );

        if (! $message) {
            return $this->fail(400, 'Message not found');
        }

        return $this->ok($this->bots->messagePayload(
            $message,
            Chat::query()->findOrFail((int) $request->input('chat_id'))
        ));
    }

    private function editMessageReplyMarkup(Request $request, Bot $bot): JsonResponse
    {
        $chatId = (int) $request->input('chat_id');
        $message = ChatMessage::query()
            ->where('chat_id', $chatId)
            ->whereKey((int) $request->input('message_id'))
            ->where('user_id', $bot->user_id)
            ->first();

        if (! $message) {
            return $this->fail(400, 'Message not found');
        }

        $meta = is_array($message->bot_meta) ? $message->bot_meta : [];
        $meta['reply_markup'] = $this->bots->normalizeReplyMarkup($request->input('reply_markup'));
        $message->forceFill(['bot_meta' => $meta])->save();

        return $this->ok($this->bots->messagePayload(
            $message->fresh(['user', 'attachment']),
            Chat::query()->findOrFail($chatId)
        ));
    }

    private function getChat(Request $request, Bot $bot): JsonResponse
    {
        $chat = Chat::query()->find((int) $request->input('chat_id'));
        if (! $chat || ! $chat->isMember($bot->user_id)) {
            return $this->fail(400, 'Chat not found');
        }

        return $this->ok($this->bots->chatPayload($chat, $bot->user));
    }

    private function getChatMember(Request $request, Bot $bot): JsonResponse
    {
        $chat = Chat::query()->find((int) $request->input('chat_id'));
        $userId = (int) $request->input('user_id');
        if (! $chat || ! $chat->isMember($bot->user_id)) {
            return $this->fail(400, 'Chat not found');
        }

        $member = $chat->members()->where('users.id', $userId)->first();
        if (! $member) {
            return $this->ok(['status' => 'left', 'user' => ['id' => $userId]]);
        }

        return $this->ok([
            'status' => ($member->pivot->role ?? '') === 'owner' ? 'creator' : 'member',
            'user' => [
                'id' => (int) $member->id,
                'is_bot' => (bool) $member->is_bot,
                'first_name' => $member->name,
            ],
        ]);
    }

    private function getChatAdministrators(Request $request, Bot $bot): JsonResponse
    {
        $chat = Chat::query()->find((int) $request->input('chat_id'));
        if (! $chat || ! $chat->isMember($bot->user_id)) {
            return $this->fail(400, 'Chat not found');
        }

        return $this->ok(
            $chat->members()
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
                ->all()
        );
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

    private function commandsInput(Request $request): array
    {
        $commands = $request->input('commands', []);
        if (is_string($commands)) {
            $decoded = json_decode($commands, true);
            $commands = is_array($decoded) ? $decoded : [];
        }

        return is_array($commands) ? $commands : [];
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
