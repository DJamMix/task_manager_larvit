<?php

namespace App\Services;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\CoreLayer\Integrations\Ebot\EBot;
use App\Models\Task;
use App\Models\User;
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
        string $fromStatus,
        string $toStatus,
        ?string $additionalMessage = null
    ): void {
        $fromStatusLabel = TaskStatusEnum::tryFrom($fromStatus)?->label() ?? $fromStatus;
        $toStatusLabel = TaskStatusEnum::tryFrom($toStatus)?->label() ?? $toStatus;

        $plainText = sprintf(
            "🔄 Пользователь %s изменил статус задачи с '%s' на '%s'",
            $user->name,
            $fromStatusLabel,
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
            'text' => json_encode($quillContent),
            'plain_text' => $plainText
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
        $taskUrl = URL::route('platform.systems.tasks.edit', $task->id);
        
        return sprintf(
            "%s\n\n🔗 [Перейти к задаче](%s)\n🏷️ ID: #T%d\n📂 Проект: %s",
            $text,
            $taskUrl,
            $task->id,
            $task->project->name
        );
    }

    protected function sendTelegramNotification(Task $task, User $actor, string $message): void
    {
        if ($task->executor && $task->executor->id !== $actor->id && $task->executor->telegram_id) {
            $this->ebot->sendMessage(
                $task->executor->telegram_id,
                $message,
                null,
                'Markdown'
            );
        }

        if ($task->creator && $task->creator->id !== $actor->id && 
            (!$task->executor || $task->creator->id !== $task->executor->id) &&
            $task->creator->telegram_id) {
            $this->ebot->sendMessage(
                $task->creator->telegram_id,
                $message,
                null,
                'Markdown'
            );
        }
    }
}