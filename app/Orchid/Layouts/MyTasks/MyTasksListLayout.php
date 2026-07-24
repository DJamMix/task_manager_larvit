<?php

namespace App\Orchid\Layouts\MyTasks;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use App\CoreLayer\Enums\TaskTypeEnum;
use App\Models\Task;
use Carbon\Carbon;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class MyTasksListLayout extends Table
{
    protected $target = 'tasks';

    protected $template = 'orchid.layouts.tasks-priority-table';

    protected function columns(): iterable
    {
        return [
            TD::make('name', __('task.name'))
                ->render(function (Task $task) {
                    $link = Link::make($task->name)
                        ->route('platform.systems.my_tasks.view', $task->id)
                        ->class('text-truncate d-inline-block')
                        ->style('max-width: 200px; white-space: normal; word-break: break-word;');

                    $html = (string) $link;

                    if ($task->isOverdue()) {
                        $html .= ' <span class="badge text-bg-danger overdue-badge">просрочено</span>';
                    }

                    return $html;
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
                ->width('100px')
                ->render(fn (Task $task) => view('components.task.status', ['status' => $task->status])),

            TD::make('project_id', __('task.project_id'))
                ->render(fn (Task $task) => $task->project->name ?? '—'),

            TD::make('task_category_id', __('task.task_category_id'))
                ->render(fn (Task $task) => $task->category->name ?? '—'),

            TD::make('hours', 'Факт / оценка')
                ->align(TD::ALIGN_CENTER)
                ->width('120px')
                ->render(function (Task $task) {
                    $spent = number_format((float) $task->hours_spent, 1);
                    $est = (float) $task->estimation_hours > 0
                        ? number_format((float) $task->estimation_hours, 1)
                        : '—';

                    return "<span title=\"Факт не влияет на оценку\">{$spent} / {$est}</span>";
                }),

            TD::make('actions', 'Действия')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(function (Task $task) {
                    return DropDown::make()
                        ->icon('bs.justify')
                        ->list([
                            Link::make('Просмотр')
                                ->route('platform.systems.my_tasks.view', $task)
                                ->icon('bs.eye'),
                        ]);
                }),
        ];
    }
}
