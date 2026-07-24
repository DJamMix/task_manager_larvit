<?php

namespace App\Orchid\Layouts\MyTasks;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class HoursSpentTask extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        return [
            Label::make('tracking_hint')
                ->title('Учёт времени')
                ->help('Трекинг фиксирует факт работы и не меняет оценку задачи. Оценку можно выставить отдельно, когда будете готовы.'),

            Input::make('tracking.hours_spent')
                ->type('number')
                ->title('Затраченные часы')
                ->placeholder('Например 1.5')
                ->help('Быстрый выбор ниже или введите своё значение')
                ->step(0.25)
                ->min(0.25)
                ->max(24)
                ->required()
                ->set('id', 'tracking-hours-input'),

            Label::make('time_presets')
                ->title(' ')
                ->help(
                    '<div class="d-flex flex-wrap gap-2 mt-1">' .
                    collect([0.25, 0.5, 1, 2, 4, 8])->map(function ($h) {
                        return '<button type="button" class="btn btn-sm btn-outline-secondary time-preset-btn" ' .
                            'onclick="document.getElementById(\'tracking-hours-input\').value=\'' . $h . '\'">' .
                            $h . 'ч</button>';
                    })->implode('') .
                    '</div>'
                ),

            DateTimer::make('tracking.work_date')
                ->title('Дата выполнения работы')
                ->format('Y-m-d')
                ->allowInput()
                ->value(now()->format('Y-m-d'))
                ->required()
                ->help('Укажите дату, когда была выполнена работа'),

            TextArea::make('tracking.work_description')
                ->title('Что сделано')
                ->placeholder('Кратко: что именно сделали за это время')
                ->rows(4)
                ->maxlength(2000)
                ->required()
                ->help('Поможет в актах и при разборе с заказчиком'),
        ];
    }
}
