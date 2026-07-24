<?php

namespace App\Orchid\Layouts\Client;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskTypeEnum;
use App\Models\Task;
use Carbon\Carbon;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ClientListTaskLayout extends Table
{
    protected $target = 'tasks';

    protected $template = 'orchid.layouts.tasks-priority-table';

    protected function columns(): iterable
    {
        return [
            TD::make('name', __('task.name'))
                ->render(function (Task $task) {
                    return Link::make($task->name)
                        ->route('platform.systems.client.project.tasks.view', ['project' => $task->project, 'task' => $task])
                        ->class('text-truncate d-inline-block')
                        ->style('max-width: 200px; white-space: normal; word-break: break-word;');
                })
                ->width('200px')
                ->style('max-width: 200px'),

            TD::make('type_task', 'Тип задачи')
                ->render(function (Task $task) {
                    $type = TaskTypeEnum::tryFrom($task->type_task);
                    if (!$type) {
                        return 'N/A';
                    }

                    $icon = match ($type) {
                        TaskTypeEnum::DEFAULT => '📝',
                        TaskTypeEnum::BUG => '🐛',
                    };

                    $badgeClass = match ($type) {
                        TaskTypeEnum::DEFAULT => 'bg-primary',
                        TaskTypeEnum::BUG => 'bg-danger',
                    };

                    return sprintf(
                        '<span class="badge %s">%s %s</span>',
                        $badgeClass,
                        $icon,
                        $type->label()
                    );
                })
                ->align(TD::ALIGN_CENTER)
                ->width('140px'),

            TD::make('priority', 'Приоритет')
                ->width('160px')
                ->render(function (Task $task) {
                    $priority = TaskPriorityEnum::tryFrom($task->priority);

                    return $priority ? $priority->badgeHtml() : '—';
                }),

            TD::make('status', __('task.status.label'))
                ->render(fn (Task $task) => view('components.task.status', ['status' => $task->status])),

            TD::make('task_category_id', __('task.task_category_id'))
                ->render(fn (Task $task) => $task->category->name ?? '—'),

            TD::make('actions', 'Действия')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(function (Task $task) {
                    return DropDown::make()
                        ->icon('bs.justify')
                        ->list([
                            Link::make('Просмотр')
                                ->route('platform.systems.client.project.tasks.view', ['project' => $task->project, 'task' => $task])
                                ->icon('bs.eye'),
                        ]);
                }),
        ];
    }
}
