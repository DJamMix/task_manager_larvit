<?php

namespace App\Orchid\Screens\Task;

use App\Models\Task;
use App\Models\TaskCategory;
use App\Orchid\Filters\TaskCategoryFilter;
use App\Orchid\Filters\TaskExecutorFilter;
use App\Orchid\Filters\TaskProjectFilter;
use App\Orchid\Filters\TaskSearchFilter;
use App\Orchid\Filters\TaskStatusFilter;
use App\Orchid\Layouts\Task\TaskListLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class TaskListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'tasks' => Task::with(['creator', 'executor', 'project', 'category'])
                ->filters()
                ->defaultSort('id')
                ->paginate(10),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Задачи';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Link::make(__('project.add'))
                ->icon('bs.plus-circle')
                ->route('platform.systems.tasks.create')
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::selection([
                TaskSearchFilter::class,
                TaskCategoryFilter::class,
                TaskStatusFilter::class,
                TaskProjectFilter::class,
                TaskExecutorFilter::class,
            ]),

            TaskListLayout::class,
        ];
    }
}
