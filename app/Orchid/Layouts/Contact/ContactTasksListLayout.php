<?php

namespace App\Orchid\Layouts\Contact;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Task;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ContactTasksListLayout extends Table
{
    protected $target = 'tasks';

    protected function columns(): iterable
    {
        return [
            TD::make('name', 'Задача')
                ->render(fn (Task $task) => Link::make($task->name)
                    ->route('platform.systems.contact.tasks.view', $task)),

            TD::make('status', 'Статус')
                ->render(fn (Task $task) => view('components.task.status', ['status' => $task->status])),

            TD::make('priority', 'Приоритет')
                ->render(function (Task $task) {
                    $priority = TaskPriorityEnum::tryFrom($task->priority);

                    return $priority ? $priority->badgeHtml() : '—';
                }),

            TD::make('project_id', 'Проект')
                ->render(fn (Task $task) => e($task->project?->name ?? '—')),

            TD::make('executor_id', 'Исполнитель')
                ->render(fn (Task $task) => e($task->executor?->displayName() ?? '—')),

            TD::make('updated_at', 'Обновлено')
                ->render(fn (Task $task) => $task->updated_at?->format('d.m.Y H:i') ?? '—'),
        ];
    }
}
