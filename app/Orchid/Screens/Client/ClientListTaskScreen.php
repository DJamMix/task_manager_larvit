<?php

namespace App\Orchid\Screens\Client;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Project;
use App\Models\Task;
use App\Orchid\Layouts\Client\ClientListTaskLayout;
use App\Orchid\Layouts\Client\ClientTaskCreateModalLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ClientListTaskScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Project $project): iterable
    {
        return [
            'tasks' => $project->tasks,
            'project' => $project,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Задачи проектов';
    }

    public function description(): string|null
    {
        return 'Функционал включает создание задач, контроль сроков выполнения, отслеживание прогресса и комментирование.';
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

        $buttons[] = Link::make('Назад')
                ->route('platform.systems.client.projects');

        return $buttons;
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.client.project.tasks',
        ];
    }

    public function createTask(Request $request, Task $task, Project $project)
    {
        $validated = $request->validate([
            'task.name' => 'required|string|max:255',
            'task.description' => 'required|string',
            'task.task_category_id' => 'required|exists:task_categories,id',
            'task.type' => 'required|string',
            'task.priority' => 'required|string',
        ]);

        $task->fill($validated['task']);
        $task->creator_id = auth()->id();
        $task->project_id = $project->id;
        $task->status = TaskStatusEnum::DRAFT->value;
        $task->save();

        Toast::info('Задача успешно создана и передана на согласование');

        return redirect()->route('platform.systems.client.project.tasks', ['project' => $project->id]);
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            ClientListTaskLayout::class,

            Layout::modal('createTaskModal', [
                ClientTaskCreateModalLayout::class
            ])
            ->title('Создание задачи')
            ->applyButton('Создать'),
        ];
    }
}
