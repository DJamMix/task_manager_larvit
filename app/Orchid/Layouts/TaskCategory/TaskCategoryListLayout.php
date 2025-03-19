<?php

namespace App\Orchid\Layouts\TaskCategory;

use App\Models\TaskCategory;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class TaskCategoryListLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'task_categories';

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): iterable
    {
        return [
            TD::make('name', __('task_category.name'))
                ->render(fn (TaskCategory $taskCategory) => Link::make($taskCategory->name)
                    ->route('platform.systems.task_categories.edit', $taskCategory->id)),
        ];
    }
}
