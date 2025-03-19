<?php

namespace App\Orchid\Layouts\TaskCategory;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Rows;

class TaskCategoryEditLayout extends Rows
{
    /**
     * Used to create the title of a group of form elements.
     *
     * @var string|null
     */
    protected $title;

    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    protected function fields(): iterable
    {
        return [
            Input::make('task_category.name')
                ->type('text')
                ->max(255)
                ->required()
                ->title(__('task_category.title'))
                ->placeholder(__('task_category.title')),
        ];
    }
}
