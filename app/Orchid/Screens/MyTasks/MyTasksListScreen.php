<?php

namespace App\Orchid\Screens\MyTasks;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Orchid\Filters\TaskCategoryFilter;
use App\Orchid\Filters\TaskCreatedAtFilter;
use App\Orchid\Filters\TaskPriorityFilter;
use App\Orchid\Filters\TaskProjectFilter;
use App\Orchid\Filters\TaskSearchFilter;
use App\Orchid\Filters\TaskStatusFilter;
use App\Orchid\Layouts\MyTasks\MyTasksCreateModalLayout;
use App\Orchid\Layouts\MyTasks\MyTasksListLayout;
use App\Services\ProjectContext;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Screen\Layouts\Modal;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MyTasksListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(ProjectContext $context): iterable
    {
        $userId = auth()->id();

        $query = Task::query()
            ->where(function ($q) use ($userId) {
                $q->where('executor_id', $userId)
                    ->orWhereRaw('JSON_CONTAINS(COALESCE(observers_ids, "[]"), ?)', [json_encode((int) $userId)]);
            })
            ->whereNotIn('status', [
                TaskStatusEnum::COMPLETED->value,
                TaskStatusEnum::CANCELED->value,
                TaskStatusEnum::UNPAID->value,
                TaskStatusEnum::DEMO->value,
            ]);

        $context->applyToTaskQuery($query);

        if (request()->has('search') && !empty(request('search'))) {
            $searchTerm = request('search');

            $search = Task::search($searchTerm)
                ->where('executor_id', $userId);

            if ($context->has()) {
                $search->where('project_id', $context->id());
            }

            $taskIds = $search->take(500)->keys();

            if ($taskIds->isNotEmpty()) {
                $query->whereIn('id', $taskIds);
            } else {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('description', 'like', "%{$searchTerm}%");
                });
            }
        }

        $tasks = $query->filters()
            ->with(['project', 'category'])
            ->orderByRaw("FIELD(priority, 'emergency', 'blocker', 'high', 'medium', 'low', 'trivial')")
            ->orderByRaw('CASE WHEN end_datetime IS NOT NULL AND end_datetime < NOW() THEN 0 ELSE 1 END')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $allTasks = Task::query()
            ->where(function ($q) use ($userId) {
                $q->where('executor_id', $userId)
                    ->orWhereRaw('JSON_CONTAINS(COALESCE(observers_ids, "[]"), ?)', [json_encode((int) $userId)]);
            })
            ->whereNotIn('status', [
                TaskStatusEnum::COMPLETED->value,
                TaskStatusEnum::CANCELED->value,
                TaskStatusEnum::UNPAID->value,
                TaskStatusEnum::DEMO->value,
            ]);
        $context->applyToTaskQuery($allTasks);

        $urgentTasks = clone $allTasks;
        $highPriorityTasks = clone $allTasks;
        $inProgressTasks = clone $allTasks;
        $todayTasks = clone $allTasks;
        $overdueTasks = clone $allTasks;

        $completedQuery = Task::where('executor_id', $userId)
            ->whereIn('status', [
                TaskStatusEnum::COMPLETED->value,
                TaskStatusEnum::CANCELED->value,
                TaskStatusEnum::UNPAID->value,
                TaskStatusEnum::DEMO->value,
            ]);
        $context->applyToTaskQuery($completedQuery);

        return [
            'tasks' => $tasks,
            'stats' => [
                'total' => (clone $allTasks)->count(),
                'urgent' => $urgentTasks->whereIn('priority', [
                    TaskPriorityEnum::EMERGENCY->value,
                    TaskPriorityEnum::BLOCKER->value,
                ])->count(),
                'high_priority' => $highPriorityTasks->where('priority', TaskPriorityEnum::HIGH->value)->count(),
                'in_progress' => $inProgressTasks->where('status', TaskStatusEnum::IN_PROGRESS->value)->count(),
                'today_created' => $todayTasks->whereDate('created_at', today())->count(),
                'completed' => $completedQuery->count(),
                'overdue' => $overdueTasks
                    ->whereNotNull('end_datetime')
                    ->where('end_datetime', '<', now())
                    ->count(),
            ],
        ];
    }

    public function name(): ?string
    {
        $project = app(ProjectContext::class)->project();

        return $project
            ? __('adminpanel.MyTasks') . ' — ' . $project->name
            : __('adminpanel.MyTasks');
    }

    public function description(): ?string
    {
        return app(ProjectContext::class)->has()
            ? 'Показаны только задачи активного проекта. Новые задачи автоматически привязываются к нему.'
            : 'Выберите проект в меню слева, чтобы сфокусироваться на одном контексте.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.my_tasks',
        ];
    }

    public function createTask(Request $request, Task $task, ProjectContext $context)
    {
        $rules = [
            'task.name' => 'required|string|max:255',
            'task.description' => 'required|string',
            'task.task_category_id' => 'required|exists:task_categories,id',
            'task.type_task' => 'required|string',
            'task.priority' => 'required|string',
            'task.end_datetime' => 'nullable|date',
        ];

        if (!$context->has()) {
            $rules['task.project_id'] = 'required|integer|exists:projects,id';
        }

        $validated = $request->validate($rules);

        $task->fill($validated['task']);
        $task->creator_id = auth()->id();
        $task->executor_id = auth()->id();
        $task->status = TaskStatusEnum::DRAFT->value;

        if ($context->has()) {
            $task->project_id = $context->id();
        }

        $task->save();

        $task->attachments()->syncWithoutDetaching(
            $request->input('task.attachments', [])
        );

        Toast::info('Задача успешно создана и передана на согласование');

        return redirect()->back();
    }

    public function commandBar(): iterable
    {
        return [
            ModalToggle::make('Создать задачу')
                ->modalTitle('Создание задачи')
                ->modal('createTaskModal')
                ->method('createTask')
                ->icon('plus-circle'),
        ];
    }

    public function layout(): iterable
    {
        $context = app(ProjectContext::class);

        $filters = [
            TaskSearchFilter::class,
            TaskCategoryFilter::class,
            TaskStatusFilter::class,
            TaskPriorityFilter::class,
            TaskCreatedAtFilter::class,
        ];

        // Фильтр проекта скрываем, если уже выбран глобальный контекст
        if (!$context->has()) {
            $filters[] = TaskProjectFilter::class;
        }

        return [
            Layout::view('partials.project-context-banner'),
            Layout::view('orchid.layouts.task-stats'),

            Layout::selection($filters),

            MyTasksListLayout::class,

            Layout::modal('createTaskModal', [
                Layout::wrapper('orchid.layouts.task-create-shell', [
                    'fields' => MyTasksCreateModalLayout::class,
                ]),
            ])
                ->title('Новая задача')
                ->size(Modal::SIZE_XL)
                ->applyButton('Создать')
                ->closeButton('Отмена'),
        ];
    }
}
