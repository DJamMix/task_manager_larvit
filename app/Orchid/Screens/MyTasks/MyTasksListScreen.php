<?php

namespace App\Orchid\Screens\MyTasks;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Project;
use App\Models\Task;
use App\Orchid\Filters\TaskCategoryFilter;
use App\Orchid\Filters\TaskPriorityFilter;
use App\Orchid\Filters\TaskProjectFilter;
use App\Orchid\Filters\TaskStatusFilter;
use App\Orchid\Layouts\MyTasks\MyTasksCreateModalLayout;
use App\Orchid\Layouts\MyTasks\MyTasksListLayout;
use App\Orchid\Layouts\MyTasks\TaskStatsLayout;
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
        $userId = auth()->id();

        // Всегда начинаем с базового запроса
        $query = Task::where('executor_id', $userId)
            ->whereNotIn('status', [
                TaskStatusEnum::COMPLETED->value,
                TaskStatusEnum::CANCELED->value,
                TaskStatusEnum::UNPAID->value,
                TaskStatusEnum::DEMO->value,
            ]);

        // Применяем поиск через Scout если есть поисковый запрос
        if (request()->has('search') && !empty(request('search'))) {
            $searchTerm = request('search');
            
            $taskIds = Task::search($searchTerm)
                ->where('executor_id', $userId)
                ->take(500)
                ->keys();
                
            // Если Scout нашел задачи - фильтруем по ID, иначе ищем через LIKE как fallback
            if ($taskIds->isNotEmpty()) {
                $query->whereIn('id', $taskIds);
            } else {
                // Fallback на обычный поиск если Scout не нашел
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
                });
            }
        }

        // Применяем остальные фильтры и пагинацию
        $tasks = $query->filters()
            ->orderBy('created_at', 'desc')
            ->paginate(15);


        // Статистика для виджетов (остается без изменений)
        $allTasks = Task::where('executor_id', $userId);
        
        $urgentTasks = clone $allTasks;
        $highPriorityTasks = clone $allTasks;
        $inProgressTasks = clone $allTasks;
        $todayTasks = clone $allTasks;

        return [
            'tasks' => $tasks,
            'stats' => [
                'total' => $allTasks->count(),
                'urgent' => $urgentTasks->whereIn('priority', [
                    TaskPriorityEnum::EMERGENCY->value,
                    TaskPriorityEnum::BLOCKER->value
                ])->count(),
                'high_priority' => $highPriorityTasks->where('priority', TaskPriorityEnum::HIGH->value)->count(),
                'in_progress' => $inProgressTasks->where('status', TaskStatusEnum::IN_PROGRESS->value)->count(),
                'today_created' => $todayTasks->whereDate('created_at', today())->count(),
                'overdue' => $allTasks->where('end_datetime', '<', now())
                    ->whereNotIn('status', [
                        TaskStatusEnum::COMPLETED->value,
                        TaskStatusEnum::CANCELED->value
                    ])->count(),
            ]
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
            'task.type_task' => 'required|string',
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
                \Orchid\Screen\Fields\Group::make([
                    Input::make('search')
                        ->type('text')
                        ->placeholder('Введите название задачи, описание...')
                        ->title('')
                        ->value(request('search'))
                        ->help('Поиск по названию, описанию задачи или статусу')
                        ->style('border-radius: 8px 0 0 8px; border-right: none;')
                        ->width('70%'),
                        
                    \Orchid\Screen\Actions\Button::make('Найти')
                        ->method('searchTasks')
                        ->icon('magnifier')
                        ->class('btn btn-primary')
                        ->style('border-radius: 0 8px 8px 0; margin-top: 23px; height: 40px;'),
                        
                    request('search') ? 
                    \Orchid\Screen\Actions\Link::make('Сбросить')
                        ->href(route('platform.systems.my_tasks'))
                        ->icon('close')
                        ->class('btn btn-outline-secondary')
                        ->style('border-radius: 8px; margin-top: 23px; height: 40px; margin-left: 10px;')
                        : null,
                ])->alignEnd()->fullWidth(),
            ])->title('Быстрый поиск задач')
            ->description('Ищите задачи по названию, описанию или статусу'),

            Layout::view('orchid.layouts.task-stats'),

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

    // Добавьте метод для обработки поиска
    public function searchTasks(Request $request)
    {
        // Используем правильное имя роута
        return redirect()->route('platform.systems.my_tasks', [
            'search' => $request->get('search')
        ]);
    }
}
