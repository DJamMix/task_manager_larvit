<?php

namespace App\Orchid\Layouts\Task;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class TaskListLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'tasks';

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): iterable
    {
        return [
            TD::make('name', __('task.name'))
                ->render(function (Task $task) {
                    return Link::make($task->name)
                        ->route('platform.systems.tasks.edit', $task->id)
                        ->class('text-truncate d-inline-block')
                        ->style('max-width: 200px; white-space: normal; word-break: break-word;');
                })
                ->width('200px')
                ->style('max-width: 200px'),

            TD::make('creator_id', __('task.creator_id'))
                ->render(fn (Task $task) => $task->creator->name ?? 'Неизвестен'),

            TD::make('status', __('task.status.label'))
                ->width('100px')
                ->render(fn (Task $task) => view('components.task.status', ['status' => $task->status])),

            TD::make('project_id', __('task.project_id'))
                ->render(fn (Task $task) => $task->project->name),

            TD::make('task_category_id', __('task.task_category_id'))
                ->render(fn (Task $task) => $task->category->name),

            TD::make('executor_id', __('task.executor_id'))
                ->render(fn (Task $task) => $task->executor->name ?? 'Не назначен'),

            TD::make('end_datetime', __('task.end_datetime'))
                ->render(fn (Task $task) => Carbon::parse($task->end_datetime)->format('d.m.Y H:i') ?? 'Нет даты'),
        ];
    }
}
