<?php

namespace App\Orchid\Layouts\Act\Steps;

use App\Orchid\Layouts\Act\Components\ActTasksList;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Fields\DateTimer;

class EditActStep
{
    public static function fields($act, $actTasks): array
    {
        $fields = [];

        $fields[] = Label::make('')->title('Основные данные акта')->class('h3');
        
        $fields[] = Group::make([
            Input::make('act.number')
                ->type('text')
                ->max(255)
                ->required()
                ->title('Номер акта'),
            DateTimer::make('act.date')
                ->required()
                ->title('Дата акта')
                ->format('Y-m-d'),
        ]);

        $fields[] = Input::make('act.customer')
            ->max(255)
            ->required()
            ->title('Заказчик');
            
        $fields[] = Input::make('act.executor')
            ->max(255)
            ->required()
            ->title('Исполнитель');

        $fields[] = Label::make('')
            ->title('Задачи в акте')
            ->class('h4 mt-4');
        
        $fields = array_merge($fields, ActTasksList::make($actTasks, false));
        
        $totalHours = $actTasks->sum(function($task) {
            return (float) ($task->pivot->hours ?? $task->estimation_hours ?? 0);
        });
        
        $formattedHours = number_format($totalHours, 2, ',', ' ');
        $fields[] = Label::make('')
            ->title('Итого: ' . $act->total_tasks . ' задач, ' . $formattedHours . ' часов');

        return $fields;
    }
}