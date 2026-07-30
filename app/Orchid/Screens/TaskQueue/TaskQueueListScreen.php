<?php

namespace App\Orchid\Screens\TaskQueue;

use App\Models\TaskQueue;
use App\Orchid\Layouts\TaskQueue\TaskQueueListLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;

class TaskQueueListScreen extends Screen
{
    public function query(): iterable
    {
        return [
            'queues' => TaskQueue::query()->orderBy('key')->paginate(),
        ];
    }

    public function name(): ?string
    {
        return 'Очереди задач';
    }

    public function description(): ?string
    {
        return 'Ключи вроде PHP, FRONTEND — выбираются при создании задачи';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.tasks'];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Создать')
                ->icon('bs.plus-circle')
                ->route('platform.systems.task_queues.create'),
        ];
    }

    public function layout(): iterable
    {
        return [TaskQueueListLayout::class];
    }
}
