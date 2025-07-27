<?php

namespace App\Orchid\Layouts\MyTasks;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Rows;

class TaskEvaluationLayout extends Rows
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
            Input::make('task.estimation_hours')
                ->type('number')
                ->title('Оценка в часах')
                ->placeholder('Введите количество часов')
                ->help('Укажите количество предполагаемых затраченных часов (допускаются дроби: 1.5, 0.75 и т.д.)')
                ->step(0.25)
                ->min(0)
                ->max(1000)
                ->required(),
        ];
    }
}
