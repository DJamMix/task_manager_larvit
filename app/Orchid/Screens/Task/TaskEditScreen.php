<?php

namespace App\Orchid\Screens\Task;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Models\TaskLink;
use App\Orchid\Layouts\Client\ClientTaskFilesLayout;
use App\Orchid\Layouts\Task\TaskEditLayout;
use App\Orchid\Layouts\Task\TaskObserversLayout;
use App\Services\CommentService;
use App\Services\TaskLogger;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class TaskEditScreen extends Screen
{
    /**
     * @var Task
     */
    public $task;

    public function query(Task $task): iterable
    {
        if (!$task->exists) {
            $context = app(\App\Services\ProjectContext::class);
            if ($context->has()) {
                $task->project_id = $context->id();
            }
        }

        if ($task->exists) {
            $task->load(['project', 'executor', 'creator', 'category', 'attachment', 'queue', 'links.relatedTask.queue', 'workflowStatus', 'sprint']);
        }

        $user = auth()->user();
        $comments = $task->exists
            ? $task->comments()->with(['user', 'parent.user', 'attachment'])->orderBy('created_at')->get()
            : collect();

        $linkOptions = [];
        if ($task->exists) {
            $linkOptions = Task::query()
                ->where('id', '!=', $task->id)
                ->when($task->project_id, fn ($q) => $q->where('project_id', $task->project_id))
                ->with('queue')
                ->orderByDesc('id')
                ->limit(80)
                ->get()
                ->mapWithKeys(fn (Task $t) => [$t->id => $t->displayKey() . ' · ' . Str::limit($t->name, 40)])
                ->all();
        }

        $statusTransitions = [];
        $statusActions = [];
        if ($task->exists) {
            $workflows = app(WorkflowService::class);
            $workflows->bootstrapDefaults($user);
            $statusTransitions = $workflows->allowedTransitions($task, $user);
            foreach ($statusTransitions as $transition) {
                $statusActions[] = Button::make($transition['name'])
                    ->method('changeStatus')
                    ->novalidate()
                    ->parameters(['status' => $transition['slug']])
                    ->class('btn btn-sm btn-outline-primary tw-status__btn');
            }
        }

        return [
            'task' => $task,
            'task_status_label' => $task->exists ? $task->statusLabel() : null,
            'discussion_comments' => $comments,
            'history_comments' => $comments->where('is_system', true)->values(),
            'notify_options' => $task->exists ? $task->participantsForNotify() : [],
            'can_discuss' => $task->exists && $task->canDiscuss((int) $user->id),
            'can_manage_links' => $task->exists,
            'related_links' => $task->exists ? $task->links : collect(),
            'link_task_options' => $linkOptions,
            'is_observer_only' => false,
            'viewer_role' => 'admin',
            'show_time_link' => false,
            'time_route' => null,
            'status_pipeline' => $task->exists
                ? TaskStatusEnum::pipelineWithState((string) $task->status)
                : [],
            'status_actions' => $statusActions,
            'status_transitions' => $statusTransitions,
            'can_change_status' => $task->exists && $statusTransitions !== [],
            'status_hint' => null,
            'timeEntries' => $task->exists
                ? $task->timeEntries()->with('user')->latest()->get()
                : collect(),
        ];
    }

    public function name(): ?string
    {
        return $this->task->exists ? ($this->task->name ?? 'Редактировать') : 'Создать задачу';
    }

    public function description(): ?string
    {
        return $this->task->exists
            ? 'Карточка задачи, обсуждение и наблюдатели'
            : 'Создание новой задачи';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.tasks',
        ];
    }

    public function commandBar(): iterable
    {
        $buttons = [];

        if ($this->task->exists) {
            $buttons[] = Link::make('К списку')
                ->icon('bs.arrow-left')
                ->route('platform.systems.tasks');
        }

        $buttons[] = Button::make(__('project.remove.title'))
            ->icon('bs.trash3')
            ->confirm(__('project.remove.warning'))
            ->method('remove')
            ->canSee($this->task->exists);

        $buttons[] = Button::make(__('project.save'))
            ->icon('bs.check-circle')
            ->method('save');

        return $buttons;
    }

    public function layout(): iterable
    {
        if (!$this->task->exists) {
            return [
                TaskEditLayout::class,
                TaskObserversLayout::class,
                ClientTaskFilesLayout::class,
            ];
        }

        return [
            Layout::tabs([
                'Задача и обсуждение' => [
                    Layout::view('orchid.layouts.task-workspace'),
                ],
                'Редактирование' => [
                    TaskEditLayout::class,
                    TaskObserversLayout::class,
                    ClientTaskFilesLayout::class,
                ],
                'Учёт времени' => [
                    Layout::table('timeEntries', [
                        TD::make('work_date', 'Дата')
                            ->render(fn ($entry) => $entry->work_date->format('d.m.Y')),
                        TD::make('user.name', 'Исполнитель')
                            ->render(fn ($entry) => $entry->user?->displayName() ?? '—'),
                        TD::make('hours_spent', 'Часы')
                            ->alignRight()
                            ->render(fn ($entry) => number_format($entry->hours_spent, 2)),
                        TD::make('work_description', 'Описание')
                            ->render(fn ($entry) => Str::limit($entry->work_description, 100)),
                    ]),
                ],
            ]),
        ];
    }

    public function save(Request $request, Task $task)
    {
        $data = $request->get('task');

        if (!is_array($data) || $data === []) {
            Toast::warning('Откройте вкладку «Редактирование», чтобы сохранить карточку задачи');
            return back();
        }

        if (!$task->exists && empty($data['project_id'])) {
            $contextId = app(\App\Services\ProjectContext::class)->id();
            if ($contextId) {
                $data['project_id'] = $contextId;
            }
        }

        if (!$task->exists) {
            $data['creator_id'] = $request->user()->id;
            $queueId = (int) ($data['queue_id'] ?? 0);
            if ($queueId <= 0) {
                Toast::error('Выберите очередь задачи');
                return back();
            }
            $queue = \App\Models\TaskQueue::query()->whereKey($queueId)->where('is_active', true)->first();
            if (!$queue) {
                Toast::error('Очередь не найдена');
                return back();
            }
            $data['queue_id'] = $queue->id;
            $data['queue_number'] = $queue->allocateNextNumber();
        } else {
            unset($data['creator_id'], $data['queue_id'], $data['queue_number']);
        }

        if (array_key_exists('observers_ids', $data)) {
            $data['observers_ids'] = collect($data['observers_ids'] ?? [])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $task->fill($data);
        $task->save();

        $task->attachments()->syncWithoutDetaching(
            $request->input('task.attachments', [])
        );

        Toast::info(__('task.save'));

        return redirect()->route('platform.systems.tasks.edit', $task);
    }

    public function remove(Task $task)
    {
        $task->delete();

        Toast::info(__('task.remove'));

        return redirect()->route('platform.systems.tasks');
    }

    public function addComment(Request $request, Task $task, CommentService $comments)
    {
        if (!$task->canDiscuss()) {
            Toast::error('Нельзя писать в этой задаче');
            return back();
        }

        $comments->addFromRequest($task, $request->user(), $request);
        Toast::success('Сообщение отправлено');

        return redirect()->route('platform.systems.tasks.edit', $task);
    }

    public function addLink(Request $request, Task $task)
    {
        $data = $request->validate([
            'related_task_id' => 'required|integer|exists:tasks,id',
            'relation' => 'required|string|in:' . implode(',', array_keys(TaskLink::relationLabels())),
        ]);

        if ((int) $data['related_task_id'] === (int) $task->id) {
            Toast::error('Нельзя связать задачу саму с собой');
            return back();
        }

        $link = TaskLink::query()->firstOrCreate([
            'task_id' => $task->id,
            'related_task_id' => (int) $data['related_task_id'],
            'relation' => $data['relation'],
        ], [
            'created_by' => $request->user()->id,
        ]);

        if ($link->wasRecentlyCreated) {
            $related = Task::query()->find((int) $data['related_task_id']);
            if ($related) {
                app(TaskLogger::class)->logLinkCreated($task, $request->user(), $related, $data['relation']);
            }
        }

        Toast::success('Связь добавлена');

        return back();
    }

    public function removeLink(Request $request, Task $task)
    {
        $link = TaskLink::query()
            ->where('task_id', $task->id)
            ->whereKey((int) $request->input('link_id'))
            ->first();

        if ($link) {
            $related = $link->relatedTask;
            $relation = (string) $link->relation;
            $link->delete();
            app(TaskLogger::class)->logLinkRemoved($task, $request->user(), $related, $relation);
        }

        Toast::info('Связь удалена');

        return back();
    }

    public function changeStatus(Task $task, Request $request)
    {
        $newStatus = $request->get('status');
        if (!$newStatus) {
            Toast::error('Не указан статус');

            return back();
        }

        try {
            app(WorkflowService::class)->changeStatus($task, $request->user(), $newStatus);
            Toast::success('Статус обновлён');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Toast::error(collect($e->errors())->flatten()->first() ?: 'Переход запрещён');
        }

        return back();
    }
}
