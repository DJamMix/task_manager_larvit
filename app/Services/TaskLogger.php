<?php

namespace App\Services;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use App\CoreLayer\Integrations\Ebot\EBot;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class TaskLogger
{
    protected EBot $ebot;

    public function __construct(EBot $ebot)
    {
        $this->ebot = $ebot;
    }

    public function logStatusChange(
        Task $task,
        User $user,
        string $toStatus,
        ?string $additionalMessage = null
    ): void {
        $toStatusLabel = TaskStatusEnum::tryFrom($toStatus)?->label() ?? $toStatus;

        $plainText = sprintf(
            "🔄 Пользователь %s изменил статус задачи на '%s'",
            $user->name,
            $toStatusLabel
        );

        if ($additionalMessage) {
            $plainText .= "\n📝 Примечание: " . $additionalMessage;
        }

        $quillContent = $this->formatForQuill($plainText);
        $telegramMessage = $this->formatForTelegram($plainText, $task);

        $this->createComment($task, $user, $quillContent, $plainText, $telegramMessage);
    }

    public function logTaskCreation(Task $task, User $user): void
    {
        $plainText = sprintf(
            "🆕 Пользователь %s создал новую задачу: %s",
            $user->name,
            $task->name
        );

        $quillContent = $this->formatForQuill($plainText);
        $telegramMessage = $this->formatForTelegram($plainText, $task);

        $this->createComment($task, $user, $quillContent, $plainText, $telegramMessage);
    }

    public function logTaskCancellation(
        Task $task,
        User $user,
        ?string $reason = null
    ): void {
        $plainText = sprintf(
            "❌ Пользователь %s отменил задачу: %s",
            $user->name,
            $task->name
        );

        if ($reason) {
            $plainText .= "\n📌 Причина: " . $reason;
        }

        $quillContent = $this->formatForQuill($plainText);
        $telegramMessage = $this->formatForTelegram($plainText, $task);

        $this->createComment($task, $user, $quillContent, $plainText, $telegramMessage);
    }

    public function logTaskReturnEstimation(
        Task $task,
        User $user,
        ?string $reason = null
    ): void {
        $plainText = sprintf(
            "↩️ Пользователь %s вернул задачу на оценку: %s",
            $user->name,
            $task->name
        );

        if ($reason) {
            $plainText .= "\n📌 Причина: " . $reason;
        }

        $quillContent = $this->formatForQuill($plainText);
        $telegramMessage = $this->formatForTelegram($plainText, $task);

        $this->createComment($task, $user, $quillContent, $plainText, $telegramMessage);
    }

    public function logTaskReturnDemoEstimation(
        Task $task,
        User $user,
        ?string $reason = null
    ): void {
        $plainText = sprintf(
            "↩️ Пользователь %s вернул задачу в работу после результатов ДЕМО: %s",
            $user->name,
            $task->name
        );

        if ($reason) {
            $plainText .= "\n📌 Причина: " . $reason;
        }

        $quillContent = $this->formatForQuill($plainText);
        $telegramMessage = $this->formatForTelegram($plainText, $task);

        $this->createComment($task, $user, $quillContent, $plainText, $telegramMessage);
    }

    public function logCustomAction(
        Task $task,
        User $user,
        string $action,
        ?string $details = null
    ): void {
        $plainText = sprintf(
            "⚡ Пользователь %s выполнил действие: %s",
            $user->name,
            $action
        );

        if ($details) {
            $plainText .= "\n🔍 Детали: " . $details;
        }

        $quillContent = $this->formatForQuill($plainText);
        $telegramMessage = $this->formatForTelegram($plainText, $task);

        $this->createComment($task, $user, $quillContent, $plainText, $telegramMessage);
    }

    protected function createComment(
        Task $task,
        User $user,
        array $quillContent,
        string $plainText,
        string $telegramMessage
    ): void {

        $task->comments()->create([
            'user_id' => $user->id,
            'text' => $quillContent,
            'plain_text' => $plainText,
            'is_system' => true,
        ]);

        // Отправляем уведомление в Telegram
        $this->sendTelegramNotification($task, $user, $telegramMessage);
    }

    protected function formatForQuill(string $text): array
    {
        $lines = explode("\n", $text);
        $delta = [];

        foreach ($lines as $line) {
            if (!empty($delta)) {
                // Добавляем перенос строки между параграфами
                $delta[] = ['insert' => "\n"];
            }

            // Определяем стиль для строки
            $attributes = $this->determineQuillAttributes($line);
            $delta[] = ['insert' => trim($line), 'attributes' => $attributes];
        }

        return [
            'ops' => $delta,
            'html' => $this->convertToHtml($delta)
        ];
    }

    protected function determineQuillAttributes(string $line): array
    {
        $attributes = [];

        // Эмодзи в начале строки определяют стиль
        if (Str::startsWith($line, '🔄')) {
            $attributes['bold'] = true;
            $attributes['color'] = '#2b6cb0'; // синий
        } elseif (Str::startsWith($line, '❌')) {
            $attributes['bold'] = true;
            $attributes['color'] = '#e53e3e'; // красный
        } elseif (Str::startsWith($line, '🆕')) {
            $attributes['bold'] = true;
            $attributes['color'] = '#38a169'; // зеленый
        } elseif (Str::startsWith($line, '📌') || Str::startsWith($line, '📝')) {
            $attributes['italic'] = true;
        }

        return $attributes;
    }

