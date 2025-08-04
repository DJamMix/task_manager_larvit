<?php

namespace App\Services;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\CoreLayer\Integrations\Ebot\EBot;
use App\Models\Task;
use App\Models\User;

class TaskLogger
{
    public function logStatusChange(
        Task $task,
        User $user,
        string $fromStatus,
        string $toStatus,
        ?string $additionalMessage = null
    ): void {
        $fromStatusLabel = TaskStatusEnum::tryFrom($fromStatus)?->label() ?? $fromStatus;
        $toStatusLabel = TaskStatusEnum::tryFrom($toStatus)?->label() ?? $toStatus;

        $message = sprintf(
            "Пользователь %s изменил статус задачи с '%s' на '%s'",
            $user->name,
            $fromStatusLabel,
            $toStatusLabel
        );

        if ($additionalMessage) {
            $message .= ". Примечание: " . $additionalMessage;
        }

        $this->createComment($task, $user, $message);
    }

    public function logTaskCreation(Task $task, User $user): void
    {
        $message = sprintf(
            "Пользователь %s создал новую задачу",
            $user->name
        );

        $this->createComment($task, $user, $message);
    }

    public function logTaskCancellation(
        Task $task,
        User $user,
        ?string $reason = null
    ): void {
        $message = sprintf(
            "Пользователь %s отменил задачу",
            $user->name
        );

        if ($reason) {
            $message .= ". Причина: " . $reason;
        }

        $this->createComment($task, $user, $message);
    }

    public function logTaskReturnEstimation(
        Task $task,
        User $user,
        ?string $reason = null
    ): void {
        $message = sprintf(
            "Пользователь %s вернул задачу на оценку",
            $user->name
        );

        if ($reason) {
            $message .= ". Причина: " . $reason;
        }

        $this->createComment($task, $user, $message);
    }

    public function logCustomAction(
        Task $task,
        User $user,
        string $action,
        ?string $details = null
    ): void {
        $message = sprintf(
            "Пользователь %s выполнил действие: %s",
            $user->name,
            $action
        );

        if ($details) {
            $message .= ". Детали: " . $details;
        }

        $this->createComment($task, $user, $message);
    }

    protected function createComment(Task $task, User $user, string $message): void
    {
        $task->comments()->create([
            'user_id' => $user->id,
            'text' => $message,
        ]);

        if($task->executor) {
            if($task->executor->id !== $user->id) {
                if($task->executor->telegram_id) {
                    EBot::sendMessage($task->executor->telegram_id, $message);
                }
            }
        }
    }
}