<?php

namespace App\Orchid\Screens\MyTasks;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Models\TaskLink;
use App\Models\TrackingTime;
use App\Orchid\Layouts\MyTasks\HoursSpentTask;
use App\Orchid\Layouts\MyTasks\TaskEvaluationLayout;
use App\Orchid\Layouts\Task\TaskObserversLayout;
use App\Services\CommentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MyTasksViewScreen extends Screen
{
    public $task;

    public function query(Task $task, Request $request): iterable
    {
        $this->authorizeAccess($task);

        $task->load(['project', 'executor', 'creator', 'category', 'attachment', 'queue', 'links.relatedTask.queue']);

        $comments = $task->comments()
            ->with(['user', 'parent.user', 'attachment'])
            ->orderBy('created_at')
            ->get();

        $user = $request->user();
        $isObserverOnly = $task->isObserver((int) $user->id)
            && (int) $task->executor_id !== (int) $user->id
            && !$user->hasAccess('platform.systems.tasks');

        $canChangeStatus = (int) $task->executor_id === (int) $user->id
            && $task->canChangeWorkflow((int) $user->id);

        $statusActions = [];
        if ($canChangeStatus) {
            foreach (TaskStatusEnum::executorTransitions((string) $task->status) as $transition) {
                $btn = Button::make($transition['label'])
                    ->method('changeStatus')
                    ->novalidate()
                    ->parameters(['status' => $transition['to']])
                    ->class(
                        ($transition['tone'] ?? 'next') === 'back'
                            ? 'btn btn-sm btn-outline-secondary tw-status__btn'
                            : 'btn btn-sm btn-primary tw-status__btn'
                    );

                if (!empty($transition['confirm'])) {
                    $btn = $btn->confirm($transition['confirm']);
                }

                $statusActions[] = $btn;
            }
        }

        $statusHint = null;
        if ($task->status === TaskStatusEnum::DEMO->value) {
            $statusHint = 'На демо у заказчика — ждите решение.';
        } elseif ($task->status === TaskStatusEnum::NEW->value && $canChangeStatus) {
            $statusHint = 'Нажмите «Взять в работу» сверху, чтобы начать.';
        }

        $linkOptions = Task::query()
            ->where('id', '!=', $task->id)
            ->when($task->project_id, fn ($q) => $q->where('project_id', $task->project_id))
            ->with('queue')
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->mapWithKeys(fn (Task $t) => [$t->id => $t->displayKey() . ' · ' . \Illuminate\Support\Str::limit($t->name, 40)])
            ->all();

        return [
            'task' => $task,
            'task_status_label' => TaskStatusEnum::tryFrom($task->status)?->label(),
            'discussion_comments' => $comments,
            'history_comments' => $comments->where('is_system', true)->values(),
            'notify_options' => $task->participantsForNotify(),
            'can_discuss' => $task->canDiscuss((int) $user->id),
            'can_manage_links' => $task->canManageTask((int) $user->id) || (int) $task->executor_id === (int) $user->id,
            'related_links' => $task->links,
            'link_task_options' => $linkOptions,
            'is_observer_only' => $isObserverOnly,
            'viewer_role' => 'employee',
            'show_time_link' => !$isObserverOnly && $task->canTrackTime((int) $user->id),
            'time_route' => (!$isObserverOnly && $task->canTrackTime((int) $user->id))
                ? route('platform.systems.my_tasks.time', $task)
                : null,
            'status_pipeline' => TaskStatusEnum::pipelineWithState((string) $task->status),
            'status_actions' => $statusActions,
            'status_hint' => $statusHint,
            'user' => $user,
        ];
    }

    public function name(): ?string
    {
        return $this->task->name ?? 'Задача';
    }

    public function description(): ?string
    {
        return 'Описание и обсуждение на одной странице';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.my_tasks'];
    }

    public function commandBar(): iterable
    {
        $task = $this->task instanceof Task
            ? $this->task
            : Task::find(data_get($this->task, 'id'));

        $buttons = [];

        if ($task && auth()->id() == $task->executor_id && $task->status === TaskStatusEnum::ESTIMATION->value) {
            $buttons[] = ModalToggle::make('Оценить задачу')
                ->modalTitle('Оценка задачи')
                ->icon('exclamation-triangle')
                ->modal('taskEvaluationModal')
                ->method('saveEstimation')
                ->class('btn btn-warning');
        }

        if ($task && $task->canTrackTime()) {
            $buttons[] = ModalToggle::make('Добавить время')
                ->modalTitle('Учет рабочего времени')
                ->modal('timeTrackingModal')
                ->method('saveTimeEntry')
                ->icon('clock');

            $buttons[] = Link::make('Журнал времени')
                ->icon('bs.journal-text')
                ->route('platform.systems.my_tasks.time', $task);
        }

        if ($task && auth()->id() == $task->executor_id && $task->status === TaskStatusEnum::NEW->value) {
            $buttons[] = Button::make('Взять в работу')
                ->method('takeWork')
                ->novalidate()
                ->icon('check')
                ->class('btn btn-primary')
                ->confirm('Задача перейдёт в статус «В работе»');
        }

        if ($task && $task->canManageTask()) {
            $buttons[] = ModalToggle::make('Наблюдатели')
                ->modal('observersModal')
                ->method('saveObservers')
                ->icon('bs.eye');
        }

        $buttons[] = Link::make('К списку')
            ->icon('bs.arrow-left')
            ->route('platform.systems.my_tasks');

        return $buttons;
    }

    public function layout(): iterable
    {
        $layouts = [
            Layout::view('orchid.layouts.task-workspace'),
        ];

        $layouts[] = Layout::modal('timeTrackingModal', [HoursSpentTask::class])
            ->title('Учет рабочего времени')
            ->applyButton('Сохранить');

        $layouts[] = Layout::modal('taskEvaluationModal', [TaskEvaluationLayout::class])
            ->title('Оценка задачи')
            ->applyButton('Отправить');

        $layouts[] = Layout::modal('observersModal', [TaskObserversLayout::class])
            ->title('Наблюдатели задачи')
            ->applyButton('Сохранить');

        return $layouts;
    }

    public function addComment(Request $request, Task $task, CommentService $comments)
    {
        $this->authorizeAccess($task);

        if (!$task->canDiscuss()) {
            Toast::error('Нельзя писать в этой задаче');
            return back();
        }

        $comments->addFromRequest($task, $request->user(), $request);
        Toast::success('Сообщение отправлено');

        return redirect()->route('platform.systems.my_tasks.view', $task);
    }

    public function addLink(Request $request, Task $task)
    {
        $this->authorizeAccess($task);
        if (!$task->canManageTask() && (int) $task->executor_id !== (int) $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'related_task_id' => 'required|integer|exists:tasks,id',
            'relation' => 'required|string|in:' . implode(',', array_keys(TaskLink::relationLabels())),
        ]);

        if ((int) $data['related_task_id'] === (int) $task->id) {
            Toast::error('Нельзя связать задачу саму с собой');
            return back();
        }

        TaskLink::query()->firstOrCreate([
            'task_id' => $task->id,
            'related_task_id' => (int) $data['related_task_id'],
            'relation' => $data['relation'],
        ], [
            'created_by' => $request->user()->id,
        ]);

        Toast::success('Связь добавлена');

        return back();
    }

    public function removeLink(Request $request, Task $task)
    {
        $this->authorizeAccess($task);
        if (!$task->canManageTask() && (int) $task->executor_id !== (int) $request->user()->id) {
            abort(403);
        }

        $linkId = (int) $request->input('link_id');
        TaskLink::query()
            ->where('task_id', $task->id)
            ->whereKey($linkId)
            ->delete();

        Toast::info('Связь удалена');

        return back();
    }

    public function saveObservers(Request $request, Task $task)
    {
        if (!$task->canManageTask()) {
            abort(403);
        }

        $ids = collect($request->input('task.observers_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $task->observers_ids = $ids;
        $task->save();

        Toast::success('Наблюдатели обновлены');

        return back();
    }

    public function takeWork(Task $task)
    {
        $this->authorizeAccess($task);

        if (!$task->canChangeWorkflow() || (int) $task->executor_id !== (int) auth()->id()) {
            Toast::error('Недостаточно прав');
            return back();
        }

        $task->status = TaskStatusEnum::IN_PROGRESS->value;
        $task->save();

        Toast::success('Задача в работе');

        return back();
    }

    public function saveTimeEntry(Task $task, Request $request)
    {
        $this->authorizeAccess($task);

        if (!$task->canTrackTime()) {
            Toast::error('Нельзя учитывать время по этой задаче');
            return back();
        }

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

        Toast::success('Время учтено. Оценка не изменилась.');
    }

    public function saveEstimation(Task $task, Request $request)
    {
        $this->authorizeAccess($task);

        $request->validate([
            'task.estimation_hours' => 'required|numeric|max:1000|min:0',
        ]);

        $task->estimation_hours = $request->input('task.estimation_hours');
        $task->status = TaskStatusEnum::ESTIMATION_REVIEW->value;
        $task->save();

        Toast::success('Оценка отправлена на согласование');
    }

    public function changeStatus(Task $task, Request $request)
    {
        $this->authorizeAccess($task);

        if (!$task->canChangeWorkflow()) {
            Toast::error('Наблюдатель не может менять статус');
            return back();
        }

        $validStatuses = [
            TaskStatusEnum::IN_PROGRESS->value,
            TaskStatusEnum::TESTING_STAGE->value,
            TaskStatusEnum::TESTING_PROD->value,
            TaskStatusEnum::DEMO->value,
            TaskStatusEnum::UNPAID->value,
        ];

        $newStatus = $request->get('status');

        if (!in_array($newStatus, $validStatuses, true)) {
            Toast::error('Недопустимый статус');
            return back();
        }

        if ($newStatus === TaskStatusEnum::UNPAID->value && $task->type_task == 'bug') {
            $newStatus = TaskStatusEnum::COMPLETED->value;
        }

        $task->status = $newStatus;
        $task->save();

        Toast::success('Статус обновлён');

        return back();
    }

    private function authorizeAccess(Task $task): void
    {
        $user = auth()->user();

        if ($task->canView((int) $user->id)) {
            return;
        }

        abort(403, 'Нет доступа к этой задаче');
    }
}
