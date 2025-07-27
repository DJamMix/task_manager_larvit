<?php

namespace App\Orchid\Layouts\MyTasks;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class HoursSpentTask extends Rows
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
            Input::make('tracking.hours_spent')
                ->type('number')
                ->title('Затраченные часы')
                ->placeholder('Введите количество часов')
                ->help('Укажите количество затраченных часов (допускаются дроби: 1.5, 0.75 и т.д.)')
                ->step(0.25)
                ->min(0)
                ->max(1000)
                ->required(),

            DateTimer::make('tracking.work_date')
                ->title('Дата выполнения работы')
                ->format('Y-m-d')
                ->allowInput()
                ->required()
                ->help('Укажите дату, когда была выполнена работа'),

            TextArea::make('tracking.work_description')
                ->title('Описание работы')
                ->placeholder('Подробно опишите выполненную работу')
                ->rows(5)
                ->maxlength(2000)
                ->required()
                ->help('Что именно было сделано? Какие задачи выполнены?'),
        ];
    }
}
