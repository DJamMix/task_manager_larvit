<?php

namespace App\Orchid\Screens\TaskCategory;

use App\Models\TaskCategory;
use App\Orchid\Layouts\TaskCategory\TaskCategoryListLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;

class TaskCategoryListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'task_categories' => TaskCategory::all(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return __('task_category.label');
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
                ->route('platform.systems.task_categories.create')
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
            TaskCategoryListLayout::class,
        ];
    }
}
