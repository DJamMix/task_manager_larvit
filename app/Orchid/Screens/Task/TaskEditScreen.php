<?php

namespace App\Orchid\Screens\Task;

use App\Models\Task;
use App\Orchid\Layouts\Task\TaskEditLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class TaskEditScreen extends Screen
{
    /**
     * @var Task
     */
    public $task;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Task $task): iterable
    {
        return [
            'task' => $task,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->task->exists ? 'Редактировать' : 'Создать';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.tasks',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make(__('project.remove.title'))
                ->icon('bs.trash3')
                ->confirm(__('project.remove.warning'))
                ->method('remove')
                ->canSee($this->task->exists),

            Button::make(__('project.save'))
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            TaskEditLayout::class,
        ];
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request, Task $task)
    {
        $task->fill($request->get('task'));
        $task->save();

        Toast::info(__('task.save'));

        return redirect()->route('platform.systems.tasks');
    }

    /**
     * @throws \Exception
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove(Task $task)
    {
        $task->delete();

        Toast::info(__('task.remove'));

        return redirect()->route('platform.systems.tasks');
    }
}
