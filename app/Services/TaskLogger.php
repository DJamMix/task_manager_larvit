<?php

namespace App\Services;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Models\TaskLink;
use App\Models\User;
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
        ?string $additionalMessage = null,
        ?string $fromStatus = null
    ): void {
        $fromLabel = $fromStatus !== null
            ? (TaskStatusEnum::tryFrom($fromStatus)?->label()
                ?? \App\Models\WorkflowStatus::query()->where('slug', $fromStatus)->value('name')
                ?? $fromStatus)
            : null;
        $toLabel = TaskStatusEnum::tryFrom($toStatus)?->label()
            ?? \App\Models\WorkflowStatus::query()->where('slug', $toStatus)->value('name')
            ?? $toStatus;

        $plainText = 'изменён статус';
        if ($fromLabel) {
            $plainText .= "\nСтатус: {$fromLabel} → {$toLabel}";
        } else {
            $plainText .= "\nСтатус: {$toLabel}";
        }
        if ($additionalMessage) {
            $plainText .= "\nПримечание: " . $additionalMessage;
        }

        $html = $this->eventHtml(
            'изменён статус',
            $fromLabel
                ? [['label' => 'Статус', 'value' => "{$fromLabel} → {$toLabel}"]]
                : [['label' => 'Статус', 'value' => $toLabel]],
            $additionalMessage
        );

        $this->createComment($task, $user, $this->opsFromPlain($plainText), $plainText, $html);
        $this->notifyParticipants(
            $task,
            $user,
            'Статус задачи изменён',
            ($fromLabel ? "{$fromLabel} → {$toLabel}" : $toLabel) . " · «{$task->name}»",
            Color::INFO
        );
    }

    public function logLinkCreated(Task $task, User $user, Task $related, string $relation): void
    {
        $relLabel = TaskLink::relationLabels()[$relation] ?? $relation;
        $left = $task->displayKey();
        $right = $related->displayKey();
        $plainText = "создана связь\nСвязи: {$left} {$relLabel} {$right}";
        $html = $this->eventHtml('создана связь', [
            ['label' => 'Связи', 'value' => "{$left} {$relLabel} {$right}"],
        ]);
        $this->createComment($task, $user, $this->opsFromPlain($plainText), $plainText, $html);
    }

    public function logLinkRemoved(Task $task, User $user, ?Task $related, string $relation): void
    {
        $relLabel = TaskLink::relationLabels()[$relation] ?? $relation;
        $left = $task->displayKey();
        $right = $related?->displayKey() ?? '—';
        $plainText = "удалена связь\nСвязи: {$left} {$relLabel} {$right}";
        $html = $this->eventHtml('удалена связь', [
            ['label' => 'Связи', 'value' => "{$left} {$relLabel} {$right}"],
        ]);
        $this->createComment($task, $user, $this->opsFromPlain($plainText), $plainText, $html);
    }

    public function logTimeLogged(Task $task, User $user, float $hours, string $workDate): void
    {
        $plainText = "учтено время\nВремя: {$hours} ч · {$workDate}";
        $html = $this->eventHtml('учтено время', [
            ['label' => 'Время', 'value' => "{$hours} ч"],
            ['label' => 'Дата', 'value' => $workDate],
        ]);
        $this->createComment($task, $user, $this->opsFromPlain($plainText), $plainText, $html);
    }

    public function logEstimationSubmitted(Task $task, User $user, float $hours): void
    {
        $plainText = "отправлена оценка\nОценка: {$hours} ч";
        $html = $this->eventHtml('отправлена оценка', [
            ['label' => 'Оценка', 'value' => "{$hours} ч"],
        ]);
        $this->createComment($task, $user, $this->opsFromPlain($plainText), $plainText, $html);
        $this->notifyParticipants(
            $task,
            $user,
            'Оценка отправлена',
            "{$user->displayName()}: {$hours} ч · «{$task->name}»",
            Color::WARNING
        );
    }

    public function logObserversChanged(Task $task, User $user, array $names): void
    {
        $list = $names === [] ? 'нет' : implode(', ', $names);
        $plainText = "изменены наблюдатели\nНаблюдатели: {$list}";
        $html = $this->eventHtml('изменены наблюдатели', [
            ['label' => 'Наблюдатели', 'value' => $list],
        ]);
        $this->createComment($task, $user, $this->opsFromPlain($plainText), $plainText, $html);
    }

    public function logTaskCreation(Task $task, User $user): void
    {
        $plainText = "создана задача\nНазвание: {$task->name}";
        $html = $this->eventHtml('создана задача', [
            ['label' => 'Название', 'value' => $task->name],
        ]);
        $this->createComment($task, $user, $this->opsFromPlain($plainText), $plainText, $html);
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
        $fields = [['label' => 'Задача', 'value' => $task->name]];
        if ($reason) {
            $fields[] = ['label' => 'Причина', 'value' => $reason];
        }
        $plainText = "задача отменена\nЗадача: {$task->name}";
        if ($reason) {
            $plainText .= "\nПричина: {$reason}";
        }
        $html = $this->eventHtml('задача отменена', $fields);
        $this->createComment($task, $user, $this->opsFromPlain($plainText), $plainText, $html);
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
        $fields = [];
        if ($reason) {
            $fields[] = ['label' => 'Причина', 'value' => $reason];
        }
        $plainText = 'возврат на оценку';
        if ($reason) {
            $plainText .= "\nПричина: {$reason}";
        }
        $html = $this->eventHtml('возврат на оценку', $fields, null);
        $this->createComment($task, $user, $this->opsFromPlain($plainText), $plainText, $html);
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
        $fields = [];
        if ($reason) {
            $fields[] = ['label' => 'Причина', 'value' => $reason];
        }
        $plainText = 'возврат после демо';
        if ($reason) {
            $plainText .= "\nПричина: {$reason}";
        }
        $html = $this->eventHtml('возврат после демо', $fields);
        $this->createComment($task, $user, $this->opsFromPlain($plainText), $plainText, $html);
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
        $plainText = $action;
        if ($details) {
            $plainText .= "\n" . $details;
        }
        $html = $this->eventHtml($action, $details ? [['label' => 'Детали', 'value' => $details]] : []);
        $this->createComment($task, $user, $this->opsFromPlain($plainText), $plainText, $html);
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
        string $plainText,
        ?string $html = null
    ): void {
        if ($html !== null) {
            $quillContent['html'] = $html;
        }

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

    /**
     * @param list<array{label: string, value: string}> $fields
     */
    protected function eventHtml(string $action, array $fields = [], ?string $note = null): string
    {
        $html = '<p><strong>' . e($action) . '</strong></p>';
        foreach ($fields as $field) {
            $html .= '<p><span>' . e($field['label']) . ': </span><strong>'
                . e($field['value']) . '</strong></p>';
        }
        if ($note) {
            $html .= '<p><em>' . e($note) . '</em></p>';
        }

        return $html;
    }

    protected function opsFromPlain(string $text): array
    {
        $lines = explode("\n", $text);
        $delta = [];
        foreach ($lines as $line) {
            if (!empty($delta)) {
                $delta[] = ['insert' => "\n"];
            }
            $delta[] = ['insert' => trim($line)];
        }

        return ['ops' => $delta];
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
