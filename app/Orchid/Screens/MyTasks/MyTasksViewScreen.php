<?php

namespace App\Orchid\Screens\MyTasks;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Models\TrackingTime;
use App\Orchid\Layouts\Comment\CommentListLayout;
use App\Orchid\Layouts\Comment\CommentSendLayout;
use App\Orchid\Layouts\MyTasks\HoursSpentTask;
use App\Orchid\Layouts\MyTasks\MyTasksViewLayout;
use App\Orchid\Layouts\MyTasks\TaskEvaluationLayout;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MyTasksViewScreen extends Screen
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
    public function query(Task $task, Request $request): iterable
    {
        $status = strtolower($task->status);
        $statusLabel = TaskStatusEnum::from($task->status)?->label();

        return [
            'task' => $this->transformTask($task),
            'task_status_label' => $statusLabel,
            'comments' => $task->comments()
                ->with('user')
                ->oldest()
                ->get()
                ->map(fn($comment) => $this->transformComment($comment)),
            'timeEntries' => $task->timeEntries()
                ->with('user')
                ->latest()
                ->get(),
            'user' => $request->user(),
        ];
    }

    protected function transformTask(Task $task): array
    {
        // Преобразуем строки дат в объекты Carbon при необходимости
        $startDatetime = is_string($task->start_datetime) 
            ? Carbon::parse($task->start_datetime) 
            : $task->start_datetime;
        
        $endDatetime = is_string($task->end_datetime) 
            ? Carbon::parse($task->end_datetime) 
            : $task->end_datetime;

        return [
            'id' => $task->id,
            'name' => $task->name,
            'creator' => ['name' => $task->creator->name ?? 'Не указан'],
            'executor' => [
                'name' => $task->executor->name ?? 'Не указан',
                'id' => $task->executor->id ?? null,
            ],
            'project' => ['name' => $task->project->name ?? null],
            'task_category' => ['name' => $task->category->name ?? null],
            'status' => $task->status,
            'status_html' => view('components.task-status', [
                'status' => strtolower($task->status),
                'label' => TaskStatusEnum::tryFrom($task->status)?->label()
            ])->render(),
            'pay_status' => $task->pay_status,
            'start_datetime' => $startDatetime?->format('d.m.Y H:i') ?? 'Не указано',
            'end_datetime' => $endDatetime?->format('d.m.Y H:i') ?? 'Не указано',
            'hours_spent' => number_format($task->hours_spent, 2),
            'estimation_hours' => number_format($task->estimation_hours, 2),
            'description' => $task->description
        ];
    }

    protected function transformComment($comment): array
    {
        return [
            'id' => $comment->id,
            'user' => ['name' => $comment->user->name ?? 'Неизвестно'],
            'created_at' => $comment->created_at->format('d.m.Y H:i'),
            'text' => $comment->text
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return __('adminpanel.MyTasksView');
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.my_tasks',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        $task = $this->task;

        $buttons = [];

        // Добавляем кнопку "Оценить задачу" если статус "estimation"
        if (auth()->id() == $task['executor']['id'] && $task['status'] === TaskStatusEnum::ESTIMATION->value) {
            $buttons[] = ModalToggle::make('Оценить задачу')
                ->modalTitle('Оценка задачи')
                ->icon('exclamation-triangle')
                ->modal('taskEvaluationModal')
                ->method('saveEstimation')
                ->class('btn btn-warning');
        }

        if(
            auth()->id() == $task['executor']['id'] &&
            ($task['status'] === TaskStatusEnum::IN_PROGRESS->value ||
            $task['status'] === TaskStatusEnum::TESTING_STAGE->value ||
            $task['status'] === TaskStatusEnum::ESTIMATION->value ||
            $task['status'] === TaskStatusEnum::TESTING_PROD->value ||
            $task['status'] === TaskStatusEnum::DEMO->value)
        ) {
            $buttons[] = ModalToggle::make('Добавить время')
                ->modalTitle('Учет рабочего времени')
                ->modal('timeTrackingModal')
                ->method('saveTimeEntry')
                ->icon('clock');
        }

        $buttons[] = Button::make('Назад')
            ->icon('arrow-left')
            ->method('back');

        return $buttons;
    }

    public function saveTimeEntry(Task $task, Request $request)
    {
        $request->validate([
            'tracking.hours_spent' => 'required|numeric|min:0.25|max:24',
            'tracking.work_date' => 'required|date',
            'tracking.work_description' => 'required|string|max:2000',
        ]);

        $tracking = new TrackingTime();
        $tracking->id = Str::ulid();
        $tracking->task_id = $task->id;
        $tracking->hours_spent = $request->input('tracking.hours_spent');
        $tracking->work_description = $request->input('tracking.work_description');
        $tracking->work_date = $request->input('tracking.work_date');
        $tracking->user_id = auth()->id();
        $tracking->save();

        $task->increment('hours_spent', $request->input('tracking.hours_spent'));

        Toast::success('Затраченные часы успешно сохранены!');
    }

    public function saveEstimation(Task $task, Request $request)
    {
        $request->validate([
            'task.estimation_hours' => 'required|numeric|max:1000|min:0',
        ]);

        $task->estimation_hours = $request->input('task.estimation_hours');
        $task->status = TaskStatusEnum::ESTIMATION_REVIEW->value;
        $task->save();
    }

    public function saveHoursSpent(Task $task, Request $request)
    {
        $request->validate([
            'task.hours_spent' => 'required|numeric|max:1000|min:0',
            'tracking.work_description' => 'required|string|max:2000',
            'tracking.work_date' => 'required|date',
        ]);

        $tracking = new \App\Models\TrackingTime();
        $tracking->id = \Illuminate\Support\Str::ulid();
        $tracking->task_id = $task->id;
        $tracking->hours_spent = $request->input('tracking.hours_spent');
        $tracking->work_description = $request->input('tracking.work_description');
        $tracking->work_date = $request->input('tracking.work_date');
        $tracking->user_id = auth()->id();
        $tracking->save();

        $task->increment('hours_spent', $request->input('tracking.hours_spent'));

        Toast::info('Затраченные часы успешно сохранены!');
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::tabs([
                'Основная информация' => MyTasksViewLayout::class,
                'Комментарии' => [
                    CommentSendLayout::class,
                    CommentListLayout::class,
                ],
                'Учет времени' => [
                    Layout::table('timeEntries', [
                        TD::make('work_date', 'Дата')
                            ->render(fn($entry) => $entry->work_date->format('d.m.Y')),
                        TD::make('user.name', 'Исполнитель'),
                        TD::make('hours_spent', 'Часы')
                            ->alignRight()
                            ->render(fn($entry) => number_format($entry->hours_spent, 2)),
                        TD::make('work_description', 'Описание')
                            ->render(fn($entry) => Str::limit($entry->work_description, 100)),
                    ]),
                ],
            ]),

            Layout::modal('timeTrackingModal', [
                HoursSpentTask::class
            ])
            ->title('Учет рабочего времени')
            ->applyButton('Сохранить'),

            Layout::modal('taskEvaluationModal', [
                TaskEvaluationLayout::class
            ])
            ->title('Оценка задачи')
            ->applyButton('Отправить'),
        ];
    }

    public function asyncGetTimeEntryData(Task $task): array
    {
        return [
            'tracking' => [  // Изменил с 'time' на 'tracking'
                'work_date' => now()->format('Y-m-d'),
                'hours_spent' => null,
                'work_description' => null,
            ]
        ];
    }

    public function back()
    {
        return redirect()->route('platform.systems.my_tasks');
    }

    public function addComment(Request $request, Task $task)
    {
        $request->validate([
            'comment.text' => 'required|string|max:1000',
        ]);

        $task->comments()->create([
            'user_id' => auth()->id(),
            'text' => $request->input('comment.text'),
        ]);

        Toast::success('Комментарий добавлен');
    }
}
