<?php

namespace App\Orchid\Layouts\Task;

use App\Models\User;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class TaskObserversLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        return [
            Select::make('task.observers_ids.')
                ->options(User::optionsForSelect())
                ->multiple()
                ->title('Наблюдатели')
                ->help('Могут писать в обсуждении задачи. Не меняют статус и не трекают время, если не исполнитель.')
                ->empty('Без наблюдателей'),
        ];
    }
}
