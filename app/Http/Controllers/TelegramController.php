<?php

namespace App\Http\Controllers;

use App\CoreLayer\Integrations\Ebot\EBot;
use App\Models\User;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function webhook(Request $request)
    {
        $data = $request->input();

        if (!isset($data['message'])) {
            return response()->json(['status' => 'no message'], 200);
        }

        $chatId = $data['message']['chat']['id'];
        $text = $data['message']['text'] ?? '';

        if (preg_match('/^\/start (\w+)$/', $text, $matches)) {
            $verificationCode = $matches[1];

            // Ищем пользователя по коду
            $user = User::where('telegram_verification_code', $verificationCode)->first();

            if ($user) {
                $user->telegram_id = $chatId;
                $user->telegram_verification_code = null; // Удаляем код, чтобы его нельзя было использовать повторно
                $user->save();

                EBot::sendMessage($chatId, "✅ Успешно включены уведомления Telegram!");
            } else {
                EBot::sendMessage($chatId, "⚠ Ошибка! Код привязки не найден или уже использован.");
            }
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