    protected function convertToHtml(array $delta): string
    {
        $html = '';
        foreach ($delta as $op) {
            if ($op['insert'] === "\n") {
                $html .= "<br>";
                continue;
            }

            $text = htmlspecialchars($op['insert']);
            $attrs = $op['attributes'] ?? [];

            if (!empty($attrs)) {
                $style = '';
                if (isset($attrs['bold'])) $style .= 'font-weight:bold;';
                if (isset($attrs['italic'])) $style .= 'font-style:italic;';
                if (isset($attrs['color'])) $style .= 'color:' . $attrs['color'] . ';';

                $html .= sprintf('<span style="%s">%s</span>', $style, $text);
            } else {
                $html .= $text;
            }
        }

        return $html;
    }

    protected function formatForTelegram(string $text, Task $task): string
    {
        // Базовое сообщение без специфичной ссылки
        return sprintf(
            "%s\n\n🏷️ ID: #T%d\n📂 Проект: %s",
            $text,
            $task->id,
            $task->project->name
        );
    }

    protected function sendTelegramNotification(Task $task, User $actor, string $baseMessage): void
    {
        // Отправка исполнителю
        if ($task->executor && $task->executor->id !== $actor->id && $task->executor->telegram_id) {
            $executorUrl = URL::route('platform.systems.my_tasks.view', $task->id);
            $executorMessage = $baseMessage . "\n\n🔗 [Перейти к задаче](" . $executorUrl . ")" .
                             "\nℹ️ Вы исполнитель этой задачи";
            
            $this->ebot->sendMessage(
                $task->executor->telegram_id,
                $executorMessage,
                null,
                'Markdown'
            );
        }

        // Отправка создателю
        if ($task->creator && $task->creator->id !== $actor->id && 
            (!$task->executor || $task->creator->id !== $task->executor->id) &&
            $task->creator->telegram_id) {
            $creatorUrl = URL::route('platform.systems.client.project.tasks.view', [
                'project' => $task->project,
                'task' => $task
            ]);
            $creatorMessage = $baseMessage . "\n\n🔗 [Перейти к задаче](" . $creatorUrl . ")" .
                            "\nℹ️ Это ваша задача";
            
            $this->ebot->sendMessage(
                $task->creator->telegram_id,
                $creatorMessage,
                null,
                'Markdown'
            );
        }

        // Отправка клиентам проекта
        foreach ($task->project->clients as $client) {
            if ($client->id !== $actor->id &&
                $client->telegram_id) {
                $clientUrl = URL::route('platform.systems.client.project.tasks.view', [
                    'project' => $task->project,
                    'task' => $task
                ]);
                $clientMessage = $baseMessage . "\n\n🔗 [Перейти к задаче](" . $clientUrl . ")" .
                                "\nℹ️ Это задача вашего проекта";
                
                $this->ebot->sendMessage(
                    $client->telegram_id,
                    $clientMessage,
                    null,
                'Markdown'
                );
            }
        }
    }

    protected function getExecutorSpecificText(string $text): string
    {
        return $text . "\n\nℹ️ Вы исполнитель этой задачи";
    }

    protected function getClientSpecificText(string $text): string
    {
        return $text . "\n\nℹ️ Это задача вашего проекта";
    }

    public function createTaskPushNotifPM(Task $task)
    {
        $pmTelegramId = 965982077; //Пока хардкод

        $priority = TaskPriorityEnum::from($task->priority);

        $taskUrl = route('platform.systems.tasks.edit', $task);

        $message = $this->getPriorityHeader($priority) . "\n\n";
        $message .= "📌 *{$task->name}*\n\n";
        $message .= "👤 *Создатель задачи:* {$task->creator->name}\n";
        $message .= "📅 *Создана:* {$task->created_at->format('d.m.Y в H:i')}\n";
        $message .= $this->getPriorityLine($priority) . "\n";

        $message .= "\n🔗 [🚀 Перейти к задаче]({$taskUrl})";
        $message .= "\n_Требуется назначить исполнителя_ 👤";

        $this->ebot->sendMessage(
            $pmTelegramId,
            $message,
            null,
            'Markdown'
        );
    }

    private function getPriorityHeader(TaskPriorityEnum $priority): string
    {
        return match($priority) {
            TaskPriorityEnum::EMERGENCY => "🔥 *🚨 АВАРИЙНАЯ ЗАДАЧА! 🚨*",
            TaskPriorityEnum::BLOCKER => "⛔ *🚧 БЛОКИРУЮЩАЯ ЗАДАЧА*",
            TaskPriorityEnum::HIGH => "⚠️ *📈 ВЫСОКИЙ ПРИОРИТЕТ*",
            TaskPriorityEnum::MEDIUM => "🔵 *📊 НОВАЯ ЗАДАЧА*",
            TaskPriorityEnum::LOW => "🔹 *📉 ЗАДАЧА НИЗКОГО ПРИОРИТЕТА*",
            TaskPriorityEnum::TRIVIAL => "⚪ *📋 НЕСРОЧНАЯ ЗАДАЧА*",
        };
    }

    private function getPriorityLine(TaskPriorityEnum $priority): string
    {
        $emoji = match($priority) {
            TaskPriorityEnum::EMERGENCY => '🔥',
            TaskPriorityEnum::BLOCKER => '⛔',
            TaskPriorityEnum::HIGH => '⚠️',
            TaskPriorityEnum::MEDIUM => '🔵',
            TaskPriorityEnum::LOW => '🔹',
            TaskPriorityEnum::TRIVIAL => '⚪',
        };
        
        return "🎯 *Уровень важности:* {$emoji} {$priority->label()}";
    }
}