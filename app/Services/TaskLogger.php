<?php

namespace App\Services;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;
use Orchid\Support\Color;

class TaskLogger
{
    public function __construct(
        private readonly DashboardNotifier $notifier,
    ) {}

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

        $this->createComment($task, $user, $this->formatForQuill($plainText), $plainText);
        $this->notifyParticipants(
            $task,
            $user,
            'Статус задачи изменён',
            "{$user->displayName()} → {$toStatusLabel} · «{$task->name}»",
            Color::INFO
        );
    }

    public function logTaskCreation(Task $task, User $user): void
    {
        $plainText = sprintf(
            "🆕 Пользователь %s создал новую задачу: %s",
            $user->name,
            $task->name
        );

        $this->createComment($task, $user, $this->formatForQuill($plainText), $plainText);
        $this->notifyParticipants(
            $task,
            $user,
            'Новая задача',
            "{$user->displayName()} создал(а) «{$task->name}»",
            Color::SUCCESS
        );
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

        $this->createComment($task, $user, $this->formatForQuill($plainText), $plainText);
        $this->notifyParticipants(
            $task,
            $user,
            'Задача отменена',
            "{$user->displayName()} отменил(а) «{$task->name}»",
            Color::DANGER
        );
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

        $this->createComment($task, $user, $this->formatForQuill($plainText), $plainText);
        $this->notifyParticipants(
            $task,
            $user,
            'Оценка отклонена',
            "{$user->displayName()} вернул(а) «{$task->name}» на оценку",
            Color::WARNING
        );
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

        $this->createComment($task, $user, $this->formatForQuill($plainText), $plainText);
        $this->notifyParticipants(
            $task,
            $user,
            'Демо отклонено',
            "{$user->displayName()} вернул(а) «{$task->name}» в работу",
            Color::WARNING
        );
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

        $this->createComment($task, $user, $this->formatForQuill($plainText), $plainText);
        $this->notifyParticipants(
            $task,
            $user,
            'Действие по задаче',
            "{$user->displayName()}: {$action} · «{$task->name}»",
            Color::INFO
        );
    }

    protected function createComment(
        Task $task,
        User $user,
        array $quillContent,
        string $plainText
    ): void {
        $task->comments()->create([
            'user_id' => $user->id,
            'text' => $quillContent,
            'plain_text' => $plainText,
            'is_system' => true,
        ]);
    }

    protected function notifyParticipants(
        Task $task,
        User $actor,
        string $title,
        string $message,
        Color $color
    ): void {
        $ids = collect([
            $task->executor_id,
            $task->creator_id,
            ...$task->observerIds(),
        ]);

        foreach ($task->project?->clients ?? [] as $client) {
            $ids->push($client->id);
        }

        $users = User::query()
            ->whereIn('id', $ids->filter()->unique()->all())
            ->get()
            ->reject(fn (User $u) => (int) $u->id === (int) $actor->id);

        foreach ($users as $user) {
            $this->notifier->send(
                $user,
                $title,
                $message,
                $this->notifier->taskUrlFor($user, $task),
                $color
            );
        }
    }

    protected function formatForQuill(string $text): array
    {
        $lines = explode("\n", $text);
        $delta = [];

        foreach ($lines as $line) {
            if (!empty($delta)) {
                $delta[] = ['insert' => "\n"];
            }

            $attributes = $this->determineQuillAttributes($line);
            $delta[] = ['insert' => trim($line), 'attributes' => $attributes];
        }

        return [
            'ops' => $delta,
            'html' => $this->convertToHtml($delta),
        ];
    }

    protected function determineQuillAttributes(string $line): array
    {
        $attributes = [];

        if (Str::startsWith($line, '🔄')) {
            $attributes['bold'] = true;
            $attributes['color'] = '#2b6cb0';
        } elseif (Str::startsWith($line, '❌')) {
            $attributes['bold'] = true;
            $attributes['color'] = '#e53e3e';
        } elseif (Str::startsWith($line, '🆕')) {
            $attributes['bold'] = true;
            $attributes['color'] = '#38a169';
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
                $html .= '<br>';
                continue;
            }

            $text = htmlspecialchars($op['insert']);
            $attrs = $op['attributes'] ?? [];

            if (!empty($attrs)) {
                $style = '';
                if (isset($attrs['bold'])) {
                    $style .= 'font-weight:bold;';
                }
                if (isset($attrs['italic'])) {
                    $style .= 'font-style:italic;';
                }
                if (isset($attrs['color'])) {
                    $style .= 'color:' . $attrs['color'] . ';';
                }

                $html .= sprintf('<span style="%s">%s</span>', $style, $text);
            } else {
                $html .= $text;
            }
        }

        return $html;
    }

    public function createTaskPushNotifPM(Task $task): void
    {
        $priority = TaskPriorityEnum::from($task->priority);
        $title = 'Новая задача — назначьте исполнителя';
        $message = "{$priority->label()} · «{$task->name}» · {$task->creator?->displayName()}";

        $managers = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'pm', 'manager']))
            ->get()
            ->filter(fn (User $u) => $u->hasAccess('platform.systems.tasks'));

        foreach ($managers as $manager) {
            if ((int) $manager->id === (int) $task->creator_id) {
                continue;
            }

            $this->notifier->send(
                $manager,
                $title,
                $message,
                $this->notifier->taskUrlFor($manager, $task),
                Color::WARNING
            );
        }
    }
}
