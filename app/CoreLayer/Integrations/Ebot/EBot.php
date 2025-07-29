<?php

namespace App\CoreLayer\Integrations\Ebot;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Класс для работы с Telegram Bot API от Дяди Вити
 */
class EBot
{
    /**
     * Отправка сообщения в Telegram чат
     *
     * @param int|string $chatId ID чата или username (например, @channelname)
     * @param string $text Текст сообщения
     * @param int|string|null $messageThreadId ID темы в форумном чате (опционально)
     * @param string|null $parseMode Режим форматирования (Markdown, HTML)
     * @param array|string|null $replyMarkup Клавиатура или inline-кнопки
     * @param string|null $token Кастомный токен бота (если не используется из конфига)
     * @return Response
     */
    public static function sendMessage($chatId, $text, $messageThreadId = null, string $parseMode = null, $replyMarkup = null, string $token = null)
    {
        $token = $token ?? config('ebot.bot_token.auth_bot');
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if($messageThreadId !== null) {
            $data['message_thread_id'] = $messageThreadId;
        }

        if($parseMode !== null) {
            $data['parse_mode'] = $parseMode;
        }

        if($replyMarkup !== null) {
            $data['reply_markup'] = $replyMarkup;
        }

        return Http::post($url, $data);
    }

    /**
     * Установка вебхука для бота
     *
     * @param string $url URL вебхука
     * @param array|null $allowedUpdates Список типов обновлений для получения
     * @param string|null $token Кастомный токен бота
     * @return Response
     */
    public static function setWebhook(string $url, string $token)
    {
        $token = $token ?? config('ebot.bot_token.auth_bot');
        $endpoint = "https://api.telegram.org/bot{$token}/setWebhook";

        $response = Http::post($endpoint, [
            'url' => $url,
        ]);

        return $response->json();
    }

    /**
     * Редактирование клавиатуры сообщения
     *
     * @param int|string $chatId ID чата
     * @param int $messageId ID сообщения для редактирования
     * @param array|string|null $replyMarkup Новая клавиатура (null для удаления)
     * @param string|null $token Кастомный токен бота
     * @return Response
     */
    public static function editMessageReplyMarkup(
        $chatId,
        $messageId,
        $replyMarkup = null,
        string $token = null
    ): Response
    {
        $token = $token ?? config('ebot.bot_token.auth_bot');
        $url = "https://api.telegram.org/bot{$token}/editMessageReplyMarkup";

        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];

        if ($replyMarkup !== null) {
            $data['reply_markup'] = is_array($replyMarkup) ? json_encode($replyMarkup) : $replyMarkup;
        }

        return Http::post($url, $data);
    }

    /**
     * Ответ на callback-запрос от inline-кнопки
     *
     * @param string $callbackQueryId ID callback-запроса
     * @param string|null $text Текст ответа (опционально)
     * @param bool $showAlert Показать как alert (по умолчанию false)
     * @param string|null $url URL для открытия (опционально)
     * @param int|null $cacheTime Время кеширования в секундах (опционально)
     * @param string|null $token Кастомный токен бота
     * @return Response
     */
    public static function answerCallbackQuery(
        string $callbackQueryId,
        string $text = null,
        bool $showAlert = false,
        string $url = null,
        int $cacheTime = null,
        string $token = null
    ): Response
    {
        $token = $token ?? config('ebot.bot_token.auth_bot');
        $endpoint = "https://api.telegram.org/bot{$token}/answerCallbackQuery";

        $data = ['callback_query_id' => $callbackQueryId];
        
        if ($text !== null) {
            $data['text'] = $text;
        }
        
        if ($showAlert) {
            $data['show_alert'] = $showAlert;
        }
        
        if ($url !== null) {
            $data['url'] = $url;
        }
        
        if ($cacheTime !== null) {
            $data['cache_time'] = $cacheTime;
        }

        return Http::post($endpoint, $data);
    }

    /**
     * Удаление вебхука бота
     *
     * @param bool $dropPendingUpdates Удалить pending updates (по умолчанию false)
     * @param string|null $token Кастомный токен бота
     * @return Response
     */
    public static function deleteWebhook(
        bool $dropPendingUpdates = false,
        string $token = null
    ): Response
    {
        $token = $token ?? config('ebot.bot_token.auth_bot');
        $endpoint = "https://api.telegram.org/bot{$token}/deleteWebhook";

        return Http::post($endpoint, [
            'drop_pending_updates' => $dropPendingUpdates
        ]);
    }

    /**
     * Получение информации о текущем вебхуке
     *
     * @param string|null $token Кастомный токен бота
     * @return Response
     */
    public static function getWebhookInfo(string $token = null): Response
    {
        $token = $token ?? config('ebot.bot_token.auth_bot');
        $endpoint = "https://api.telegram.org/bot{$token}/getWebhookInfo";

        return Http::get($endpoint);
    }

    /**
     * Редактирование текста сообщения
     *
     * @param int|string $chatId ID чата
     * @param int $messageId ID сообщения для редактирования
     * @param string $text Новый текст сообщения
     * @param string|null $parseMode Режим форматирования (Markdown, HTML)
     * @param array|null $entities Специальные entities (опционально)
     * @param bool $disableWebPagePreview Отключить превью ссылок
     * @param array|string|null $replyMarkup Новая клавиатура (опционально)
     * @param string|null $token Кастомный токен бота
     * @return Response
     */
    public static function editMessageText(
        $chatId,
        int $messageId,
        string $text,
        ?string $parseMode = null,
        ?array $entities = null,
        bool $disableWebPagePreview = false,
        $replyMarkup = null,
        ?string $token = null
    ): Response
    {
        $token = $token ?? config('ebot.bot_token.auth_bot');
        $url = "https://api.telegram.org/bot{$token}/editMessageText";

        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'disable_web_page_preview' => $disableWebPagePreview
        ];

        if ($parseMode !== null) {
            $data['parse_mode'] = $parseMode;
        }

        if ($entities !== null) {
            $data['entities'] = $entities;
        }

        if ($replyMarkup !== null) {
            $data['reply_markup'] = is_array($replyMarkup) ? json_encode($replyMarkup) : $replyMarkup;
        }

        return Http::post($url, $data);
    }
}