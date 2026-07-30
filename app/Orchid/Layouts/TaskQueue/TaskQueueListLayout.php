<?php

namespace App\Orchid\Layouts\TaskQueue;

use App\Models\TaskQueue;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class TaskQueueListLayout extends Table
{
    protected $target = 'queues';

    protected function columns(): iterable
    {
        return [
            TD::make('key', 'Ключ')
                ->render(fn (TaskQueue $q) => Link::make($q->key)
                    ->route('platform.systems.task_queues.edit', $q)),

            TD::make('name', 'Название'),

            TD::make('next_number', 'След. №')
                ->alignRight(),

            TD::make('is_active', 'Активна')
                ->render(fn (TaskQueue $q) => $q->is_active ? 'да' : 'нет'),
        ];
    }
}
