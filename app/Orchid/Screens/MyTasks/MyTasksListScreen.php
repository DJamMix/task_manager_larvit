<?php

namespace App\Orchid\Screens\MyTasks;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Project;
use App\Models\Task;
use App\Orchid\Filters\TaskCategoryFilter;
use App\Orchid\Filters\TaskPriorityFilter;
use App\Orchid\Filters\TaskProjectFilter;
use App\Orchid\Filters\TaskStatusFilter;
use App\Orchid\Layouts\MyTasks\MyTasksCreateModalLayout;
use App\Orchid\Layouts\MyTasks\MyTasksListLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MyTasksListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'tasks' => Task::where('executor_id', auth()->id())
                ->filters()
                ->whereNotIn('status', [
                    TaskStatusEnum::COMPLETED->value,
                    TaskStatusEnum::CANCELED->value,
                    TaskStatusEnum::UNPAID->value,
                    TaskStatusEnum::DEMO->value,
                ])
                ->orderBy('created_at', 'desc')
                ->paginate(15),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return __('adminpanel.MyTasks');
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.my_tasks',
        ];
    }

    public function createTask(Request $request, Task $task)
    {
        $validated = $request->validate([
            'task.name' => 'required|string|max:255',
            'task.description' => 'required|string',
            'task.task_category_id' => 'required|exists:task_categories,id',
            'task.type' => 'required|string',
            'task.priority' => 'required|string',
            'task.project_id' => 'required|integer',
        ]);

        $task->fill($validated['task']);
        $task->creator_id = auth()->id();
        $task->executor_id = auth()->id();
        $task->status = TaskStatusEnum::DRAFT->value;
        $task->save();

        Toast::info('Задача успешно создана и передана на согласование');

        return redirect()->back();
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        $buttons = [];

        $buttons[] = ModalToggle::make('Создать задачу')
            ->modalTitle('Создание задачи')
            ->modal('createTaskModal')
            ->method('createTask')
            ->icon('plus-circle');

        return $buttons;
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::rows([
                Input::make('search')
                    ->type('text')
                    ->placeholder('Поиск временно недоступен')
                    ->title('Быстрый поиск')
                    ->disabled()
                    ->help('Данная функция находится в разработке и будет доступна в следующем обновлении системы'),
            ]),

            Layout::selection([
                TaskCategoryFilter::class,
                TaskStatusFilter::class,
                TaskProjectFilter::class,
                TaskPriorityFilter::class,
            ]),

            MyTasksListLayout::class,

            Layout::modal('createTaskModal', [
                MyTasksCreateModalLayout::class
            ])
            ->title('Создание задачи')
            ->applyButton('Создать'),
        ];
    }
}
