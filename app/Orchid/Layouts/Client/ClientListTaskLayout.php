<?php

namespace App\Orchid\Layouts\Client;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\Models\Task;
use Carbon\Carbon;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ClientListTaskLayout extends Table
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
                        ->route('platform.systems.client.project.tasks.view', ['project' => $task->project, 'task' => $task])
                        ->class('text-truncate d-inline-block')
                        ->style('max-width: 200px; white-space: normal; word-break: break-word;');
                })
                ->width('200px')
                ->style('max-width: 200px'),

            TD::make('status', __('task.status.label'))
                ->render(fn (Task $task) => view('components.task.status', ['status' => $task->status])),

            TD::make('task_category_id', __('task.task_category_id'))
                ->render(fn (Task $task) => $task->category->name),

            TD::make('priority', __('Приоритет'))
                ->render(function (Task $task) {
                    $priority = TaskPriorityEnum::tryFrom($task->priority);
                    if (!$priority) {
                        return 'N/A';
                    }
                    
                    return sprintf(
                        '<span class="me-2">%s</span> %s',
                        $priority->icon(),
                        $priority->label()
                    );
                }),

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
