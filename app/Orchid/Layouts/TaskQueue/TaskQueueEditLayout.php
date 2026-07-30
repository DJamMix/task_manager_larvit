<?php

namespace App\Orchid\Layouts\TaskQueue;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class TaskQueueEditLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        $queue = $this->query->get('queue');
        $isNew = !($queue?->exists ?? false);

        return [
            Input::make('queue.key')
                ->title('Ключ')
                ->required()
                ->max(32)
                ->disabled(!$isNew)
                ->help('PHP, FRONTEND, DEVOPS… Используется в номере задачи: PHP-12')
                ->placeholder('PHP'),

            Input::make('queue.name')
                ->title('Название')
                ->required()
                ->max(120)
                ->placeholder('Backend'),

            TextArea::make('queue.description')
                ->title('Описание')
                ->rows(2)
                ->nullable(),

            CheckBox::make('queue.is_active')
                ->title('Активна')
                ->sendTrueOrFalse()
                ->value(true),
        ];
    }
}
