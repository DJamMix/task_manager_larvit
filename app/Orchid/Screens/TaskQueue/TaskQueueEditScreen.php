<?php

namespace App\Orchid\Screens\TaskQueue;

use App\Models\TaskQueue;
use App\Orchid\Layouts\TaskQueue\TaskQueueEditLayout;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class TaskQueueEditScreen extends Screen
{
    public $queue;

    public function query(TaskQueue $queue): iterable
    {
        return ['queue' => $queue];
    }

    public function name(): ?string
    {
        return $this->queue?->exists ? 'Очередь ' . $this->queue->key : 'Новая очередь';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.tasks'];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Удалить')
                ->icon('bs.trash3')
                ->confirm('Удалить очередь? Задачи останутся без ключа.')
                ->method('remove')
                ->canSee((bool) $this->queue?->exists),

            Button::make('Сохранить')
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    public function layout(): iterable
    {
        return [TaskQueueEditLayout::class];
    }

    public function save(Request $request, TaskQueue $queue)
    {
        $data = $request->validate([
            'queue.key' => [
                Rule::requiredIf(!$queue->exists),
                'nullable',
                'string',
                'max:32',
                'regex:/^[A-Za-z][A-Za-z0-9_-]*$/',
                Rule::unique('task_queues', 'key')->ignore($queue->id),
            ],
            'queue.name' => 'required|string|max:120',
            'queue.description' => 'nullable|string|max:2000',
            'queue.is_active' => 'nullable|boolean',
        ])['queue'];

        if ($queue->exists) {
            unset($data['key']);
        } else {
            $data['key'] = strtoupper((string) $data['key']);
            $data['next_number'] = 1;
        }

        $queue->fill($data)->save();
        Toast::info('Очередь сохранена');

        return redirect()->route('platform.systems.task_queues');
    }

    public function remove(TaskQueue $queue)
    {
        $queue->delete();
        Toast::info('Очередь удалена');

        return redirect()->route('platform.systems.task_queues');
    }
}
